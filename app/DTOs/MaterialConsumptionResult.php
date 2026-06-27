<?php

namespace App\DTOs;

class MaterialConsumptionResult
{
    /**
     * @param  array<int, array<string, mixed>>  $lotConsumptions
     */
    public function __construct(
        public readonly float $totalCost,
        public readonly float $averageUnitCost,
        public readonly array $lotConsumptions = [],
    ) {}
}
