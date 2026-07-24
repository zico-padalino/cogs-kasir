<?php

namespace App\Services;

use App\DTOs\CogsResult;
use App\Enums\ProductType;
use App\Enums\ProductionOrderStatus;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\SalesTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu formula biaya: HPP (Harga Pokok Penjualan).
 * Kolom COGS di database = link ke nilai HPP yang sama (bukan perhitungan terpisah).
 */
class CogsCalculationService
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
        private readonly BomCostService $bomCostService,
        private readonly OverheadAllocationService $overheadAllocationService,
        private readonly ProductHppService $productHppService,
    ) {}

    public function calculateProductionHpp(ProductionOrder $order): CogsResult
    {
        return $this->calculateProductionCogs($order);
    }

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
        $totalHpp = $directMaterial + $directLabor + $overhead['total'];

        return new CogsResult(
            directMaterial: $directMaterial,
            directLabor: $directLabor,
            manufacturingOverhead: $overhead['total'],
            totalHpp: $totalHpp,
            unitHpp: $totalHpp / $quantity,
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

    public function calculateSaleHpp(Product $product, float $quantity, bool $consumeInventory = true): CogsResult
    {
        return $this->calculateSaleCogs($product, $quantity, $consumeInventory);
    }

    public function calculateSaleCogs(
        Product $product,
        float $quantity,
        bool $consumeInventory = true,
        array $extraRequirements = [],
        ?string $consumeNote = null,
        ?array $overheadRateIds = null,
    ): CogsResult
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity harus lebih dari 0.');
        }

        $consumptionDetails = [];
        $directMaterial = 0.0;
        $logAction = $consumeInventory ? 'sale' : null;

        if ($consumeInventory && $this->shouldConsumeFromInventory($product, $quantity)) {
            $consumption = $this->inventoryCostService->consumeStock(
                product: $product,
                quantity: $quantity,
                logAction: $logAction,
                note: $consumeNote,
            );
            $directMaterial = $consumption->totalCost;
            $consumptionDetails[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'cost' => round($consumption->totalCost, 4),
                'lots' => $consumption->lotConsumptions,
                'mode' => 'finished_goods_inventory',
            ];
        } else {
            $bomRollUp = $this->bomCostService->rollUpCost($product, $quantity);
            $directMaterial = $bomRollUp['total_cost'];

            if ($consumeInventory) {
                $requirements = $this->bomCostService->explodeBom($product, $quantity);

                foreach ($requirements as $req) {
                    $consumption = $this->inventoryCostService->consumeStock(
                        product: $req['product'],
                        quantity: $req['quantity'],
                        logAction: $logAction,
                        note: $consumeNote,
                    );
                    $consumptionDetails[] = [
                        'product_id' => $req['product']->id,
                        'sku' => $req['product']->sku,
                        'quantity' => $req['quantity'],
                        'cost' => round($consumption->totalCost, 4),
                        'lots' => $consumption->lotConsumptions,
                        'mode' => 'bom_explosion',
                    ];
                }

                $directMaterial = array_sum(array_column($consumptionDetails, 'cost'));
            }
        }

        foreach ($extraRequirements as $req) {
            /** @var Product $material */
            $material = $req['product'];
            $qty = (float) $req['quantity'];

            if ($qty <= 0) {
                continue;
            }

            if ($consumeInventory) {
                $consumption = $this->inventoryCostService->consumeStock(
                    product: $material,
                    quantity: $qty,
                    logAction: $logAction,
                    note: $req['note'] ?? $consumeNote,
                );
                $cost = $consumption->totalCost;
                $lots = $consumption->lotConsumptions;
            } else {
                $unitCost = $this->inventoryCostService->getWeightedAverageCost($material);
                $cost = $unitCost * $qty;
                $lots = [];
            }

            $consumptionDetails[] = [
                'product_id' => $material->id,
                'sku' => $material->sku,
                'quantity' => $qty,
                'cost' => round($cost, 4),
                'lots' => $lots,
                'mode' => 'addon_material',
            ];
            $directMaterial += $cost;
        }

        $overhead = $this->overheadAllocationService->allocateForSale(
            directMaterial: $directMaterial,
            units: $quantity,
            overheadRateIds: $overheadRateIds,
        );

        $totalHpp = $directMaterial + $overhead['total'];

        return new CogsResult(
            directMaterial: $directMaterial,
            directLabor: 0,
            manufacturingOverhead: $overhead['total'],
            totalHpp: $totalHpp,
            unitHpp: $totalHpp / $quantity,
            calculationMethod: $product->costing_method->value,
            breakdown: [
                'consumption_mode' => $consumptionDetails[0]['mode'] ?? 'bom_only',
                'inventory_consumed' => $consumeInventory,
                'consumption_details' => $consumptionDetails,
                'overhead' => $overhead,
                'overhead_rate_ids' => $overheadRateIds,
            ],
        );
    }

    private function shouldConsumeFromInventory(Product $product, float $quantity): bool
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            return false;
        }

        return $product->availableQuantity() >= $quantity;
    }

    public function recordSaleCogs(SalesTransaction $sale, array $extraRequirements = []): CogsCalculation
    {
        return DB::transaction(function () use ($sale, $extraRequirements) {
            $note = 'Penjualan '.$sale->invoice_number;
            $result = $this->calculateSaleCogs(
                product: $sale->product,
                quantity: (float) $sale->quantity,
                consumeInventory: true,
                extraRequirements: $extraRequirements,
                consumeNote: $note,
            );

            return $this->persistCalculation(
                result: $result,
                productId: $sale->product_id,
                quantity: (float) $sale->quantity,
                referenceType: SalesTransaction::class,
                referenceId: $sale->id,
            );
        });
    }

    public function reverseSaleCogs(SalesTransaction $sale): void
    {
        DB::transaction(function () use ($sale) {
            $calculations = CogsCalculation::query()
                ->where('reference_type', SalesTransaction::class)
                ->where('reference_id', $sale->id)
                ->lockForUpdate()
                ->get();

            $note = 'Batal penjualan '.$sale->invoice_number;

            foreach ($calculations as $calculation) {
                foreach ($calculation->breakdown['consumption_details'] ?? [] as $detail) {
                    $productId = (int) ($detail['product_id'] ?? 0);
                    $qty = (float) ($detail['quantity'] ?? 0);
                    if ($productId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $product = Product::query()->find($productId);
                    if (! $product) {
                        continue;
                    }

                    $cost = (float) ($detail['cost'] ?? 0);
                    $unitCost = $qty > 0 ? ($cost / $qty) : 0.0;

                    $this->inventoryCostService->restoreConsumedStock(
                        product: $product,
                        quantity: $qty,
                        unitCost: $unitCost,
                        lotConsumptions: is_array($detail['lots'] ?? null) ? $detail['lots'] : [],
                        note: $note,
                    );
                }

                $calculation->delete();
            }
        });
    }

    public function recordProductionCogs(ProductionOrder $order): CogsCalculation
    {
        $result = $this->calculateProductionCogs($order);

        $calculation = $this->persistCalculation(
            result: $result,
            productId: $order->product_id,
            quantity: (float) $order->quantity_completed,
            referenceType: ProductionOrder::class,
            referenceId: $order->id,
        );

        $this->productHppService->syncFromResult($order->product, $result);

        return $calculation;
    }

    /**
     * Hitung modal per porsi dari resep (bahan + biaya lain), tanpa mengurangi stok.
     *
     * @param  list<int>|null  $overheadRateIds
     */
    public function recalculateRecipeHpp(Product $product, ?array $overheadRateIds = null): CogsResult
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            throw new RuntimeException('Hanya menu yang bisa dihitung modalnya dari resep.');
        }

        if ($product->billOfMaterials()->doesntExist()) {
            throw new RuntimeException('Resep masih kosong. Tambah bahan dulu.');
        }

        $result = $this->calculateSaleCogs(
            $product,
            1,
            consumeInventory: false,
            overheadRateIds: $overheadRateIds,
        );

        if ($result->directMaterial <= 0) {
            throw new RuntimeException('Modal bahan Rp 0. Pastikan setiap bahan punya harga beli atau stok.');
        }

        if ($result->unitHpp <= 0) {
            throw new RuntimeException('Modal tidak bisa dihitung. Periksa resep dan harga bahan.');
        }

        $this->productHppService->syncFromResult($product, $result);

        $product->update([
            'standard_cost' => $result->unitHpp,
        ]);

        $this->persistCalculation(
            result: $result,
            productId: $product->id,
            quantity: 1,
            referenceType: Product::class,
            referenceId: $product->id,
        );

        return $result;
    }

    private function persistCalculation(
        CogsResult $result,
        int $productId,
        float $quantity,
        string $referenceType,
        int $referenceId,
    ): CogsCalculation {
        return CogsCalculation::create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'direct_material' => $result->directMaterial,
            'direct_labor' => $result->directLabor,
            'manufacturing_overhead' => $result->manufacturingOverhead,
            'total_hpp' => $result->totalHpp,
            'unit_hpp' => $result->unitHpp,
            'total_cogs' => $result->totalHpp,
            'unit_cogs' => $result->unitHpp,
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
            'total_hpp' => round($calculations->sum(fn (CogsCalculation $calc) => $calc->totalHpp()), 4),
            'total_cogs' => round($calculations->sum(fn (CogsCalculation $calc) => $calc->totalHpp()), 4),
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
                    'total_hpp' => round($group->sum(fn (CogsCalculation $calc) => $calc->totalHpp()), 4),
                    'total_cogs' => round($group->sum(fn (CogsCalculation $calc) => $calc->totalHpp()), 4),
                    'average_unit_hpp' => $group->sum('quantity') > 0
                        ? round($group->sum(fn (CogsCalculation $calc) => $calc->totalHpp()) / $group->sum('quantity'), 4)
                        : 0,
                    'average_unit_cogs' => $group->sum('quantity') > 0
                        ? round($group->sum(fn (CogsCalculation $calc) => $calc->totalHpp()) / $group->sum('quantity'), 4)
                        : 0,
                ];
            })->values()->all(),
        ];
    }
}
