<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKasirProductRequest;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Services\ProductHppService;
use App\Support\Format;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KasirProductController extends Controller
{
    public function index()
    {
        $products = Product::sellable()
            ->orderBy('menu_category')
            ->orderBy('name')
            ->get();

        return view('kasir.products.index', [
            'products' => $products,
            'menuCategories' => MenuCategory::options(),
            'format' => Format::class,
            'productHpp' => app(ProductHppService::class),
        ]);
    }

    public function edit(Product $product, ProductHppService $productHpp)
    {
        $this->assertSellable($product);

        return view('kasir.products.edit', [
            'product' => $product,
            'presets' => config('pos.product_presets', []),
            'menuCategories' => MenuCategory::options(),
            'format' => Format::class,
            'unitHpp' => $productHpp->effectiveUnitHpp($product),
            'grossMargin' => $productHpp->grossMargin($product),
            'marginPercent' => $productHpp->grossMarginPercent($product),
        ]);
    }

    public function update(UpdateKasirProductRequest $request, Product $product)
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

        return redirect()
            ->route('kasir.products.edit', $product)
            ->with('success', 'Menu "'.$product->name.'" diperbarui.');
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
        $path = $product->image_path;

        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        if (str_starts_with($path, 'images/')) {
            return;
        }

        if (str_starts_with($path, 'uploads/')) {
            $full = public_path($path);
            if (is_file($full)) {
                @unlink($full);
            }

            return;
        }

        Storage::disk('public')->delete($path);
    }
}
