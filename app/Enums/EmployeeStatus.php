<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-green',
            self::Inactive => 'badge-slate',
        };
    }
}
