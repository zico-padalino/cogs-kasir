<?php

namespace App\Models;

use App\Enums\SalaryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    protected $fillable = [
        'employee_id',
        'period_month',
        'period_end',
        'base_salary',
        'daily_salary',
        'work_days',
        'allowance',
        'deduction',
        'manual_deduction',
        'deduction_waivers',
        'total',
        'status',
        'paid_at',
        'business_expense_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'period_end' => 'date',
            'base_salary' => 'decimal:4',
            'daily_salary' => 'decimal:4',
            'work_days' => 'integer',
            'allowance' => 'decimal:4',
            'deduction' => 'decimal:4',
            'manual_deduction' => 'decimal:4',
            'deduction_waivers' => 'array',
            'total' => 'decimal:4',
            'status' => SalaryStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function autoDeduction(): float
    {
        return max(0, (float) $this->deduction - (float) ($this->manual_deduction ?? 0));
    }

    public function periodLabel(): string
    {
        $start = $this->period_month;
        $end = $this->period_end ?? $start?->copy()->endOfMonth();

        if (! $start) {
            return '—';
        }

        if ($end && $start->toDateString() === $end->toDateString()) {
            return $start->translatedFormat('d M Y');
        }

        if ($end && $start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->translatedFormat('d').'–'.$end->translatedFormat('d M Y');
        }

        if ($end) {
            return $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
        }

        return $start->translatedFormat('F Y');
    }

    /**
     * Infer jenis periode dari rentang tanggal: month | week | day | range.
     */
    public function periodKind(): string
    {
        $start = $this->period_month?->copy()?->startOfDay();
        $end = ($this->period_end ?? $this->period_month)?->copy()?->startOfDay();

        if (! $start || ! $end) {
            return 'range';
        }

        if ($start->equalTo($end)) {
            return 'day';
        }

        $isFullMonth = $start->isSameDay($start->copy()->startOfMonth())
            && $end->isSameDay($start->copy()->endOfMonth()->startOfDay());

        if ($isFullMonth) {
            return 'month';
        }

        $isWeek = $start->isSameDay($start->copy()->startOfWeek(\Carbon\Carbon::MONDAY))
            && $end->isSameDay($start->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->startOfDay())
            && (int) $start->diffInDays($end) === 6;

        if ($isWeek) {
            return 'week';
        }

        return 'range';
    }

    public function periodKindLabel(): string
    {
        return match ($this->periodKind()) {
            'month' => 'Per bulan',
            'week' => 'Per minggu',
            'day' => 'Per hari',
            default => 'Rentang tanggal',
        };
    }

    public function periodKindBadgeClass(): string
    {
        return match ($this->periodKind()) {
            'month' => 'badge-blue',
            'week' => 'badge-violet',
            'day' => 'badge-amber',
            default => 'badge-slate',
        };
    }

    public function periodDayCount(): int
    {
        $start = $this->period_month?->copy()?->startOfDay();
        $end = ($this->period_end ?? $this->period_month)?->copy()?->startOfDay();

        if (! $start || ! $end) {
            return 0;
        }

        return $start->diffInDays($end) + 1;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function businessExpense(): BelongsTo
    {
        return $this->belongsTo(BusinessExpense::class);
    }

    public function dailyTotal(): float
    {
        return (float) $this->daily_salary * (int) $this->work_days;
    }
}
