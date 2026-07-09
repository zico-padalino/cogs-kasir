<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\ProductionOrderService;
use App\Support\Format;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function index()
    {
        $orders = ProductionOrder::with(['product'])
            ->latest()
            ->paginate(15);

        $products = Product::whereIn('type', ['semi_finished', 'finished_good'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('production-orders.index', [
            'orders' => $orders,
            'products' => $products,
        ]);
    }

    public function create()
    {
        return redirect()->route('production-orders.index');
    }

    public function store(StoreProductionOrderRequest $request, ProductionOrderService $service)
    {
        $order = ProductionOrder::create([
            'order_number' => 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'product_id' => $request->product_id,
            'quantity_planned' => $request->quantity_planned,
            'notes' => $request->notes,
        ]);

        $service->createFromBom(
            order: $order,
            labors: $request->labors ?? [],
            machineHours: (float) ($request->machine_hours ?? 0),
        );

        if ($request->boolean('langsung_hitung', true)) {
            try {
                $service->start($order);
                $service->complete($order);
            } catch (\RuntimeException $e) {
                $order->materials()->delete();
                $order->labors()->delete();
                $order->delete();

                return back()->with('error', $e->getMessage())->withInput();
            }

            return redirect()->route('menu-pricing.index')
                ->with('success', 'Produksi tercatat. Modal sudah dihitung — lanjut isi harga jual.');
        }

        return redirect()->route('production-orders.show', $order)->with('success', 'Produksi dicatat. Buka detail untuk hitung modal.');
    }

    public function show(ProductionOrder $productionOrder)
    {
        $productionOrder->load(['product', 'materials.product', 'labors']);

        return view('production-orders.show', [
            'order' => $productionOrder,
            'cogs' => CogsCalculation::where('reference_type', ProductionOrder::class)
                ->where('reference_id', $productionOrder->id)
                ->first(),
            'format' => Format::class,
        ]);
    }

    public function edit(ProductionOrder $productionOrder)
    {
        if ($productionOrder->status !== ProductionOrderStatus::Draft) {
            return redirect()->route('production-orders.show', $productionOrder)
                ->with('error', 'Hanya jadwal yang belum dimulai yang bisa diedit.');
        }

        $products = Product::whereIn('type', ['semi_finished', 'finished_good'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('production-orders.edit', [
            'order' => $productionOrder->load(['labors']),
            'products' => $products,
        ]);
    }

    public function update(Request $request, ProductionOrder $productionOrder, ProductionOrderService $service)
    {
        if ($productionOrder->status !== ProductionOrderStatus::Draft) {
            return back()->with('error', 'Hanya jadwal yang belum dimulai yang bisa diedit.');
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity_planned' => ['required', 'numeric', 'gt:0'],
            'machine_hours' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'labors' => ['nullable', 'array'],
            'labors.*.description' => ['required_with:labors', 'string'],
            'labors.*.labor_hours' => ['required_with:labors', 'numeric', 'min:0'],
            'labors.*.hourly_rate' => ['required_with:labors', 'numeric', 'min:0'],
        ]);

        if (isset($validated['labors'])) {
            foreach ($validated['labors'] as $index => $labor) {
                $validated['labors'][$index]['hourly_rate'] = Format::parseRupiah($labor['hourly_rate']);
            }
        }

        $productionOrder->materials()->delete();
        $productionOrder->labors()->delete();

        $productionOrder->update([
            'product_id' => $validated['product_id'],
            'quantity_planned' => $validated['quantity_planned'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $service->createFromBom(
            order: $productionOrder->fresh(),
            labors: $validated['labors'] ?? [],
            machineHours: (float) ($validated['machine_hours'] ?? 0),
        );

        return redirect()->route('production-orders.show', $productionOrder)->with('success', 'Jadwal produksi diperbarui.');
    }

    public function destroy(ProductionOrder $productionOrder)
    {
        if ($productionOrder->status === ProductionOrderStatus::Completed) {
            return back()->with('error', 'Produksi yang sudah selesai tidak bisa dihapus. Hapus riwayat biaya terlebih dahulu jika perlu.');
        }

        $productionOrder->materials()->delete();
        $productionOrder->labors()->delete();
        CogsCalculation::where('reference_type', ProductionOrder::class)
            ->where('reference_id', $productionOrder->id)
            ->delete();
        $productionOrder->delete();

        return redirect()->route('production-orders.index')->with('success', 'Jadwal produksi dihapus.');
    }

    public function start(ProductionOrder $productionOrder, ProductionOrderService $service)
    {
        try {
            $service->start($productionOrder);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Produksi dimulai.');
    }

    public function complete(Request $request, ProductionOrder $productionOrder, ProductionOrderService $service)
    {
        $request->validate(['quantity_completed' => ['nullable', 'numeric', 'gt:0']]);

        try {
            $service->complete(
                $productionOrder,
                $request->filled('quantity_completed') ? (float) $request->quantity_completed : null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)->with('success', 'Produksi selesai. Biaya pokok sudah terhitung.');
    }
}
