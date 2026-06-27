<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\Product;
use App\Services\InventoryCostService;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
    ) {}

    public function receive(StoreInventoryReceiptRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);

        $lot = $this->inventoryCostService->receiveStock(
            product: $product,
            quantity: (float) $request->quantity,
            unitCost: (float) $request->unit_cost,
            lotNumber: $request->lot_number,
        );

        return response()->json([
            'message' => 'Stok berhasil diterima.',
            'data' => $lot->load('product'),
        ], 201);
    }

    public function stock(Product $product): JsonResponse
    {
        $lots = $product->inventoryLots()
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->get();

        return response()->json([
            'data' => [
                'product' => $product,
                'available_quantity' => $product->availableQuantity(),
                'weighted_average_cost' => $this->inventoryCostService->getWeightedAverageCost($product),
                'lots' => $lots,
            ],
        ]);
    }
}
