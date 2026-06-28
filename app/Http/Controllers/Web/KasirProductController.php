<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKasirProductRequest;
use App\Models\Product;
use App\Support\Format;
use Illuminate\Support\Facades\Storage;

class KasirProductController extends Controller
{
    public function edit(Product $product)
    {
        $this->assertSellable($product);

        return view('kasir.products.edit', [
            'product' => $product,
            'presets' => config('pos.product_presets', []),
            'format' => Format::class,
        ]);
    }

    public function update(UpdateKasirProductRequest $request, Product $product)
    {
        $this->assertSellable($product);

        $data = [
            'description' => $request->input('description'),
            'selling_price' => $request->input('selling_price'),
        ];

        if ($request->boolean('remove_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = null;
        } elseif ($request->hasFile('image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('preset_image')) {
            $this->deleteStoredImage($product);
            $data['image_path'] = $request->input('preset_image');
        }

        $product->update($data);

        return redirect()
            ->route('kasir.index')
            ->with('success', 'Menu "'.$product->name.'" diperbarui.');
    }

    private function assertSellable(Product $product): void
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            abort(403, 'Produk ini tidak bisa diatur dari kasir.');
        }
    }

    private function deleteStoredImage(Product $product): void
    {
        $path = $product->image_path;

        if (! $path || str_starts_with($path, 'images/') || str_starts_with($path, 'http')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
