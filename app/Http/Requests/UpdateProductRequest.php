<?php

namespace App\Http\Requests;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\NormalizesRupiahInput;
use App\Support\MaterialUnits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahFields(['standard_cost', 'selling_price']);

        if ($this->has('unit_preset')) {
            $unit = MaterialUnits::resolveMenu(
                $this->input('unit_preset'),
                $this->input('unit_custom'),
            );

            $this->merge([
                'unit' => $unit !== '' ? $unit : 'pcs',
            ]);
        } elseif (! $this->filled('unit')) {
            $this->merge(['unit' => 'pcs']);
        }
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
            'unit_preset' => ['nullable', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
            'unit' => ['required', 'string', 'max:20'],
            'standard_cost' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'costing_method' => ['sometimes', Rule::enum(CostingMethod::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_menu_item' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_custom.required_if' => 'Isi satuan sendiri jika memilih Lainnya.',
        ];
    }
}
