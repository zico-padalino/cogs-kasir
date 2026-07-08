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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PosOrderService
{
    public function __construct(
        private readonly CogsCalculationService $cogsCalculationService,
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

    public function createKasirOrder(?User $cashier = null): PosOrder
    {
        return DB::transaction(function () use ($cashier) {
            $orderDay = now()->toDateString();

            return PosOrder::create([
                'order_number' => $this->nextOrderNumberForDay($orderDay),
                'order_day' => $orderDay,
                'source' => PosOrderSource::Kasir,
                'order_type' => PosOrderType::Takeaway,
                'status' => PosOrderStatus::Open,
                'user_id' => $cashier?->id,
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

    public function resolveOnlineOrder(?int $sessionOrderId = null): PosOrder
    {
        if ($sessionOrderId) {
            $order = PosOrder::query()
                ->whereKey($sessionOrderId)
                ->where('source', PosOrderSource::Online)
                ->first();

            if ($order && in_array($order->status, [PosOrderStatus::Open, PosOrderStatus::Submitted, PosOrderStatus::Confirmed, PosOrderStatus::Paid], true)) {
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
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang bisa diubah dari menu QR.');
        }

        if (! $order->isEditable()) {
            throw new RuntimeException('Pesanan sudah dikirim. Silakan bayar di kasir.');
        }

        $order->update([
            'customer_note' => trim($customerNote),
        ]);

        return $order->fresh();
    }

    public function addItem(PosOrder $order, Product $product, float $quantity, ?float $unitPrice = null, bool $fromKasir = false, ?string $notes = null): PosOrderItem
    {
        $this->assertOrderMutable($order, $fromKasir);

        $this->assertSellable($product, $quantity);

        $price = $unitPrice ?? (float) $product->selling_price;
        if ($price <= 0) {
            $price = (float) $product->standard_cost;
        }

        $item = PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => round($quantity * $price, 4),
            'notes' => $notes ? trim($notes) : null,
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

        $order->update(['status' => PosOrderStatus::Submitted]);

        return $order->fresh(['items.product', 'table']);
    }

    public function confirmOrder(PosOrder $order, ?User $cashier = null): PosOrder
    {
        if ($order->source !== PosOrderSource::Online) {
            throw new RuntimeException('Hanya pesanan online yang perlu dikonfirmasi kasir.');
        }

        if ($order->status !== PosOrderStatus::Submitted) {
            throw new RuntimeException('Pesanan tidak bisa dikonfirmasi.');
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException('Pesanan masih kosong.');
        }

        $order->update([
            'status' => PosOrderStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $cashier?->id,
        ]);

        return $order->fresh(['items.product', 'table']);
    }

    /**
     * @return array{order: PosOrder, invoice: string}
     */
    public function payOrder(
        PosOrder $order,
        PaymentMethod $paymentMethod,
        ?User $cashier = null,
        ?float $amountReceived = null,
    ): array {
        if ($order->source === PosOrderSource::Online) {
            if ($order->status === PosOrderStatus::Submitted) {
                throw new RuntimeException('Konfirmasi pesanan selesai dulu sebelum bayar.');
            }

            if ($order->status !== PosOrderStatus::Confirmed) {
                throw new RuntimeException('Pesanan sudah dibayar atau dibatalkan.');
            }
        } elseif ($order->status !== PosOrderStatus::Open) {
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

        if ($paymentMethod === PaymentMethod::Cash) {
            if ($amountReceived === null || $amountReceived < $total) {
                throw new RuntimeException('Uang diterima harus minimal sebesar total tagihan.');
            }

            $changeAmount = round($amountReceived - $total, 4);
        }

        return DB::transaction(function () use ($order, $paymentMethod, $cashier, $amountReceived, $changeAmount) {
            $invoiceBase = 'POS-'.$order->order_number;
            $soldAt = now();

            foreach ($order->items as $index => $item) {
                $sale = SalesTransaction::create([
                    'pos_order_id' => $order->id,
                    'invoice_number' => $invoiceBase.'-'.($index + 1),
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'selling_price' => $item->unit_price,
                    'total_revenue' => $item->line_total,
                    'sold_at' => $soldAt,
                ]);

                $this->cogsCalculationService->recordSaleCogs($sale);
            }

            $order->update([
                'status' => PosOrderStatus::Paid,
                'payment_method' => $paymentMethod,
                'paid_at' => $soldAt,
                'user_id' => $cashier?->id ?? $order->user_id,
                'amount_received' => $amountReceived,
                'change_amount' => $changeAmount,
            ]);

            return [
                'order' => $order->fresh(['items.product', 'table', 'cashier']),
                'invoice' => $invoiceBase,
            ];
        });
    }

    public function cancelOrder(PosOrder $order): void
    {
        if ($order->status === PosOrderStatus::Paid) {
            throw new RuntimeException('Pesanan lunas tidak bisa dibatalkan.');
        }

        $order->update(['status' => PosOrderStatus::Cancelled]);
    }

    /** @return Collection<int, Product> */
    public function sellableProducts(): Collection
    {
        return Product::sellable()
            ->orderBy('menu_category')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->availableQuantity() > 0)
            ->values();
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
        $subtotal = (float) $order->items()->sum('line_total');

        $order->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);
    }

    private function assertSellable(Product $product, float $quantity): void
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            throw new RuntimeException('Produk tidak dijual di kasir.');
        }

        if (! $product->is_active) {
            throw new RuntimeException('Produk tidak aktif.');
        }

        if ($product->availableQuantity() < $quantity) {
            throw new RuntimeException("Stok {$product->name} tidak cukup (tersedia: {$product->availableQuantity()}).");
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
}
