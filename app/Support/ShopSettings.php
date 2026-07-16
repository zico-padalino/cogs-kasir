<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ShopSettings
{
    public const CACHE_KEY = 'shop_settings.v5';

    public const KEYS = [
        'shop_name',
        'shop_title',
        'logo_path',
        'attendance_enabled',
        'attendance_clock_in',
        'attendance_clock_out',
        'attendance_early_minutes',
        'attendance_latitude',
        'attendance_longitude',
        'attendance_radius_meters',
        'attendance_required_user_ids',
        'attendance_required_employee_ids',
    ];

    public static function defaults(): array
    {
        return [
            'shop_name' => (string) config('pos.shop_name', 'Coffee & Kitchen'),
            'shop_title' => (string) config('pos.shop_title', 'Menu & pesanan dari HP'),
            'logo_path' => null,
            'attendance_enabled' => '1',
            'attendance_clock_in' => '08:00',
            'attendance_clock_out' => '17:00',
            'attendance_early_minutes' => '60',
            'attendance_latitude' => '',
            'attendance_longitude' => '',
            'attendance_radius_meters' => '100',
            'attendance_required_user_ids' => '',
            'attendance_required_employee_ids' => '',
        ];
    }

    public static function all(): array
    {
        $defaults = self::defaults();

        try {
            if (! Schema::hasTable('app_settings')) {
                return $defaults;
            }

            return Cache::remember(self::CACHE_KEY, 3600, function () use ($defaults) {
                $stored = AppSetting::query()
                    ->whereIn('key', self::KEYS)
                    ->pluck('value', 'key')
                    ->all();

                $merged = $defaults;
                foreach (self::KEYS as $key) {
                    if (array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
                        $merged[$key] = $stored[$key];
                    }
                }

                return $merged;
            });
        } catch (Throwable) {
            return $defaults;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            AppSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        self::forgetCache();
        self::applyToConfig();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function applyToConfig(): void
    {
        $settings = self::all();

        config([
            'pos.shop_name' => $settings['shop_name'],
            'pos.shop_title' => $settings['shop_title'],
            'pos.logo_path' => $settings['logo_path'],
        ]);
    }

    public static function logoUrl(?string $path = null): ?string
    {
        $path ??= self::get('logo_path');

        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Favicon / tab icon — pakai logo toko jika ada, else favicon default.
     */
    public static function faviconUrl(): string
    {
        return self::logoUrl() ?: asset('favicon.png');
    }

    public static function appleTouchIconUrl(): string
    {
        return self::logoUrl() ?: asset('icons/apple-touch-icon.png');
    }

    public static function initial(): string
    {
        $name = trim((string) self::get('shop_name', 'P'));

        return mb_strtoupper(mb_substr($name !== '' ? $name : 'P', 0, 1));
    }
}
