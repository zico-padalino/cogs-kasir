<?php

namespace App\Support;

class Format
{
    public static function rupiah(float|int|string|null $amount, int $decimals = 0): string
    {
        return 'Rp '.number_format((float) $amount, $decimals, ',', '.');
    }

    /**
     * Format angka tampilan.
     * Default: tanpa desimal sia-sia (40 bukan 40,00). Pecahan tetap tampil jika ada (1,5).
     * Kirim $decimals eksplisit jika ingin force jumlah digit (mis. persen).
     */
    public static function number(float|int|string|null $amount, ?int $decimals = null): string
    {
        $value = (float) $amount;

        if ($decimals !== null) {
            return number_format($value, $decimals, ',', '.');
        }

        if (abs($value - round($value)) < 0.0000001) {
            return number_format((float) round($value), 0, ',', '.');
        }

        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',') ?: '0';
    }

    public static function parseRupiah(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $string = trim((string) $value);

        if (preg_match('/^\d+$/', $string)) {
            return (float) $string;
        }

        $string = preg_replace('/[^\d,.-]/', '', $string) ?? '';
        $string = str_replace('.', '', $string);
        $string = str_replace(',', '.', $string);

        return (float) $string;
    }

    /** Nilai untuk input number HTML (titik desimal, tanpa trailing nol). */
    public static function inputNumber(float|int|string|null $amount): string
    {
        $value = (float) $amount;

        if (abs($value - round($value)) < 0.0000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    public static function inputValue(float|int|string|null $amount, int $decimals = 0): string
    {
        return self::number($amount, $decimals);
    }
}
