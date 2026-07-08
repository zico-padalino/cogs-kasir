<?php

namespace App\Enums;

enum CashLedgerType: string
{
    case FloatIn = 'float_in';
    case SaleIn = 'sale_in';
    case ChangeOut = 'change_out';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::FloatIn => 'Setoran kas',
            self::SaleIn => 'Penjualan tunai',
            self::ChangeOut => 'Kembalian',
            self::Expense => 'Pengeluaran',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::FloatIn, self::SaleIn => 'in',
            self::ChangeOut, self::Expense => 'out',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::FloatIn => 'badge-green',
            self::SaleIn => 'badge-blue',
            self::ChangeOut => 'badge-amber',
            self::Expense => 'badge-slate',
        };
    }

    public function isManual(): bool
    {
        return match ($this) {
            self::FloatIn, self::Expense => true,
            default => false,
        };
    }
}
