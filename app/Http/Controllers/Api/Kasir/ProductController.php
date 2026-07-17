<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKasirProductRequest;
use App\Http\Resources\Kasir\MenuCategoryResource;
use App\Http\Resources\Kasir\MenuProductResource;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Services\ProductHppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(ProductHppService $productHpp): JsonResponse
    {
        $products = Product::sellable()
            ->with('addons')
            ->orderBy('menu_category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'products' => $products->map(function (Product $product) use ($productHpp) {
                    $base = (new MenuProductResource($product))->resolve();
                    $base['unit_hpp'] = $productHpp->effectiveUnitHpp($product);
                    $base['gross_margin'] = $productHpp->grossMargin($product);
                    $base['margin_percent'] = $productHpp->grossMarginPercent($product);

                    return $base;
                })->values(),
                'menu_categories' => MenuCategory::options(),
            ],
        ]);
    }

    public function show(Product $product, ProductHppService $productHpp): JsonResponse
    {
        $this->assertSellable($product);
        $product->load('addons');

        $base = (new MenuProductResource($product))->resolve();
        $base['unit_hpp'] = $productHpp->effectiveUnitHpp($product);
        $base['gross_margin'] = $productHpp->grossMargin($product);
        $base['margin_percent'] = $productHpp->grossMarginPercent($product);
        $base['presets'] = config('pos.product_presets', []);

        return response()->json([
            'data' => $base,
        ]);
    }

    public function update(UpdateKasirProductRequest $request, Product $product): JsonResponse
    {
        $this->assertSellable($product);

        $data = [
            'description' => $request->input('description'),
            'menu_category' => $request->input('menu_category'),
        ];

        if ($request->boolean('remove_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $this->storeMenuImage($request->file('image'));
        } elseif ($request->filled('preset_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->input('preset_image');
        }

        $product->update($data);
        $product->load('addons');

        return response()->json([
            'message' => 'Menu "'.$product->name.'" diperbarui.',
            'data' => new MenuProductResource($product),
        ]);
    }

    private function assertSellable(Product $product): void
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true) || ! $product->is_menu_item) {
            abort(403, 'Produk ini tidak bisa diatur dari kasir.');
        }
    }

    private function storeMenuImage(UploadedFile $file): string
    {
        $dir = public_path('uploads/menu');
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = Str::uuid()->toString().'.'.$ext;
        $file->move($dir, $name);

        return 'uploads/menu/'.$name;
    }

    private function deleteStoredImage(Product $product): void
    {
        $path = (string) $product->image_path;

        if ($path === '' || str_starts_with($path, 'images/') || str_starts_with($path, 'http')) {
            return;
        }

        $full = public_path($path);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
