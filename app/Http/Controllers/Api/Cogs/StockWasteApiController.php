<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StockWaste;
use App\Services\WasteStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockWasteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');
        $date = $request->input('date', now()->toDateString());
        $reason = $request->input('reason');

        $query = StockWaste::query()->with(['product:id,name,unit', 'user:id,name'])->latest();

        if ($period === 'month') {
            $month = strlen((string) $date) >= 7 ? substr((string) $date, 0, 7) : now()->format('Y-m');
            [$y, $m] = array_pad(explode('-', $month), 2, now()->format('m'));
            $query->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $m);
        } else {
            $day = strlen((string) $date) >= 10 ? substr((string) $date, 0, 10) : now()->toDateString();
            $query->whereDate('created_at', $day);
        }

        if ($reason && array_key_exists($reason, StockWaste::REASONS)) {
            $query->where('reason', $reason);
        }

        $wastes = $query->limit(200)->get();

        return response()->json([
            'data' => [
                'reasons' => StockWaste::REASONS,
                'total_qty' => (float) $wastes->sum('quantity'),
                'total_cost' => (float) $wastes->sum('total_cost'),
                'items' => $wastes->map(fn (StockWaste $w) => [
                    'id' => $w->id,
                    'product_id' => $w->product_id,
                    'product_name' => $w->product?->name,
                    'unit' => $w->product?->unit,
                    'quantity' => (float) $w->quantity,
                    'reason' => $w->reason,
                    'reason_label' => $w->reasonLabel(),
                    'total_cost' => (float) $w->total_cost,
                    'note' => $w->note,
                    'created_at' => $w->created_at?->toIso8601String(),
                ])->values(),
                'products' => Product::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'unit', 'type', 'is_menu_item']),
            ],
        ]);
    }

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
            'message' => 'Stok rusak/gagal dicatat.',
            'data' => [
                'id' => $waste->id,
                'product_id' => $waste->product_id,
                'quantity' => (float) $waste->quantity,
                'total_cost' => (float) $waste->total_cost,
                'in_stock' => $product->fresh()->isMenuInStock(),
                'stock_qty' => $product->fresh()->availableQuantity(),
            ],
        ], 201);
    }
}
