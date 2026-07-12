<?php

namespace App\Enums;

enum PosOrderStatus: string
{
    case Open = 'open';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Draft',
            self::Submitted => 'Menunggu Kasir',
            self::Confirmed => 'Siap Bayar',
            self::Paid => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge-slate',
            self::Submitted => 'badge-amber',
            self::Confirmed => 'badge-brand',
            self::Paid => 'badge-green',
            self::Cancelled => 'badge-slate',
        };
    }
}
