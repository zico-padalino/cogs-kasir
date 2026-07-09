<?php

namespace App\Enums;

enum OverheadAllocationBase: string
{
    case DirectMaterial = 'direct_material';
    case DirectLabor = 'direct_labor';
    case LaborHours = 'labor_hours';
    case MachineHours = 'machine_hours';
    case UnitsProduced = 'units_produced';

    public function label(): string
    {
        return match ($this) {
            self::DirectMaterial => '% dari harga bahan',
            self::DirectLabor => '% dari upah kerja',
            self::LaborHours => 'Per jam orang kerja',
            self::MachineHours => 'Per jam mesin dipakai',
            self::UnitsProduced => 'Per buah produk',
        };
    }

    /** Nilai ditulis sebagai desimal (0,15 = 15%), bukan rupiah. */
    public function isRatioBased(): bool
    {
        return match ($this) {
            self::DirectMaterial, self::DirectLabor => true,
            default => false,
        };
    }
}
