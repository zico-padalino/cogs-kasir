<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Enums\ProductType;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Services\QrisDynamicService;
use App\Support\ShopSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class OnlineQrisSelfPayTest extends TestCase
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

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'cashier_name')) {
            Schema::table('pos_orders', function ($table) {
                $table->string('cashier_name')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'cashier_employee_id')) {
            Schema::table('pos_orders', function ($table) {
                $table->unsignedBigInteger('cashier_employee_id')->nullable();
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

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'confirmed_at')) {
            Schema::table('pos_orders', function ($table) {
                $table->timestamp('confirmed_at')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'confirmed_by')) {
            Schema::table('pos_orders', function ($table) {
                $table->unsignedBigInteger('confirmed_by')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'served_at')) {
            Schema::table('pos_orders', function ($table) {
                $table->timestamp('served_at')->nullable();
            });
        }

        if (Schema::hasTable('pos_orders') && ! Schema::hasColumn('pos_orders', 'paid_at')) {
            Schema::table('pos_orders', function ($table) {
                $table->timestamp('paid_at')->nullable();
            });
        }

        if (Schema::hasTable('sales_transactions') && ! Schema::hasColumn('sales_transactions', 'pos_order_id')) {
            Schema::table('sales_transactions', function ($table) {
                $table->unsignedBigInteger('pos_order_id')->nullable();
            });
        }
    }

    private function seedStaticQris(): void
    {
        $service = app(QrisDynamicService::class);
        $merchantAccount =
            '0011ID.DANA.WWW'.
            '011793600912345678901'.
            '0215ID1026554625172';

        $withoutCrc =
            '000201'.
            '010211'.
            '26'.str_pad((string) strlen($merchantAccount), 2, '0', STR_PAD_LEFT).$merchantAccount.
            '52045812'.
            '5303360'.
            '5802ID'.
            '5911KEDAI TJOAN'.
            '6007Jakarta'.
            '62070703A01';

        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('crc16');
        $method->setAccessible(true);
        $static = $withoutCrc.'6304'.$method->invoke($service, $withoutCrc.'6304');

        ShopSettings::put(['qris_payload' => $static]);
    }

    public function test_customer_can_pay_online_order_with_qris_proof(): void
    {
        $this->seedStaticQris();

        $product = Product::query()->create([
            'name' => 'Kopi Tes',
            'sku' => 'KOPI-TES',
            'type' => ProductType::FinishedGood,
            'unit' => 'pcs',
            'selling_price' => 15000,
            'is_active' => true,
            'is_menu_item' => true,
            'is_sold_out' => false,
        ]);

        app(InventoryCostService::class)->receiveStock($product, 5, 5000);

        $order = PosOrder::query()->create([
            'order_number' => 'T-001',
            'order_day' => now()->toDateString(),
            'source' => PosOrderSource::Online,
            'order_type' => PosOrderType::Takeaway,
            'status' => PosOrderStatus::Submitted,
            'customer_note' => 'Budi',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        PosOrderItem::query()->create([
            'pos_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);

        $response = $this->withSession(['online_order_id' => $order->id])
            ->post(route('order.menu.pay'), [
                'payment_method' => 'qris',
                'payment_proof' => UploadedFile::fake()->image('bukti.jpg', 640, 480),
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $response->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(PosOrderStatus::Paid, $order->status);
        $this->assertSame(PaymentMethod::Qris, $order->payment_method);
        $this->assertNotEmpty($order->payment_proof_path);
        $this->assertTrue($order->paidByCustomerOnline());
        $this->assertFileExists(public_path($order->payment_proof_path));

        @unlink(public_path($order->payment_proof_path));
    }
}
