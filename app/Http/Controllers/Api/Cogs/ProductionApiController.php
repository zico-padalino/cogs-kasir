<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\ProductionOrderService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionApiController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = ProductionOrder::with(['product'])
            ->latest()
            ->paginate(15);

        $products = Product::whereIn('type', ['semi_finished', 'finished_good'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Daftar produksi berhasil dimuat.',
            'data' => [
                'orders' => $orders,
                'products' => $products,
            ],
        ]);
    }

    public function store(StoreProductionOrderRequest $request, ProductionOrderService $service): JsonResponse
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

                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return response()->json([
                'message' => 'Produksi tercatat. Modal sudah dihitung — lanjut isi harga jual.',
                'data' => $order->fresh()->load(['product', 'materials.product', 'labors']),
            ], 201);
        }

        return response()->json([
            'message' => 'Produksi dicatat. Buka detail untuk hitung modal.',
            'data' => $order->fresh()->load(['product', 'materials.product', 'labors']),
        ], 201);
    }

    public function show(ProductionOrder $productionOrder): JsonResponse
    {
        $productionOrder->load(['product', 'materials.product', 'labors']);

        $cogs = CogsCalculation::where('reference_type', ProductionOrder::class)
            ->where('reference_id', $productionOrder->id)
            ->first();

        return response()->json([
            'message' => 'Detail produksi berhasil dimuat.',
            'data' => [
                'order' => $productionOrder,
                'cogs' => $cogs,
            ],
        ]);
    }

    public function update(Request $request, ProductionOrder $productionOrder, ProductionOrderService $service): JsonResponse
    {
        if ($productionOrder->status !== ProductionOrderStatus::Draft) {
            return response()->json([
                'message' => 'Hanya jadwal yang belum dimulai yang bisa diedit.',
            ], 422);
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

        return response()->json([
            'message' => 'Jadwal produksi diperbarui.',
            'data' => $productionOrder->fresh()->load(['product', 'materials.product', 'labors']),
        ]);
    }

    public function destroy(ProductionOrder $productionOrder): JsonResponse
    {
        if ($productionOrder->status === ProductionOrderStatus::Completed) {
            return response()->json([
                'message' => 'Produksi yang sudah selesai tidak bisa dihapus. Hapus riwayat biaya terlebih dahulu jika perlu.',
            ], 422);
        }

        $productionOrder->materials()->delete();
        $productionOrder->labors()->delete();
        CogsCalculation::where('reference_type', ProductionOrder::class)
            ->where('reference_id', $productionOrder->id)
            ->delete();
        $productionOrder->delete();

        return response()->json([
            'message' => 'Jadwal produksi dihapus.',
        ]);
    }

    public function start(ProductionOrder $productionOrder, ProductionOrderService $service): JsonResponse
    {
        try {
            $service->start($productionOrder);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Produksi dimulai.',
            'data' => $productionOrder->fresh()->load(['product', 'materials.product', 'labors']),
        ]);
    }

    public function complete(Request $request, ProductionOrder $productionOrder, ProductionOrderService $service): JsonResponse
    {
        $request->validate(['quantity_completed' => ['nullable', 'numeric', 'gt:0']]);

        try {
            $service->complete(
                $productionOrder,
                $request->filled('quantity_completed') ? (float) $request->quantity_completed : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $cogs = CogsCalculation::where('reference_type', ProductionOrder::class)
            ->where('reference_id', $productionOrder->id)
            ->first();

        return response()->json([
            'message' => 'Produksi selesai. Biaya pokok sudah terhitung.',
            'data' => [
                'order' => $productionOrder->fresh()->load(['product', 'materials.product', 'labors']),
                'cogs' => $cogs,
            ],
        ]);
    }
}
