<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache katalog menu jual (file store) untuk halaman QR /pesan & kasir.
 * Mengurangi query berat saat banyak HP buka menu bersamaan (EP/NPROC).
 *
 * Sengaja pakai store `file` — jangan CACHE_STORE=database (bisa tanpa tabel cache).
 */
final class MenuCatalogCache
{
    private const KEY = 'pos:sellable_menu_catalog_v2';

    public static function ttlSeconds(): int
    {
        return max(30, (int) config('pos.menu_catalog_ttl_seconds', 180));
    }

    /**
     * @param  callable(): Collection  $callback
     * @return Collection<int, \App\Models\Product>
     */
    public static function remember(callable $callback): Collection
    {
        try {
            $cached = Cache::store('file')->remember(self::KEY, self::ttlSeconds(), function () use ($callback) {
                $value = $callback();

                return $value instanceof Collection ? $value->values()->all() : [];
            });

            return collect($cached);
        } catch (\Throwable) {
            $value = $callback();

            return $value instanceof Collection ? $value : collect($value);
        }
    }

    public static function forget(): void
    {
        try {
            Cache::store('file')->forget(self::KEY);
        } catch (\Throwable) {
            // cache opsional
        }
    }
}
