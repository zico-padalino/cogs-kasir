<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StockWaste;
use App\Services\WasteStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WasteController extends Controller
{
    public function store(Request $request, WasteStockService $wasteService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', Rule::in(array_keys(StockWaste::REASONS))],
            'pos_order_id' => ['nullable', 'exists:pos_orders,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);
        $order = ! empty($validated['pos_order_id'])
            ? PosOrder::query()->find($validated['pos_order_id'])
            : null;

        try {
            $waste = $wasteService->record(
                product: $product,
                quantity: (float) $validated['quantity'],
                reason: $validated['reason'],
                note: $validated['note'] ?? null,
                posOrder: $order,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Stok rusak dicatat. Menu ikut tersinkron.',
            'data' => [
                'id' => $waste->id,
                'product_id' => $product->id,
                'in_stock' => $product->fresh()->isMenuInStock(),
                'stock_qty' => $product->fresh()->availableQuantity(),
            ],
        ], 201);
    }
}
