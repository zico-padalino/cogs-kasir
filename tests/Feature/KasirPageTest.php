<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\InventoryLot;
use App\Models\PosTable;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirPageTest extends TestCase
{
    use RefreshDatabase;

    private function kasirUser(): User
    {
        return User::factory()->kasir()->create();
    }

    private function sellableProduct(): Product
    {
        $product = Product::create([
            'sku' => 'FG-TEST-001',
            'name' => 'Roti Test',
            'type' => ProductType::FinishedGood,
            'selling_price' => 20000,
            'costing_method' => 'weighted_average',
            'is_active' => true,
        ]);

        InventoryLot::create([
            'product_id' => $product->id,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 12000,
            'received_at' => now(),
        ]);

        return $product;
    }

    public function test_kasir_pos_page_requires_kasir_role(): void
    {
        $cogsUser = User::factory()->cogs()->create();

        $this->actingAs($cogsUser)
            ->get(route('kasir.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_kasir_can_access_pos(): void
    {
        $this->actingAs($this->kasirUser())
            ->get(route('kasir.index'))
            ->assertOk()
            ->assertSee('Point of Sale');
    }

    public function test_online_table_order_page_is_public(): void
    {
        $table = PosTable::create([
            'table_number' => '99',
            'label' => 'Meja Test',
            'barcode_token' => 'test-token-public',
        ]);

        $this->get(route('order.table', $table->barcode_token))
            ->assertOk()
            ->assertSee('Meja Test')
            ->assertSee('Menu Meja')
            ->assertDontSee('Silakan Bayar di Kasir');
    }

    public function test_submitted_table_order_shows_pay_at_cashier_message(): void
    {
        $product = $this->sellableProduct();
        $table = PosTable::create([
            'table_number' => '88',
            'label' => 'Meja Bayar',
            'barcode_token' => 'test-token-pay',
        ]);

        $this->post(route('order.table.items', $table->barcode_token), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('order.table.submit', $table->barcode_token))
            ->assertRedirect();

        $this->get(route('order.table', $table->barcode_token))
            ->assertOk()
            ->assertSee('Silakan Bayar di Kasir')
            ->assertDontSee('Kirim Pesanan');
    }

    public function test_kasir_can_open_table_barcode_page(): void
    {
        $table = PosTable::create([
            'table_number' => '77',
            'label' => 'Meja QR',
            'barcode_token' => 'test-token-qr',
        ]);

        $this->actingAs($this->kasirUser())
            ->get(route('kasir.tables.barcode', $table))
            ->assertOk()
            ->assertSee('Scan untuk Pesan')
            ->assertSee('Meja QR');
    }

    public function test_kasir_checkout_reduces_inventory(): void
    {
        $product = $this->sellableProduct();
        $kasir = $this->kasirUser();

        $this->actingAs($kasir)->get(route('kasir.index'));

        $this->actingAs($kasir)
            ->post(route('kasir.items.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($kasir)
            ->post(route('kasir.pay'), [
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertEquals(48, $product->fresh()->availableQuantity());
        $this->assertDatabaseHas('pos_orders', ['status' => 'paid']);
        $this->assertDatabaseHas('sales_transactions', ['product_id' => $product->id]);
        $this->assertDatabaseHas('cogs_calculations', ['product_id' => $product->id]);
    }
}
