<?php

namespace App\Services;

use App\DTOs\CogsResult;
use App\Enums\ProductType;
use App\Models\CogsCalculation;
use App\Models\Product;

class ProductHppService
{
    public function effectiveUnitHpp(Product $product): float
    {
        if ((float) $product->unit_hpp > 0) {
            return (float) $product->unit_hpp;
        }

        return (float) $product->standard_cost;
    }

    public function syncFromResult(Product $product, CogsResult $result): void
    {
        if ($result->unitHpp <= 0) {
            return;
        }

        $product->update([
            'unit_hpp' => $result->unitHpp,
            'hpp_updated_at' => now(),
        ]);
    }

    public function syncFromCalculation(Product $product, CogsCalculation $calculation): void
    {
        $unitHpp = (float) ($calculation->unit_hpp ?? $calculation->unit_cogs);

        if ($unitHpp <= 0) {
            return;
        }

        $product->update([
            'unit_hpp' => $unitHpp,
            'hpp_updated_at' => $calculation->calculated_at ?? now(),
        ]);
    }

    public function grossMargin(Product $product): float
    {
        $sellingPrice = (float) $product->selling_price;

        if ($sellingPrice <= 0) {
            return 0;
        }

        return $sellingPrice - $this->effectiveUnitHpp($product);
    }

    public function grossMarginPercent(Product $product): float
    {
        $sellingPrice = (float) $product->selling_price;

        if ($sellingPrice <= 0) {
            return 0;
        }

        return round(($this->grossMargin($product) / $sellingPrice) * 100, 1);
    }

    public function markAsMenuItem(Product $product, bool $isMenuItem = true): void
    {
        if ($isMenuItem && ! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            return;
        }

        $product->update(['is_menu_item' => $isMenuItem]);
    }
}
