<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PosOrderItem */
class PosOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_image_url' => $this->product?->imageUrl(),
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_total' => (float) $this->line_total,
            'notes' => $this->notes,
            'addon_ids' => $this->addon_ids ?? [],
            'is_delivered' => (bool) $this->is_delivered,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
