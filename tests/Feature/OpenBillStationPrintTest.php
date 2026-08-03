<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\ProductType;
use App\Models\Employee;
use App\Models\InventoryLot;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\KasirPin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpenBillStationPrintTest extends TestCase
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
    }

    private function unlockKasir(): User
    {
        $user = User::factory()->kasir()->create();
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-'.uniqid(),
            'name' => 'Kasir Test',
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
            'user_id' => $user->id,
        ]);
        KasirPin::setPin($employee, '1234');
        KasirPin::unlock($employee->fresh());

        return $user;
    }

    private function openBillWithItems(): PosOrder
    {
        $food = Product::create([
            'sku' => 'FOOD-OB-1',
            'name' => 'Nasi Goreng',
            'type' => ProductType::FinishedGood,
            'selling_price' => 25000,
            'costing_method' => 'weighted_average',
            'is_active' => true,
            'is_menu_item' => true,
            'menu_category' => 'makanan',
        ]);
        InventoryLot::create([
            'product_id' => $food->id,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
        ]);

        $drink = Product::create([
            'sku' => 'DRINK-OB-1',
            'name' => 'Es Teh',
            'type' => ProductType::FinishedGood,
            'selling_price' => 8000,
            'costing_method' => 'weighted_average',
            'is_active' => true,
            'is_menu_item' => true,
            'menu_category' => 'minuman',
        ]);
        InventoryLot::create([
            'product_id' => $drink->id,
            'quantity_received' => 20,
            'quantity_remaining' => 20,
            'unit_cost' => 2000,
            'received_at' => now(),
        ]);

        $order = PosOrder::create([
            'order_number' => 'TRX-OB-PRINT-001',
            'order_day' => now()->toDateString(),
            'source' => PosOrderSource::Kasir,
            'order_type' => 'takeaway',
            'status' => PosOrderStatus::Unpaid,
            'customer_note' => 'Andi',
            'subtotal' => 33000,
            'total' => 33000,
        ]);

        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $food->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'line_total' => 25000,
        ]);
        PosOrderItem::create([
            'pos_order_id' => $order->id,
            'product_id' => $drink->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'line_total' => 8000,
        ]);

        return $order->fresh(['items.product']);
    }

    public function test_open_bill_can_print_kitchen_and_bar(): void
    {
        $order = $this->openBillWithItems();
        $kasir = $this->unlockKasir();

        $this->actingAs($kasir)
            ->get(route('kasir.receipt.kitchen-print', $order))
            ->assertOk()
            ->assertSee('Struk Dapur')
            ->assertSee('TAGIHAN TERBUKA')
            ->assertSee('Nasi Goreng');

        $this->actingAs($kasir)
            ->get(route('kasir.receipt.bar-print', $order))
            ->assertOk()
            ->assertSee('Struk Bar')
            ->assertSee('TAGIHAN TERBUKA')
            ->assertSee('Es Teh');

        $this->actingAs($kasir)
            ->get(route('kasir.receipt.kitchen', $order))
            ->assertOk();

        $this->actingAs($kasir)
            ->get(route('kasir.receipt.bar', $order))
            ->assertOk();

        $this->actingAs($kasir)
            ->getJson(route('kasir.receipt.thermal-json', $order).'?variant=kitchen')
            ->assertOk()
            ->assertJsonPath('variant', 'kitchen');

        $this->actingAs($kasir)
            ->getJson(route('kasir.receipt.thermal-json', $order).'?variant=bar')
            ->assertOk()
            ->assertJsonPath('variant', 'bar');
    }

    public function test_open_bill_cannot_print_customer_receipt(): void
    {
        $order = $this->openBillWithItems();
        $kasir = $this->unlockKasir();

        $this->actingAs($kasir)
            ->get(route('kasir.receipt', $order))
            ->assertRedirect(route('kasir.index'));

        $this->actingAs($kasir)
            ->get(route('kasir.receipt.pdf', $order))
            ->assertNotFound();

        $this->actingAs($kasir)
            ->getJson(route('kasir.receipt.thermal-json', $order).'?variant=customer')
            ->assertNotFound();
    }

    public function test_open_bill_cart_shows_station_print_buttons(): void
    {
        $order = $this->openBillWithItems();

        $html = view('kasir.partials.cart-panel', [
            'order' => $order,
            'format' => \App\Support\Format::class,
        ])->render();

        $this->assertStringContainsString('Cetak Dapur', $html);
        $this->assertStringContainsString('Cetak Bar', $html);
        $this->assertStringContainsString(route('kasir.receipt.kitchen-print', $order), $html);
        $this->assertStringContainsString(route('kasir.receipt.bar-print', $order), $html);
    }
}
