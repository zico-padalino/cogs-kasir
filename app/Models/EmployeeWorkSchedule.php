<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkSchedule extends Model
{
    protected $fillable = [
        'employee_id',
        'day_of_week',
        'clock_in',
        'clock_out',
        'is_off',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_off' => 'boolean',
        ];
    }

    /** @return array<int, string> */
    public static function dayLabels(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
