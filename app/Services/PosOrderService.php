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
use App\Support\KitchenBoardCache;
use App\Support\MenuCatalogCache;
use App\Support\PosDiscount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly CogsCalculationService $cogsCalculationService,
        private readonly CashLedgerService $cashLedgerService,
        private readonly KasirPushNotifier $kasirPushNotifier,
        private readonly StockReservationService $stockReservationService,
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
                'order_type' => null,
                'status' => PosOrderStatus::Open,
                'user_id' => $attr['user_id'],
                'cashier_employee_id' => $attr['cashier_employee_id'],
                'cashier_name' => $attr['cashier_name'],
            ]);
        });
    }

    public function updateOrderContext(
        PosOrder $order,
        ?PosOrderType $orderType = null,
        ?int $tableId = null,
        ?string $customerNote = null,
    ): PosOrder {
        if (! $order->isKasirEditable()) {
            throw new RuntimeException('Pesanan tidak bisa diubah.');
        }

        $data = [
            'customer_note' => filled($customerNote) ? trim($customerNote) : null,
        ];

        if ($orderType !== null) {
            $data['order_type'] = $orderType;

            if ($order->source === PosOrderSource::Kasir) {
                $data['pos_table_id'] = null;
            } elseif ($orderType === PosOrderType::DineIn) {
                $data['pos_table_id'] = $tableId;
            } else {
                $data['pos_table_id'] = null;
            }
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
                PosOrderStatus::PendingPayment,
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
                'order_type' => null,
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
        return DB::transaction(function () use ($order, $product, $quantity, $unitPrice, $fromKasir, $notes, $addonIds) {
            $this->assertOrderMutable($order, $fromKasir);

            if ($fromKasir
                && $order->source === PosOrderSource::Kasir
                && ! filled($order->customer_note)
            ) {
                throw new RuntimeException('Isi nama pelanggan dulu sebelum menambah menu.');
            }

            if ($fromKasir
                && $order->source === PosOrderSource::Kasir
                && $order->order_type === null
            ) {
                throw new RuntimeException('Pilih Dine In atau Take Away dulu sebelum menambah menu.');
            }

            $this->assertSellable($product, $quantity, forPayment: false, order: $order);

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
            $this->syncOpenBillStock($order);

            return $item->load('product');
        });
    }

    public function updateItemQuantity(PosOrderItem $item, float $quantity, bool $fromKasir = false): void
    {
        DB::transaction(function () use ($item, $quantity, $fromKasir) {
            $order = $item->order;

            $this->assertOrderMutable($order, $fromKasir);

            $this->assertSellable($item->product, $quantity, forPayment: false, order: $order);

            $item->update([
                'quantity' => $quantity,
                'line_total' => round($quantity * (float) $item->unit_price, 4),
            ]);

            $this->recalculateTotals($order);
            $this->syncOpenBillStock($order);
        });
    }

    public function removeItem(PosOrderItem $item, bool $fromKasir = false): void
    {
        DB::transaction(function () use ($item, $fromKasir) {
            $order = $item->order;

            $this->assertOrderMutable($order, $fromKasir);

            $item->delete();
            $this->recalculateTotals($order);
            $this->syncOpenBillStock($order);
        });
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
            throw new RuntimeException('Isi nama pemesan dulu sebelum lanjut bayar.');
        }

        if ($order->order_type === null) {
            throw new RuntimeException('Pilih Take Away atau Dine In dulu.');
        }

        // Belum masuk antrean kasir — pelanggan pilih QRIS atau tunai dulu.
        $order->update(['status' => PosOrderStatus::PendingPayment]);

        $fresh = $order->fresh(['items.product', 'table']);
        $this->activity()->orderEvent(
            'order_submitted',
            'Pesanan meja '.$fresh->order_number.' siap dibayar'.($fresh->customer_note ? ' oleh '.$fresh->customer_note : '').'.',
            $fresh,
            ['actor_name' => $fresh->customer_note],
        );

        return $fresh;
    }

    /** Pelanggan pilih bayar tunai → baru masuk antrean kasir. */
    public function sendCashOrderToKasir(PosOrder $order): PosOrder
    {
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang bisa dikirim ke kasir.');
        }

        if ($order->status !== PosOrderStatus::PendingPayment) {
            throw new RuntimeException('Pesanan sudah dikirim atau belum siap.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Pesanan masih kosong.');
        }

        $order->update(['status' => PosOrderStatus::Submitted]);

        $fresh = $order->fresh(['items.product', 'table']);
        KitchenBoardCache::forget();
        $this->kasirPushNotifier->notifyNewOnlineOrder($fresh);
        $this->activity()->orderEvent(
            'order_cash_kasir',
            'Pesanan meja '.$fresh->order_number.' dikirim ke kasir (tunai)'.($fresh->customer_note ? ' oleh '.$fresh->customer_note : '').'.',
            $fresh,
            ['actor_name' => $fresh->customer_note],
        );

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

        if (! in_array($order->status, [PosOrderStatus::PendingPayment, PosOrderStatus::Submitted], true)) {
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

        $fresh = $order->fresh(['items.product', 'table']);
        KitchenBoardCache::forget();
        $this->activity()->orderEvent(
            'order_confirmed',
            'Pesanan '.$fresh->order_number.' dikonfirmasi kasir'.($fresh->cashier_name ? ' ('.$fresh->cashier_name.')' : '').'.',
            $fresh,
        );

        return $fresh;
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
            if (in_array($order->status, [PosOrderStatus::PendingPayment, PosOrderStatus::Submitted], true)) {
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
            if (! $item->product) {
                throw new RuntimeException('Ada item tanpa produk. Hapus item bermasalah lalu coba bayar lagi.');
            }

            $this->assertSellable($item->product, (float) $item->quantity, forPayment: true, order: $order);
        }

        $total = (float) $order->total;
        $changeAmount = null;
        $allowsProof = in_array($paymentMethod, [PaymentMethod::Qris, PaymentMethod::Transfer], true);

        if ($paymentMethod === PaymentMethod::Cash) {
            if ($amountReceived === null || $amountReceived < $total) {
                throw new RuntimeException('Uang diterima harus minimal sebesar total tagihan.');
            }

            $changeAmount = round($amountReceived - $total, 4);
        }

        $proofPath = null;
        if ($allowsProof && $paymentProof) {
            $proofPath = $this->storePaymentProof($paymentProof);
        }

        if ($allowsProof && $paymentMethod === PaymentMethod::Qris && $order->source === PosOrderSource::Online && ! $proofPath && ! $cashier) {
            // Bayar mandiri dari meja: bukti wajib.
            throw new RuntimeException('Unggah bukti pembayaran QRIS dulu.');
        }

        try {
            $result = DB::transaction(function () use ($order, $paymentMethod, $cashier, $amountReceived, $changeAmount, $proofPath, $attr) {
                // Lepas booking dulu supaya FIFO/COGS bisa memotong lot fisik.
                $this->stockReservationService->releaseForOrder($order);
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
                if (str_starts_with($proofPath, 'uploads/')) {
                    $full = public_path($proofPath);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                } else {
                    Storage::disk('public')->delete($proofPath);
                }
            }

            throw $e;
        }

        if (($result['stock_out'] ?? []) !== []) {
            $this->kasirPushNotifier->notifyStockOut($result['stock_out'], $result['order']);
        }

        KitchenBoardCache::forget();

        // QRIS mandiri dari meja: baru masuk radar kasir saat sudah lunas.
        if ($result['order']->source === PosOrderSource::Online && ! $cashier) {
            $this->kasirPushNotifier->notifyNewOnlineOrder($result['order']);
        }

        $this->kasirPushNotifier->notifyKitchenOrder($result['order']);

        $paid = $result['order'];
        $this->activity()->orderEvent(
            'order_paid',
            'Transaksi '.$paid->order_number.' lunas Rp '.number_format((float) $paid->total, 0, ',', '.').
            ($paid->payment_method ? ' via '.$paid->payment_method->label() : '').'.',
            $paid,
            [
                'invoice' => $result['invoice'] ?? null,
                'amount_received' => $paid->amount_received,
                'change_amount' => $paid->change_amount,
            ],
        );

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
     * Stok langsung dibooking (tidak bisa dijual ke pesanan lain) sampai lunas / dibatalkan.
     *
     * @return array{order: PosOrder, merged: bool}
     */
    public function openBill(PosOrder $order, ?array $attribution = null): array
    {
        if ($order->source !== PosOrderSource::Kasir) {
            throw new RuntimeException('Tagihan terbuka hanya bisa dibuat dari kasir.');
        }

        if (! in_array($order->status, [PosOrderStatus::Open, PosOrderStatus::Unpaid], true)) {
            throw new RuntimeException('Pesanan ini tidak bisa disimpan sebagai tagihan terbuka.');
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            throw new RuntimeException('Tambah item dulu sebelum simpan tagihan terbuka.');
        }

        if (! filled($order->customer_note) && ! $order->pos_table_id) {
            throw new RuntimeException('Isi nama pelanggan (atau pilih meja) supaya tagihan terbuka mudah dicari.');
        }

        $attr = $attribution ?? [];

        $result = DB::transaction(function () use ($order, $attr) {
            $existing = $order->status === PosOrderStatus::Open
                ? $this->findMatchingOpenBill($order)
                : null;

            if ($existing && (int) $existing->id !== (int) $order->id) {
                foreach ($order->items as $item) {
                    $item->update(['pos_order_id' => $existing->id]);
                }

                $this->recalculateTotals($existing);

                $this->stockReservationService->releaseForOrder($order);

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

                $freshExisting = $existing->fresh(['items.product', 'table']);
                $this->stockReservationService->syncForOrder($freshExisting);

                return [
                    'order' => $freshExisting->fresh(['items.product', 'table']),
                    'merged' => true,
                ];
            }

            $order->update([
                'status' => PosOrderStatus::Unpaid,
                'user_id' => $attr['user_id'] ?? $order->user_id,
                'cashier_employee_id' => $attr['cashier_employee_id'] ?? $order->cashier_employee_id,
                'cashier_name' => $attr['cashier_name'] ?? $order->cashier_name,
            ]);

            $fresh = $order->fresh(['items.product', 'table']);
            $this->stockReservationService->syncForOrder($fresh);

            return [
                'order' => $fresh->fresh(['items.product', 'table']),
                'merged' => false,
            ];
        });

        $this->kasirPushNotifier->notifyKitchenOrder($result['order']);
        KitchenBoardCache::forget();

        return $result;
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

        DB::transaction(function () use ($order) {
            $this->stockReservationService->releaseForOrder($order);
            $order->update(['status' => PosOrderStatus::Cancelled]);
        });

        $fresh = $order->fresh(['items.product', 'table']);
        if ($fresh) {
            $this->activity()->orderEvent(
                'order_cancelled',
                'Pesanan '.$fresh->order_number.' dibatalkan.',
                $fresh,
            );
        }
    }

    public function cancelPendingOnlineOrder(PosOrder $order): void
    {
        $isOnlineWaiting = $order->source === PosOrderSource::Online
            && in_array($order->status, [
                PosOrderStatus::PendingPayment,
                PosOrderStatus::Submitted,
                PosOrderStatus::Confirmed,
            ], true);

        $isOpenBill = $order->source === PosOrderSource::Kasir
            && $order->status === PosOrderStatus::Unpaid;

        if (! $isOnlineWaiting && ! $isOpenBill) {
            throw new RuntimeException('Pesanan ini tidak bisa dihapus dari daftar menunggu.');
        }

        $this->cancelOrder($order);
    }

    /**
     * Batalkan pelunasan (stok + COGS + kas), buka ulang pesanan agar bisa diedit lalu dibayar lagi.
     */
    public function reopenForEdit(PosOrder $order): PosOrder
    {
        if (! $order->canReopenForEdit()) {
            throw new RuntimeException('Hanya pesanan yang sudah dibayar yang bisa diedit ulang.');
        }

        $fresh = DB::transaction(function () use ($order) {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->load(['salesTransactions.product']);

            if (! $order->canReopenForEdit()) {
                throw new RuntimeException('Hanya pesanan yang sudah dibayar yang bisa diedit ulang.');
            }

            foreach ($order->salesTransactions as $sale) {
                $this->cogsCalculationService->reverseSaleCogs($sale);
                $sale->delete();
            }

            $this->cashLedgerService->clearOrderSaleEntries($order);

            $proofPath = $order->payment_proof_path;
            $nextStatus = $order->source === PosOrderSource::Online
                ? PosOrderStatus::Confirmed
                : PosOrderStatus::Unpaid;

            $order->update([
                'status' => $nextStatus,
                'payment_method' => null,
                'payment_proof_path' => null,
                'paid_at' => null,
                'served_at' => null,
                'amount_received' => null,
                'change_amount' => null,
            ]);

            if ($proofPath) {
                if (str_starts_with($proofPath, 'uploads/')) {
                    $full = public_path($proofPath);
                    if (is_file($full)) {
                        @unlink($full);
                    }
                } else {
                    Storage::disk('public')->delete($proofPath);
                }
            }

            $fresh = $order->fresh(['items.product', 'table', 'cashier']);

            if ($fresh->isOpenBill()) {
                $this->stockReservationService->syncForOrder($fresh);
            }

            return $fresh->fresh(['items.product', 'table', 'cashier']);
        });

        $this->activity()->orderEvent(
            'order_reopened',
            'Transaksi '.$fresh->order_number.' dibuka ulang untuk diedit.',
            $fresh,
        );

        return $fresh;
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

        KitchenBoardCache::forget();

        return $order->fresh(['items.product', 'table']);
    }

    /** Tandai item sudah / belum diantar (open bill atau setelah bayar). */
    public function setItemDelivered(PosOrderItem $item, bool $delivered): PosOrder
    {
        $order = $item->order;

        if (! $order || ! $order->canChecklistDelivered()) {
            throw new RuntimeException('Ceklis antar hanya untuk Open Bill atau pesanan yang sudah dibayar.');
        }

        $item->update([
            'is_delivered' => $delivered,
            'delivered_at' => $delivered ? now() : null,
        ]);

        if ($delivered && $order->status === PosOrderStatus::Paid) {
            $remaining = $order->items()->where('is_delivered', false)->exists();
            if (! $remaining) {
                return $this->markServed($order->fresh());
            }
        }

        KitchenBoardCache::forget();

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

    /**
     * Antrian dapur: open bill + sudah bayar (belum selesai diantar).
     * Hanya item kategori dapur (default: makanan, snack).
     *
     * @return Collection<int, PosOrder>
     */
    public function kitchenOrders()
    {
        $categories = $this->kitchenCategories();
        $since = now()->subDays(2)->startOfDay();

        return PosOrder::query()
            ->with([
                'table:id,table_number,label',
                'items' => function ($query) use ($categories) {
                    $query->select([
                        'id',
                        'pos_order_id',
                        'product_id',
                        'quantity',
                        'unit_price',
                        'line_total',
                        'notes',
                        'addon_ids',
                        'is_delivered',
                        'delivered_at',
                    ])->whereHas('product', function ($product) use ($categories) {
                        $product->whereIn('menu_category', $categories);
                    });
                },
                'items.product:id,name,menu_category,image_path',
            ])
            ->where(function ($query) {
                $query->where(function ($openBill) {
                    $openBill->where('source', PosOrderSource::Kasir)
                        ->where('status', PosOrderStatus::Unpaid);
                })->orWhere('status', PosOrderStatus::Paid);
            })
            ->where(function ($query) use ($since) {
                $query->where('order_day', '>=', $since->toDateString())
                    ->orWhere('updated_at', '>=', $since);
            })
            ->whereHas('items.product', function ($query) use ($categories) {
                $query->whereIn('menu_category', $categories);
            })
            ->orderByRaw('COALESCE(paid_at, confirmed_at, updated_at, created_at) asc')
            ->limit(40)
            ->get()
            ->map(function (PosOrder $order) use ($categories) {
                $kitchenItems = $order->items
                    ->filter(fn ($item) => in_array($item->product?->menu_category, $categories, true))
                    ->values();
                $order->setRelation('items', $kitchenItems);

                return $order;
            })
            ->filter(fn (PosOrder $order) => $order->items->isNotEmpty())
            ->values();
    }

    /** @return list<string> */
    public function kitchenCategories(): array
    {
        return $this->stationCategories('kitchen');
    }

    /** @return list<string> */
    public function barCategories(): array
    {
        return $this->stationCategories('bar');
    }

    public function isKitchenProductCategory(?string $category): bool
    {
        return in_array($category, $this->kitchenCategories(), true);
    }

    public function isBarProductCategory(?string $category): bool
    {
        return in_array($category, $this->barCategories(), true);
    }

    /**
     * Salinan order hanya berisi item stasiun (dapur/bar) untuk cetak tiket.
     *
     * @param  'kitchen'|'bar'  $station
     */
    public function orderForStation(PosOrder $order, string $station): PosOrder
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $categories = $this->stationCategories($station);
        $filtered = $order->items
            ->filter(fn ($item) => in_array($item->product?->menu_category, $categories, true))
            ->values();
        $order->setRelation('items', $filtered);

        return $order;
    }

    /**
     * @param  'kitchen'|'bar'  $station
     * @return list<string>
     */
    public function stationCategories(string $station): array
    {
        $defaults = $station === 'bar'
            ? ['minuman']
            : ['makanan', 'snack'];
        $key = $station === 'bar' ? 'pos.bar_categories' : 'pos.kitchen_categories';
        $categories = config($key, $defaults);

        return array_values(array_filter(
            is_array($categories) ? $categories : $defaults,
            fn ($slug) => is_string($slug) && $slug !== '',
        ));
    }

    /** @return Collection<int, Product> */
    public function sellableProducts(): Collection
    {
        // File cache — jangan pakai CACHE_STORE=database (bisa tanpa tabel → 500).
        return MenuCatalogCache::remember(function () {
            return Product::sellable()
                ->with(['addons' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')])
                ->orderBy('menu_category')
                ->orderBy('name')
                ->get();
        });
    }

    /** @return list<string> */
    public function menuCategories(Collection $products): array
    {
        $configured = array_keys(MenuCategory::options());
        $used = $products
            ->map(function ($product) {
                if (is_array($product)) {
                    $value = $product['menu_category'] ?? null;

                    return is_string($value) ? $value : null;
                }

                $value = data_get($product, 'menu_category');

                return is_string($value) ? $value : null;
            })
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

    private function assertSellable(Product $product, float $quantity, bool $forPayment = false, ?PosOrder $order = null): void
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

        // Ceklis "Habis" hanya blokir tambah item baru — pelunasan open bill / draft tetap boleh.
        if (! $forPayment && $product->is_sold_out) {
            throw new RuntimeException($product->name.' sedang habis.');
        }

        // Open bill / pelunasan: abaikan booking milik order ini sendiri.
        $exceptOrderId = ($order && ($forPayment || $order->isOpenBill())) ? (int) $order->id : null;

        if ($product->isMenuStockTracked() && $product->availableQuantity($exceptOrderId) < $quantity) {
            throw new RuntimeException($product->name.' stok habis / tidak cukup.');
        }
    }

    private function syncOpenBillStock(PosOrder $order): void
    {
        $order->refresh();

        if ($order->isOpenBill()) {
            $this->stockReservationService->syncForOrder($order);
        }
    }

    /** Pastikan open bill lama ikut punya booking stok (mis. setelah migrasi / buka ulang). */
    public function ensureOpenBillStockBooking(PosOrder $order): void
    {
        if ($order->isOpenBill()) {
            $this->stockReservationService->syncForOrder($order);
        }
    }

    /** Booking ulang semua tagihan terbuka yang belum punya reserve (migrasi / data lama). */
    public function syncMissingOpenBillStockBookings(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('inventory_reservations')) {
            return;
        }

        // Throttle: hindari kerja berat berulang saat banyak tab buka /kasir (Number of Processes).
        $lock = storage_path('framework/cache/.openbill-sync');
        $dir = dirname($lock);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_file($lock) && filemtime($lock) > time() - 90) {
            return;
        }
        @touch($lock);

        PosOrder::query()
            ->where('source', PosOrderSource::Kasir)
            ->where('status', PosOrderStatus::Unpaid)
            ->whereDoesntHave('inventoryReservations')
            ->with(['items.product'])
            ->orderBy('id')
            ->each(function (PosOrder $order) {
                try {
                    $this->stockReservationService->syncForOrder($order);
                } catch (\RuntimeException) {
                    // Stok sudah tidak cukup untuk bill lama — error muncul saat buka/update bill.
                }
            });
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
    private function storePaymentProof(UploadedFile $paymentProof): string
    {
        $dirRelative = 'uploads/payment-proofs/'.now()->format('Y/m');
        $dir = public_path($dirRelative);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755);
        }

        $ext = strtolower($paymentProof->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
            $ext = 'jpg';
        }

        $name = Str::uuid()->toString().'.'.$ext;
        $paymentProof->move($dir, $name);

        return $dirRelative.'/'.$name;
    }

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

    private function activity(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }
}
