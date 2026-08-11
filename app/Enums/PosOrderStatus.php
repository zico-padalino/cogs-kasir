<?php

namespace App\Enums;

enum PosOrderStatus: string
{
    case Open = 'open';
    /** Online: pelanggan memilih cara bayar — belum masuk antrean kasir. */
    case PendingPayment = 'pending_payment';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Draft',
            self::PendingPayment => 'Menunggu Bayar',
            self::Submitted => 'Menunggu Kasir',
            self::Confirmed => 'Siap Bayar',
            self::Unpaid => 'Tagihan terbuka',
            self::Paid => 'Sudah Bayar',
            self::Served => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge-slate',
            self::PendingPayment => 'badge-amber',
            self::Submitted => 'badge-amber',
            self::Confirmed => 'badge-brand',
            self::Unpaid => 'badge-blue',
            self::Paid => 'badge-brand',
            self::Served => 'badge-green',
            self::Cancelled => 'badge-slate',
        };
    }
}
