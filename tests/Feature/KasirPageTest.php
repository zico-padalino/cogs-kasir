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

    private function activeTable(): PosTable
    {
        return PosTable::create([
            'table_number' => '01',
            'label' => 'Meja 1',
            'barcode_token' => 'legacy-token-01',
        ]);
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
            ->assertSee('Menu')
            ->assertSee('Pesanan');
    }

    public function test_online_menu_page_is_public(): void
    {
        $this->get(route('order.menu'))
            ->assertOk()
            ->assertSee('Pesan Online')
            ->assertDontSee('Silakan Bayar di Kasir');
    }

    public function test_legacy_table_url_redirects_to_single_menu(): void
    {
        $this->get('/meja/legacy-old-token')
            ->assertRedirect('/pesan');
    }

    public function test_submitted_online_order_shows_pay_at_cashier_message(): void
    {
        $product = $this->sellableProduct();
        $table = $this->activeTable();

        $this->patch(route('order.menu.customer'), [
            'customer_note' => 'Budi',
        ])->assertRedirect();

        $this->patch(route('order.menu.table'), [
            'pos_table_id' => $table->id,
        ])->assertRedirect();

        $this->post(route('order.menu.items'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('order.menu.submit'))
            ->assertRedirect();

        $this->get(route('order.menu'))
            ->assertOk()
            ->assertSee('Silakan Bayar di Kasir')
            ->assertSee('Budi')
            ->assertDontSee('Kirim Pesanan');
    }

    public function test_each_device_gets_separate_order(): void
    {
        $this->get(route('order.menu'))->assertOk();
        $firstOrderId = session('online_order_id');

        $this->flushSession();

        $this->get(route('order.menu'))->assertOk();
        $secondOrderId = session('online_order_id');

        $this->assertNotNull($firstOrderId);
        $this->assertNotNull($secondOrderId);
        $this->assertNotSame($firstOrderId, $secondOrderId);
        $this->assertEquals(2, \App\Models\PosOrder::count());
    }

    public function test_submit_online_order_requires_customer_name_and_table(): void
    {
        $product = $this->sellableProduct();
        $table = $this->activeTable();

        $this->post(route('order.menu.items'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('order.menu.submit'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->patch(route('order.menu.customer'), [
            'customer_note' => 'Ani',
        ])->assertRedirect();

        $this->post(route('order.menu.submit'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->patch(route('order.menu.table'), [
            'pos_table_id' => $table->id,
        ])->assertRedirect();

        $this->post(route('order.menu.submit'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_kasir_can_open_barcode_page(): void
    {
        $this->actingAs($this->kasirUser())
            ->get(route('kasir.barcode'))
            ->assertOk()
            ->assertSee('Scan untuk Pesan');
    }

    public function test_kasir_tables_page_shows_single_barcode(): void
    {
        PosTable::create([
            'table_number' => '77',
            'label' => 'Meja 77',
            'barcode_token' => 'legacy-token-77',
        ]);

        $this->actingAs($this->kasirUser())
            ->get(route('kasir.tables'))
            ->assertOk()
            ->assertSee('Barcode Pesanan')
            ->assertSee('/pesan')
            ->assertSee('satu QR untuk semua meja')
            ->assertSee('Meja 77');
    }

    public function test_kasir_can_manage_menu_items(): void
    {
        $product = $this->sellableProduct();

        $this->actingAs($this->kasirUser())
            ->get(route('kasir.products.index'))
            ->assertOk()
            ->assertSee('Kelola Menu')
            ->assertSee('Roti Test');

        $this->actingAs($this->kasirUser())
            ->get(route('kasir.products.edit', $product))
            ->assertOk()
            ->assertSee('Atur Menu')
            ->assertSee($product->sku);
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
                'amount_received' => 50000,
            ])
            ->assertRedirect();

        $this->assertEquals(48, $product->fresh()->availableQuantity());
        $this->assertDatabaseHas('pos_orders', ['status' => 'paid']);
        $this->assertDatabaseHas('sales_transactions', ['product_id' => $product->id]);
        $this->assertDatabaseHas('cogs_calculations', ['product_id' => $product->id]);
    }
}
