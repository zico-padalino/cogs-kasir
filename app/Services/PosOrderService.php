<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Enums\ProductType;
use App\Models\MenuCategory;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Support\PosDiscount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly CogsCalculationService $cogsCalculationService,
        private readonly CashLedgerService $cashLedgerService,
        private readonly KasirPushNotifier $kasirPushNotifier,
    ) {}

    public function generateOrderNumber(?string $orderDay = null): string
    {
        $orderDay ??= now()->toDateString();

        return DB::transaction(fn () => $this->nextOrderNumberForDay($orderDay));
    }

    private function nextOrderNumberForDay(string $orderDay): string
    {
        $prefix = 'TRX-'.str_replace('-', '', $orderDay).'-';

        $max = PosOrder::query()
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('order_number')
            ->map(function (string $number) use ($prefix) {
                $suffix = substr($number, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        $next = $max + 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function createKasirOrder(?User $cashier = null, ?array $attribution = null): PosOrder
    {
        return DB::transaction(function () use ($cashier, $attribution) {
            $orderDay = now()->toDateString();
            $attr = $this->resolveCashierAttribution($cashier, $attribution);

            return PosOrder::create([
                'order_number' => $this->nextOrderNumberForDay($orderDay),
                'order_day' => $orderDay,
                'source' => PosOrderSource::Kasir,
                'order_type' => PosOrderType::Takeaway,
                'status' => PosOrderStatus::Open,
                'user_id' => $attr['user_id'],
                'cashier_employee_id' => $attr['cashier_employee_id'],
                'cashier_name' => $attr['cashier_name'],
            ]);
        });
    }

    public function updateOrderContext(
        PosOrder $order,
        PosOrderType $orderType,
        ?int $tableId = null,
        ?string $customerNote = null,
    ): PosOrder {
        if (! $order->isKasirEditable()) {
            throw new RuntimeException('Pesanan tidak bisa diubah.');
        }

        $data = [
            'order_type' => $orderType,
            'customer_note' => filled($customerNote) ? trim($customerNote) : null,
        ];

        if ($order->source === PosOrderSource::Kasir) {
            $data['pos_table_id'] = null;
        } elseif ($orderType === PosOrderType::DineIn) {
            $data['pos_table_id'] = $tableId;
        }

        $order->update($data);

        return $order->fresh(['table']);
    }

    public function updateDiscount(PosOrder $order, ?string $discountType, float $discountValue): PosOrder
    {
        $this->assertDiscountMutable($order);

        $type = in_array($discountType, ['amount', 'percent'], true) ? $discountType : null;
        $value = $type ? max(0, $discountValue) : 0.0;

        $order->update([
            'discount_type' => $type,
            'discount_value' => $value,
        ]);

        $this->recalculateTotals($order);

        return $order->fresh(['items.product', 'table']);
    }

    public function resolveOnlineOrder(?int $sessionOrderId = null): PosOrder
    {
        if ($sessionOrderId) {
            $order = PosOrder::query()
                ->whereKey($sessionOrderId)
                ->where('source', PosOrderSource::Online)
                ->first();

            if ($order && in_array($order->status, [
                PosOrderStatus::Open,
                PosOrderStatus::Submitted,
                PosOrderStatus::Confirmed,
                PosOrderStatus::Paid,
                PosOrderStatus::Served,
            ], true)) {
                return $order;
            }
        }

        return $this->createOnlineOrder();
    }

    public function createOnlineOrder(?int $tableId = null): PosOrder
    {
        return DB::transaction(function () use ($tableId) {
            $orderDay = now()->toDateString();

            return PosOrder::create([
                'order_number' => $this->nextOrderNumberForDay($orderDay),
                'order_day' => $orderDay,
                'pos_table_id' => $tableId,
                'source' => PosOrderSource::Online,
                'order_type' => PosOrderType::Takeaway,
                'status' => PosOrderStatus::Open,
            ]);
        });
    }

    public function updateOnlineCustomerNote(PosOrder $order, string $customerNote): PosOrder
    {
        return $this->updateOnlineCustomerDetails($order, $customerNote);
    }

    public function updateOnlineCustomerDetails(PosOrder $order, string $customerNote, ?string $orderType = null): PosOrder
    {
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang bisa diubah dari menu QR.');
        }

        if (! $order->isEditable()) {
            throw new RuntimeException('Pesanan sudah dikirim. Silakan bayar di kasir.');
        }

        $payload = [
            'customer_note' => trim($customerNote),
        ];

        if ($orderType !== null) {
            $payload['order_type'] = PosOrderType::from($orderType);
        }

        $order->update($payload);

        return $order->fresh();
    }

    public function addItem(PosOrder $order, Product $product, float $quantity, ?float $unitPrice = null, bool $fromKasir = false, ?string $notes = null, array $addonIds = []): PosOrderItem
    {
        $this->assertOrderMutable($order, $fromKasir);

        $this->assertSellable($product, $quantity);

        $addons = collect();
        if ($addonIds !== []) {
            $addons = $product->addons()
                ->active()
                ->whereIn('id', $addonIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $addonExtra = (float) $addons->sum('selling_price');
        $price = $unitPrice ?? (float) $product->selling_price;
        if ($price <= 0) {
            $price = $product->effectiveUnitHpp();
        }
        $price = round($price + $addonExtra, 4);

        $addonNote = $addons->isNotEmpty()
            ? $addons->map(fn ($addon) => '+'.$addon->name)->implode(' ')
            : '';
        $mergedNotes = \App\Support\PosItemNotes::merge($notes, $addonNote !== '' ? $addonNote : null);

        $item = PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => round($quantity * $price, 4),
            'notes' => $mergedNotes,
            'addon_ids' => $addons->pluck('id')->values()->all() ?: null,
        ]);

        $this->recalculateTotals($order);

        return $item->load('product');
    }

    public function updateItemQuantity(PosOrderItem $item, float $quantity, bool $fromKasir = false): void
    {
        $order = $item->order;

        $this->assertOrderMutable($order, $fromKasir);

        $this->assertSellable($item->product, $quantity);

        $item->update([
            'quantity' => $quantity,
            'line_total' => round($quantity * (float) $item->unit_price, 4),
        ]);

        $this->recalculateTotals($order);
    }

    public function removeItem(PosOrderItem $item, bool $fromKasir = false): void
    {
        $order = $item->order;

        $this->assertOrderMutable($order, $fromKasir);

        $item->delete();
        $this->recalculateTotals($order);
    }

    public function submitOnlineOrder(PosOrder $order): PosOrder
    {
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang bisa dikirim dari meja.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Pesanan masih kosong.');
        }

        if (! filled($order->customer_note)) {
            throw new RuntimeException('Isi nama pemesan dulu sebelum kirim ke kasir.');
        }

        if ($order->order_type === null) {
            throw new RuntimeException('Pilih Take Away atau Dine In dulu.');
        }

        $order->update(['status' => PosOrderStatus::Submitted]);

        $fresh = $order->fresh(['items.product', 'table']);
        $this->kasirPushNotifier->notifyNewOnlineOrder($fresh);

        return $fresh;
    }

    public function confirmOrder(PosOrder $order, ?User $cashier = null, ?array $attribution = null): PosOrder
    {
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang masuk antrean kasir.');
        }

        if ($order->status === PosOrderStatus::Confirmed) {
            return $order->fresh(['items.product', 'table']);
        }

        if ($order->status !== PosOrderStatus::Submitted) {
            throw new RuntimeException('Pesanan tidak bisa dimasukkan ke kasir.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Pesanan masih kosong.');
        }

        $attr = $this->resolveCashierAttribution($cashier, $attribution);

        $order->update([
            'status' => PosOrderStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $attr['user_id'],
            'user_id' => $attr['user_id'] ?? $order->user_id,
            'cashier_employee_id' => $attr['cashier_employee_id'] ?? $order->cashier_employee_id,
            'cashier_name' => $attr['cashier_name'] ?? $order->cashier_name,
        ]);

        return $order->fresh(['items.product', 'table']);
    }

    /**
     * @return array{
     *     order: PosOrder,
     *     invoice: string,
     *     stock_out: list<array{id: int, name: string, type: string, type_label: string, sku: string|null}>,
     *     stock_out_message: ?string
     * }
     */
    public function payOrder(
        PosOrder $order,
        PaymentMethod $paymentMethod,
        ?User $cashier = null,
        ?float $amountReceived = null,
        ?UploadedFile $paymentProof = null,
        ?array $attribution = null,
    ): array {
        $attr = $this->resolveCashierAttribution($cashier, $attribution);

        if ($order->source === PosOrderSource::Online) {
            if ($order->status === PosOrderStatus::Submitted) {
                $order = $this->confirmOrder($order, $cashier, $attr);
            }

            if ($order->status !== PosOrderStatus::Confirmed) {
                throw new RuntimeException('Pesanan sudah dibayar atau dibatalkan.');
            }
        } elseif (! in_array($order->status, [PosOrderStatus::Open, PosOrderStatus::Unpaid], true)) {
            throw new RuntimeException('Pesanan sudah dibayar atau dibatalkan.');
        }

        $order->load('items.product');

        if ($order->items->isEmpty()) {
            throw new RuntimeException('Tidak ada item untuk dibayar.');
        }

        foreach ($order->items as $item) {
            $this->assertSellable($item->product, (float) $item->quantity);
        }

        $total = (float) $order->total;
        $changeAmount = null;
        $needsProof = in_array($paymentMethod, [PaymentMethod::Qris, PaymentMethod::Transfer], true);

        if ($paymentMethod === PaymentMethod::Cash) {
            if ($amountReceived === null || $amountReceived < $total) {
                throw new RuntimeException('Uang diterima harus minimal sebesar total tagihan.');
            }

            $changeAmount = round($amountReceived - $total, 4);
        }

        if ($needsProof && ! $paymentProof) {
            throw new RuntimeException('Upload foto bukti pembayaran untuk QRIS / Transfer.');
        }

        $proofPath = null;
        if ($needsProof && $paymentProof) {
            $proofPath = $paymentProof->store('payment-proofs/'.now()->format('Y/m'), 'public');
        }

        try {
            $result = DB::transaction(function () use ($order, $paymentMethod, $cashier, $amountReceived, $changeAmount, $proofPath, $attr) {
                $invoiceBase = 'POS-'.$order->order_number;
                $soldAt = now();
                $subtotal = (float) $order->subtotal;
                $payableTotal = (float) $order->total;
                $allocatedRevenue = 0.0;
                $itemCount = $order->items->count();
                $consumedProductIds = [];

                foreach ($order->items as $index => $item) {
                    $lineSubtotal = (float) $item->line_total;

                    if ($index === $itemCount - 1) {
                        $lineRevenue = round($payableTotal - $allocatedRevenue, 4);
                    } elseif ($subtotal > 0) {
                        $lineRevenue = round($payableTotal * ($lineSubtotal / $subtotal), 4);
                        $allocatedRevenue += $lineRevenue;
                    } else {
                        $lineRevenue = 0.0;
                    }

                    $sale = SalesTransaction::create([
                        'pos_order_id' => $order->id,
                        'invoice_number' => $invoiceBase.'-'.($index + 1),
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'selling_price' => $item->unit_price,
                        'total_revenue' => $lineRevenue,
                        'sold_at' => $soldAt,
                    ]);

                    $calculation = $this->cogsCalculationService->recordSaleCogs(
                        $sale,
                        $this->addonMaterialRequirements($item),
                    );

                    foreach ($calculation->breakdown['consumption_details'] ?? [] as $detail) {
                        if (! empty($detail['product_id'])) {
                            $consumedProductIds[] = (int) $detail['product_id'];
                        }
                    }
                }

                $order->update([
                    'status' => PosOrderStatus::Paid,
                    'payment_method' => $paymentMethod,
                    'payment_proof_path' => $proofPath,
                    'paid_at' => $soldAt,
                    'user_id' => $attr['user_id'] ?? $order->user_id,
                    'cashier_employee_id' => $attr['cashier_employee_id'] ?? $order->cashier_employee_id,
                    'cashier_name' => $attr['cashier_name'] ?? $order->cashier_name,
                    'amount_received' => $amountReceived,
                    'change_amount' => $changeAmount,
                ]);

                $paidOrder = $order->fresh(['items.product', 'table', 'cashier']);

                if ($paymentMethod === PaymentMethod::Cash) {
                    $this->cashLedgerService->recordCashSale($paidOrder, $cashier);
                }

                $stockOut = $this->resolveDepletedStockItems($consumedProductIds);

                return [
                    'order' => $paidOrder,
                    'invoice' => $invoiceBase,
                    'stock_out' => $stockOut,
                    'stock_out_message' => $this->formatStockOutMessage($stockOut, $paidOrder),
                ];
            });
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            throw $e;
        }

        if (($result['stock_out'] ?? []) !== []) {
            $this->kasirPushNotifier->notifyStockOut($result['stock_out'], $result['order']);
        }

        return $result;
    }

    /**
     * @param  list<int>  $productIds
     * @return list<array{id: int, name: string, type: string, type_label: string, sku: string|null}>
     */
    private function resolveDepletedStockItems(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter($productIds)));

        if ($ids === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->availableQuantity() <= 0)
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type->value,
                'type_label' => match ($product->type) {
                    ProductType::FinishedGood => 'Barang Jadi',
                    ProductType::SemiFinished => 'Bahan Jadi',
                    ProductType::RawMaterial => 'Barang Stok',
                },
                'sku' => $product->sku,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, name: string, type: string, type_label: string, sku?: string|null}>  $items
     */
    private function formatStockOutMessage(array $items, ?PosOrder $order = null): ?string
    {
        if ($items === []) {
            return null;
        }

        $list = collect($items)
            ->map(fn (array $item) => $item['name'].' ('.$item['type_label'].')')
            ->implode(', ');

        $suffix = $order?->order_number ? " setelah pesanan {$order->order_number}" : '';

        return "Stok habis{$suffix}: {$list}.";
    }

    /**
     * @return list<array{product: Product, quantity: float, note: string}>
     */
    private function addonMaterialRequirements(PosOrderItem $item): array
    {
        $addonIds = collect($item->addon_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($addonIds === []) {
            return [];
        }

        $addons = $item->product->addons()
            ->with('material')
            ->whereIn('id', $addonIds)
            ->get();

        $requirements = [];
        $itemQty = (float) $item->quantity;

        foreach ($addons as $addon) {
            if (! $addon->material_product_id || ! $addon->material || ! $addon->material_quantity) {
                continue;
            }

            $qty = (float) $addon->material_quantity * $itemQty;
            if ($qty <= 0) {
                continue;
            }

            $requirements[] = [
                'product' => $addon->material,
                'quantity' => $qty,
                'note' => 'Add-on '.$addon->name.' · '.$item->product->name,
            ];
        }

        return $requirements;
    }

    /**
     * Buka / perbarui Open Bill di kasir saja.
     * Jika nama (atau meja) sama dengan Open Bill yang sudah ada, item digabung ke bill itu.
     * Stok belum dipotong sampai lunas.
     *
     * @return array{order: PosOrder, merged: bool}
     */
    public function openBill(PosOrder $order, ?array $attribution = null): array
    {
        if ($order->source !== PosOrderSource::Kasir) {
            throw new RuntimeException('Open Bill hanya bisa dibuat dari kasir.');
        }

        if (! in_array($order->status, [PosOrderStatus::Open, PosOrderStatus::Unpaid], true)) {
            throw new RuntimeException('Pesanan ini tidak bisa disimpan sebagai Open Bill.');
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            throw new RuntimeException('Tambah item dulu sebelum buat Open Bill.');
        }

        if (! filled($order->customer_note) && ! $order->pos_table_id) {
            throw new RuntimeException('Isi nama pelanggan (atau pilih meja) supaya Open Bill mudah dicari.');
        }

        $attr = $attribution ?? [];

        return DB::transaction(function () use ($order, $attr) {
            $existing = $order->status === PosOrderStatus::Open
                ? $this->findMatchingOpenBill($order)
                : null;

            if ($existing && (int) $existing->id !== (int) $order->id) {
                foreach ($order->items as $item) {
                    $item->update(['pos_order_id' => $existing->id]);
                }

                $this->recalculateTotals($existing);

                $order->update([
                    'status' => PosOrderStatus::Cancelled,
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'total' => 0,
                ]);

                $existing->update([
                    'user_id' => $attr['user_id'] ?? $existing->user_id,
                    'cashier_employee_id' => $attr['cashier_employee_id'] ?? $existing->cashier_employee_id,
                    'cashier_name' => $attr['cashier_name'] ?? $existing->cashier_name,
                ]);

                return [
                    'order' => $existing->fresh(['items.product', 'table']),
                    'merged' => true,
                ];
            }

            $order->update([
                'status' => PosOrderStatus::Unpaid,
                'user_id' => $attr['user_id'] ?? $order->user_id,
                'cashier_employee_id' => $attr['cashier_employee_id'] ?? $order->cashier_employee_id,
                'cashier_name' => $attr['cashier_name'] ?? $order->cashier_name,
            ]);

            return [
                'order' => $order->fresh(['items.product', 'table']),
                'merged' => false,
            ];
        });
    }

    /** Cari Open Bill kasir yang cocok (nama sama, atau meja sama jika tanpa nama). */
    private function findMatchingOpenBill(PosOrder $order): ?PosOrder
    {
        $base = PosOrder::query()
            ->where('source', PosOrderSource::Kasir)
            ->where('status', PosOrderStatus::Unpaid)
            ->whereKeyNot($order->id);

        if (filled($order->customer_note)) {
            $name = mb_strtolower(trim((string) $order->customer_note));

            return (clone $base)
                ->whereRaw('LOWER(TRIM(customer_note)) = ?', [$name])
                ->latest('id')
                ->first();
        }

        if ($order->pos_table_id) {
            return (clone $base)
                ->where('pos_table_id', $order->pos_table_id)
                ->latest('id')
                ->first();
        }

        return null;
    }

    public function cancelOrder(PosOrder $order): void
    {
        if (in_array($order->status, [PosOrderStatus::Paid, PosOrderStatus::Served], true)) {
            throw new RuntimeException('Pesanan lunas tidak bisa dibatalkan.');
        }

        $order->update(['status' => PosOrderStatus::Cancelled]);
    }

    public function cancelPendingOnlineOrder(PosOrder $order): void
    {
        $isOnlineWaiting = $order->source === PosOrderSource::Online
            && in_array($order->status, [PosOrderStatus::Submitted, PosOrderStatus::Confirmed], true);

        $isOpenBill = $order->source === PosOrderSource::Kasir
            && $order->status === PosOrderStatus::Unpaid;

        if (! $isOnlineWaiting && ! $isOpenBill) {
            throw new RuntimeException('Pesanan ini tidak bisa dihapus dari daftar menunggu.');
        }

        $this->cancelOrder($order);
    }

    /** Konfirmasi pesanan sudah diantar / selesai (setelah bayar). */
    public function markServed(PosOrder $order): PosOrder
    {
        if ($order->status !== PosOrderStatus::Paid) {
            throw new RuntimeException('Hanya pesanan yang sudah dibayar yang bisa dikonfirmasi selesai.');
        }

        $order->update([
            'status' => PosOrderStatus::Served,
            'served_at' => now(),
        ]);

        return $order->fresh(['items.product', 'table']);
    }

    /** @return Collection<int, PosOrder> */
    public function waitingOrders()
    {
        return PosOrder::query()
            ->with(['table', 'items.product'])
            ->where(function ($query) {
                $query->where(function ($online) {
                    $online->where('source', PosOrderSource::Online)
                        ->whereIn('status', [PosOrderStatus::Submitted, PosOrderStatus::Confirmed]);
                })->orWhere(function ($openBill) {
                    $openBill->where('source', PosOrderSource::Kasir)
                        ->where('status', PosOrderStatus::Unpaid);
                })->orWhere(function ($awaitingServe) {
                    $awaitingServe->where('status', PosOrderStatus::Paid);
                });
            })
            ->latest()
            ->get();
    }

    /** @return Collection<int, Product> */
    public function sellableProducts(): Collection
    {
        return Product::sellable()
            ->with(['addons' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('menu_category')
            ->orderBy('name')
            ->get();
    }

    /** @return list<string> */
    public function menuCategories(Collection $products): array
    {
        $configured = array_keys(MenuCategory::options());
        $used = $products
            ->pluck('menu_category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $ordered = array_values(array_intersect($configured, $used));
        $extras = array_values(array_diff($used, $ordered));

        return array_merge($ordered, $extras);
    }

    /** @return array<string, string> */
    public function menuCategoryLabels(): array
    {
        return MenuCategory::options();
    }

    private function recalculateTotals(PosOrder $order): void
    {
        $order->refresh();

        $subtotal = (float) $order->items()->sum('line_total');
        $discountAmount = PosDiscount::amountFor(
            $subtotal,
            $order->discount_type,
            (float) $order->discount_value,
        );

        $order->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => max(0, round($subtotal - $discountAmount, 4)),
        ]);
    }

    private function assertDiscountMutable(PosOrder $order): void
    {
        if ($order->isKasirEditable() || $order->canCheckoutAtKasir() || $order->needsKasirConfirmation()) {
            return;
        }

        throw new RuntimeException('Diskon tidak bisa diubah untuk pesanan ini.');
    }

    private function assertSellable(Product $product, float $quantity): void
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            throw new RuntimeException('Produk tidak dijual di kasir.');
        }

        if (! $product->is_active) {
            throw new RuntimeException('Produk tidak aktif.');
        }

        if (! $product->is_menu_item) {
            throw new RuntimeException('Produk tidak tampil di menu kasir.');
        }

        if ((float) $product->selling_price <= 0) {
            throw new RuntimeException('Harga jual belum diatur.');
        }

        if ($product->isMenuStockTracked() && $product->availableQuantity() < $quantity) {
            throw new RuntimeException($product->name.' stok habis / tidak cukup.');
        }
    }

    private function assertOrderMutable(PosOrder $order, bool $fromKasir = false): void
    {
        $mutable = $fromKasir ? $order->isKasirEditable() : $order->isEditable();

        if (! $mutable) {
            throw new RuntimeException(
                $fromKasir
                    ? 'Pesanan tidak bisa diubah.'
                    : 'Pesanan sudah dikirim. Silakan bayar di kasir.'
            );
        }
    }

    /**
     * @param  array{user_id?: ?int, cashier_employee_id?: ?int, cashier_name?: ?string}|null  $attribution
     * @return array{user_id: ?int, cashier_employee_id: ?int, cashier_name: ?string}
     */
    private function resolveCashierAttribution(?User $cashier, ?array $attribution): array
    {
        if (is_array($attribution)) {
            return [
                'user_id' => $attribution['user_id'] ?? $cashier?->id,
                'cashier_employee_id' => $attribution['cashier_employee_id'] ?? null,
                'cashier_name' => $attribution['cashier_name'] ?? $cashier?->name,
            ];
        }

        return [
            'user_id' => $cashier?->id,
            'cashier_employee_id' => null,
            'cashier_name' => $cashier?->name,
        ];
    }
}
