<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Services\ProductDeletionService;
use App\Support\Format;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::withCount('billOfMaterials')
            ->latest()
            ->paginate(15);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create', [
            'productTypes' => ProductType::cases(),
            'costingMethods' => CostingMethod::cases(),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        Product::create($request->validated());

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function show(Product $product)
    {
        $product->load(['billOfMaterials.childProduct', 'inventoryLots']);

        $allProducts = Product::where('id', '!=', $product->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('products.show', [
            'product' => $product,
            'allProducts' => $allProducts,
            'format' => Format::class,
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', [
            'product' => $product,
            'productTypes' => ProductType::cases(),
            'costingMethods' => CostingMethod::cases(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product, ProductDeletionService $deletionService)
    {
        try {
            $deletionService->delete($product);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
    }

    public function storeBom(Request $request, Product $product)
    {
        $validated = $request->validate([
            'child_product_id' => ['required', 'exists:products,id', 'not_in:'.$product->id],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        BillOfMaterial::updateOrCreate(
            [
                'parent_product_id' => $product->id,
                'child_product_id' => $validated['child_product_id'],
            ],
            [
                'quantity' => $validated['quantity'],
                'scrap_percentage' => $validated['scrap_percentage'] ?? 0,
                'sequence' => $validated['sequence'] ?? 0,
            ],
        );

        return redirect()->route('products.show', $product)->with('success', 'Bahan resep berhasil ditambahkan.');
    }

    public function updateBom(Request $request, Product $product, BillOfMaterial $bom)
    {
        if ($bom->parent_product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $bom->update($validated);

        return redirect()->route('products.show', $product)->with('success', 'Resep berhasil diperbarui.');
    }

    public function destroyBom(Product $product, BillOfMaterial $bom)
    {
        if ($bom->parent_product_id !== $product->id) {
            abort(404);
        }

        $bom->delete();

        return redirect()->route('products.show', $product)->with('success', 'Bahan resep dihapus.');
    }
}
