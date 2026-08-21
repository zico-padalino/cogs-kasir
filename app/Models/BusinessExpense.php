<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    public const CATEGORIES = [
        'operasional' => 'Operasional',
        'bahan_stok' => 'Bahan & stok',
        'gaji' => 'Gaji karyawan',
        'utilitas' => 'Listrik, air & internet',
        'pemasaran' => 'Pemasaran',
        'perawatan' => 'Perawatan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'amount',
        'category',
        'payment_method',
        'note',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'payment_method' => PaymentMethod::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    /** Sumber potongan omzet: gaji atau pengeluaran lain. */
    public function sourceKind(): string
    {
        return $this->category === 'gaji' ? 'gaji' : 'lainnya';
    }

    public function sourceLabel(): string
    {
        return $this->sourceKind() === 'gaji'
            ? 'Potongan dari gaji'
            : 'Potongan lain-lain';
    }

    public function sourceBadgeClass(): string
    {
        return $this->sourceKind() === 'gaji' ? 'badge-violet' : 'badge-slate';
    }
}
