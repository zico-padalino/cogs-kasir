<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillOfMaterial extends Model
{
    protected $fillable = [
        'parent_product_id',
        'child_product_id',
        'quantity',
        'scrap_percentage',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'scrap_percentage' => 'decimal:4',
        ];
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function childProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }

    public function effectiveQuantity(): float
    {
        $scrapMultiplier = 1 + ((float) $this->scrap_percentage / 100);

        return (float) $this->quantity * $scrapMultiplier;
    }
}
