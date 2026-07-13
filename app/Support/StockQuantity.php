<?php

namespace App\Support;

use InvalidArgumentException;

class StockQuantity
{
    /**
     * Ubah input stok sisa (langsung / konversi satuan / dari berat porsi) ke satuan stok produk.
     *
     * @param  array<string, mixed>  $input
     * @return array{quantity: float, note: string}
     */
    public static function resolveRemaining(array $input, string $stockUnit): array
    {
        $mode = (string) ($input['adjust_mode'] ?? 'direct');
        $stockUnit = MaterialUnits::normalize($stockUnit) ?: trim($stockUnit);

        if ($mode === 'portion') {
            return self::fromPortion($input, $stockUnit);
        }

        $qty = (float) ($input['quantity_remaining'] ?? $input['adjust_qty'] ?? 0);
        $fromUnit = MaterialUnits::normalize($input['adjust_unit'] ?? $stockUnit) ?: $stockUnit;

        if ($qty < 0) {
            throw new InvalidArgumentException('Stok sisa tidak boleh negatif.');
        }

        if ($qty == 0.0) {
            return [
                'quantity' => 0.0,
                'note' => 'stok sisa 0',
            ];
        }

        try {
            $converted = MaterialUnits::convert($qty, $fromUnit, $stockUnit);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        $note = MaterialUnits::normalize($fromUnit) === MaterialUnits::normalize($stockUnit)
            ? sprintf('%s %s', Format::number($converted), MaterialUnits::label($stockUnit))
            : sprintf(
                '%s %s = %s %s',
                Format::number($qty),
                MaterialUnits::label($fromUnit),
                Format::number($converted),
                MaterialUnits::label($stockUnit),
            );

        return [
            'quantity' => $converted,
            'note' => $note,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{quantity: float, note: string}
     */
    private static function fromPortion(array $input, string $stockUnit): array
    {
        $portionSize = (float) ($input['adjust_portion_size'] ?? 0);
        $portionUnit = MaterialUnits::normalize($input['adjust_portion_unit'] ?? 'gr');
        $physicalQty = (float) ($input['adjust_physical_qty'] ?? 0);
        $physicalUnit = MaterialUnits::normalize($input['adjust_physical_unit'] ?? $portionUnit);

        if ($portionSize <= 0) {
            throw new InvalidArgumentException('Isi 1 satuan stok berapa gram/ml.');
        }

        if ($physicalQty < 0) {
            throw new InvalidArgumentException('Sisa fisik tidak boleh negatif.');
        }

        if ($physicalQty == 0.0) {
            return [
                'quantity' => 0.0,
                'note' => sprintf(
                    'sisa fisik 0 · 1 stok = %s %s',
                    Format::number($portionSize),
                    MaterialUnits::label($portionUnit),
                ),
            ];
        }

        $inPortionUnit = MaterialUnits::convert($physicalQty, $physicalUnit, $portionUnit);
        $quantity = MaterialUnits::roundQuantity($inPortionUnit / $portionSize);

        return [
            'quantity' => $quantity,
            'note' => sprintf(
                'sisa %s %s · 1 stok = %s %s → %s %s',
                Format::number($physicalQty),
                MaterialUnits::label($physicalUnit),
                Format::number($portionSize),
                MaterialUnits::label($portionUnit),
                Format::number($quantity),
                MaterialUnits::label($stockUnit),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationRules(string $prefix = ''): array
    {
        $p = $prefix;

        return [
            $p.'adjust_mode' => ['nullable', 'in:direct,portion'],
            $p.'quantity_remaining' => ['nullable', 'numeric', 'min:0', 'required_if:'.$p.'adjust_mode,direct'],
            $p.'adjust_unit' => ['nullable', 'string', 'max:20'],
            $p.'adjust_portion_size' => ['nullable', 'numeric', 'gt:0', 'required_if:'.$p.'adjust_mode,portion'],
            $p.'adjust_portion_unit' => ['nullable', 'string', 'max:20', 'required_if:'.$p.'adjust_mode,portion', 'in:gr,kg,ml,liter'],
            $p.'adjust_physical_qty' => ['nullable', 'numeric', 'min:0', 'required_if:'.$p.'adjust_mode,portion'],
            $p.'adjust_physical_unit' => ['nullable', 'string', 'max:20', 'required_if:'.$p.'adjust_mode,portion', 'in:gr,kg,ml,liter'],
        ];
    }
}
