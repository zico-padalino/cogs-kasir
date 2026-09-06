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
        $stockQty = round($this->availableQuantity(), 4);
        $stockTracked = $this->isMenuStockTracked();
        $inStock = $this->isMenuInStock();
        $stockMinus = $stockTracked && $stockQty <= 0 && ! $this->is_sold_out;

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
            'stock_qty' => $stockQty,
            'stock_tracked' => $stockTracked,
            'stock_minus' => $stockMinus,
            'in_stock' => $inStock,
            'can_add' => (float) $this->selling_price > 0 && $inStock,
            // is_sold_out = hanya ceklis Habis manual (bukan stok kosong).
            'is_sold_out' => (bool) $this->is_sold_out,
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price' => (float) $addon->selling_price,
            ])->values()),
        ];
    }
}
