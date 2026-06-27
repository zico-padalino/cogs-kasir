<?php

namespace App\Enums;

enum PosOrderSource: string
{
    case Kasir = 'kasir';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Kasir => 'Kasir',
            self::Online => 'Online (Meja)',
        };
    }
}
