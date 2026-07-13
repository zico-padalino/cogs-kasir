<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class KasirPin
{
    public const SESSION_OPERATOR = 'kasir_operator_id';

    public const SESSION_VERIFIED_AT = 'kasir_pin_verified_at';

    /** Durasi sesi PIN sebelum harus dimasukkan lagi. */
    public const IDLE_MINUTES = 10;

    public static function operator(): ?User
    {
        $id = Session::get(self::SESSION_OPERATOR);

        if (! $id || ! self::isUnlocked()) {
            return null;
        }

        return User::query()->find($id);
    }

    public static function operatorOrAuth(): ?User
    {
        return self::operator() ?? auth()->user();
    }

    public static function isUnlocked(): bool
    {
        $operatorId = Session::get(self::SESSION_OPERATOR);
        $verifiedTs = self::verifiedTimestamp(Session::get(self::SESSION_VERIFIED_AT));

        if (! $operatorId || $verifiedTs === null) {
            return false;
        }

        $elapsed = now()->getTimestamp() - $verifiedTs;

        if ($elapsed < 0) {
            return false;
        }

        return $elapsed <= (self::IDLE_MINUTES * 60);
    }

    public static function expiresAtTimestamp(): ?int
    {
        if (! Session::get(self::SESSION_OPERATOR)) {
            return null;
        }

        $verifiedTs = self::verifiedTimestamp(Session::get(self::SESSION_VERIFIED_AT));

        if ($verifiedTs === null) {
            return null;
        }

        return $verifiedTs + (self::IDLE_MINUTES * 60);
    }

    public static function unlock(User $operator): void
    {
        Session::put(self::SESSION_OPERATOR, $operator->id);
        Session::put(self::SESSION_VERIFIED_AT, now()->getTimestamp());
    }

    public static function lock(): void
    {
        Session::forget([self::SESSION_OPERATOR, self::SESSION_VERIFIED_AT]);
    }

    public static function findByPin(string $pin): ?User
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';

        if (strlen($pin) < 4 || strlen($pin) > 6) {
            return null;
        }

        $candidates = User::query()
            ->whereNotNull('pin_hash')
            ->get()
            ->filter(fn (User $user) => $user->hasModule(UserRole::Kasir));

        foreach ($candidates as $user) {
            if (Hash::check($pin, $user->pin_hash)) {
                return $user;
            }
        }

        return null;
    }

    public static function setPin(User $user, string $pin): void
    {
        $pin = preg_replace('/\D+/', '', $pin) ?? '';

        $user->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
        ])->save();
    }

    public static function clearPin(User $user): void
    {
        $user->forceFill([
            'pin_hash' => null,
            'pin_set_at' => null,
        ])->save();
    }

    public static function hasPin(User $user): bool
    {
        return filled($user->pin_hash);
    }

    private static function verifiedTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
