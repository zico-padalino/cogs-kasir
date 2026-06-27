<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTransaction extends Model
{
    protected $fillable = [
        'invoice_number',
        'product_id',
        'quantity',
        'selling_price',
        'total_revenue',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'selling_price' => 'decimal:4',
            'total_revenue' => 'decimal:4',
            'sold_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
