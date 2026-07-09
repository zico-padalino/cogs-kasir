<?php

namespace App\Services;

use App\DTOs\MaterialConsumptionResult;
use App\Enums\CostingMethod;
use App\Models\InventoryLot;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryCostService
{
    public function receiveStock(
        Product $product,
        float $quantity,
        float $unitCost,
        ?string $lotNumber = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): InventoryLot {
        return InventoryLot::create([
            'product_id' => $product->id,
            'lot_number' => $lotNumber,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'received_at' => now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }

    public function consumeStock(Product $product, float $quantity, bool $persist = true): MaterialConsumptionResult
    {
        if ($quantity <= 0) {
            return new MaterialConsumptionResult(0, 0);
        }

        return match ($product->costing_method) {
            CostingMethod::Fifo => $this->consumeFifo($product, $quantity, $persist),
            CostingMethod::WeightedAverage => $this->consumeWeightedAverage($product, $quantity, $persist),
            CostingMethod::Standard => $this->consumeStandard($product, $quantity, $persist),
        };
    }

    public function getWeightedAverageCost(Product $product): float
    {
        $lots = $product->inventoryLots()
            ->where('quantity_remaining', '>', 0)
            ->get();

        $totalQty = $lots->sum('quantity_remaining');
        if ($totalQty <= 0) {
            return $product->effectiveUnitHpp();
        }

        $totalValue = $lots->sum(fn (InventoryLot $lot) => (float) $lot->quantity_remaining * (float) $lot->unit_cost);

        return $totalValue / $totalQty;
    }

    private function consumeFifo(Product $product, float $quantity, bool $persist): MaterialConsumptionResult
    {
        return DB::transaction(function () use ($product, $quantity, $persist) {
            $lots = $product->inventoryLots()
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $totalCost = 0.0;
            $consumptions = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $available = (float) $lot->quantity_remaining;
                $consumed = min($available, $remaining);
                $cost = $consumed * (float) $lot->unit_cost;

                $consumptions[] = [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'quantity' => $consumed,
                    'unit_cost' => (float) $lot->unit_cost,
                    'cost' => round($cost, 4),
                ];

                if ($persist) {
                    $lot->quantity_remaining = $available - $consumed;
                    $lot->save();
                }

                $totalCost += $cost;
                $remaining -= $consumed;
            }

            if ($remaining > 0.000001) {
                throw new RuntimeException(
                    "Stok tidak mencukupi untuk produk {$product->sku}. Kekurangan: {$remaining}"
                );
            }

            return new MaterialConsumptionResult(
                totalCost: $totalCost,
                averageUnitCost: $totalCost / $quantity,
                lotConsumptions: $consumptions,
            );
        });
    }

    private function consumeWeightedAverage(Product $product, float $quantity, bool $persist): MaterialConsumptionResult
    {
        $unitCost = $this->getWeightedAverageCost($product);

        if ($persist) {
            $this->consumeFifo($product, $quantity, true);
        }

        return new MaterialConsumptionResult(
            totalCost: $quantity * $unitCost,
            averageUnitCost: $unitCost,
            lotConsumptions: [
                [
                    'method' => 'weighted_average',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'cost' => round($quantity * $unitCost, 4),
                ],
            ],
        );
    }

    private function consumeStandard(Product $product, float $quantity, bool $persist): MaterialConsumptionResult
    {
        $unitCost = $product->effectiveUnitHpp();

        if ($persist) {
            $this->consumeFifo($product, $quantity, true);
        }

        return new MaterialConsumptionResult(
            totalCost: $quantity * $unitCost,
            averageUnitCost: $unitCost,
            lotConsumptions: [
                [
                    'method' => 'standard',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'cost' => round($quantity * $unitCost, 4),
                ],
            ],
        );
    }
}
