<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderLabor extends Model
{
    protected $fillable = [
        'production_order_id',
        'description',
        'labor_hours',
        'hourly_rate',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'labor_hours' => 'decimal:4',
            'hourly_rate' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
