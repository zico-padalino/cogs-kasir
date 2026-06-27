<?php

namespace Tests\Feature;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Models\Product;
use App\Services\CogsCalculationService;
use App\Services\InventoryCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CogsCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifo_inventory_consumption(): void
    {
        $product = Product::create([
            'sku' => 'TEST-FIFO',
            'name' => 'Test FIFO',
            'type' => ProductType::RawMaterial,
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 100,
        ]);

        $service = app(InventoryCostService::class);
        $service->receiveStock($product, 10, 100, 'LOT-1');
        $service->receiveStock($product, 10, 200, 'LOT-2');

        $result = $service->consumeStock($product, 15);

        $this->assertEquals(2000, $result->totalCost);
        $this->assertEquals(2, count($result->lotConsumptions));
    }

    public function test_weighted_average_cost(): void
    {
        $product = Product::create([
            'sku' => 'TEST-WAVG',
            'name' => 'Test Weighted Average',
            'type' => ProductType::RawMaterial,
            'costing_method' => CostingMethod::WeightedAverage,
            'standard_cost' => 100,
        ]);

        $service = app(InventoryCostService::class);
        $service->receiveStock($product, 100, 10000, 'LOT-1');
        $service->receiveStock($product, 100, 12000, 'LOT-2');

        $avgCost = $service->getWeightedAverageCost($product);

        $this->assertEquals(11000, $avgCost);
    }

    public function test_sale_cogs_calculation_endpoint(): void
    {
        $product = Product::create([
            'sku' => 'TEST-SALE',
            'name' => 'Test Product',
            'type' => ProductType::FinishedGood,
            'costing_method' => CostingMethod::WeightedAverage,
            'standard_cost' => 50000,
        ]);

        app(InventoryCostService::class)->receiveStock($product, 100, 50000);

        $response = $this->postJson('/api/v1/cogs/calculate', [
            'product_id' => $product->id,
            'quantity' => 10,
            'consume_inventory' => false,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'direct_material',
                    'direct_labor',
                    'manufacturing_overhead',
                    'total_cogs',
                    'unit_cogs',
                    'calculation_method',
                    'breakdown',
                ],
            ]);
    }

    public function test_cogs_summary_report(): void
    {
        $response = $this->getJson('/api/v1/cogs/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_records',
                    'total_cogs',
                    'total_direct_material',
                    'total_direct_labor',
                    'total_overhead',
                    'by_product',
                ],
            ]);
    }
}
