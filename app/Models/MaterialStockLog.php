<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockLog extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'product_unit',
        'inventory_lot_id',
        'action',
        'quantity_before',
        'quantity_after',
        'quantity_delta',
        'unit_cost',
        'lot_number',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:6',
            'quantity_after' => 'decimal:6',
            'quantity_delta' => 'decimal:6',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'create' => 'Tambah bahan',
            'receive' => 'Tambah stok',
            'adjust' => 'Ubah stok sisa',
            'update' => 'Edit batch',
            'delete' => 'Hapus batch',
            'sale' => 'Penjualan kasir',
            'production' => 'Produksi',
            'consume' => 'Pemakaian stok',
            default => ucfirst($this->action),
        };
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'create' => 'badge-green',
            'receive' => 'badge-blue',
            'adjust' => 'badge-amber',
            'update' => 'badge-slate',
            'delete' => 'badge-slate',
            'sale' => 'badge-amber',
            'production' => 'badge-blue',
            'consume' => 'badge-amber',
            default => 'badge-slate',
        };
    }
}
