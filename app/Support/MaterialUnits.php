<?php

namespace App\Support;

class MaterialUnits
{
    /**
     * @return array<string, string> value => label
     */
    public static function presets(): array
    {
        return [
            'kg' => 'kg',
            'gr' => 'gram',
            'liter' => 'liter',
            'ml' => 'ml',
            'pcs' => 'buah',
            'bungkus' => 'bungkus',
            'kaleng' => 'kaleng',
            'ikat' => 'ikat',
        ];
    }

    public static function resolve(?string $preset, ?string $custom = null): string
    {
        $preset = trim((string) $preset);

        if ($preset === 'other') {
            return trim((string) $custom);
        }

        if ($preset !== '' && array_key_exists($preset, self::presets())) {
            return $preset;
        }

        $custom = trim((string) $custom);

        return $custom !== '' ? $custom : 'kg';
    }

    public static function guessPreset(?string $unit): string
    {
        $unit = strtolower(trim((string) $unit));

        if ($unit === '' || array_key_exists($unit, self::presets())) {
            return $unit !== '' ? $unit : 'kg';
        }

        return 'other';
    }
}
