<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    protected $fillable = [
        'order_number',
        'product_id',
        'quantity_planned',
        'quantity_completed',
        'status',
        'machine_hours',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionOrderStatus::class,
            'quantity_planned' => 'decimal:6',
            'quantity_completed' => 'decimal:6',
            'machine_hours' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    public function labors(): HasMany
    {
        return $this->hasMany(ProductionOrderLabor::class);
    }

    public function totalDirectMaterial(): float
    {
        return (float) $this->materials()->sum('total_cost');
    }

    public function totalDirectLabor(): float
    {
        return (float) $this->labors()->sum('total_cost');
    }

    public function totalLaborHours(): float
    {
        return (float) $this->labors()->sum('labor_hours');
    }
}
