<?php

namespace App\Support;

use InvalidArgumentException;

class MaterialPurchase
{
    /**
     * Resolve purchase input into stock quantity + unit cost.
     *
     * @param  array<string, mixed>  $input
     * @return array{quantity: float, unit_cost: float, note: string, package_label: ?string}
     */
    public static function resolve(array $input): array
    {
        $mode = $input['purchase_mode'] ?? 'direct';

        if (! in_array($mode, ['direct', 'pack', 'portion'], true)) {
            $mode = 'direct';
        }

        if ($mode === 'pack') {
            return self::resolvePack($input);
        }

        if ($mode === 'portion') {
            return self::resolvePortion($input);
        }

        $quantity = (float) ($input['quantity'] ?? 0);
        $unitCost = Format::parseRupiah($input['unit_cost'] ?? 0);

        return [
            'quantity' => round($quantity, 6),
            'unit_cost' => round($unitCost, 4),
            'note' => '',
            'package_label' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{quantity: float, unit_cost: float, note: string, package_label: ?string}
     */
    private static function resolvePack(array $input): array
    {
        $packageQty = (float) ($input['package_qty'] ?? 0);
        $unitsPerPackage = (float) ($input['units_per_package'] ?? 0);
        $packageCost = Format::parseRupiah($input['package_cost'] ?? 0);
        $packageLabel = self::packageLabel(
            $input['package_preset'] ?? 'dus',
            $input['package_custom'] ?? null,
        );

        if ($packageQty <= 0 || $unitsPerPackage <= 0 || $packageCost < 0) {
            return [
                'quantity' => 0.0,
                'unit_cost' => 0.0,
                'note' => '',
                'package_label' => $packageLabel,
            ];
        }

        $quantity = $packageQty * $unitsPerPackage;
        $unitCost = $packageCost / $unitsPerPackage;

        return [
            'quantity' => round($quantity, 6),
            'unit_cost' => round($unitCost, 4),
            'note' => sprintf(
                '%s %s × %s = %s (harga %s/%s)',
                Format::number($packageQty),
                $packageLabel,
                Format::number($unitsPerPackage),
                Format::number($quantity),
                Format::rupiah($packageCost, 0),
                $packageLabel,
            ),
            'package_label' => $packageLabel,
        ];
    }

    /**
     * Contoh: 1 satuan stok = 250 gram, beli 1 kg → stok 4.
     *
     * @param  array<string, mixed>  $input
     * @return array{quantity: float, unit_cost: float, note: string, package_label: ?string}
     */
    private static function resolvePortion(array $input): array
    {
        $portionSize = (float) ($input['portion_size'] ?? 0);
        $portionUnit = MaterialUnits::normalize($input['portion_unit'] ?? 'gr');
        $purchaseQty = (float) ($input['purchase_qty'] ?? 0);
        $purchaseUnit = MaterialUnits::normalize($input['purchase_unit'] ?? 'kg');
        $purchaseCost = Format::parseRupiah($input['purchase_cost'] ?? 0);

        if ($portionSize <= 0 || $purchaseQty <= 0 || $purchaseCost < 0) {
            return [
                'quantity' => 0.0,
                'unit_cost' => 0.0,
                'note' => '',
                'package_label' => null,
            ];
        }

        try {
            $purchaseFamily = MaterialUnits::family($purchaseUnit);

            // Beli per pcs/buah: 1 pcs = 1 satuan stok (berat porsi hanya keterangan)
            if ($purchaseFamily === 'count') {
                $quantity = $purchaseQty;
            } else {
                $purchaseInPortionUnit = MaterialUnits::convert($purchaseQty, $purchaseUnit, $portionUnit);
                $quantity = $purchaseInPortionUnit / $portionSize;
            }
        } catch (InvalidArgumentException) {
            return [
                'quantity' => 0.0,
                'unit_cost' => 0.0,
                'note' => '',
                'package_label' => null,
            ];
        }

        if ($quantity <= 0) {
            return [
                'quantity' => 0.0,
                'unit_cost' => 0.0,
                'note' => '',
                'package_label' => null,
            ];
        }

        $unitCost = $purchaseCost / $quantity;

        $note = $purchaseFamily === 'count'
            ? sprintf(
                'beli %s %s = stok %s · 1 stok ≈ %s %s (harga %s)',
                Format::number($purchaseQty),
                MaterialUnits::label($purchaseUnit),
                Format::number($quantity),
                Format::number($portionSize),
                MaterialUnits::label($portionUnit),
                Format::rupiah($purchaseCost, 0),
            )
            : sprintf(
                '1 stok = %s %s · beli %s %s = stok %s (harga %s)',
                Format::number($portionSize),
                MaterialUnits::label($portionUnit),
                Format::number($purchaseQty),
                MaterialUnits::label($purchaseUnit),
                Format::number($quantity),
                Format::rupiah($purchaseCost, 0),
            );

        return [
            'quantity' => round($quantity, 6),
            'unit_cost' => round($unitCost, 4),
            'note' => $note,
            'package_label' => null,
        ];
    }

    public static function packageLabel(?string $preset, ?string $custom = null): string
    {
        $preset = trim((string) $preset);

        if ($preset === 'other') {
            $custom = trim((string) $custom);

            return $custom !== '' ? $custom : 'kemasan';
        }

        $allowed = ['dus', 'karton', 'sak', 'pack', 'box', 'bal'];

        if (in_array($preset, $allowed, true)) {
            return $preset;
        }

        $custom = trim((string) $custom);

        return $custom !== '' ? $custom : 'dus';
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(bool $requireProductId = false): array
    {
        $rules = [
            'purchase_mode' => ['required', 'in:direct,pack,portion'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,direct'],
            'unit_cost' => ['nullable', 'required_if:purchase_mode,direct'],
            'package_qty' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,pack'],
            'units_per_package' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,pack'],
            'package_cost' => ['nullable', 'required_if:purchase_mode,pack'],
            'package_preset' => ['nullable', 'string', 'max:20'],
            'package_custom' => ['nullable', 'string', 'max:20', 'required_if:package_preset,other'],
            'portion_size' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,portion'],
            'portion_unit' => ['nullable', 'string', 'max:20', 'required_if:purchase_mode,portion', 'in:gr,kg,ml,liter'],
            'purchase_qty' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,portion'],
            'purchase_unit' => ['nullable', 'string', 'max:20', 'required_if:purchase_mode,portion', 'in:gr,kg,ml,liter,pcs'],
            'purchase_cost' => ['nullable', 'required_if:purchase_mode,portion'],
            'lot_number' => ['nullable', 'string', 'max:100'],
        ];

        if ($requireProductId) {
            $rules['product_id'] = ['required', 'exists:products,id'];
        }

        return $rules;
    }
}
