<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRupiahInput;
use Illuminate\Foundation\Http\FormRequest;

class CalculateCogsRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahFields(['selling_price']);
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
            'consume_inventory' => ['sometimes', 'boolean'],
            'record_sale' => ['sometimes', 'boolean'],
            'invoice_number' => ['required_if:record_sale,true', 'string', 'unique:sales_transactions,invoice_number'],
            'selling_price' => ['required_if:record_sale,true', 'numeric', 'min:0'],
        ];
    }
}
