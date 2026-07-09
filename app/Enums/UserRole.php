<?php

namespace App\Enums;

enum UserRole: string
{
    case Cogs = 'cogs';
    case Kasir = 'kasir';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Cogs => 'Hitung Biaya',
            self::Kasir => 'Kasir',
            self::Admin => 'Admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Cogs => 'Hitung biaya bahan, produksi, dan harga pokok produk',
            self::Kasir => 'Penjualan & transaksi kasir',
            self::Admin => 'Karyawan, absensi, gaji & pengaturan akun',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cogs => '📊',
            self::Kasir => '🧾',
            self::Admin => '👥',
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Cogs => 'dashboard',
            self::Kasir => 'kasir.index',
            self::Admin => 'admin.dashboard',
        };
    }
}
