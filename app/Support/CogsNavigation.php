<?php

namespace App\Support;

class CogsNavigation
{
    /**
     * URL untuk masuk modul COGS (halaman kerja terakhir, bukan beranda).
     */
    public static function preferredUrl(): string
    {
        $lastUrl = session('cogs.last_url');

        if (is_string($lastUrl) && self::isCogsPath(parse_url($lastUrl, PHP_URL_PATH) ?? '')) {
            $path = parse_url($lastUrl, PHP_URL_PATH) ?? '';

            if ($path !== '/dashboard') {
                return $lastUrl;
            }
        }

        return route(self::preferredRouteName());
    }

    /**
     * Route name untuk masuk modul COGS (bukan beranda/panduan).
     */
    public static function preferredRouteName(): string
    {
        $last = session('cogs.last_route');

        if (is_string($last) && self::isRememberableRoute($last)) {
            $resolved = self::resolvableRouteName($last);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (SetupProgress::isFullyComplete()) {
            return 'menu-pricing.index';
        }

        $step = SetupProgress::currentStep();

        return $step['route'] ?? 'overhead-rates.index';
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

    private static function isRememberableRoute(string $name): bool
    {
        return self::isCogsRoute($name) && $name !== 'dashboard';
    }

    private static function resolvableRouteName(string $name): ?string
    {
        if (self::routeExists($name)) {
            return $name;
        }

        $fallbacks = [
            'products.show' => 'products.index',
            'products.edit' => 'products.index',
            'products.create' => 'products.index',
            'menu-pos.edit' => 'menu-pos.index',
            'overhead-rates.edit' => 'overhead-rates.index',
            'production-orders.show' => 'production-orders.index',
            'production-orders.edit' => 'production-orders.index',
            'production-orders.create' => 'production-orders.index',
            'cogs.history.show' => 'cogs.history',
            'cogs.result' => 'menu-pricing.index',
            'cogs.calculate' => 'menu-pricing.index',
        ];

        $fallback = $fallbacks[$name] ?? null;

        if ($fallback !== null && self::routeExists($fallback)) {
            return $fallback;
        }

        return null;
    }

    private static function routeExists(string $name): bool
    {
        try {
            route($name);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private static function routePrefixes(): array
    {
        return [
            'materials.',
            'overhead-rates.',
            'products.',
            'production-orders.',
            'menu-pricing.',
            'menu-pos.',
            'menu-categories.',
            'cogs.',
        ];
    }

    /** @return list<string> */
    private static function urlPrefixes(): array
    {
        return [
            '/bahan',
            '/inventory',
            '/overhead-rates',
            '/products',
            '/production-orders',
            '/harga-jual',
            '/menu-pos',
            '/menu-kategori',
            '/cogs',
        ];
    }
}
