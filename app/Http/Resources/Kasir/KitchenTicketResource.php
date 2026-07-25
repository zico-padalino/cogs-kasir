<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload tipis untuk poll dapur (APK/web) — hemat CPU di shared hosting.
 *
 * @mixin \App\Models\PosOrder
 */
class KitchenTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->source?->value,
            'order_type' => $this->order_type?->value,
            'order_type_label' => $this->order_type?->label(),
            'order_type_icon' => $this->order_type?->icon(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'customer_note' => $this->customer_note,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_open_bill' => $this->isOpenBill(),
            'can_checklist_delivered' => $this->canChecklistDelivered(),
            'can_mark_served' => $this->canMarkServed(),
            'table' => $this->whenLoaded('table', fn () => $this->table ? [
                'id' => $this->table->id,
                'table_number' => $this->table->table_number,
                'label' => $this->table->label,
            ] : null),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'menu_category' => $item->product?->menu_category,
                    'quantity' => (float) $item->quantity,
                    'notes' => $item->notes,
                    'is_delivered' => (bool) $item->is_delivered,
                    'delivered_at' => $item->delivered_at?->toIso8601String(),
                ])->values()->all();
            }),
        ];
    }
}
