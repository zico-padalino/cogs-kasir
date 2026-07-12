<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\MaterialStockLog;
use App\Models\Product;
use App\Models\User;

class MaterialStockLogService
{
    public function log(
        string $action,
        Product $product,
        ?float $quantityBefore = null,
        ?float $quantityAfter = null,
        ?float $unitCost = null,
        ?InventoryLot $lot = null,
        ?string $note = null,
        ?User $user = null,
    ): MaterialStockLog {
        $before = $quantityBefore;
        $after = $quantityAfter;
        $delta = null;

        if ($before !== null && $after !== null) {
            $delta = round($after - $before, 6);
        }

        return MaterialStockLog::query()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_unit' => $product->unit,
            'inventory_lot_id' => $lot?->id,
            'action' => $action,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quantity_delta' => $delta,
            'unit_cost' => $unitCost,
            'lot_number' => $lot?->lot_number,
            'note' => $note,
            'user_id' => $user?->id ?? auth()->id(),
        ]);
    }
}
