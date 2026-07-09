<?php

namespace App\Http\Requests;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\NormalizesRupiahInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahFields(['standard_cost', 'selling_price']);
    }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'unit' => ['sometimes', 'string', 'max:20'],
            'standard_cost' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'costing_method' => ['sometimes', Rule::enum(CostingMethod::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_menu_item' => ['sometimes', 'boolean'],
        ];
    }
}
