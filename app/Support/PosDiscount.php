<?php

namespace App\Support;

class PosDiscount
{
    public static function amountFor(float $subtotal, ?string $type, float $value): float
    {
        if ($subtotal <= 0 || $value <= 0) {
            return 0.0;
        }

        return match ($type) {
            'amount' => round(min($value, $subtotal), 4),
            'percent' => round($subtotal * (min($value, 100) / 100), 4),
            default => 0.0,
        };
    }

    public static function label(?string $type, float $value): ?string
    {
        return match ($type) {
            'amount' => 'Rp '.number_format($value, 0, ',', '.'),
            'percent' => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%',
            default => null,
        };
    }
}
