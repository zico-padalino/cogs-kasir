<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MenuStockService
{
    public function __construct(private readonly InventoryCostService $inventoryCostService)
    {
    }

    public function syncMenuStock(Product $product, float $targetQuantity): void
    {
        if ($targetQuantity < 0) {
            throw new RuntimeException('Stok menu tidak boleh negatif.');
        }

        DB::transaction(function () use ($product, $targetQuantity) {
            $product->refresh();
            $current = $product->availableQuantity();
            $delta = round($targetQuantity - $current, 6);

            if (abs($delta) < 0.000001) {
                return;
            }

            if ($delta > 0) {
                $this->inventoryCostService->receiveStock(
                    product: $product,
                    quantity: $delta,
                    unitCost: $product->effectiveUnitHpp(),
                    lotNumber: 'MENU-'.now()->format('YmdHis'),
                );

                return;
            }

            $this->inventoryCostService->consumeStock($product, abs($delta));
        });
    }
}
