<?php

namespace App\Enums;

enum SalaryStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Paid => 'Lunas',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-amber',
            self::Paid => 'badge-green',
        };
    }
}
