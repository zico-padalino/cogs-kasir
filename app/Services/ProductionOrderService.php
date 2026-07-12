<?php

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLabor;
use App\Models\ProductionOrderMaterial;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionOrderService
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
        private readonly BomCostService $bomCostService,
        private readonly CogsCalculationService $cogsCalculationService,
    ) {}

    /**
     * @param  array<int, array{description: string, labor_hours: float, hourly_rate: float}>  $labors
     */
    public function createFromBom(
        ProductionOrder $order,
        array $labors = [],
        float $machineHours = 0,
    ): ProductionOrder {
        return DB::transaction(function () use ($order, $labors, $machineHours) {
            $requirements = $this->bomCostService->explodeBom(
                $order->product,
                (float) $order->quantity_planned,
            );

            foreach ($requirements as $req) {
                ProductionOrderMaterial::create([
                    'production_order_id' => $order->id,
                    'product_id' => $req['product']->id,
                    'quantity_planned' => $req['quantity'],
                    'quantity_used' => 0,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                ]);
            }

            foreach ($labors as $labor) {
                $totalCost = $labor['labor_hours'] * $labor['hourly_rate'];
                ProductionOrderLabor::create([
                    'production_order_id' => $order->id,
                    'description' => $labor['description'],
                    'labor_hours' => $labor['labor_hours'],
                    'hourly_rate' => $labor['hourly_rate'],
                    'total_cost' => $totalCost,
                ]);
            }

            $order->update([
                'machine_hours' => $machineHours,
                'status' => ProductionOrderStatus::Draft,
            ]);

            return $order->fresh(['materials.product', 'labors', 'product']);
        });
    }

    public function start(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== ProductionOrderStatus::Draft) {
            throw new RuntimeException('Hanya production order draft yang bisa dimulai.');
        }

        $order->update([
            'status' => ProductionOrderStatus::InProgress,
            'started_at' => now(),
        ]);

        return $order->fresh();
    }

    public function complete(ProductionOrder $order, ?float $quantityCompleted = null): ProductionOrder
    {
        if (! in_array($order->status, [ProductionOrderStatus::Draft, ProductionOrderStatus::InProgress])) {
            throw new RuntimeException('Production order tidak bisa diselesaikan.');
        }

        return DB::transaction(function () use ($order, $quantityCompleted) {
            $completedQty = $quantityCompleted ?? (float) $order->quantity_planned;

            foreach ($order->materials as $material) {
                $ratio = $completedQty / (float) $order->quantity_planned;
                $qtyToUse = (float) $material->quantity_planned * $ratio;

                $consumption = $this->inventoryCostService->consumeStock(
                    product: $material->product,
                    quantity: $qtyToUse,
                    logAction: 'production',
                    note: 'Produksi '.$order->order_number,
                );

                $material->update([
                    'quantity_used' => $qtyToUse,
                    'unit_cost' => $consumption->averageUnitCost,
                    'total_cost' => $consumption->totalCost,
                ]);
            }

            $order->update([
                'quantity_completed' => $completedQty,
                'status' => ProductionOrderStatus::Completed,
                'completed_at' => now(),
            ]);

            $unitCost = $this->cogsCalculationService
                ->calculateProductionCogs($order->fresh(['materials.product', 'labors']))
                ->unitHpp;

            $this->inventoryCostService->receiveStock(
                product: $order->product,
                quantity: $completedQty,
                unitCost: $unitCost,
                lotNumber: $order->order_number,
                sourceType: ProductionOrder::class,
                sourceId: $order->id,
            );

            $this->cogsCalculationService->recordProductionCogs($order->fresh(['materials.product', 'labors']));

            return $order->fresh(['materials.product', 'labors', 'product']);
        });
    }
}
