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
            self::Fifo => 'FIFO (First In First Out)',
            self::WeightedAverage => 'Rata-rata Tertimbang',
            self::Standard => 'Biaya Standar',
        };
    }
}
