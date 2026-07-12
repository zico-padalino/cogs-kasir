<?php

namespace App\Services;

use App\DTOs\MaterialConsumptionResult;
use App\Enums\CostingMethod;
use App\Models\InventoryLot;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class InventoryCostService
{
    public function __construct(
        private readonly MaterialStockLogService $stockLogService,
    ) {}

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

    public function consumeStock(
        Product $product,
        float $quantity,
        bool $persist = true,
        ?string $logAction = null,
        ?string $note = null,
    ): MaterialConsumptionResult {
        if ($quantity <= 0) {
            return new MaterialConsumptionResult(0, 0);
        }

        $before = $persist && $logAction ? $product->availableQuantity() : null;

        $result = match ($product->costing_method) {
            CostingMethod::Fifo => $this->consumeFifo($product, $quantity, $persist),
            CostingMethod::WeightedAverage => $this->consumeWeightedAverage($product, $quantity, $persist),
            CostingMethod::Standard => $this->consumeStandard($product, $quantity, $persist),
        };

        if ($persist && $logAction && $before !== null && Schema::hasTable('material_stock_logs')) {
            $after = round($before - $quantity, 6);
            $this->stockLogService->log(
                action: $logAction,
                product: $product,
                quantityBefore: $before,
                quantityAfter: $after,
                unitCost: $result->averageUnitCost,
                note: $note,
            );
        }

        return $result;
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

    /**
     * Sesuaikan stok sisa bahan ke jumlah aktual (stock opname).
     * Bertambah → lot baru; berkurang → konsumsi FIFO/WA.
     */
    public function syncAvailableQuantity(Product $product, float $targetQuantity): void
    {
        if ($targetQuantity < 0) {
            throw new RuntimeException('Stok sisa tidak boleh negatif.');
        }

        DB::transaction(function () use ($product, $targetQuantity) {
            $product->refresh();
            $current = $product->availableQuantity();
            $delta = round($targetQuantity - $current, 6);

            if (abs($delta) < 0.000001) {
                return;
            }

            if ($delta > 0) {
                $this->receiveStock(
                    product: $product,
                    quantity: $delta,
                    unitCost: $this->getWeightedAverageCost($product) ?: $product->effectiveUnitHpp(),
                    lotNumber: 'ADJ-'.now()->format('YmdHis'),
                );

                return;
            }

            $this->consumeStock($product, abs($delta));
        });
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
                $shortage = rtrim(rtrim(number_format($remaining, 6, '.', ''), '0'), '.') ?: '0';
                throw new RuntimeException(
                    "Stok {$product->name} tidak cukup. Kekurangan {$shortage} {$product->unit}."
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
