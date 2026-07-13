<?php

namespace App\Support;

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
        $mode = ($input['purchase_mode'] ?? 'direct') === 'pack' ? 'pack' : 'direct';

        if ($mode === 'pack') {
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
                    Format::number($packageQty, 2),
                    $packageLabel,
                    Format::number($unitsPerPackage, 2),
                    Format::number($quantity, 2),
                    Format::rupiah($packageCost, 0),
                    $packageLabel,
                ),
                'package_label' => $packageLabel,
            ];
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
            'purchase_mode' => ['required', 'in:direct,pack'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,direct'],
            'unit_cost' => ['nullable', 'required_if:purchase_mode,direct'],
            'package_qty' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,pack'],
            'units_per_package' => ['nullable', 'numeric', 'gt:0', 'required_if:purchase_mode,pack'],
            'package_cost' => ['nullable', 'required_if:purchase_mode,pack'],
            'package_preset' => ['nullable', 'string', 'max:20'],
            'package_custom' => ['nullable', 'string', 'max:20', 'required_if:package_preset,other'],
            'lot_number' => ['nullable', 'string', 'max:100'],
        ];

        if ($requireProductId) {
            $rules['product_id'] = ['required', 'exists:products,id'];
        }

        return $rules;
    }
}
