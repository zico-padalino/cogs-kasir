<?php

namespace App\Enums;

enum PosOrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine In',
            self::Takeaway => 'Take Away',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DineIn => '🪑',
            self::Takeaway => '🥡',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::DineIn => 'Pelanggan makan di tempat',
            self::Takeaway => 'Bawa pulang / antrian',
        };
    }
}
