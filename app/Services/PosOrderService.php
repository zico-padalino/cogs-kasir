<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\ProductType;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
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

    public function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.random_int(100, 999);
    }

    public function createKasirOrder(?User $cashier = null): PosOrder
    {
        return PosOrder::create([
            'order_number' => $this->generateOrderNumber(),
            'source' => PosOrderSource::Kasir,
            'status' => PosOrderStatus::Open,
            'user_id' => $cashier?->id,
        ]);
    }

    public function getOrCreateOnlineOrder(PosTable $table): PosOrder
    {
        $existing = $table->activeOrder();

        if ($existing) {
            return $existing;
        }

        return PosOrder::create([
            'order_number' => $this->generateOrderNumber(),
            'pos_table_id' => $table->id,
            'source' => PosOrderSource::Online,
            'status' => PosOrderStatus::Open,
        ]);
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

        $order->update(['status' => PosOrderStatus::Submitted]);

        return $order->fresh(['items.product', 'table']);
    }

    /**
     * @return array{order: PosOrder, invoice: string}
     */
    public function payOrder(PosOrder $order, PaymentMethod $paymentMethod, ?User $cashier = null): array
    {
        if (! in_array($order->status, [PosOrderStatus::Open, PosOrderStatus::Submitted], true)) {
            throw new RuntimeException('Pesanan sudah dibayar atau dibatalkan.');
        }

        $order->load('items.product');

        if ($order->items->isEmpty()) {
            throw new RuntimeException('Tidak ada item untuk dibayar.');
        }

        foreach ($order->items as $item) {
            $this->assertSellable($item->product, (float) $item->quantity);
        }

        return DB::transaction(function () use ($order, $paymentMethod, $cashier) {
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
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->availableQuantity() > 0)
            ->values();
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
