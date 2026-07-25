<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ShopSettings
{
    public const CACHE_KEY = 'shop_settings.v7';

    public const KEYS = [
        'shop_name',
        'shop_title',
        'logo_path',
        'receipt_paper',
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

    /** @return list<string> */
    public static function receiptPaperOptions(): array
    {
        return ['58mm', '80mm', '58x210mm'];
    }

    public static function defaults(): array
    {
        return [
            'shop_name' => (string) config('pos.shop_name', 'Coffee & Kitchen'),
            'shop_title' => (string) config('pos.shop_title', 'Menu & pesanan dari HP'),
            'logo_path' => null,
            'receipt_paper' => (string) config('pos.thermal.paper', '58mm'),
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
        $paper = self::normalizeReceiptPaper((string) ($settings['receipt_paper'] ?? '58mm'));

        config([
            'pos.shop_name' => $settings['shop_name'],
            'pos.shop_title' => $settings['shop_title'],
            'pos.logo_path' => $settings['logo_path'],
            'pos.thermal.paper' => $paper,
        ]);
    }

    public static function normalizeReceiptPaper(?string $paper): string
    {
        $paper = strtolower(trim((string) $paper));

        return in_array($paper, self::receiptPaperOptions(), true) ? $paper : '58mm';
    }

    /** Lebar ESC/POS: 58x210mm = 58mm (32 kolom), 80mm = 48 kolom. */
    public static function receiptPaperWidth(?string $paper = null): string
    {
        $paper = self::normalizeReceiptPaper($paper ?? (string) self::get('receipt_paper', '58mm'));

        return $paper === '80mm' ? '80mm' : '58mm';
    }

    public static function receiptPaperLabel(?string $paper = null): string
    {
        return match (self::normalizeReceiptPaper($paper ?? (string) self::get('receipt_paper', '58mm'))) {
            '80mm' => '80mm',
            '58x210mm' => '58 × 210mm',
            default => '58mm',
        };
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

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        // Logo baru: public/uploads (aman di DomaiNesia). Legacy: /storage/...
        $relative = str_starts_with($normalized, 'uploads/')
            ? '/'.$normalized
            : '/storage/'.$normalized;

        // Absolute URL wajib untuk mobile (React Native Image tidak bisa pakai path relatif).
        return url($relative);
    }

    /** Simpan logo ke public/uploads/branding (bukan storage symlink). */
    public static function storeLogo(UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $name = Str::uuid()->toString().'.'.$ext;
        $file->move($dir, $name);

        return 'uploads/branding/'.$name;
    }

    public static function deleteLogoFile(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($normalized, 'uploads/')) {
            $full = public_path($normalized);
            if (is_file($full)) {
                @unlink($full);
            }

            return;
        }

        Storage::disk('public')->delete($normalized);
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
