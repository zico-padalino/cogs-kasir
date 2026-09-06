<?php

namespace App\Models;

use App\Casts\SafeBackedEnumCast;
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
            'allocation_base' => SafeBackedEnumCast::class.':'.OverheadAllocationBase::class,
            'rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function effectiveAllocationBase(): OverheadAllocationBase
    {
        $value = $this->allocation_base;

        if ($value instanceof OverheadAllocationBase) {
            return $value;
        }

        if (is_string($value)) {
            return OverheadAllocationBase::tryFrom($value) ?? OverheadAllocationBase::DirectMaterial;
        }

        return OverheadAllocationBase::DirectMaterial;
    }
}
