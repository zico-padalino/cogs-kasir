<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache singkat antrian dapur (file store) agar poll APK/web tidak
 * memukul DB terus-menerus — kurangi 503 di shared hosting.
 */
final class KitchenBoardCache
{
    private const TTL_SECONDS = 45;

    /** @param  callable(): array<string, mixed>  $callback */
    public static function remember(string $channel, callable $callback): array
    {
        $key = self::key($channel);

        try {
            /** @var array<string, mixed> $payload */
            $payload = Cache::store('file')->remember($key, self::TTL_SECONDS, function () use ($callback) {
                $value = $callback();

                return is_array($value) ? $value : [];
            });

            return $payload;
        } catch (\Throwable) {
            $value = $callback();

            return is_array($value) ? $value : [];
        }
    }

    public static function forget(): void
    {
        foreach (['api', 'web', 'web-pending'] as $channel) {
            try {
                Cache::store('file')->forget(self::key($channel));
            } catch (\Throwable) {
                // ignore — cache opsional
            }
        }
    }

    private static function key(string $channel): string
    {
        return 'pos:kitchen_board_'.$channel.'_v1';
    }
}
