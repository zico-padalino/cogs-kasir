<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateCogsRequest;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Services\BomCostService;
use App\Services\CogsCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CogsController extends Controller
{
    public function __construct(
        private readonly CogsCalculationService $cogsCalculationService,
        private readonly BomCostService $bomCostService,
    ) {}

    public function calculate(CalculateCogsRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $quantity = (float) $request->quantity;
        $consumeInventory = $request->boolean('consume_inventory', true);

        if ($request->boolean('record_sale')) {
            return DB::transaction(function () use ($request, $product, $quantity) {
                $sale = SalesTransaction::create([
                    'invoice_number' => $request->invoice_number,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'selling_price' => $request->selling_price,
                    'total_revenue' => $quantity * $request->selling_price,
                    'sold_at' => now(),
                ]);

                $calculation = $this->cogsCalculationService->recordSaleCogs($sale);
                $grossProfit = (float) $sale->total_revenue - (float) $calculation->total_cogs;

                return response()->json([
                    'message' => 'COGS penjualan berhasil dihitung.',
                    'data' => [
                        'sale' => $sale,
                        'cogs' => $calculation,
                        'gross_profit' => round($grossProfit, 4),
                        'gross_margin_percentage' => $sale->total_revenue > 0
                            ? round(($grossProfit / $sale->total_revenue) * 100, 2)
                            : 0,
                    ],
                ]);
            });
        }

        $result = $this->cogsCalculationService->calculateSaleCogs(
            $product,
            $quantity,
            $consumeInventory,
        );

        return response()->json([
            'message' => 'Simulasi COGS berhasil.',
            'data' => $result->toArray(),
        ]);
    }

    public function rollUp(Product $product): JsonResponse
    {
        $quantity = (float) request('quantity', 1);
        $rollUp = $this->bomCostService->rollUpCost($product, $quantity);

        return response()->json([
            'message' => 'Cost roll-up BOM berhasil.',
            'data' => $rollUp,
        ]);
    }

    public function history(): JsonResponse
    {
        $calculations = CogsCalculation::with('product')
            ->latest('calculated_at')
            ->paginate(20);

        return response()->json($calculations);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => $this->cogsCalculationService->getSummaryReport(),
        ]);
    }
}
