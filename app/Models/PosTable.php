<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PosTable extends Model
{
    protected $fillable = [
        'table_number',
        'label',
        'barcode_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosTable $table) {
            if (! $table->barcode_token) {
                $table->barcode_token = Str::uuid()->toString();
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    public function activeOrder(): ?PosOrder
    {
        return $this->orders()
            ->whereIn('status', ['open', 'submitted'])
            ->latest('id')
            ->first();
    }

    public function orderUrl(): string
    {
        return url(route('order.table', $this->barcode_token, absolute: false));
    }
}
