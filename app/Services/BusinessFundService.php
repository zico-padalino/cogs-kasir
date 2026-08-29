<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PosOrderStatus;
use App\Enums\SalaryStatus;
use App\Models\BusinessExpense;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PosOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class BusinessFundService
{
    /**
     * @return array{
     *     date: Carbon,
     *     opening: float,
     *     revenue: float,
     *     expense: float,
     *     expense_gaji: float,
     *     expense_lainnya: float,
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
        $expenseGaji = round((float) $entries->where('category', 'gaji')->sum('amount'), 4);
        $expenseLainnya = round($expense - $expenseGaji, 4);

        return [
            'date' => $start,
            'opening' => $opening,
            'revenue' => $revenue,
            'expense' => $expense,
            'expense_gaji' => $expenseGaji,
            'expense_lainnya' => $expenseLainnya,
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
            $this->validateExpense($amount, $category, $note);

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
            $this->validateExpense($amount, $category, $note);

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

    /**
     * Saat gaji dikonfirmasi bayar / diedit (lunas): potong Dana Usaha (omzet bersih − pengeluaran).
     */
    public function syncSalaryExpense(
        EmployeeSalary $salary,
        ?User $user = null,
        PaymentMethod $paymentMethod = PaymentMethod::Transfer,
    ): ?BusinessExpense {
        if ($salary->status !== SalaryStatus::Paid) {
            return null;
        }

        if (! Schema::hasTable('business_expenses')) {
            return null;
        }

        $salary->loadMissing('employee');
        $amount = round((float) $salary->total, 4);
        if ($amount <= 0) {
            $this->removeSalaryExpense($salary);

            return null;
        }

        $note = $this->salaryExpenseNote($salary);
        $occurredAt = $salary->paid_at?->copy() ?? now();

        return DB::transaction(function () use ($salary, $user, $paymentMethod, $amount, $note, $occurredAt) {
            $expenseId = Schema::hasColumn('employee_salaries', 'business_expense_id')
                ? $salary->business_expense_id
                : null;

            $expense = $expenseId
                ? BusinessExpense::query()->find($expenseId)
                : null;

            if ($expense) {
                $expense = $this->updateExpense(
                    $expense,
                    $amount,
                    'gaji',
                    $expense->payment_method ?? $paymentMethod,
                    $note,
                    $occurredAt,
                );
            } else {
                $expense = $this->addExpense(
                    $amount,
                    'gaji',
                    $paymentMethod,
                    $note,
                    $occurredAt,
                    $user,
                );
            }

            if (Schema::hasColumn('employee_salaries', 'business_expense_id')) {
                if ((int) $salary->business_expense_id !== (int) $expense->id) {
                    $salary->forceFill(['business_expense_id' => $expense->id])->save();
                }
            }

            return $expense;
        });
    }

    public function removeSalaryExpense(EmployeeSalary $salary): void
    {
        if (! Schema::hasTable('business_expenses')) {
            return;
        }

        DB::transaction(function () use ($salary) {
            $expense = null;
            if (Schema::hasColumn('employee_salaries', 'business_expense_id') && $salary->business_expense_id) {
                $expense = BusinessExpense::query()->find($salary->business_expense_id);
            }

            if ($expense) {
                $expense->delete();
            }

            if (Schema::hasColumn('employee_salaries', 'business_expense_id') && $salary->business_expense_id) {
                $salary->forceFill(['business_expense_id' => null])->save();
            }
        });
    }

    public function salaryExpenseNote(EmployeeSalary $salary): string
    {
        $salary->loadMissing('employee');
        $name = trim((string) ($salary->employee?->name ?? 'Karyawan'));
        $kind = $salary->periodKindLabel();
        $period = $salary->periodLabel();

        $note = "Gaji karyawan: {$name} · {$kind} · {$period}";

        $userNote = trim((string) preg_replace(
            '/\s*\|\s*(Harian|Potongan|≈).*/u',
            '',
            (string) ($salary->notes ?? '')
        ));
        if ($userNote !== '') {
            $note .= ' — '.$userNote;
        }

        return mb_substr($note, 0, 255);
    }

    private function validateExpense(
        float $amount,
        string $category,
        string $note,
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
    }

    private function balanceAt(Carbon $until): float
    {
        $revenue = $this->revenueUntil($until);
        $expense = (float) BusinessExpense::query()
            ->where('occurred_at', '<=', $until)
            ->sum('amount');

        return round($revenue - $expense, 4);
    }

    private function revenueUntil(Carbon $until): float
    {
        $ordersTotal = (float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $until)
            ->sum('total');

        $lostTotal = Schema::hasTable('stock_wastes')
            ? (float) \App\Models\StockWaste::query()
                ->where('created_at', '<=', $until)
                ->sum('total_cost')
            : 0.0;

        return round($ordersTotal - $lostTotal, 4);
    }

    private function revenueBetween(Carbon $start, Carbon $end): float
    {
        $ordersTotal = (float) PosOrder::query()
            ->whereIn('status', [PosOrderStatus::Paid, PosOrderStatus::Served])
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total');

        $lostTotal = Schema::hasTable('stock_wastes')
            ? (float) \App\Models\StockWaste::query()
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_cost')
            : 0.0;

        return round($ordersTotal - $lostTotal, 4);
    }
}
