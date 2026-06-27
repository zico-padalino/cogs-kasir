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
            self::DirectMaterial => 'Bahan Langsung',
            self::DirectLabor => 'Tenaga Kerja Langsung',
            self::LaborHours => 'Jam Kerja',
            self::MachineHours => 'Jam Mesin',
            self::UnitsProduced => 'Unit Produksi',
        };
    }
}
