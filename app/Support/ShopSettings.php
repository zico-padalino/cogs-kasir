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
    public const CACHE_KEY = 'shop_settings.v10';

    public const KEYS = [
        'shop_name',
        'shop_title',
        'logo_path',
        'qris_path',
        'attendance_enabled',
        'attendance_clock_in',
        'attendance_clock_out',
        'attendance_early_minutes',
        'attendance_latitude',
        'attendance_longitude',
        'attendance_radius_meters',
        'attendance_required_user_ids',
        'attendance_required_employee_ids',
        'salary_default_deduction',
        'salary_deduction_late',
        'salary_late_after_minutes',
        'salary_deduction_alpha',
        'salary_deduction_izin',
        'salary_deduction_sakit',
    ];

    public static function defaults(): array
    {
        return [
            'shop_name' => (string) config('pos.shop_name', 'Coffee & Kitchen'),
            'shop_title' => (string) config('pos.shop_title', 'Menu & pesanan dari HP'),
            'logo_path' => null,
            'qris_path' => null,
            'attendance_enabled' => '1',
            'attendance_clock_in' => '08:00',
            'attendance_clock_out' => '17:00',
            'attendance_early_minutes' => '60',
            'attendance_latitude' => '',
            'attendance_longitude' => '',
            'attendance_radius_meters' => '100',
            'attendance_required_user_ids' => '',
            'attendance_required_employee_ids' => '',
            'salary_default_deduction' => '0',
            'salary_deduction_late' => '0',
            'salary_late_after_minutes' => '0',
            'salary_deduction_alpha' => '0',
            'salary_deduction_izin' => '0',
            'salary_deduction_sakit' => '0',
        ];
    }

    public static function salaryDefaultDeduction(): float
    {
        return Format::parseRupiah(self::get('salary_default_deduction', '0'));
    }

    /**
     * Tarif potongan gaji (semua opsional; 0 = tidak dipakai).
     *
     * @return array{fixed: float, late: float, late_after_minutes: int, alpha: float, izin: float, sakit: float}
     */
    public static function salaryDeductionRates(): array
    {
        return [
            'fixed' => Format::parseRupiah(self::get('salary_default_deduction', '0')),
            'late' => Format::parseRupiah(self::get('salary_deduction_late', '0')),
            'late_after_minutes' => max(0, (int) self::get('salary_late_after_minutes', '0')),
            'alpha' => Format::parseRupiah(self::get('salary_deduction_alpha', '0')),
            'izin' => Format::parseRupiah(self::get('salary_deduction_izin', '0')),
            'sakit' => Format::parseRupiah(self::get('salary_deduction_sakit', '0')),
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
            'pos.qris_path' => $settings['qris_path'],
        ]);
    }

    public static function logoUrl(?string $path = null): ?string
    {
        $path ??= self::get('logo_path');

        if (! $path) {
            return null;
        }

        return self::publicUploadUrl($path);
    }

    /**
     * URL gambar QRIS pembayaran (kasir web + mobile).
     * Fallback ke public/qris.jpeg jika belum diunggah.
     */
    public static function qrisUrl(?string $path = null): string
    {
        $path ??= self::get('qris_path');

        if ($path) {
            $url = self::publicUploadUrl($path);
            if ($url) {
                return self::withFileVersion($url, $path);
            }
        }

        $fallbackRelative = 'qris.jpeg';
        $fallbackFull = public_path($fallbackRelative);
        $url = asset($fallbackRelative);

        if (is_file($fallbackFull)) {
            return $url.'?v='.filemtime($fallbackFull);
        }

        return $url;
    }

    public static function hasCustomQris(): bool
    {
        $path = self::get('qris_path');

        return is_string($path) && $path !== '';
    }

    /** Simpan logo ke public/uploads/branding (bukan storage symlink). */
    public static function storeLogo(UploadedFile $file): string
    {
        return self::storePublicUpload($file, 'uploads/branding');
    }

    /** Simpan QRIS ke public/uploads/qris. */
    public static function storeQris(UploadedFile $file): string
    {
        return self::storePublicUpload($file, 'uploads/qris');
    }

    public static function deleteLogoFile(?string $path): void
    {
        self::deletePublicUpload($path);
    }

    public static function deleteQrisFile(?string $path): void
    {
        self::deletePublicUpload($path);
    }

    private static function publicUploadUrl(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        // Upload baru: public/uploads (aman di DomaiNesia). Legacy: /storage/...
        $relative = str_starts_with($normalized, 'uploads/')
            ? '/'.$normalized
            : '/storage/'.$normalized;

        // Absolute URL wajib untuk mobile (React Native Image tidak bisa pakai path relatif).
        return url($relative);
    }

    private static function withFileVersion(string $url, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $url;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $full = str_starts_with($normalized, 'uploads/')
            ? public_path($normalized)
            : Storage::disk('public')->path($normalized);

        if (is_file($full)) {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url.$separator.'v='.filemtime($full);
        }

        return $url;
    }

    private static function storePublicUpload(UploadedFile $file, string $directory): string
    {
        $dir = public_path($directory);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $name = Str::uuid()->toString().'.'.$ext;
        $file->move($dir, $name);

        return rtrim($directory, '/').'/'.$name;
    }

    private static function deletePublicUpload(?string $path): void
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
