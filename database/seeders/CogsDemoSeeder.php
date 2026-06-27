<?php

namespace Database\Seeders;

use App\Enums\CostingMethod;
use App\Enums\OverheadAllocationBase;
use App\Enums\ProductType;
use App\Models\BillOfMaterial;
use App\Models\OverheadRate;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Services\ProductionOrderService;
use App\Models\ProductionOrder;
use Illuminate\Database\Seeder;

class CogsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $overheadRates = [
            [
                'name' => 'Overhead Pabrik - Bahan Langsung',
                'allocation_base' => OverheadAllocationBase::DirectMaterial,
                'rate' => 0.15,
                'description' => '15% dari biaya bahan langsung',
            ],
            [
                'name' => 'Overhead Tenaga Kerja',
                'allocation_base' => OverheadAllocationBase::LaborHours,
                'rate' => 25000,
                'description' => 'Rp 25.000 per jam kerja',
            ],
            [
                'name' => 'Overhead Mesin',
                'allocation_base' => OverheadAllocationBase::MachineHours,
                'rate' => 50000,
                'description' => 'Rp 50.000 per jam mesin',
            ],
        ];

        foreach ($overheadRates as $rate) {
            OverheadRate::create($rate);
        }

        $flour = Product::create([
            'sku' => 'RM-FLOUR-001',
            'name' => 'Tepung Terigu',
            'type' => ProductType::RawMaterial,
            'unit' => 'kg',
            'standard_cost' => 12000,
            'costing_method' => CostingMethod::Fifo,
        ]);

        $sugar = Product::create([
            'sku' => 'RM-SUGAR-001',
            'name' => 'Gula Pasir',
            'type' => ProductType::RawMaterial,
            'unit' => 'kg',
            'standard_cost' => 15000,
            'costing_method' => CostingMethod::Fifo,
        ]);

        $butter = Product::create([
            'sku' => 'RM-BUTTER-001',
            'name' => 'Mentega',
            'type' => ProductType::RawMaterial,
            'unit' => 'kg',
            'standard_cost' => 85000,
            'costing_method' => CostingMethod::WeightedAverage,
        ]);

        $dough = Product::create([
            'sku' => 'SF-DOUGH-001',
            'name' => 'Adonan Roti',
            'type' => ProductType::SemiFinished,
            'unit' => 'kg',
            'standard_cost' => 0,
            'costing_method' => CostingMethod::WeightedAverage,
        ]);

        $bread = Product::create([
            'sku' => 'FG-BREAD-001',
            'name' => 'Roti Tawar Premium',
            'type' => ProductType::FinishedGood,
            'unit' => 'loaf',
            'standard_cost' => 0,
            'costing_method' => CostingMethod::WeightedAverage,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $dough->id,
            'child_product_id' => $flour->id,
            'quantity' => 0.6,
            'scrap_percentage' => 2,
            'sequence' => 1,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $dough->id,
            'child_product_id' => $sugar->id,
            'quantity' => 0.1,
            'scrap_percentage' => 1,
            'sequence' => 2,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $dough->id,
            'child_product_id' => $butter->id,
            'quantity' => 0.05,
            'scrap_percentage' => 0,
            'sequence' => 3,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $bread->id,
            'child_product_id' => $dough->id,
            'quantity' => 0.5,
            'scrap_percentage' => 3,
            'sequence' => 1,
        ]);

        $inventoryService = app(InventoryCostService::class);

        $inventoryService->receiveStock($flour, 500, 11500, 'LOT-FLOUR-001');
        $inventoryService->receiveStock($flour, 300, 12500, 'LOT-FLOUR-002');
        $inventoryService->receiveStock($sugar, 200, 14800, 'LOT-SUGAR-001');
        $inventoryService->receiveStock($butter, 50, 84000, 'LOT-BUTTER-001');
        $inventoryService->receiveStock($butter, 30, 86000, 'LOT-BUTTER-002');

        $productionOrderService = app(ProductionOrderService::class);

        $order = ProductionOrder::create([
            'order_number' => 'PO-DEMO-001',
            'product_id' => $bread->id,
            'quantity_planned' => 100,
            'notes' => 'Produksi demo roti tawar',
        ]);

        $productionOrderService->createFromBom(
            order: $order,
            labors: [
                [
                    'description' => 'Operator Produksi',
                    'labor_hours' => 8,
                    'hourly_rate' => 20000,
                ],
                [
                    'description' => 'Quality Control',
                    'labor_hours' => 2,
                    'hourly_rate' => 25000,
                ],
            ],
            machineHours: 6,
        );

        $productionOrderService->start($order->fresh());
        $productionOrderService->complete($order->fresh(['materials.product', 'labors', 'product']));
    }
}
