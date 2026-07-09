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
            self::DirectMaterial => 'Total biaya bahan',
            self::DirectLabor => 'Total gaji pekerja',
            self::LaborHours => 'Jam kerja',
            self::MachineHours => 'Jam mesin jalan',
            self::UnitsProduced => 'Jumlah produk dibuat',
        };
    }
}
