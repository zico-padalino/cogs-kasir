<?php

namespace App\Models;

use App\Casts\SafeBackedEnumCast;
use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Support\MenuCatalogCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'type',
        'unit',
        'standard_cost',
        'unit_hpp',
        'selling_price',
        'costing_method',
        'description',
        'menu_category',
        'image_path',
        'is_active',
        'is_menu_item',
        'is_sold_out',
        'hpp_updated_at',
    ];

    protected static function booted(): void
    {
        static::saved(static fn () => MenuCatalogCache::forget());
        static::deleted(static fn () => MenuCatalogCache::forget());
    }

    protected function casts(): array
    {
        return [
            'type' => SafeBackedEnumCast::class.':'.ProductType::class,
            'costing_method' => SafeBackedEnumCast::class.':'.CostingMethod::class,
            'standard_cost' => 'decimal:4',
            'unit_hpp' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'is_active' => 'boolean',
            'is_menu_item' => 'boolean',
            'is_sold_out' => 'boolean',
            'hpp_updated_at' => 'datetime',
        ];
    }

    public function billOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class, 'parent_product_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ProductAddon::class)->orderBy('sort_order')->orderBy('name');
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

    /** @var bool|null */
    private static $inventoryReservationsTableExists = null;

    public static function inventoryReservationsEnabled(): bool
    {
        if (self::$inventoryReservationsTableExists !== null) {
            return self::$inventoryReservationsTableExists;
        }

        try {
            return self::$inventoryReservationsTableExists = \Illuminate\Support\Facades\Schema::hasTable('inventory_reservations');
        } catch (\Throwable) {
            return self::$inventoryReservationsTableExists = false;
        }
    }

    /** Stok fisik di lot (termasuk lot minus / oversell). */
    public function onHandQuantity(): float
    {
        try {
            return (float) $this->inventoryLots()->sum('quantity_remaining');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** Qty yang dibooking open bill (tagihan terbuka). */
    public function reservedQuantity(?int $exceptOrderId = null): float
    {
        try {
            if (! self::inventoryReservationsEnabled()) {
                return 0.0;
            }

            $query = InventoryReservation::query()->where('product_id', $this->id);

            if ($exceptOrderId) {
                $query->where('pos_order_id', '!=', $exceptOrderId);
            }

            return (float) $query->sum('quantity');
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Stok yang masih bisa dijual / dibooking.
     * = on-hand − booking open bill (kecuali order yang dikecualikan).
     * Bisa negatif jika allow_negative_stock aktif dan stok sudah oversell.
     */
    public function availableQuantity(?int $exceptOrderId = null): float
    {
        try {
            $available = $this->onHandQuantity() - $this->reservedQuantity($exceptOrderId);

            if (config('pos.allow_negative_stock', true)) {
                return round($available, 6);
            }

            return max(0.0, $available);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /** Menu dengan lot FG/SF yang pernah ada — stok 0 = Habis di kasir. */
    public function isMenuStockTracked(): bool
    {
        return $this->inventoryLots()->exists();
    }

    /** Stok menu kosong atau minus (bukan ceklis Habis manual). */
    public function isStockNegativeOrEmpty(?int $exceptOrderId = null): bool
    {
        if (! $this->isMenuStockTracked()) {
            return false;
        }

        return $this->availableQuantity($exceptOrderId) <= 0;
    }

    public function isMenuInStock(): bool
    {
        if (! $this->is_menu_item) {
            return true;
        }

        // Ceklis "Habis" dari Kelola Menu (manual) — tetap blokir pesan.
        if ($this->is_sold_out) {
            return false;
        }

        // Stok 0/minus: tetap boleh dipesan (peringatan di kasir/COGS).
        if (config('pos.allow_negative_stock', true)) {
            return true;
        }

        if (! $this->isMenuStockTracked()) {
            return true;
        }

        return $this->availableQuantity() > 0;
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_menu_item', true)
            ->whereIn('type', [ProductType::FinishedGood, ProductType::SemiFinished]);
    }

    public function effectiveUnitHpp(): float
    {
        if ((float) $this->unit_hpp > 0) {
            return (float) $this->unit_hpp;
        }

        return (float) $this->standard_cost;
    }

    public function effectiveType(): ProductType
    {
        $value = $this->type;

        if ($value instanceof ProductType) {
            return $value;
        }

        if (is_string($value)) {
            foreach (ProductType::cases() as $type) {
                if ($type->value === $value) {
                    return $type;
                }
            }
        }

        return ProductType::RawMaterial;
    }

    public function effectiveCostingMethod(): CostingMethod
    {
        $value = $this->costing_method;

        if ($value instanceof CostingMethod) {
            return $value;
        }

        if (is_string($value)) {
            foreach (CostingMethod::cases() as $method) {
                if ($method->value === $value) {
                    return $method;
                }
            }
        }

        return CostingMethod::WeightedAverage;
    }

    public function imageUrl(): string
    {
        if ($this->image_path) {
            if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
                return $this->image_path;
            }

            if (str_starts_with($this->image_path, 'images/')
                || str_starts_with($this->image_path, 'uploads/')) {
                return asset($this->image_path);
            }

            if (Storage::disk('public')->exists($this->image_path)) {
                return Storage::disk('public')->url($this->image_path);
            }

            return asset('storage/'.$this->image_path);
        }

        return asset('images/products/default-food.svg');
    }

    public function hasCustomUpload(): bool
    {
        $path = (string) $this->image_path;

        return $path !== ''
            && ! str_starts_with($path, 'images/')
            && ! str_starts_with($path, 'http');
    }
}
