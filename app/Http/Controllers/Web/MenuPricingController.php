<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MenuPricingController extends Controller
{
    public function index(ProductHppService $hppService)
    {
        $products = Product::query()
            ->where('type', ProductType::FinishedGood->value)
            ->where('is_active', true)
            ->latest('id')
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

        // Harga jual boleh 0 agar checklist "Tampilkan di Kasir" tetap bisa disimpan.
        $product->update([
            'selling_price' => max(0, $sellingPrice),
            'is_menu_item' => $request->boolean('is_menu_item'),
        ]);

        $hppService->markAsMenuItem($product, $request->boolean('is_menu_item'));

        $message = $sellingPrice > 0
            ? "Harga {$product->name} sudah disimpan."
            : "Pengaturan {$product->name} sudah disimpan.";

        return redirect()->route('menu-pricing.index')
            ->with('success', $message);
    }

    public function destroy(Product $product, ProductHppService $hppService)
    {
        if ($product->type !== ProductType::FinishedGood) {
            abort(404);
        }

        $product->update([
            'selling_price' => 0,
            'is_menu_item' => false,
        ]);

        $hppService->markAsMenuItem($product, false);

        return redirect()->route('menu-pricing.index')
            ->with('success', "Harga jual {$product->name} sudah dihapus.");
    }
}
