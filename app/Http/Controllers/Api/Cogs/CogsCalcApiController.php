<?php

namespace App\Http\Controllers\Api\Cogs;

use App\DTOs\CogsResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateCogsRequest;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Services\BomCostService;
use App\Services\CogsCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CogsCalcApiController extends Controller
{
    public function __construct(
        private readonly CogsCalculationService $cogsCalculationService,
        private readonly BomCostService $bomCostService,
    ) {}

    public function process(CalculateCogsRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $quantity = (float) $request->quantity;

        if ($request->boolean('record_sale')) {
            $result = DB::transaction(function () use ($request, $product, $quantity) {
                $sale = SalesTransaction::create([
                    'invoice_number' => $request->invoice_number,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'selling_price' => $request->selling_price,
                    'total_revenue' => $quantity * $request->selling_price,
                    'sold_at' => now(),
                ]);

                $calculation = $this->cogsCalculationService->recordSaleCogs($sale);
                $grossProfit = (float) $sale->total_revenue - $calculation->totalHpp();

                return compact('sale', 'calculation', 'grossProfit');
            });

            return response()->json([
                'message' => 'Penjualan dan biaya pokok berhasil dicatat.',
                'data' => [
                    'sale' => $result['sale'],
                    'calculation' => $result['calculation'],
                    'gross_profit' => round($result['grossProfit'], 4),
                ],
            ], 201);
        }

        $cogsResult = $this->cogsCalculationService->calculateSaleCogs(
            $product,
            $quantity,
            $request->boolean('consume_inventory', false),
        );

        $preview = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'result' => $cogsResult->toArray(),
        ];

        Cache::put($this->previewCacheKey(), $preview, now()->addHours(2));

        return response()->json([
            'message' => 'Simulasi COGS berhasil.',
            'data' => [
                'product' => $product,
                'quantity' => $quantity,
                'cogs_result' => $cogsResult->toArray(),
            ],
        ]);
    }

    public function result(): JsonResponse
    {
        $preview = Cache::get($this->previewCacheKey());

        if (! is_array($preview) || ! isset($preview['product_id'], $preview['quantity'], $preview['result'])) {
            return response()->json([
                'message' => 'Belum ada simulasi COGS. Jalankan perhitungan terlebih dahulu.',
            ], 404);
        }

        $product = Product::findOrFail($preview['product_id']);
        $result = $preview['result'];

        $cogsResult = new CogsResult(
            directMaterial: (float) $result['direct_material'],
            directLabor: (float) ($result['direct_labor'] ?? 0),
            manufacturingOverhead: (float) $result['manufacturing_overhead'],
            totalHpp: (float) $result['total_hpp'],
            unitHpp: (float) $result['unit_hpp'],
            calculationMethod: (string) $result['calculation_method'],
            breakdown: $result['breakdown'] ?? [],
        );

        return response()->json([
            'message' => 'Hasil simulasi COGS terbaru.',
            'data' => [
                'product' => $product,
                'quantity' => (float) $preview['quantity'],
                'cogs_result' => $cogsResult->toArray(),
                'is_sale' => false,
            ],
        ]);
    }

    public function history(): JsonResponse
    {
        $calculations = CogsCalculation::with('product')
            ->latest('calculated_at')
            ->paginate(15);

        $summary = CogsCalculation::query()
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(total_cogs), 0) as total_cost')
            ->first();

        return response()->json([
            'message' => 'Riwayat perhitungan COGS berhasil dimuat.',
            'data' => [
                'calculations' => $calculations,
                'summary' => $summary,
            ],
        ]);
    }

    public function show(CogsCalculation $calculation): JsonResponse
    {
        $calculation->load('product');

        return response()->json([
            'message' => 'Detail perhitungan COGS berhasil dimuat.',
            'data' => $calculation,
        ]);
    }

    public function destroy(CogsCalculation $calculation): JsonResponse
    {
        $calculation->delete();

        return response()->json([
            'message' => 'Riwayat perhitungan dihapus.',
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

    public function summary(): JsonResponse
    {
        return response()->json([
            'message' => 'Ringkasan COGS berhasil dimuat.',
            'data' => $this->cogsCalculationService->getSummaryReport(),
        ]);
    }

    private function previewCacheKey(): string
    {
        $userId = auth()->id() ?? 'guest';

        return 'cogs_preview_'.$userId;
    }
}
