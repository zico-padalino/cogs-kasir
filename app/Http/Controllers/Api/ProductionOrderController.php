<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\ProductionOrder;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $productionOrderService,
    ) {}

    public function index(): JsonResponse
    {
        $orders = ProductionOrder::with(['product', 'materials.product', 'labors'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        $order = ProductionOrder::create([
            'order_number' => 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'product_id' => $request->product_id,
            'quantity_planned' => $request->quantity_planned,
            'notes' => $request->notes,
        ]);

        $order = $this->productionOrderService->createFromBom(
            order: $order,
            labors: $request->labors ?? [],
            machineHours: (float) ($request->machine_hours ?? 0),
        );

        return response()->json([
            'message' => 'Production order berhasil dibuat.',
            'data' => $order,
        ], 201);
    }

    public function show(ProductionOrder $productionOrder): JsonResponse
    {
        $productionOrder->load(['product', 'materials.product', 'labors']);

        return response()->json(['data' => $productionOrder]);
    }

    public function start(ProductionOrder $productionOrder): JsonResponse
    {
        $order = $this->productionOrderService->start($productionOrder);

        return response()->json([
            'message' => 'Production order dimulai.',
            'data' => $order,
        ]);
    }

    public function complete(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $validated = $request->validate([
            'quantity_completed' => ['sometimes', 'numeric', 'gt:0'],
        ]);

        $order = $this->productionOrderService->complete(
            $productionOrder,
            isset($validated['quantity_completed']) ? (float) $validated['quantity_completed'] : null,
        );

        return response()->json([
            'message' => 'Production order selesai. COGS telah dihitung.',
            'data' => $order,
        ]);
    }
}
