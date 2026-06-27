<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLot extends Model
{
    protected $fillable = [
        'product_id',
        'lot_number',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'received_at',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:6',
            'quantity_remaining' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
