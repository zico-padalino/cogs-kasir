<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuPricingApiController extends Controller
{
    public function index(ProductHppService $hppService): JsonResponse
    {
        $items = Product::query()
            ->where('type', ProductType::FinishedGood->value)
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
        if ($product->type !== ProductType::FinishedGood) {
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

        // Mode persen hanya dipakai jika modal sudah ada; kalau tidak, jatuh ke harga manual (boleh 0).
        if ($mode === 'percent' && $modal > 0
            && array_key_exists('margin_percent', $validated)
            && $validated['margin_percent'] !== null
            && $validated['margin_percent'] !== '') {
            $percent = min(99.9, max(0, (float) $validated['margin_percent']));
            $sellingPrice = $percent >= 99.9
                ? round($modal * 1000)
                : round($modal / (1 - ($percent / 100)));
        } else {
            $sellingPrice = Format::parseRupiah($validated['selling_price'] ?? 0);
        }

        // Harga jual boleh 0 agar checklist "Tampilkan di Kasir" tetap bisa disimpan.
        $product->update([
            'selling_price' => max(0, $sellingPrice),
            'is_menu_item' => $request->boolean('is_menu_item'),
        ]);

        $hppService->markAsMenuItem($product, $request->boolean('is_menu_item'));

        $message = $sellingPrice > 0
            ? "Harga {$product->name} sudah disimpan."
            : "Pengaturan {$product->name} sudah disimpan.";

        return response()->json([
            'message' => $message,
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
        if ($product->type !== ProductType::FinishedGood) {
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
