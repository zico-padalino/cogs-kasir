<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CogsCalculation extends Model
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'product_id',
        'quantity',
        'direct_material',
        'direct_labor',
        'manufacturing_overhead',
        'total_cogs',
        'unit_cogs',
        'calculation_method',
        'breakdown',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'direct_material' => 'decimal:4',
            'direct_labor' => 'decimal:4',
            'manufacturing_overhead' => 'decimal:4',
            'total_cogs' => 'decimal:4',
            'unit_cogs' => 'decimal:4',
            'breakdown' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
