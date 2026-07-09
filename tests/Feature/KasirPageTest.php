<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\InventoryLot;
use App\Models\PosOrder;
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
            'is_menu_item' => true,
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

        $this->patch(route('order.menu.customer'), [
            'customer_note' => 'Budi',
        ])->assertRedirect();

        $this->post(route('order.menu.items'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->post(route('order.menu.submit'))
            ->assertRedirect(route('order.menu').'#ke-kasir');

        $this->get(route('order.menu'))
            ->assertOk()
            ->assertSee('Silakan ke Kasir')
            ->assertSee('Konfirmasi pesanan dan pembayaran')
            ->assertSee('Budi')
            ->assertDontSee('Kirim ke Kasir');
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

    public function test_submit_online_order_requires_customer_name(): void
    {
        $product = $this->sellableProduct();

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

    public function test_kasir_pending_poll_requires_kasir_role(): void
    {
        $cogsUser = User::factory()->cogs()->create();

        $this->actingAs($cogsUser)
            ->getJson(route('kasir.pending.poll'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_kasir_pending_poll_returns_submitted_orders(): void
    {
        $product = $this->sellableProduct();
        $kasir = $this->kasirUser();

        $this->patch(route('order.menu.customer'), ['customer_note' => 'Budi']);
        $this->post(route('order.menu.items'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('order.menu.submit'));

        $this->actingAs($kasir)
            ->getJson(route('kasir.pending.poll'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('has_pending', true)
            ->assertJsonStructure(['order_ids', 'html']);
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

    public function test_pos_order_number_uses_unique_daily_format(): void
    {
        $kasir = $this->kasirUser();

        $this->travelTo('2026-07-08 10:00:00');
        $this->actingAs($kasir)->get(route('kasir.index'));

        $this->assertDatabaseHas('pos_orders', ['order_number' => 'TRX-20260708-001']);

        $this->actingAs($kasir)
            ->post(route('kasir.new-order'))
            ->assertRedirect();

        $this->assertDatabaseHas('pos_orders', ['order_number' => 'TRX-20260708-002']);
    }

    public function test_pos_order_number_is_unique_across_days(): void
    {
        $kasir = $this->kasirUser();

        $this->travelTo('2026-06-28 10:00:00');
        $this->actingAs($kasir)->get(route('kasir.index'));
        $this->assertDatabaseHas('pos_orders', [
            'order_number' => 'TRX-20260628-001',
            'order_day' => '2026-06-28',
        ]);

        $this->travelTo('2026-06-29 09:00:00');
        $this->actingAs($kasir)->post(route('kasir.new-order'))->assertRedirect();
        $this->assertDatabaseHas('pos_orders', [
            'order_number' => 'TRX-20260629-001',
            'order_day' => '2026-06-29',
        ]);
        $this->assertEquals(1, PosOrder::where('order_number', 'TRX-20260628-001')->count());
        $this->assertEquals(1, PosOrder::where('order_number', 'TRX-20260629-001')->count());
    }

    public function test_kasir_cannot_pay_online_order_before_confirm(): void
    {
        $product = $this->sellableProduct();
        $kasir = $this->kasirUser();

        $this->patch(route('order.menu.customer'), ['customer_note' => 'Budi']);
        $this->post(route('order.menu.items'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('order.menu.submit'));

        $order = PosOrder::where('status', 'submitted')->first();
        $this->assertNotNull($order);

        $this->actingAs($kasir)
            ->post(route('kasir.load-order', $order))
            ->assertRedirect();

        $this->actingAs($kasir)
            ->post(route('kasir.pay'), [
                'payment_method' => 'cash',
                'amount_received' => 50000,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'submitted',
        ]);
    }

    public function test_kasir_can_confirm_and_pay_online_order(): void
    {
        $product = $this->sellableProduct();
        $kasir = $this->kasirUser();

        $this->patch(route('order.menu.customer'), ['customer_note' => 'Budi']);
        $this->post(route('order.menu.items'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('order.menu.submit'));

        $order = PosOrder::where('status', 'submitted')->first();
        $this->assertNotNull($order);

        $this->actingAs($kasir)
            ->post(route('kasir.orders.confirm', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'confirmed_by' => $kasir->id,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.pay'), [
                'payment_method' => 'cash',
                'amount_received' => 50000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_online_order_status_shows_confirmed_after_kasir_confirm(): void
    {
        $product = $this->sellableProduct();
        $kasir = $this->kasirUser();

        $this->patch(route('order.menu.customer'), ['customer_note' => 'Budi']);
        $this->post(route('order.menu.items'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->post(route('order.menu.submit'));

        $order = PosOrder::where('status', 'submitted')->first();

        $this->actingAs($kasir)
            ->post(route('kasir.orders.confirm', $order));

        $this->getJson(route('order.menu.status'))
            ->assertOk()
            ->assertJsonPath('is_confirmed', true)
            ->assertJsonPath('status', 'confirmed');

        $this->get(route('order.menu'))
            ->assertOk()
            ->assertSee('Pesanan dikonfirmasi')
            ->assertSee('Silakan ke Kasir untuk Bayar');
    }

    public function test_kasir_can_cancel_pending_online_order(): void
    {
        $kasir = $this->kasirUser();

        $order = PosOrder::create([
            'order_number' => 'TRX-TEST-DEL-001',
            'source' => 'online',
            'order_type' => 'takeaway',
            'status' => 'submitted',
            'customer_note' => 'Budi',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.orders.cancel', $order))
            ->assertRedirect(route('kasir.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pos_orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_pwa_manifest_for_kasir_is_available(): void
    {
        $this->get(route('pwa.manifest', 'kasir'))
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/kasir')
            ->assertJsonStructure(['icons', 'name', 'short_name', 'theme_color']);
    }

    public function test_pwa_manifest_for_order_is_available(): void
    {
        $this->get(route('pwa.manifest', 'order'))
            ->assertOk()
            ->assertJsonPath('start_url', '/pesan')
            ->assertJsonPath('scope', '/pesan');
    }
}
