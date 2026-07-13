<?php

namespace App\Services;

use App\Enums\OverheadAllocationBase;
use App\Models\OverheadRate;
use App\Models\ProductionOrder;

class OverheadAllocationService
{
    /**
     * @return array{total: float, details: array<int, array<string, mixed>>}
     */
    public function allocateForProduction(ProductionOrder $order): array
    {
        $directMaterial = $order->totalDirectMaterial();
        $directLabor = $order->totalDirectLabor();
        $laborHours = $order->totalLaborHours();
        $machineHours = (float) $order->machine_hours;
        $unitsProduced = (float) $order->quantity_completed;

        $rates = OverheadRate::where('is_active', true)->get();
        $details = [];
        $total = 0.0;

        foreach ($rates as $rate) {
            $baseValue = match ($rate->allocation_base) {
                OverheadAllocationBase::DirectMaterial => $directMaterial,
                OverheadAllocationBase::DirectLabor => $directLabor,
                OverheadAllocationBase::LaborHours => $laborHours,
                OverheadAllocationBase::MachineHours => $machineHours,
                OverheadAllocationBase::UnitsProduced => $unitsProduced,
            };

            $allocated = $baseValue * (float) $rate->rate;

            $details[] = [
                'overhead_rate_id' => $rate->id,
                'name' => $rate->name,
                'allocation_base' => $rate->allocation_base->value,
                'base_value' => round($baseValue, 4),
                'rate' => (float) $rate->rate,
                'allocated_cost' => round($allocated, 4),
            ];

            $total += $allocated;
        }

        return [
            'total' => round($total, 4),
            'details' => $details,
        ];
    }

    /**
     * @param  list<int>|null  $overheadRateIds  null = semua rate aktif; [] = tanpa biaya lain
     * @return array{total: float, details: array<int, array<string, mixed>>}
     */
    public function allocateForSale(
        float $directMaterial,
        float $directLabor = 0,
        float $laborHours = 0,
        float $machineHours = 0,
        float $units = 1,
        ?array $overheadRateIds = null,
    ): array {
        $query = OverheadRate::query()->where('is_active', true);

        if ($overheadRateIds !== null) {
            $ids = array_values(array_unique(array_map('intval', $overheadRateIds)));
            if ($ids === []) {
                return ['total' => 0.0, 'details' => []];
            }
            $query->whereIn('id', $ids);
        }

        $rates = $query->orderBy('name')->get();
        $details = [];
        $total = 0.0;

        foreach ($rates as $rate) {
            $baseValue = match ($rate->allocation_base) {
                OverheadAllocationBase::DirectMaterial => $directMaterial,
                OverheadAllocationBase::DirectLabor => $directLabor,
                OverheadAllocationBase::LaborHours => $laborHours,
                OverheadAllocationBase::MachineHours => $machineHours,
                OverheadAllocationBase::UnitsProduced => $units,
            };

            $allocated = $baseValue * (float) $rate->rate;

            $details[] = [
                'overhead_rate_id' => $rate->id,
                'name' => $rate->name,
                'description' => $rate->description,
                'allocation_base' => $rate->allocation_base->value,
                'rule_label' => $rate->allocation_base->plainRule(),
                'rate_label' => $rate->allocation_base->formatRate((float) $rate->rate),
                'base_value' => round($baseValue, 4),
                'rate' => (float) $rate->rate,
                'allocated_cost' => round($allocated, 4),
            ];

            $total += $allocated;
        }

        return [
            'total' => round($total, 4),
            'details' => $details,
        ];
    }
}
