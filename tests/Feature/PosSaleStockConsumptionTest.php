<?php

namespace Tests\Feature;

use App\Enums\CostingMethod;
use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Enums\ProductType;
use App\Models\BillOfMaterial;
use App\Models\InventoryLot;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\User;
use App\Services\CogsCalculationService;
use App\Services\InventoryCostService;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosSaleStockConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'order_day')) {
            Schema::table('pos_orders', function ($table) {
                $table->date('order_day')->nullable()->after('order_number');
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'payment_proof_path')) {
            Schema::table('pos_orders', function ($table) {
                $table->string('payment_proof_path')->nullable();
            });
        }

        if (Schema::hasTable('pos_order_items') && ! Schema::hasColumn('pos_order_items', 'addon_ids')) {
            Schema::table('pos_order_items', function ($table) {
                $table->json('addon_ids')->nullable();
            });
        }
    }

    public function test_record_sale_cogs_consumes_finished_goods_stock(): void
    {
        $product = Product::create([
            'sku' => 'FG-STOCK',
            'name' => 'Menu Stok',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 20000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        app(InventoryCostService::class)->receiveStock($product, 10, 12000);

        $sale = \App\Models\SalesTransaction::create([
            'invoice_number' => 'INV-TEST-1',
            'product_id' => $product->id,
            'quantity' => 3,
            'selling_price' => 20000,
            'total_revenue' => 60000,
            'sold_at' => now(),
        ]);

        app(CogsCalculationService::class)->recordSaleCogs($sale);

        $this->assertEquals(7, $product->fresh()->availableQuantity());
    }

    public function test_record_sale_cogs_consumes_bom_materials_when_no_finished_stock(): void
    {
        $flour = Product::create([
            'sku' => 'RM-FLOUR',
            'name' => 'Tepung',
            'type' => ProductType::RawMaterial,
            'unit' => 'g',
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 10,
            'is_active' => true,
        ]);

        app(InventoryCostService::class)->receiveStock($flour, 1000, 10);

        $menu = Product::create([
            'sku' => 'FG-ROTI',
            'name' => 'Roti',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 15000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $menu->id,
            'child_product_id' => $flour->id,
            'quantity' => 100,
            'scrap_percentage' => 0,
            'sequence' => 1,
        ]);

        $sale = \App\Models\SalesTransaction::create([
            'invoice_number' => 'INV-TEST-2',
            'product_id' => $menu->id,
            'quantity' => 2,
            'selling_price' => 15000,
            'total_revenue' => 30000,
            'sold_at' => now(),
        ]);

        app(CogsCalculationService::class)->recordSaleCogs($sale);

        $this->assertEquals(800, $flour->fresh()->availableQuantity());
    }

    public function test_pay_order_consumes_addon_material_stock(): void
    {
        $egg = Product::create([
            'sku' => 'RM-EGG',
            'name' => 'Telur',
            'type' => ProductType::RawMaterial,
            'unit' => 'pcs',
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 2000,
            'is_active' => true,
        ]);

        $rice = Product::create([
            'sku' => 'RM-RICE',
            'name' => 'Beras',
            'type' => ProductType::RawMaterial,
            'unit' => 'g',
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 10,
            'is_active' => true,
        ]);

        app(InventoryCostService::class)->receiveStock($egg, 20, 2000);
        app(InventoryCostService::class)->receiveStock($rice, 1000, 10);

        $menu = Product::create([
            'sku' => 'FG-NASI',
            'name' => 'Nasi Goreng',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 20000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        BillOfMaterial::create([
            'parent_product_id' => $menu->id,
            'child_product_id' => $rice->id,
            'quantity' => 150,
            'scrap_percentage' => 0,
            'sequence' => 1,
        ]);

        $addon = ProductAddon::create([
            'product_id' => $menu->id,
            'name' => 'Telur',
            'selling_price' => 5000,
            'material_product_id' => $egg->id,
            'material_quantity' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $kasir = User::factory()->kasir()->create();

        $order = PosOrder::create([
            'order_number' => 'TRX-TEST-STOCK-001',
            'order_day' => now()->toDateString(),
            'source' => PosOrderSource::Kasir,
            'order_type' => PosOrderType::Takeaway,
            'status' => PosOrderStatus::Open,
            'user_id' => $kasir->id,
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $menu->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'line_total' => 25000,
            'addon_ids' => [$addon->id],
            'notes' => '+Telur',
        ]);

        app(PosOrderService::class)->payOrder(
            $order->fresh('items.product'),
            PaymentMethod::Qris,
            $kasir,
            null,
            UploadedFile::fake()->image('bukti.jpg'),
        );

        $this->assertEquals(850, $rice->fresh()->availableQuantity());
        $this->assertEquals(19, $egg->fresh()->availableQuantity());
        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }
}
