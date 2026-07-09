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

    /** Judul singkat untuk tabel & daftar. */
    public function plainRule(): string
    {
        return match ($this) {
            self::DirectMaterial => 'Tambahan dari harga bahan',
            self::DirectLabor => 'Tambahan dari upah kerja',
            self::LaborHours => 'Per jam kerja',
            self::MachineHours => 'Per jam mesin',
            self::UnitsProduced => 'Per buah produk',
        };
    }

    /** Tampilan nilai yang mudah dibaca (10% atau Rp 25.000/jam). */
    public function formatRate(float $rate): string
    {
        if ($this->isRatioBased()) {
            $percent = round($rate * 100, 2);
            $text = rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');

            return $text.'%';
        }

        if ($this === self::LaborHours || $this === self::MachineHours) {
            return 'Rp '.number_format($rate, 0, ',', '.').' / jam';
        }

        return 'Rp '.number_format($rate, 0, ',', '.').' / produk';
    }
}
