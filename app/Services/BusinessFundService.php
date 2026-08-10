<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Models\BusinessExpense;
use App\Models\Employee;
use App\Models\PosOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessFundService
{
    /**
     * @return array{
     *     date: Carbon,
     *     opening: float,
     *     revenue: float,
     *     expense: float,
     *     closing: float,
     *     entries: \Illuminate\Database\Eloquent\Collection<int, BusinessExpense>
     * }
     */
    public function dayReport(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $opening = $this->balanceBefore($start);
        $revenue = $this->revenueBetween($start, $end);
        $entries = BusinessExpense::query()
            ->with('user:id,name')
            ->whereBetween('occurred_at', [$start, $end])
            ->latest('occurred_at')
            ->latest('id')
            ->get();
        $expense = round((float) $entries->sum('amount'), 4);

        return [
            'date' => $start,
            'opening' => $opening,
            'revenue' => $revenue,
            'expense' => $expense,
            'closing' => round($opening + $revenue - $expense, 4),
            'entries' => $entries,
        ];
    }

    public function balance(?Carbon $until = null): float
    {
        return $this->balanceAt($until ?? now());
    }

    /**
     * Perkiraan pengeluaran untuk dashboard COGS.
     *
     * @return array{
     *     month_label: string,
     *     month_to_date: float,
     *     avg_daily_30: float,
     *     projected_month: float,
     *     remaining_estimate: float,
     *     estimated_salary: float,
     *     days_elapsed: int,
     *     days_in_month: int
     * }
     */
    public function expenseForecast(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy();
        $monthStart = $asOf->copy()->startOfMonth();
        $daysInMonth = (int) $asOf->daysInMonth;
        $daysElapsed = max(1, (int) $asOf->day);

        $monthToDate = round((float) BusinessExpense::query()
            ->whereBetween('occurred_at', [$monthStart, $asOf->copy()->endOfDay()])
            ->sum('amount'), 4);

        $windowStart = $asOf->copy()->subDays(29)->startOfDay();
        $last30Total = round((float) BusinessExpense::query()
            ->whereBetween('occurred_at', [$windowStart, $asOf->copy()->endOfDay()])
            ->sum('amount'), 4);
        $avgDaily30 = round($last30Total / 30, 4);

        $projectedFromMtd = round(($monthToDate / $daysElapsed) * $daysInMonth, 4);
        $projectedFromAvg = round($avgDaily30 * $daysInMonth, 4);
        $projectedMonth = $monthToDate > 0
            ? max($monthToDate, $projectedFromMtd)
            : $projectedFromAvg;

        $remainingEstimate = max(0, round($projectedMonth - $monthToDate, 4));

        $estimatedSalary = round((float) Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->get(['base_salary', 'daily_salary', 'id'])
            ->sum(function (Employee $employee) {
                return (float) $employee->base_salary + $employee->estimatedMonthlyFromDaily();
            }), 4);

        return [
            'month_label' => $asOf->translatedFormat('F Y'),
            'month_to_date' => $monthToDate,
            'avg_daily_30' => $avgDaily30,
            'projected_month' => $projectedMonth,
            'remaining_estimate' => $remainingEstimate,
            'estimated_salary' => $estimatedSalary,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
        ];
    }

    public function balanceBefore(Carbon $date): float
    {
        return $this->balanceAt($date->copy()->startOfDay()->subMicrosecond());
    }

    public function addExpense(
        float $amount,
        string $category,
        PaymentMethod $paymentMethod,
        string $note,
        Carbon $occurredAt,
        ?User $user = null,
    ): BusinessExpense {
        return DB::transaction(function () use ($amount, $category, $paymentMethod, $note, $occurredAt, $user) {
            $this->validateExpense($amount, $category, $note, $occurredAt);

            return BusinessExpense::query()->create([
                'amount' => round($amount, 4),
                'category' => $category,
                'payment_method' => $paymentMethod,
                'note' => trim($note),
                'user_id' => $user?->id ?? auth()->id(),
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    public function updateExpense(
        BusinessExpense $expense,
        float $amount,
        string $category,
        PaymentMethod $paymentMethod,
        string $note,
        Carbon $occurredAt,
    ): BusinessExpense {
        return DB::transaction(function () use ($expense, $amount, $category, $paymentMethod, $note, $occurredAt) {
            $this->validateExpense($amount, $category, $note, $occurredAt, $expense->id);

            $expense->update([
                'amount' => round($amount, 4),
                'category' => $category,
                'payment_method' => $paymentMethod,
                'note' => trim($note),
                'occurred_at' => $occurredAt,
            ]);

            return $expense->fresh('user');
        });
    }

    private function validateExpense(
        float $amount,
        string $category,
        string $note,
        Carbon $occurredAt,
        ?int $excludeExpenseId = null,
    ): void {
        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal pengeluaran harus lebih dari 0.');
        }
        if (! array_key_exists($category, BusinessExpense::CATEGORIES)) {
            throw new RuntimeException('Kategori pengeluaran tidak valid.');
        }
        if (trim($note) === '') {
            throw new RuntimeException('Keterangan pengeluaran wajib diisi.');
        }

        $available = $this->balanceAt($occurredAt->copy()->endOfDay(), $excludeExpenseId);
        if ($amount > $available + 0.0001) {
            throw new RuntimeException(
                'Saldo dana usaha tidak cukup. Saldo tersedia pada tanggal tersebut Rp '.
                number_format($available, 0, ',', '.').'.'
            );
        }
    }

    private function balanceAt(Carbon $until, ?int $excludeExpenseId = null): float
    {
        $revenue = $this->revenueUntil($until);
        $expense = (float) BusinessExpense::query()
            ->where('occurred_at', '<=', $until)
            ->when($excludeExpenseId, fn ($query) => $query->where('id', '!=', $excludeExpenseId))
            ->sum('amount');

        return round($revenue - $expense, 4);
    }

    private function revenueUntil(Carbon $until): float
    {
        return round((float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $until)
            ->sum('total'), 4);
    }

    private function revenueBetween(Carbon $start, Carbon $end): float
    {
        return round((float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total'), 4);
    }
}
