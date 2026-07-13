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

    public const SESSION_EXPIRES_AT = 'kasir_pin_expires_at';

    public static function idleMinutes(): int
    {
        return max(1, (int) config('pos.kasir_pin_ttl_minutes', 10));
    }

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
        if (! Session::get(self::SESSION_OPERATOR)) {
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

        // Kompatibilitas sesi lama yang hanya punya verified_at
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

    public static function unlock(User $operator): void
    {
        $now = now()->getTimestamp();
        $expiresAt = $now + (self::idleMinutes() * 60);

        Session::put(self::SESSION_OPERATOR, (int) $operator->id);
        Session::put(self::SESSION_VERIFIED_AT, $now);
        Session::put(self::SESSION_EXPIRES_AT, $expiresAt);
        Session::save();
    }

    public static function lock(): void
    {
        Session::forget([
            self::SESSION_OPERATOR,
            self::SESSION_VERIFIED_AT,
            self::SESSION_EXPIRES_AT,
        ]);
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
