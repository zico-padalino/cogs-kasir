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

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $this->get('/')->assertRedirect(route('login'));
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

    public function test_inventory_page_is_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('inventory.index'))->assertOk();
    }

    public function test_production_order_pages_are_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('production-orders.index'))->assertOk();
        $this->get(route('production-orders.create'))->assertOk();
    }

    public function test_cogs_pages_are_accessible(): void
    {
        $this->actingAsCogsUser();

        $this->get(route('cogs.calculate'))->assertOk();
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
            ->assertRedirect(route('products.index'));

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
