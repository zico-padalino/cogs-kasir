<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRupiahInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryReceiptRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahFields(['unit_cost']);
    }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'lot_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
