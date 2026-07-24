<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCogsUser(): User
    {
        $user = User::factory()->cogs()->create();

        $this->actingAs($user);

        return $user;
    }

    public function test_guest_sees_login_at_root(): void
    {
        $this->get('/')->assertOk()->assertSee('Selamat datang');
    }

    public function test_dashboard_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_product_pages_are_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('products.index'))->assertOk();
        $this->get(route('products.create'))->assertOk();
    }

    public function test_materials_page_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('materials.index'))->assertOk();
    }

    public function test_production_order_pages_are_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('production-orders.index'))->assertOk();
        $this->get(route('production-orders.create'))->assertRedirect(route('production-orders.index'));
    }

    public function test_menu_pricing_page_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('menu-pricing.index'))->assertOk();
    }

    public function test_cogs_pages_are_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('cogs.calculate'))->assertRedirect(route('menu-pricing.index'));
        $this->get(route('cogs.history'))->assertOk();
    }

    public function test_overhead_page_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('overhead-rates.index'))->assertOk();
    }

    public function test_reset_data_page_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('reset-data.show'))->assertOk();
    }

    public function test_cogs_home_opens_beranda(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('materials.index'))->assertOk();
        $this->get(route('home'))->assertRedirect(route('dashboard'));

        $this->get(route('menu-pricing.index'))->assertOk();
        $this->get(route('home'))->assertRedirect(route('dashboard'));
    }

    public function test_can_add_recipe_ingredient_with_gram_unit(): void
    {
        $this->actingAsCogsUser();

        $menu = \App\Models\Product::create([
            'sku' => 'MENU-BOM',
            'name' => 'Nasi Goreng',
            'type' => 'finished_good',
            'unit' => 'porsi',
            'costing_method' => 'weighted_average',
            'is_active' => true,
            'is_menu_item' => true,
        ]);

        $flour = \App\Models\Product::create([
            'sku' => 'BAHAN-BOM',
            'name' => 'Tepung',
            'type' => 'raw_material',
            'unit' => 'kg',
            'costing_method' => 'fifo',
            'is_active' => true,
        ]);

        $this->post(route('products.bom.store', $menu), [
            'child_product_id' => $flour->id,
            'quantity' => 100,
            'unit' => 'gr',
        ])->assertRedirect(route('products.show', $menu));

        $this->assertDatabaseHas('bill_of_materials', [
            'parent_product_id' => $menu->id,
            'child_product_id' => $flour->id,
            'quantity' => 0.1,
        ]);
    }

    public function test_recipe_rejects_mismatched_unit(): void
    {
        $this->actingAsCogsUser();

        $menu = \App\Models\Product::create([
            'sku' => 'MENU-BOM-2',
            'name' => 'Es Teh',
            'type' => 'finished_good',
            'unit' => 'gelas',
            'costing_method' => 'weighted_average',
            'is_active' => true,
        ]);

        $tea = \App\Models\Product::create([
            'sku' => 'BAHAN-BOM-2',
            'name' => 'Teh Celup',
            'type' => 'raw_material',
            'unit' => 'pcs',
            'costing_method' => 'fifo',
            'is_active' => true,
        ]);

        $this->from(route('products.show', $menu))
            ->post(route('products.bom.store', $menu), [
                'child_product_id' => $tea->id,
                'quantity' => 1,
                'unit' => 'gr',
            ])
            ->assertRedirect(route('products.show', $menu))
            ->assertSessionHasErrors('unit');
    }

    public function test_delete_product_without_dependencies(): void
    {
        $this->actingAsCogsUser();

        $product = \App\Models\Product::create([
            'sku' => 'DEL-001',
            'name' => 'Hapus Test',
            'type' => 'raw_material',
            'costing_method' => 'fifo',
        ]);

        $this->delete(route('products.destroy', $product))
            ->assertRedirect(route('materials.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_product_with_completed_production_shows_error(): void
    {
        $this->actingAsCogsUser();

        $product = \App\Models\Product::create([
            'sku' => 'DEL-002',
            'name' => 'Produk Selesai',
            'type' => 'finished_good',
            'costing_method' => 'weighted_average',
        ]);

        $order = \App\Models\ProductionOrder::create([
            'order_number' => 'PO-TEST',
            'product_id' => $product->id,
            'quantity_planned' => 10,
            'quantity_completed' => 10,
            'status' => 'completed',
        ]);

        \App\Models\CogsCalculation::create([
            'reference_type' => \App\Models\ProductionOrder::class,
            'reference_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'total_cogs' => 100000,
            'unit_cogs' => 10000,
            'calculation_method' => 'test',
            'calculated_at' => now(),
        ]);

        $this->from(route('products.index'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_reset_data_clears_database(): void
    {
        $this->actingAsCogsUser();

        \App\Models\Product::create([
            'sku' => 'TEST-RESET',
            'name' => 'Test Reset',
            'type' => 'raw_material',
            'costing_method' => 'fifo',
        ]);

        $this->assertEquals(1, \App\Models\Product::count());

        $this->post(route('reset-data.store'), [
            'confirmation' => 'RESET',
        ])->assertRedirect(route('dashboard'));

        $this->assertEquals(0, \App\Models\Product::count());
    }
}
