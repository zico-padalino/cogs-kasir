<?php

namespace App\Http\Controllers\Web\Kasir;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StockWaste;
use App\Services\WasteStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WasteController extends Controller
{
    public function store(Request $request, WasteStockService $wasteService)
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
            $wasteService->record(
                product: $product,
                quantity: (float) $validated['quantity'],
                reason: $validated['reason'],
                note: $validated['note'] ?? null,
                posOrder: $order,
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stok rusak dicatat. Menu ikut tersinkron.');
    }
}
