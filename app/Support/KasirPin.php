<?php

namespace App\Support;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class KasirPin
{
    public const SESSION_OPERATOR = 'kasir_operator_employee_id';

    /** @deprecated Digunakan hanya untuk kompatibilitas sesi lama berbasis user id */
    public const SESSION_OPERATOR_USER = 'kasir_operator_id';

    public const SESSION_VERIFIED_AT = 'kasir_pin_verified_at';

    public const SESSION_EXPIRES_AT = 'kasir_pin_expires_at';

    public static function idleMinutes(): int
    {
        return max(1, (int) config('pos.kasir_pin_ttl_minutes', 10));
    }

    public static function operatorEmployee(): ?Employee
    {
        $id = Session::get(self::SESSION_OPERATOR);

        if (! $id || ! self::isUnlocked()) {
            return null;
        }

        return Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->find($id);
    }

    /**
     * User terhubung ke karyawan yang sedang bertugas (bisa null).
     */
    public static function operator(): ?User
    {
        return self::operatorEmployee()?->user;
    }

    public static function operatorName(): string
    {
        return self::operatorEmployee()?->name
            ?? self::operator()?->name
            ?? auth()->user()?->name
            ?? 'Kasir';
    }

    /**
     * Data petugas untuk dicatat di transaksi POS.
     *
     * @return array{user_id: ?int, cashier_employee_id: ?int, cashier_name: string}
     */
    public static function cashierAttribution(): array
    {
        $employee = self::operatorEmployee();
        $user = $employee?->user ?? auth()->user();

        return [
            'user_id' => $user?->id,
            'cashier_employee_id' => $employee?->id,
            'cashier_name' => $employee?->name
                ?? $user?->name
                ?? 'Kasir',
        ];
    }

    /**
     * User untuk FK teknis: akun karyawan jika ada, else akun login stasiun.
     */
    public static function operatorOrAuth(): ?User
    {
        return self::operator() ?? auth()->user();
    }

    public static function isUnlocked(): bool
    {
        // Sesi lama berbasis user id tidak valid lagi — minta PIN ulang.
        if (! Session::get(self::SESSION_OPERATOR)) {
            if (Session::get(self::SESSION_OPERATOR_USER)) {
                self::lock();
            }

            return false;
        }

        $expiresAt = self::expiresAtTimestamp();

        if ($expiresAt === null) {
            return false;
        }

        return now()->getTimestamp() < $expiresAt;
    }

    public static function expiresAtTimestamp(): ?int
    {
        $expiresAt = self::toUnix(Session::get(self::SESSION_EXPIRES_AT));

        if ($expiresAt !== null) {
            return $expiresAt;
        }

        $verifiedAt = self::toUnix(Session::get(self::SESSION_VERIFIED_AT));

        if ($verifiedAt === null) {
            return null;
        }

        return $verifiedAt + (self::idleMinutes() * 60);
    }

    public static function remainingSeconds(): int
    {
        $expiresAt = self::expiresAtTimestamp();

        if ($expiresAt === null) {
            return 0;
        }

        return max(0, $expiresAt - now()->getTimestamp());
    }

    /** @return array{unlocked: bool, expires_at: ?int, server_now: int, remaining_seconds: int} */
    public static function statusPayload(): array
    {
        $unlocked = self::isUnlocked();

        if (! $unlocked) {
            self::lock();
        }

        return [
            'unlocked' => $unlocked,
            'expires_at' => $unlocked ? self::expiresAtTimestamp() : null,
            'server_now' => now()->getTimestamp(),
            'remaining_seconds' => $unlocked ? self::remainingSeconds() : 0,
        ];
    }

    public static function unlock(Employee $operator): void
    {
        $now = now()->getTimestamp();
        $expiresAt = $now + (self::idleMinutes() * 60);

        Session::forget(self::SESSION_OPERATOR_USER);
        Session::put(self::SESSION_OPERATOR, (int) $operator->id);
        Session::put(self::SESSION_VERIFIED_AT, $now);
        Session::put(self::SESSION_EXPIRES_AT, $expiresAt);
        Session::save();
    }

    public static function lock(): void
    {
        Session::forget([
            self::SESSION_OPERATOR,
            self::SESSION_OPERATOR_USER,
            self::SESSION_VERIFIED_AT,
            self::SESSION_EXPIRES_AT,
        ]);
    }

    public static function findByPin(string $pin): ?Employee
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';

        if (strlen($pin) < 4 || strlen($pin) > 6) {
            return null;
        }

        $candidates = Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->whereNotNull('pin_hash')
            ->get();

        foreach ($candidates as $employee) {
            if (Hash::check($pin, (string) $employee->pin_hash)) {
                return $employee;
            }
        }

        return null;
    }

    public static function setPin(Employee $employee, string $pin): void
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';

        $employee->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
        ])->save();
    }

    public static function clearPin(Employee $employee): void
    {
        $employee->forceFill([
            'pin_hash' => null,
            'pin_set_at' => null,
        ])->save();
    }

    public static function hasPin(Employee $employee): bool
    {
        return filled($employee->pin_hash);
    }

    public static function employeeForUser(User $user): ?Employee
    {
        return Employee::query()
            ->where('user_id', $user->id)
            ->where('status', EmployeeStatus::Active)
            ->first();
    }

    private static function toUnix(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value > 1_000_000_000_000 ? (int) floor($value / 1000) : $value;
        }

        if (is_float($value)) {
            $asInt = (int) $value;

            return $asInt > 1_000_000_000_000 ? (int) floor($asInt / 1000) : $asInt;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            if (ctype_digit($trimmed)) {
                $asInt = (int) $trimmed;

                return $asInt > 1_000_000_000_000 ? (int) floor($asInt / 1000) : $asInt;
            }

            try {
                return Carbon::parse($trimmed)->getTimestamp();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
