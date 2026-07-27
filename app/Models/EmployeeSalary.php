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
        'base_salary',
        'daily_salary',
        'work_days',
        'allowance',
        'deduction',
        'total',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'base_salary' => 'decimal:4',
            'daily_salary' => 'decimal:4',
            'work_days' => 'integer',
            'allowance' => 'decimal:4',
            'deduction' => 'decimal:4',
            'total' => 'decimal:4',
            'status' => SalaryStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function dailyTotal(): float
    {
        return (float) $this->daily_salary * (int) $this->work_days;
    }
}
