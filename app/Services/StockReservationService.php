<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\InventoryReservation;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Booking stok untuk Open Bill (status unpaid).
 * Lot fisik belum dipotong; qty tersedia = on-hand − reserved.
 * Saat bayar: release dulu, lalu FIFO/COGS seperti biasa.
 */
class StockReservationService
{
    public function __construct(
        private readonly BomCostService $bomCostService,
    ) {}

    public function releaseForOrder(PosOrder $order): void
    {
        if (! Schema::hasTable('inventory_reservations')) {
            return;
        }

        InventoryReservation::query()
            ->where('pos_order_id', $order->id)
            ->delete();
    }

    public function syncForOrder(PosOrder $order): void
    {
        if (! Schema::hasTable('inventory_reservations')) {
            return;
        }

        if (! $order->isOpenBill()) {
            $this->releaseForOrder($order);

            return;
        }

        DB::transaction(function () use ($order) {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $order->load(['items.product.addons.material']);

            $this->releaseForOrder($order);

            if ($order->items->isEmpty()) {
                return;
            }

            /** @var array<int, array{product: Product, quantity: float, pos_order_item_id: ?int}> $rows */
            $rows = [];

            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                foreach ($this->requirementsForItem($item) as $req) {
                    $productId = (int) $req['product']->id;
                    $qty = (float) $req['quantity'];
                    if ($qty <= 0) {
                        continue;
                    }

                    if (! isset($rows[$productId])) {
                        $rows[$productId] = [
                            'product' => $req['product'],
                            'quantity' => 0.0,
                            'pos_order_item_id' => (int) $item->id,
                        ];
                    }

                    $rows[$productId]['quantity'] += $qty;
                }
            }

            foreach ($rows as $row) {
                /** @var Product $product */
                $product = $row['product'];
                $need = round($row['quantity'], 4);
                $available = $product->availableQuantity();

                if ($available + 0.000001 < $need) {
                    $shortage = rtrim(rtrim(number_format($need - $available, 4, '.', ''), '0'), '.') ?: '0';
                    throw new RuntimeException(
                        "Stok {$product->name} tidak cukup untuk booking open bill. Kekurangan {$shortage} {$product->unit}."
                    );
                }

                InventoryReservation::create([
                    'pos_order_id' => $order->id,
                    'pos_order_item_id' => $row['pos_order_item_id'],
                    'product_id' => $product->id,
                    'quantity' => $need,
                ]);
            }
        });
    }

    /**
     * Kebutuhan stok per item — selaras logika jual (FG jika cukup, else BOM) + add-on.
     *
     * @return list<array{product: Product, quantity: float}>
     */
    public function requirementsForItem(PosOrderItem $item): array
    {
        $product = $item->product;
        $quantity = (float) $item->quantity;

        if (! $product || $quantity <= 0) {
            return [];
        }

        $requirements = [];

        if ($this->shouldReserveFinishedGoods($product, $quantity)) {
            $requirements[] = [
                'product' => $product,
                'quantity' => $quantity,
            ];
        } else {
            foreach ($this->bomCostService->explodeBom($product, $quantity) as $req) {
                $requirements[] = [
                    'product' => $req['product'],
                    'quantity' => (float) $req['quantity'],
                ];
            }
        }

        foreach ($this->addonMaterialRequirements($item) as $req) {
            $requirements[] = $req;
        }

        return $requirements;
    }

    private function shouldReserveFinishedGoods(Product $product, float $quantity): bool
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            return false;
        }

        return $product->availableQuantity() >= $quantity;
    }

    /**
     * @return list<array{product: Product, quantity: float}>
     */
    private function addonMaterialRequirements(PosOrderItem $item): array
    {
        $addonIds = collect($item->addon_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($addonIds === [] || ! $item->product) {
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
            ];
        }

        return $requirements;
    }
}
