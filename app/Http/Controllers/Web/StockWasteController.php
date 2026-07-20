<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StockWaste;
use App\Services\WasteStockService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockWasteController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', 'day');
        $date = $request->input('date', now()->toDateString());
        $reason = $request->input('reason');

        $query = StockWaste::query()->with(['product', 'user', 'posOrder'])->latest();

        if ($period === 'month') {
            $month = strlen($date) >= 7 ? substr($date, 0, 7) : now()->format('Y-m');
            [$y, $m] = array_pad(explode('-', $month), 2, now()->format('m'));
            $query->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $m);
            $label = 'Bulan '.$month;
        } else {
            $day = strlen($date) >= 10 ? substr($date, 0, 10) : now()->toDateString();
            $query->whereDate('created_at', $day);
            $label = 'Tanggal '.$day;
        }

        if ($reason && array_key_exists($reason, StockWaste::REASONS)) {
            $query->where('reason', $reason);
        }

        $wastes = $query->limit(200)->get();
        $totalQty = (float) $wastes->sum('quantity');
        $totalCost = (float) $wastes->sum('total_cost');

        $activeProducts = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'type', 'is_menu_item']);

        $menuProducts = $activeProducts
            ->filter(fn (Product $p) => in_array($p->type, [ProductType::FinishedGood, ProductType::SemiFinished], true))
            ->values();
        $materials = $activeProducts
            ->filter(fn (Product $p) => $p->type === ProductType::RawMaterial)
            ->values();

        return view('stock-wastes.index', [
            'wastes' => $wastes,
            'menuProducts' => $menuProducts,
            'materials' => $materials,
            'reasons' => StockWaste::REASONS,
            'period' => $period,
            'date' => $date,
            'reason' => $reason,
            'label' => $label,
            'totalQty' => $totalQty,
            'totalCost' => $totalCost,
            'format' => Format::class,
        ]);
    }

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

        return back()->with('success', 'Stok rusak/gagal dicatat. Stok inventori sudah dikurangi.');
    }
}
