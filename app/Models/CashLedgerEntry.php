<?php

namespace App\Models;

use App\Enums\CashLedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashLedgerEntry extends Model
{
    protected $fillable = [
        'type',
        'direction',
        'amount',
        'note',
        'pos_order_id',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashLedgerType::class,
            'amount' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === 'out' ? -$amount : $amount;
    }
}
