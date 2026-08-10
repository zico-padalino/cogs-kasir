<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Models\BusinessExpense;
use App\Models\PosOrder;
use App\Models\User;
use App\Services\BusinessFundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_expense_cannot_exceed_available_business_fund(): void
    {
        $date = Carbon::parse('2026-07-26 12:00:00');
        $user = User::factory()->cogs()->create();
        $this->paidOrder('TRX-010', 100_000, 80_000, PaymentMethod::Qris, $date);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Saldo dana usaha tidak cukup');

        app(BusinessFundService::class)->addExpense(
            amount: 80_001,
            category: 'operasional',
            paymentMethod: PaymentMethod::Qris,
            note: 'Biaya operasional',
            occurredAt: $date,
            user: $user,
        );
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
