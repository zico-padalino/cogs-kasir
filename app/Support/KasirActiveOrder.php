<?php

namespace App\Support;

use App\Models\PosOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class KasirActiveOrder
{
    public static function cacheKey(User|int $user): string
    {
        $id = $user instanceof User ? $user->id : $user;

        return 'kasir_active_order:'.$id;
    }

    public static function getId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        if (self::usesCache()) {
            $id = Cache::get(self::cacheKey($user));

            return $id ? (int) $id : null;
        }

        $id = session('kasir_order_id');

        return $id ? (int) $id : null;
    }

    public static function set(PosOrder|int $order, ?User $user = null): void
    {
        $user ??= auth()->user();
        $orderId = $order instanceof PosOrder ? (int) $order->id : (int) $order;

        if (self::usesCache() && $user) {
            Cache::put(self::cacheKey($user), $orderId, now()->addDays(7));

            return;
        }

        session(['kasir_order_id' => $orderId]);
    }

    public static function forget(?User $user = null): void
    {
        $user ??= auth()->user();

        if (self::usesCache() && $user) {
            Cache::forget(self::cacheKey($user));

            return;
        }

        session()->forget('kasir_order_id');
    }

    public static function find(?User $user = null): ?PosOrder
    {
        $orderId = self::getId($user);

        if (! $orderId) {
            return null;
        }

        return PosOrder::with('items.product')
            ->where('id', $orderId)
            ->whereIn('status', ['open', 'submitted', 'confirmed', 'unpaid'])
            ->first();
    }

    public static function usesCache(): bool
    {
        $request = request();

        return $request && ($request->is('api/*') || $request->bearerToken());
    }
}
