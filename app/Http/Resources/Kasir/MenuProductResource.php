<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class MenuProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'menu_category' => $this->menu_category,
            'selling_price' => (float) $this->selling_price,
            'unit_hpp' => (float) $this->effectiveUnitHpp(),
            'image_url' => $this->imageUrl(),
            'image_path' => $this->image_path,
            'is_active' => (bool) $this->is_active,
            'sold_out_manual' => (bool) $this->is_sold_out,
            'stock_qty' => round($this->availableQuantity(), 4),
            'stock_tracked' => $this->isMenuStockTracked(),
            'in_stock' => $this->isMenuInStock(),
            'can_add' => (float) $this->selling_price > 0 && $this->isMenuInStock(),
            'is_sold_out' => (float) $this->selling_price > 0 && ! $this->isMenuInStock(),
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price' => (float) $addon->selling_price,
            ])->values()),
        ];
    }
}
