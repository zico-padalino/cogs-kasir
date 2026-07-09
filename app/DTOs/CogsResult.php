<?php

namespace App\DTOs;

class CogsResult
{
    /**
     * HPP dan COGS memakai nilai yang sama — satu formula, dua nama kolom.
     *
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(
        public readonly float $directMaterial,
        public readonly float $directLabor,
        public readonly float $manufacturingOverhead,
        public readonly float $totalHpp,
        public readonly float $unitHpp,
        public readonly string $calculationMethod,
        public readonly array $breakdown = [],
    ) {}

    /** @deprecated Gunakan totalHpp — COGS = HPP */
    public function totalCogs(): float
    {
        return $this->totalHpp;
    }

    /** @deprecated Gunakan unitHpp — COGS = HPP */
    public function unitCogs(): float
    {
        return $this->unitHpp;
    }

    public function toArray(): array
    {
        return [
            'direct_material' => round($this->directMaterial, 4),
            'direct_labor' => round($this->directLabor, 4),
            'manufacturing_overhead' => round($this->manufacturingOverhead, 4),
            'total_hpp' => round($this->totalHpp, 4),
            'unit_hpp' => round($this->unitHpp, 4),
            'total_cogs' => round($this->totalHpp, 4),
            'unit_cogs' => round($this->unitHpp, 4),
            'calculation_method' => $this->calculationMethod,
            'breakdown' => $this->breakdown,
        ];
    }
}
