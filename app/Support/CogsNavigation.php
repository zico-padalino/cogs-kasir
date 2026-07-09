<?php

namespace App\Support;

class CogsNavigation
{
    /**
     * Route name untuk masuk modul COGS (bukan beranda/panduan).
     */
    public static function preferredRouteName(): string
    {
        $last = session('cogs.last_route');

        if (is_string($last) && self::isCogsRoute($last) && $last !== 'dashboard') {
            return $last;
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

        foreach (['materials.', 'overhead-rates.', 'products.', 'production-orders.', 'menu-pricing.', 'cogs.'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
