<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kasir\PosOrderResource;
use App\Models\PosOrder;
use App\Models\PosTable;
use App\Support\PosMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = PosOrder::with(['table', 'items.product', 'cashier'])
            ->whereIn('status', ['submitted', 'confirmed', 'paid'])
            ->latest()
            ->paginate(20);

        return PosOrderResource::collection($orders)->response();
    }

    public function show(PosOrder $order): JsonResponse
    {
        $order->load(['items.product', 'table', 'cashier', 'salesTransactions']);

        return response()->json([
            'data' => new PosOrderResource($order),
        ]);
    }
}
