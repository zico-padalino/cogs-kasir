<?php

namespace App\Http\Resources\Kasir;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PosOrder */
class PosOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_day' => $this->order_day?->toDateString(),
            'source' => $this->source?->value,
            'order_type' => $this->order_type?->value,
            'order_type_label' => $this->order_type?->label(),
            'order_type_icon' => $this->order_type?->icon(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_badge' => $this->status?->badgeClass(),
            'customer_note' => $this->customer_note,
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => (float) $this->discount_amount,
            'has_discount' => $this->hasDiscount(),
            'total' => (float) $this->total,
            'amount_received' => $this->amount_received !== null ? (float) $this->amount_received : null,
            'change_amount' => $this->change_amount !== null ? (float) $this->change_amount : null,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'payment_proof_url' => $this->paymentProofUrl(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cashier_name' => $this->cashierDisplayName(),
            'item_count' => $this->whenLoaded('items', fn () => $this->items->count(), $this->items()->count()),
            'can_checkout' => $this->canCheckoutAtKasir(),
            'is_editable' => $this->isKasirEditable(),
            'is_pay_on_leave' => $this->isPayOnLeave(),
            'table' => $this->whenLoaded('table', fn () => $this->table ? [
                'id' => $this->table->id,
                'table_number' => $this->table->table_number,
                'label' => $this->table->label,
            ] : null),
            'items' => PosOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
