<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class KasirPin
{
    public const SESSION_OPERATOR = 'kasir_operator_id';

    public const SESSION_VERIFIED_AT = 'kasir_pin_verified_at';

    public const IDLE_MINUTES = 15;

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
        $verifiedAt = Session::get(self::SESSION_VERIFIED_AT);

        if (! $verifiedAt || ! Session::get(self::SESSION_OPERATOR)) {
            return false;
        }

        return now()->diffInMinutes($verifiedAt) <= self::IDLE_MINUTES;
    }

    public static function unlock(User $operator): void
    {
        Session::put(self::SESSION_OPERATOR, $operator->id);
        Session::put(self::SESSION_VERIFIED_AT, now());
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
}
