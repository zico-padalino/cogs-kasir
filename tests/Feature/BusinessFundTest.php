<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Enums\SalaryStatus;
use App\Models\BusinessExpense;
use App\Models\EmployeeSalary;
use App\Models\PosOrder;
use App\Models\StockWaste;
use App\Models\User;
use App\Services\BusinessFundService;
use App\Services\SalesReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BusinessFundTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_uses_net_revenue_from_every_payment_method_and_carries_balance(): void
    {
        $yesterday = Carbon::parse('2026-07-25 12:00:00');
        $today = Carbon::parse('2026-07-26 12:00:00');

        $this->paidOrder('TRX-001', 120_000, 100_000, PaymentMethod::Cash, $yesterday);
        $this->paidOrder('TRX-002', 110_000, 90_000, PaymentMethod::Cash, $today);
        $this->paidOrder('TRX-003', 200_000, 175_000, PaymentMethod::Qris, $today);
        $this->paidOrder('TRX-004', 300_000, 250_000, PaymentMethod::Transfer, $today);

        BusinessExpense::query()->create([
            'amount' => 65_000,
            'category' => 'utilitas',
            'payment_method' => PaymentMethod::Transfer,
            'note' => 'Bayar listrik',
            'occurred_at' => $today,
        ]);

        $report = app(BusinessFundService::class)->dayReport($today);

        $this->assertSame(100_000.0, $report['opening']);
        $this->assertSame(515_000.0, $report['revenue']);
        $this->assertSame(65_000.0, $report['expense']);
        $this->assertSame(550_000.0, $report['closing']);
    }

    public function test_expense_forecast_projects_month_from_mtd_pace(): void
    {
        $asOf = Carbon::parse('2026-07-10 15:00:00');

        BusinessExpense::query()->create([
            'amount' => 100_000,
            'category' => 'operasional',
            'payment_method' => PaymentMethod::Cash,
            'note' => 'Belanja awal bulan',
            'occurred_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);
        BusinessExpense::query()->create([
            'amount' => 200_000,
            'category' => 'utilitas',
            'payment_method' => PaymentMethod::Transfer,
            'note' => 'Bayar listrik',
            'occurred_at' => Carbon::parse('2026-07-05 10:00:00'),
        ]);

        $forecast = app(BusinessFundService::class)->expenseForecast($asOf);

        $this->assertSame(300_000.0, $forecast['month_to_date']);
        $this->assertSame(10, $forecast['days_elapsed']);
        $this->assertSame(31, $forecast['days_in_month']);
        $this->assertSame(930_000.0, $forecast['projected_month']);
        $this->assertSame(630_000.0, $forecast['remaining_estimate']);
    }

    public function test_expense_can_exceed_available_business_fund_and_closing_goes_negative(): void
    {
        $date = Carbon::parse('2026-07-26 12:00:00');
        $user = User::factory()->cogs()->create();
        $this->paidOrder('TRX-010', 100_000, 80_000, PaymentMethod::Qris, $date);

        app(BusinessFundService::class)->addExpense(
            amount: 80_001,
            category: 'operasional',
            paymentMethod: PaymentMethod::Qris,
            note: 'Biaya operasional',
            occurredAt: $date,
            user: $user,
        );

        $report = app(BusinessFundService::class)->dayReport($date);

        $this->assertSame(80_001.0, $report['expense']);
        $this->assertSame(-1.0, $report['closing']);
        $this->assertSame(-1.0, app(BusinessFundService::class)->balance($date->copy()->endOfDay()));
    }

    public function test_cogs_user_can_create_update_and_delete_expense(): void
    {
        $user = User::factory()->cogs()->create();
        $date = now()->toDateString();
        $this->paidOrder('TRX-020', 200_000, 150_000, PaymentMethod::Transfer, now());

        $this->actingAs($user)
            ->post(route('business-funds.store'), [
                'amount' => 50_000,
                'category' => 'operasional',
                'payment_method' => PaymentMethod::Transfer->value,
                'note' => 'Belanja kebutuhan toko',
                'date' => $date,
            ])
            ->assertRedirect(route('business-funds.index', ['date' => $date]));

        $expense = BusinessExpense::query()->firstOrFail();
        $this->assertSame($user->id, $expense->user_id);

        $this->actingAs($user)
            ->put(route('business-funds.update', $expense), [
                'amount' => 40_000,
                'category' => 'bahan_stok',
                'payment_method' => PaymentMethod::Cash->value,
                'note' => 'Belanja bahan',
                'date' => $date,
            ])
            ->assertRedirect(route('business-funds.index', ['date' => $date]));

        $this->assertDatabaseHas('business_expenses', [
            'id' => $expense->id,
            'amount' => 40_000,
            'category' => 'bahan_stok',
            'payment_method' => PaymentMethod::Cash->value,
            'note' => 'Belanja bahan',
        ]);

        $this->actingAs($user)
            ->delete(route('business-funds.destroy', $expense))
            ->assertRedirect();

        $this->assertDatabaseMissing('business_expenses', ['id' => $expense->id]);
    }

    public function test_cash_business_expense_stays_separate_from_cash_drawer_ledger(): void
    {
        $user = User::factory()->cogs()->create();
        $date = now();
        $this->paidOrder('TRX-030', 150_000, 150_000, PaymentMethod::Cash, $date);

        app(BusinessFundService::class)->addExpense(
            amount: 25_000,
            category: 'operasional',
            paymentMethod: PaymentMethod::Cash,
            note: 'Pengeluaran dana usaha tunai',
            occurredAt: $date,
            user: $user,
        );

        $this->assertDatabaseCount('business_expenses', 1);
        $this->assertSame(125_000.0, app(BusinessFundService::class)->balance());
    }

    public function test_net_sales_reduces_by_discount_and_lost_goods(): void
    {
        $date = Carbon::parse('2026-07-28 10:00:00');

        Schema::dropIfExists('stock_wastes');
        Schema::create('stock_wastes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('pos_order_id')->nullable();
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 12, 4)->default(0);
            $table->string('consumption_mode')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        $this->paidOrder('TRX-101', 100_000, 90_000, PaymentMethod::Cash, $date);
        $this->paidOrder('TRX-102', 90_000, 80_000, PaymentMethod::Qris, $date);

        StockWaste::query()->forceCreate([
            'product_id' => 1,
            'quantity' => 1,
            'reason' => 'rusak',
            'unit_cost' => 25_000,
            'total_cost' => 25_000,
            'consumption_mode' => 'direct_inventory',
            'user_id' => 1,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $report = app(SalesReportService::class)->reportData(new Request([
            'period' => 'day',
            'date' => $date->toDateString(),
        ]));

        $this->assertSame(190_000.0, $report['omzet_kotor']);
        $this->assertSame(20_000.0, $report['diskon_total']);
        $this->assertSame(25_000.0, $report['lost_total']);
        $this->assertSame(145_000.0, $report['omzet']);
    }

    public function test_admin_net_sales_also_reduces_by_business_expenses(): void
    {
        $date = Carbon::parse('2026-07-28 10:00:00');

        Schema::dropIfExists('business_expenses');
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 4);
            $table->string('category');
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        $this->paidOrder('TRX-201', 100_000, 100_000, PaymentMethod::Cash, $date);

        BusinessExpense::query()->forceCreate([
            'amount' => 30_000,
            'category' => 'operasional',
            'payment_method' => PaymentMethod::Cash->value,
            'note' => 'Beli gas',
            'user_id' => 1,
            'occurred_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $report = app(SalesReportService::class)->reportData(new Request([
            'period' => 'day',
            'date' => $date->toDateString(),
        ]), subtractExpensesFromNet: true);

        $this->assertSame(100_000.0, $report['omzet_kotor']);
        $this->assertSame(30_000.0, $report['expense_total']);
        $this->assertSame(0.0, $report['expense_gaji']);
        $this->assertSame(30_000.0, $report['expense_lainnya']);
        $this->assertSame(70_000.0, $report['omzet']);
    }

    public function test_admin_net_sales_tracks_salary_expenses_separately(): void
    {
        $date = Carbon::parse('2026-07-28 10:00:00');

        Schema::dropIfExists('business_expenses');
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 4);
            $table->string('category');
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        $this->paidOrder('TRX-301', 200_000, 200_000, PaymentMethod::Cash, $date);

        BusinessExpense::query()->forceCreate([
            'amount' => 50_000,
            'category' => 'gaji',
            'payment_method' => PaymentMethod::Transfer->value,
            'note' => 'Gaji bulan Juli',
            'user_id' => 1,
            'occurred_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
        BusinessExpense::query()->forceCreate([
            'amount' => 20_000,
            'category' => 'operasional',
            'payment_method' => PaymentMethod::Cash->value,
            'note' => 'Beli gas',
            'user_id' => 1,
            'occurred_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $report = app(SalesReportService::class)->reportData(new Request([
            'period' => 'day',
            'date' => $date->toDateString(),
        ]), subtractExpensesFromNet: true);

        $this->assertSame(70_000.0, $report['expense_total']);
        $this->assertSame(0.0, $report['expense_gaji']);
        $this->assertSame(50_000.0, $report['expense_gaji_manual']);
        $this->assertSame(20_000.0, $report['expense_lainnya']);
        $this->assertSame(130_000.0, $report['omzet']);
    }

    public function test_admin_salary_expense_counts_confirmed_employee_salaries_only(): void
    {
        $date = Carbon::parse('2026-07-28 10:00:00');

        Schema::dropIfExists('business_expenses');
        Schema::create('business_expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 4);
            $table->string('category');
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('employee_salaries');
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('period_month');
            $table->date('period_end')->nullable();
            $table->decimal('base_salary', 12, 4)->default(0);
            $table->decimal('daily_salary', 12, 4)->default(0);
            $table->unsignedInteger('work_days')->default(0);
            $table->decimal('allowance', 12, 4)->default(0);
            $table->decimal('deduction', 12, 4)->default(0);
            $table->decimal('manual_deduction', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('business_expense_id')->nullable();
            $table->timestamps();
        });

        $this->paidOrder('TRX-401', 300_000, 300_000, PaymentMethod::Cash, $date);

        BusinessExpense::query()->forceCreate([
            'amount' => 50_000,
            'category' => 'gaji',
            'payment_method' => PaymentMethod::Transfer->value,
            'note' => 'Input manual di Dana Usaha',
            'user_id' => 1,
            'occurred_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $linkedExpense = BusinessExpense::query()->forceCreate([
            'amount' => 80_000,
            'category' => 'gaji',
            'payment_method' => PaymentMethod::Transfer->value,
            'note' => 'Gaji karyawan: Budi · Per bulan · Juli 2026',
            'user_id' => 1,
            'occurred_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        EmployeeSalary::query()->forceCreate([
            'employee_id' => 1,
            'period_month' => $date->toDateString(),
            'period_end' => $date->toDateString(),
            'base_salary' => 80_000,
            'total' => 80_000,
            'status' => SalaryStatus::Paid->value,
            'paid_at' => $date,
            'business_expense_id' => $linkedExpense->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $report = app(SalesReportService::class)->reportData(new Request([
            'period' => 'day',
            'date' => $date->toDateString(),
        ]), subtractExpensesFromNet: true);

        $this->assertSame(130_000.0, $report['expense_total']);
        $this->assertSame(80_000.0, $report['expense_gaji']);
        $this->assertSame(50_000.0, $report['expense_gaji_manual']);
        $this->assertSame(0.0, $report['expense_lainnya']);
        $this->assertSame(170_000.0, $report['omzet']);
    }

    public function test_fund_page_is_visible_to_cogs_but_not_kasir_only_user(): void
    {
        $cogs = User::factory()->cogs()->create();
        $kasir = User::factory()->kasir()->create();

        $this->actingAs($cogs)
            ->get(route('business-funds.index'))
            ->assertOk()
            ->assertSee('Uang sebelumnya + penjualan');

        $this->actingAs($kasir)
            ->get(route('business-funds.index'))
            ->assertRedirect($kasir->homeUrl());
    }

    private function paidOrder(
        string $number,
        float $subtotal,
        float $total,
        PaymentMethod $paymentMethod,
        Carbon $paidAt,
    ): PosOrder {
        return PosOrder::query()->create([
            'order_number' => $number,
            'source' => 'kasir',
            'status' => PosOrderStatus::Paid,
            'subtotal' => $subtotal,
            'discount_amount' => $subtotal - $total,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
        ]);
    }
}
