<?php

namespace App\Models;

use App\Enums\OverheadAllocationBase;
use Illuminate\Database\Eloquent\Model;

class OverheadRate extends Model
{
    protected $fillable = [
        'name',
        'allocation_base',
        'rate',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'allocation_base' => OverheadAllocationBase::class,
            'rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
