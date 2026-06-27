<?php

namespace App\Enums;

enum UserRole: string
{
    case Cogs = 'cogs';
    case Kasir = 'kasir';

    public function label(): string
    {
        return match ($this) {
            self::Cogs => 'COGS',
            self::Kasir => 'Kasir',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Cogs => 'Perhitungan biaya produk & produksi',
            self::Kasir => 'Penjualan & transaksi kasir',
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Cogs => 'dashboard',
            self::Kasir => 'kasir.index',
        };
    }
}
