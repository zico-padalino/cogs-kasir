<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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
        'daily_salary',
        'status',
        'user_id',
        'pin_hash',
        'pin_set_at',
        'notes',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'base_salary' => 'decimal:4',
            'daily_salary' => 'decimal:4',
            'status' => EmployeeStatus::class,
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

    public function workSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }

    /**
     * Pegawai yang boleh muncul di absensi (aktif, bukan akun root).
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeForAttendance(Builder $query): Builder
    {
        $query->where('status', EmployeeStatus::Active);

        // Hindari 500 jika kolom users.is_root belum dimigrasi di hosting.
        if (Schema::hasColumn('users', 'is_root')) {
            $query->whereDoesntHave(
                'user',
                fn (Builder $userQuery) => $userQuery->where('is_root', true),
            );
        }

        return $query;
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
