<?php

namespace App\DTOs;

class CogsResult
{
    /**
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(
        public readonly float $directMaterial,
        public readonly float $directLabor,
        public readonly float $manufacturingOverhead,
        public readonly float $totalCogs,
        public readonly float $unitCogs,
        public readonly string $calculationMethod,
        public readonly array $breakdown = [],
    ) {}

    public function toArray(): array
    {
        return [
            'direct_material' => round($this->directMaterial, 4),
            'direct_labor' => round($this->directLabor, 4),
            'manufacturing_overhead' => round($this->manufacturingOverhead, 4),
            'total_cogs' => round($this->totalCogs, 4),
            'unit_cogs' => round($this->unitCogs, 4),
            'calculation_method' => $this->calculationMethod,
            'breakdown' => $this->breakdown,
        ];
    }
}
