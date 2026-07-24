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

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'cashier_employee_id')) {
            Schema::table('pos_orders', function ($table) {
                $table->unsignedBigInteger('cashier_employee_id')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'cashier_name')) {
            Schema::table('pos_orders', function ($table) {
                $table->string('cashier_name')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'amount_received')) {
            Schema::table('pos_orders', function ($table) {
                $table->decimal('amount_received', 15, 4)->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'change_amount')) {
            Schema::table('pos_orders', function ($table) {
                $table->decimal('change_amount', 15, 4)->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'served_at')) {
            Schema::table('pos_orders', function ($table) {
                $table->timestamp('served_at')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'payment_method')) {
            Schema::table('pos_orders', function ($table) {
                $table->string('payment_method')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'paid_at')) {
            Schema::table('pos_orders', function ($table) {
                $table->timestamp('paid_at')->nullable();
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

    public function test_pay_order_notifies_when_finished_goods_and_stock_run_out(): void
    {
        $flour = Product::create([
            'sku' => 'RM-FLOUR-OUT',
            'name' => 'Tepung Habis',
            'type' => ProductType::RawMaterial,
            'unit' => 'g',
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 10,
            'is_active' => true,
        ]);

        $menuFg = Product::create([
            'sku' => 'FG-OUT',
            'name' => 'Roti Habis',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 15000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        $menuBom = Product::create([
            'sku' => 'FG-BOM-OUT',
            'name' => 'Menu Resep',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 12000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        app(InventoryCostService::class)->receiveStock($menuFg, 1, 8000);
        app(InventoryCostService::class)->receiveStock($flour, 100, 10);

        BillOfMaterial::create([
            'parent_product_id' => $menuBom->id,
            'child_product_id' => $flour->id,
            'quantity' => 100,
            'scrap_percentage' => 0,
            'sequence' => 1,
        ]);

        $kasir = User::factory()->kasir()->create();

        $order = PosOrder::create([
            'order_number' => 'TRX-TEST-STOCK-OUT-001',
            'order_day' => now()->toDateString(),
            'source' => PosOrderSource::Kasir,
            'order_type' => PosOrderType::Takeaway,
            'status' => PosOrderStatus::Open,
            'user_id' => $kasir->id,
            'subtotal' => 27000,
            'total' => 27000,
        ]);

        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $menuFg->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);

        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $menuBom->id,
            'quantity' => 1,
            'unit_price' => 12000,
            'line_total' => 12000,
        ]);

        $notifier = \Mockery::mock(\App\Services\KasirPushNotifier::class);
        $notifier->shouldReceive('notifyStockOut')
            ->once()
            ->withArgs(function (array $items, $paidOrder) use ($menuFg, $flour, $order) {
                $ids = collect($items)->pluck('id')->sort()->values()->all();
                $expected = collect([$menuFg->id, $flour->id])->sort()->values()->all();

                return $ids === $expected
                    && $paidOrder->id === $order->id
                    && collect($items)->contains(fn ($item) => $item['type_label'] === 'Barang Jadi')
                    && collect($items)->contains(fn ($item) => $item['type_label'] === 'Barang Stok');
            });

        $this->app->instance(\App\Services\KasirPushNotifier::class, $notifier);

        $result = app(PosOrderService::class)->payOrder(
            $order->fresh('items.product'),
            PaymentMethod::Qris,
            $kasir,
            null,
            UploadedFile::fake()->image('bukti.jpg'),
        );

        $this->assertEquals(0, $menuFg->fresh()->availableQuantity());
        $this->assertEquals(0, $flour->fresh()->availableQuantity());
        $this->assertNotNull($result['stock_out_message']);
        $this->assertStringContainsString('Roti Habis', $result['stock_out_message']);
        $this->assertStringContainsString('Tepung Habis', $result['stock_out_message']);
        $this->assertCount(2, $result['stock_out']);
    }

    public function test_reopen_for_edit_restores_stock_and_reopens_order(): void
    {
        $flour = Product::create([
            'sku' => 'RM-FLOUR-EDIT',
            'name' => 'Tepung Edit',
            'type' => ProductType::RawMaterial,
            'unit' => 'g',
            'costing_method' => CostingMethod::Fifo,
            'standard_cost' => 10,
            'is_active' => true,
        ]);

        $menu = Product::create([
            'sku' => 'FG-EDIT',
            'name' => 'Roti Edit',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 15000,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        app(InventoryCostService::class)->receiveStock($flour, 200, 10);

        BillOfMaterial::create([
            'parent_product_id' => $menu->id,
            'child_product_id' => $flour->id,
            'quantity' => 100,
            'scrap_percentage' => 0,
            'sequence' => 1,
        ]);

        $kasir = User::factory()->kasir()->create();

        $order = PosOrder::create([
            'order_number' => 'TRX-TEST-EDIT-001',
            'order_day' => now()->toDateString(),
            'source' => PosOrderSource::Kasir,
            'order_type' => PosOrderType::Takeaway,
            'status' => PosOrderStatus::Open,
            'user_id' => $kasir->id,
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $menu->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);

        $service = app(PosOrderService::class);

        $service->payOrder(
            $order->fresh('items.product'),
            PaymentMethod::Qris,
            $kasir,
            null,
            UploadedFile::fake()->image('bukti-edit.jpg'),
        );

        $this->assertEquals(100, $flour->fresh()->availableQuantity());
        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('sales_transactions', [
            'pos_order_id' => $order->id,
        ]);

        $reopened = $service->reopenForEdit($order->fresh());

        $this->assertEquals(PosOrderStatus::Unpaid, $reopened->status);
        $this->assertNull($reopened->payment_method);
        $this->assertNull($reopened->paid_at);
        $this->assertEquals(200, $flour->fresh()->availableQuantity());
        $this->assertDatabaseMissing('sales_transactions', [
            'pos_order_id' => $order->id,
        ]);
        $this->assertTrue($reopened->isKasirEditable());
    }
}
