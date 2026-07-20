<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsAssetLog extends Model
{
    protected $fillable = [
        'ops_asset_id',
        'action',
        'quantity',
        'quantity_before',
        'quantity_after',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'quantity_before' => 'decimal:6',
            'quantity_after' => 'decimal:6',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OpsAsset::class, 'ops_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'receive' => 'Tambah stok',
            'damage' => 'Rusak',
            default => ucfirst($this->action),
        };
    }
}
