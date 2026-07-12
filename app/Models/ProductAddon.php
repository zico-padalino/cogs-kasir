<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAddon extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'selling_price',
        'material_product_id',
        'material_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:4',
            'material_quantity' => 'decimal:6',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
