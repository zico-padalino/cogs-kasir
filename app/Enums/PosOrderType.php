<?php

namespace App\Enums;

enum PosOrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Makan di Tempat',
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
}
