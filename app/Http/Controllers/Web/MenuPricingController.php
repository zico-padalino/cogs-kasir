<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Http\Request;

class MenuPricingController extends Controller
{
    public function index(ProductHppService $hppService)
    {
        $products = Product::query()
            ->whereIn('type', [ProductType::FinishedGood->value, ProductType::SemiFinished->value])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($hppService) {
                $modal = $hppService->effectiveUnitHpp($product);

                return [
                    'product' => $product,
                    'modal' => $modal,
                    'untung' => $hppService->grossMargin($product),
                    'persen_untung' => $hppService->grossMarginPercent($product),
                ];
            });

        return view('menu-pricing.index', [
            'items' => $products,
            'format' => Format::class,
        ]);
    }

    public function update(Request $request, Product $product, ProductHppService $hppService)
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            abort(404);
        }

        $validated = $request->validate([
            'selling_price' => ['required'],
            'is_menu_item' => ['sometimes', 'boolean'],
        ]);

        $sellingPrice = Format::parseRupiah($validated['selling_price']);

        $product->update([
            'selling_price' => max(0, $sellingPrice),
            'is_menu_item' => $request->boolean('is_menu_item'),
        ]);

        $hppService->markAsMenuItem($product, $request->boolean('is_menu_item'));

        return redirect()->route('menu-pricing.index')
            ->with('success', "Harga {$product->name} sudah disimpan.");
    }
}
