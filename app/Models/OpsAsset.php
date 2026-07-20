<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsAsset extends Model
{
    protected $fillable = [
        'name',
        'unit',
        'quantity_on_hand',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OpsAssetLog::class)->latest();
    }
}
