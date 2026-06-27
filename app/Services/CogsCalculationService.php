<?php

namespace App\Services;

use App\DTOs\CogsResult;
use App\Enums\ProductionOrderStatus;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\SalesTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CogsCalculationService
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
        private readonly BomCostService $bomCostService,
        private readonly OverheadAllocationService $overheadAllocationService,
    ) {}

    public function calculateProductionCogs(ProductionOrder $order): CogsResult
    {
        if ($order->status !== ProductionOrderStatus::Completed) {
            throw new RuntimeException('Production order harus berstatus completed.');
        }

        $quantity = (float) $order->quantity_completed;
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity completed harus lebih dari 0.');
        }

        $directMaterial = $order->totalDirectMaterial();
        $directLabor = $order->totalDirectLabor();
        $overhead = $this->overheadAllocationService->allocateForProduction($order);
        $totalCogs = $directMaterial + $directLabor + $overhead['total'];

        return new CogsResult(
            directMaterial: $directMaterial,
            directLabor: $directLabor,
            manufacturingOverhead: $overhead['total'],
            totalCogs: $totalCogs,
            unitCogs: $totalCogs / $quantity,
            calculationMethod: 'absorption_costing_production',
            breakdown: [
                'production_order_id' => $order->id,
                'order_number' => $order->order_number,
                'quantity_completed' => $quantity,
                'materials' => $order->materials->map(fn ($m) => [
                    'product_id' => $m->product_id,
                    'sku' => $m->product->sku,
                    'quantity_used' => (float) $m->quantity_used,
                    'unit_cost' => (float) $m->unit_cost,
                    'total_cost' => (float) $m->total_cost,
                ])->all(),
                'labors' => $order->labors->map(fn ($l) => [
                    'description' => $l->description,
                    'labor_hours' => (float) $l->labor_hours,
                    'hourly_rate' => (float) $l->hourly_rate,
                    'total_cost' => (float) $l->total_cost,
                ])->all(),
                'overhead' => $overhead,
            ],
        );
    }

    public function calculateSaleCogs(Product $product, float $quantity, bool $consumeInventory = true): CogsResult
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity harus lebih dari 0.');
        }

        $bomRollUp = $this->bomCostService->rollUpCost($product, $quantity);
        $directMaterial = $bomRollUp['total_cost'];

        if ($consumeInventory) {
            $requirements = $this->bomCostService->explodeBom($product, $quantity);
            $consumptionDetails = [];

            foreach ($requirements as $req) {
                $consumption = $this->inventoryCostService->consumeStock($req['product'], $req['quantity']);
                $consumptionDetails[] = [
                    'product_id' => $req['product']->id,
                    'sku' => $req['product']->sku,
                    'quantity' => $req['quantity'],
                    'cost' => round($consumption->totalCost, 4),
                    'lots' => $consumption->lotConsumptions,
                ];
            }

            $directMaterial = array_sum(array_column($consumptionDetails, 'cost'));
        }

        $overhead = $this->overheadAllocationService->allocateForSale(
            directMaterial: $directMaterial,
            units: $quantity,
        );

        $totalCogs = $directMaterial + $overhead['total'];

        return new CogsResult(
            directMaterial: $directMaterial,
            directLabor: 0,
            manufacturingOverhead: $overhead['total'],
            totalCogs: $totalCogs,
            unitCogs: $totalCogs / $quantity,
            calculationMethod: $product->costing_method->value,
            breakdown: [
                'bom_roll_up' => $bomRollUp,
                'overhead' => $overhead,
                'inventory_consumed' => $consumeInventory,
            ],
        );
    }

    public function recordSaleCogs(SalesTransaction $sale): CogsCalculation
    {
        return DB::transaction(function () use ($sale) {
            $result = $this->calculateSaleCogs($sale->product, (float) $sale->quantity);

            return CogsCalculation::create([
                'reference_type' => SalesTransaction::class,
                'reference_id' => $sale->id,
                'product_id' => $sale->product_id,
                'quantity' => $sale->quantity,
                'direct_material' => $result->directMaterial,
                'direct_labor' => $result->directLabor,
                'manufacturing_overhead' => $result->manufacturingOverhead,
                'total_cogs' => $result->totalCogs,
                'unit_cogs' => $result->unitCogs,
                'calculation_method' => $result->calculationMethod,
                'breakdown' => $result->breakdown,
                'calculated_at' => now(),
            ]);
        });
    }

    public function recordProductionCogs(ProductionOrder $order): CogsCalculation
    {
        $result = $this->calculateProductionCogs($order);

        return CogsCalculation::create([
            'reference_type' => ProductionOrder::class,
            'reference_id' => $order->id,
            'product_id' => $order->product_id,
            'quantity' => $order->quantity_completed,
            'direct_material' => $result->directMaterial,
            'direct_labor' => $result->directLabor,
            'manufacturing_overhead' => $result->manufacturingOverhead,
            'total_cogs' => $result->totalCogs,
            'unit_cogs' => $result->unitCogs,
            'calculation_method' => $result->calculationMethod,
            'breakdown' => $result->breakdown,
            'calculated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummaryReport(): array
    {
        $calculations = CogsCalculation::with('product')->latest('calculated_at')->get();

        return [
            'total_records' => $calculations->count(),
            'total_cogs' => round($calculations->sum('total_cogs'), 4),
            'total_direct_material' => round($calculations->sum('direct_material'), 4),
            'total_direct_labor' => round($calculations->sum('direct_labor'), 4),
            'total_overhead' => round($calculations->sum('manufacturing_overhead'), 4),
            'by_product' => $calculations->groupBy('product_id')->map(function ($group) {
                $product = $group->first()->product;

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'total_quantity' => round($group->sum('quantity'), 6),
                    'total_cogs' => round($group->sum('total_cogs'), 4),
                    'average_unit_cogs' => $group->sum('quantity') > 0
                        ? round($group->sum('total_cogs') / $group->sum('quantity'), 4)
                        : 0,
                ];
            })->values()->all(),
        ];
    }
}
