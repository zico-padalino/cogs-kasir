<?php

namespace App\Support;

class CogsNavigation
{
    /**
     * URL untuk masuk modul COGS — selalu ke Beranda.
     */
    public static function preferredUrl(): string
    {
        return route(self::preferredRouteName());
    }

    /**
     * Route name untuk masuk modul COGS — selalu ke Beranda.
     */
    public static function preferredRouteName(): string
    {
        return 'dashboard';
    }

    public static function isCogsRoute(?string $name): bool
    {
        if (! $name) {
            return false;
        }

        if ($name === 'dashboard' || $name === 'reset-data.show') {
            return true;
        }

        foreach (self::routePrefixes() as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isCogsPath(string $path): bool
    {
        $path = '/'.trim($path, '/');

        if ($path === '/' || $path === '/dashboard' || $path === '/reset-data') {
            return true;
        }

        foreach (self::urlPrefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function rememberFromRequest(string $routeName, string $url): void
    {
        if ($routeName === 'dashboard') {
            return;
        }

        if (! self::isCogsRoute($routeName)) {
            return;
        }

        session([
            'cogs.last_route' => $routeName,
            'cogs.last_url' => $url,
        ]);
    }

    /** @return list<string> */
    private static function routePrefixes(): array
    {
        return [
            'materials.',
            'bahan-jadi.',
            'overhead-rates.',
            'products.',
            'production-orders.',
            'menu-pricing.',
            'menu-pos.',
            'menu-categories.',
            'cogs.',
            'stock-wastes.',
            'ops-assets.',
        ];
    }

    /** @return list<string> */
    private static function urlPrefixes(): array
    {
        return [
            '/bahan',
            '/bahan-jadi',
            '/inventory',
            '/overhead-rates',
            '/products',
            '/production-orders',
            '/harga-jual',
            '/menu-pos',
            '/menu-kategori',
            '/cogs',
            '/stok-rusak',
            '/inventaris-operasional',
        ];
    }
}
