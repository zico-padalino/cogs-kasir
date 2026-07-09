<?php

namespace App\Enums;

enum CostingMethod: string
{
    case Fifo = 'fifo';
    case WeightedAverage = 'weighted_average';
    case Standard = 'standard';

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'Stok lama keluar dulu',
            self::WeightedAverage => 'Rata-rata harga beli',
            self::Standard => 'Harga perkiraan tetap',
        };
    }
}
