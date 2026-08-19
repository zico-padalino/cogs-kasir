<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache ID katalog menu (file store) — bukan Eloquent model.
 * Model di file cache sering rusak saat unserialize → pluck() crash (HTTP 500).
 */
final class MenuCatalogCache
{
    private const KEY = 'pos:sellable_menu_ids_v3';

    /** @var list<string> */
    private const LEGACY_KEYS = [
        'pos:sellable_menu_catalog_v2',
        'pos:sellable_menu_ids_v3',
    ];

    public static function ttlSeconds(): int
    {
        return max(30, (int) config('pos.menu_catalog_ttl_seconds', 180));
    }

    /**
     * @param  callable(): Collection  $callback  loader penuh (Product + addons)
     * @return Collection<int, Product>
     */
    public static function remember(callable $callback): Collection
    {
        try {
            $cachedIds = Cache::store('file')->get(self::KEY);

            if (is_array($cachedIds) && $cachedIds !== []) {
                $hydrated = self::hydrateOrdered($cachedIds);
                if ($hydrated->isNotEmpty()) {
                    return $hydrated;
                }
            }

            $value = $callback();
            $products = $value instanceof Collection ? $value : collect($value);

            $ids = $products
                ->map(fn ($product) => (int) data_get($product, 'id'))
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($ids !== []) {
                Cache::store('file')->put(self::KEY, $ids, self::ttlSeconds());
            }

            return $products->values();
        } catch (\Throwable) {
            $value = $callback();

            return $value instanceof Collection ? $value : collect($value);
        }
    }

    /**
     * @param  list<int|string>  $ids
     * @return Collection<int, Product>
     */
    private static function hydrateOrdered(array $ids): Collection
    {
        $orderedIds = array_values(array_unique(array_map('intval', $ids)));
        $orderedIds = array_values(array_filter($orderedIds, fn (int $id) => $id > 0));

        if ($orderedIds === []) {
            return collect();
        }

        // Use sellable() scope so disabled / non-menu products are excluded even
        // when their IDs are still present in the cache (e.g. after deactivation).
        $products = Product::sellable()
            ->whereIn('id', $orderedIds)
            ->with(['addons' => fn ($q) => $q->active()->orderBy('sort_order')->orderBy('name')])
            ->get()
            ->keyBy('id');

        return collect($orderedIds)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
    }

    public static function forget(): void
    {
        foreach (self::LEGACY_KEYS as $key) {
            try {
                Cache::store('file')->forget($key);
            } catch (\Throwable) {
                // cache opsional
            }
        }
    }
}
