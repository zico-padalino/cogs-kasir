<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRupiahInput;
use App\Support\MaterialPurchase;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryReceiptRequest extends FormRequest
{
    use NormalizesRupiahInput;

    protected function prepareForValidation(): void
    {
        $this->normalizeRupiahFields(['unit_cost', 'package_cost', 'purchase_cost']);

        if (! $this->filled('purchase_mode')) {
            $this->merge(['purchase_mode' => 'direct']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return MaterialPurchase::validationRules(requireProductId: true);
    }

    public function messages(): array
    {
        return [
            'package_custom.required_if' => 'Isi nama kemasan jika memilih Lainnya.',
            'units_per_package.required_if' => 'Isi berapa jumlah stok dalam 1 kemasan.',
            'package_qty.required_if' => 'Isi berapa kemasan yang dibeli.',
            'package_cost.required_if' => 'Isi harga per kemasan.',
        ];
    }
}
