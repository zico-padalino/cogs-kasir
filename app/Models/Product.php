<?php

namespace App\Models;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'type',
        'unit',
        'standard_cost',
        'costing_method',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'costing_method' => CostingMethod::class,
            'standard_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function billOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'parent_product_id');
    }

    public function usedInBillOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'child_product_id');
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function cogsCalculations(): HasMany
    {
        return $this->hasMany(CogsCalculation::class);
    }

    public function availableQuantity(): float
    {
        return (float) $this->inventoryLots()
            ->where('quantity_remaining', '>', 0)
            ->sum('quantity_remaining');
    }
}
