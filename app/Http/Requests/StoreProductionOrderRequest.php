<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRupiahInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahInArray('labors', ['hourly_rate']);
    }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'quantity_planned' => ['required', 'numeric', 'gt:0'],
            'machine_hours' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'labors' => ['sometimes', 'array'],
            'labors.*.description' => ['required_with:labors', 'string'],
            'labors.*.labor_hours' => ['required_with:labors', 'numeric', 'gt:0'],
            'labors.*.hourly_rate' => ['required_with:labors', 'numeric', 'min:0'],
        ];
    }
}
