<?php

namespace App\Enums;

enum ProductType: string
{
    case RawMaterial = 'raw_material';
    case SemiFinished = 'semi_finished';
    case FinishedGood = 'finished_good';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Bahan Baku',
            self::SemiFinished => 'Bahan Jadi',
            self::FinishedGood => 'Barang Jadi',
        };
    }
}
