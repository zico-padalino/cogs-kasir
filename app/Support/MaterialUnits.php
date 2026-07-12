<?php

namespace App\Support;

use InvalidArgumentException;

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

    /**
     * Faktor ke satuan dasar keluarga (kg / liter / pcs).
     *
     * @return array<string, array{family: string, to_base: float}>
     */
    public static function definitions(): array
    {
        return [
            'kg' => ['family' => 'mass', 'to_base' => 1.0],
            'gr' => ['family' => 'mass', 'to_base' => 0.001],
            'gram' => ['family' => 'mass', 'to_base' => 0.001],
            'g' => ['family' => 'mass', 'to_base' => 0.001],
            'liter' => ['family' => 'volume', 'to_base' => 1.0],
            'l' => ['family' => 'volume', 'to_base' => 1.0],
            'lt' => ['family' => 'volume', 'to_base' => 1.0],
            'ml' => ['family' => 'volume', 'to_base' => 0.001],
            'pcs' => ['family' => 'count', 'to_base' => 1.0],
            'buah' => ['family' => 'count', 'to_base' => 1.0],
            'bungkus' => ['family' => 'count', 'to_base' => 1.0],
            'kaleng' => ['family' => 'count', 'to_base' => 1.0],
            'ikat' => ['family' => 'count', 'to_base' => 1.0],
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
        $unit = self::normalize($unit);

        if ($unit === '' || array_key_exists($unit, self::presets())) {
            return $unit !== '' ? $unit : 'kg';
        }

        return 'other';
    }

    public static function normalize(?string $unit): string
    {
        $unit = strtolower(trim((string) $unit));

        return match ($unit) {
            'gram', 'g', 'grs', 'grams' => 'gr',
            'l', 'lt', 'ltr' => 'liter',
            'buah', 'pcs', 'pc', 'piece', 'pieces' => 'pcs',
            default => $unit,
        };
    }

    public static function label(?string $unit): string
    {
        $normalized = self::normalize($unit);
        $presets = self::presets();

        if ($normalized !== '' && array_key_exists($normalized, $presets)) {
            return $presets[$normalized];
        }

        return $unit !== null && trim((string) $unit) !== '' ? trim((string) $unit) : 'kg';
    }

    public static function family(?string $unit): ?string
    {
        $normalized = self::normalize($unit);
        $definitions = self::definitions();

        return $definitions[$normalized]['family'] ?? null;
    }

    /**
     * Satuan yang bisa dipilih saat isi resep, berdasarkan satuan stok bahan.
     *
     * @return array<string, string> value => label
     */
    public static function recipeOptions(?string $stockUnit): array
    {
        $normalized = self::normalize($stockUnit);
        $family = self::family($normalized);
        $presets = self::presets();

        if ($normalized === '' || $family === null) {
            $raw = trim((string) $stockUnit);
            $key = $normalized !== '' ? $normalized : ($raw !== '' ? $raw : 'satuan');

            return [$key => $raw !== '' ? $raw : 'satuan'];
        }

        // Satuan hitung (buah/bungkus/dll) tidak saling dikonversi.
        if ($family === 'count') {
            $key = array_key_exists($normalized, $presets) ? $normalized : $normalized;

            return [$key => self::label($normalized)];
        }

        $options = [];

        foreach ($presets as $value => $label) {
            if (self::family($value) === $family) {
                $options[$value] = $label;
            }
        }

        if (! array_key_exists($normalized, $options)) {
            $options[$normalized] = self::label($normalized);
        }

        return $options;
    }

    /**
     * Konversi jumlah dari satuan input ke satuan stok bahan.
     */
    public static function convert(float $quantity, ?string $fromUnit, ?string $toUnit): float
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah harus lebih dari 0.');
        }

        $from = self::normalize($fromUnit);
        $to = self::normalize($toUnit);

        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Satuan tidak boleh kosong.');
        }

        if ($from === $to) {
            return self::roundQuantity($quantity);
        }

        $definitions = self::definitions();

        if (! isset($definitions[$from], $definitions[$to])) {
            throw new InvalidArgumentException('Satuan tidak dikenali. Pakai satuan yang sama dengan stok bahan.');
        }

        if ($definitions[$from]['family'] !== $definitions[$to]['family']) {
            throw new InvalidArgumentException(
                'Satuan '.self::label($from).' tidak cocok dengan stok '.self::label($to).'.'
            );
        }

        if ($definitions[$from]['family'] === 'count') {
            throw new InvalidArgumentException(
                'Satuan stok bahan adalah '.self::label($to).'. Isi jumlah dengan satuan itu.'
            );
        }

        $inBase = $quantity * $definitions[$from]['to_base'];
        $converted = $inBase / $definitions[$to]['to_base'];

        return self::roundQuantity($converted);
    }

    /**
     * Tampilkan jumlah dengan satuan yang lebih mudah dibaca (mis. 0.05 kg → 50 gram).
     *
     * @return array{quantity: float, unit: string, label: string}
     */
    public static function present(float $quantity, ?string $unit): array
    {
        $normalized = self::normalize($unit);
        $family = self::family($normalized);
        $qty = self::roundQuantity($quantity);

        if ($family === 'mass' && $normalized === 'kg' && $qty > 0 && $qty < 1) {
            return [
                'quantity' => self::roundQuantity($qty * 1000),
                'unit' => 'gr',
                'label' => 'gram',
            ];
        }

        if ($family === 'volume' && $normalized === 'liter' && $qty > 0 && $qty < 1) {
            return [
                'quantity' => self::roundQuantity($qty * 1000),
                'unit' => 'ml',
                'label' => 'ml',
            ];
        }

        return [
            'quantity' => $qty,
            'unit' => $normalized !== '' ? $normalized : (string) $unit,
            'label' => self::label($normalized !== '' ? $normalized : $unit),
        ];
    }

    public static function preferredInputUnit(?string $stockUnit): string
    {
        $options = self::recipeOptions($stockUnit);

        if (isset($options['gr'])) {
            return 'gr';
        }

        if (isset($options['ml'])) {
            return 'ml';
        }

        $normalized = self::normalize($stockUnit);

        if ($normalized !== '' && array_key_exists($normalized, $options)) {
            return $normalized;
        }

        return (string) array_key_first($options);
    }

    public static function roundQuantity(float $quantity): float
    {
        return round($quantity, 6);
    }
}
