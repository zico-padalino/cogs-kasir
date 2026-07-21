<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'employee_code',
        'name',
        'phone',
        'email',
        'position',
        'department',
        'hire_date',
        'base_salary',
        'status',
        'user_id',
        'pin_hash',
        'pin_set_at',
        'notes',
        'face_photo_path',
        'face_descriptor',
    ];

    protected $hidden = [
        'pin_hash',
        'face_descriptor',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'base_salary' => 'decimal:4',
            'status' => EmployeeStatus::class,
            'face_descriptor' => 'array',
            'pin_set_at' => 'datetime',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || $value === ''
                ? $value
                : mb_strtoupper(trim($value), 'UTF-8'),
        );
    }

    public function hasFaceEnrollment(): bool
    {
        return filled($this->face_photo_path)
            && is_array($this->face_descriptor)
            && count($this->face_descriptor) >= 64;
    }

    /**
     * Profil karyawan siap dipakai (telepon/jabatan tidak lagi wajib).
     */
    public function isProfileComplete(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function missingProfileFields(): array
    {
        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public static function nextCode(): string
    {
        $prefix = 'EMP-'.now()->format('Ym').'-';
        $max = static::query()
            ->where('employee_code', 'like', $prefix.'%')
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $seq = $max ? ((int) substr($max, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
