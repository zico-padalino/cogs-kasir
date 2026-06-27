<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\BillOfMaterial;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with(['billOfMaterials.childProduct'])
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['billOfMaterials.childProduct', 'inventoryLots']);

        return response()->json([
            'data' => array_merge($product->toArray(), [
                'available_quantity' => $product->availableQuantity(),
            ]),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'standard_cost' => ['sometimes', 'numeric', 'min:0'],
            'costing_method' => ['sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->fresh(),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    public function storeBom(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'child_product_id' => ['required', 'exists:products,id', 'not_in:'.$product->id],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'scrap_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ]);

        $bom = BillOfMaterial::updateOrCreate(
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

        $bom->load('childProduct');

        return response()->json([
            'message' => 'BOM berhasil disimpan.',
            'data' => $bom,
        ], 201);
    }
}
