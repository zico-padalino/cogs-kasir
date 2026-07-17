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
            'addons' => $this->whenLoaded('addons', fn () => $this->addons->map(fn ($addon) => [
                'id' => $addon->id,
                'name' => $addon->name,
                'price' => (float) $addon->price,
            ])->values()),
        ];
    }
}
