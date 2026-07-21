<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MenuPricingApiController extends Controller
{
    public function index(ProductHppService $hppService): JsonResponse
    {
        $items = Product::query()
            ->whereIn('type', [ProductType::FinishedGood->value, ProductType::SemiFinished->value])
            ->where('is_active', true)
            ->latest('id')
            ->get()
            ->map(function (Product $product) use ($hppService) {
                return [
                    'product' => $product,
                    'modal' => $hppService->effectiveUnitHpp($product),
                    'untung' => $hppService->grossMargin($product),
                    'persen_untung' => $hppService->grossMarginPercent($product),
                ];
            });

        return response()->json([
            'message' => 'Daftar harga menu berhasil dimuat.',
            'data' => $items,
        ]);
    }

    public function update(Request $request, Product $product, ProductHppService $hppService): JsonResponse
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'selling_price' => ['nullable'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'pricing_mode' => ['nullable', 'in:price,percent'],
            'is_menu_item' => ['sometimes', 'boolean'],
        ]);

        $modal = $hppService->effectiveUnitHpp($product);
        $mode = $validated['pricing_mode'] ?? 'price';
        $sellingPrice = 0.0;

        if ($mode === 'percent') {
            if ($modal <= 0) {
                throw ValidationException::withMessages([
                    'margin_percent' => 'Modal belum terisi. Isi harga jual secara manual, atau lengkapi resep dulu.',
                ]);
            }

            if (! array_key_exists('margin_percent', $validated) || $validated['margin_percent'] === null || $validated['margin_percent'] === '') {
                throw ValidationException::withMessages([
                    'margin_percent' => 'Isi persen untung.',
                ]);
            }

            $percent = min(99.9, max(0, (float) $validated['margin_percent']));
            $sellingPrice = $percent >= 99.9
                ? round($modal * 1000)
                : round($modal / (1 - ($percent / 100)));
        } else {
            $sellingPrice = Format::parseRupiah($validated['selling_price'] ?? 0);
        }

        if ($sellingPrice <= 0) {
            throw ValidationException::withMessages([
                'selling_price' => 'Isi harga jual atau persen untung.',
            ]);
        }

        $product->update([
            'selling_price' => max(0, $sellingPrice),
            'is_menu_item' => $request->boolean('is_menu_item'),
        ]);

        $hppService->markAsMenuItem($product, $request->boolean('is_menu_item'));

        return response()->json([
            'message' => "Harga {$product->name} sudah disimpan.",
            'data' => [
                'product' => $product->fresh(),
                'modal' => $hppService->effectiveUnitHpp($product),
                'untung' => $hppService->grossMargin($product),
                'persen_untung' => $hppService->grossMarginPercent($product),
            ],
        ]);
    }

    public function destroy(Product $product, ProductHppService $hppService): JsonResponse
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            abort(404);
        }

        $product->update([
            'selling_price' => 0,
            'is_menu_item' => false,
        ]);

        $hppService->markAsMenuItem($product, false);

        return response()->json([
            'message' => "Harga jual {$product->name} sudah dihapus.",
            'data' => [
                'product' => $product->fresh(),
                'modal' => $hppService->effectiveUnitHpp($product),
                'untung' => $hppService->grossMargin($product),
                'persen_untung' => $hppService->grossMarginPercent($product),
            ],
        ]);
    }
}
