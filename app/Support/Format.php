<?php

namespace App\Support;

class Format
{
    public static function rupiah(float|int|string|null $amount, int $decimals = 0): string
    {
        return 'Rp '.number_format((float) $amount, $decimals, ',', '.');
    }

    public static function number(float|int|string|null $amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals, ',', '.');
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

    public static function inputValue(float|int|string|null $amount, int $decimals = 0): string
    {
        return self::number($amount, $decimals);
    }
}
