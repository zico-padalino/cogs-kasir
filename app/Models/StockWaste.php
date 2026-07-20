<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockWaste extends Model
{
    public const REASONS = [
        'rusak' => 'Rusak',
        'gagal' => 'Gagal / reject',
        'kadaluarsa' => 'Kadaluarsa',
        'order' => 'Dari order',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'product_id',
        'quantity',
        'reason',
        'pos_order_id',
        'unit_cost',
        'total_cost',
        'consumption_mode',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posOrder(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
