<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateCogsRequest;
use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Services\BomCostService;
use App\Services\CogsCalculationService;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

class CogsController extends Controller
{
    public function calculate(BomCostService $bomService)
    {
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('cogs.calculate', [
            'products' => $products,
            'format' => Format::class,
        ]);
    }

    public function process(CalculateCogsRequest $request, CogsCalculationService $cogsService)
    {
        $product = Product::findOrFail($request->product_id);
        $quantity = (float) $request->quantity;

        if ($request->boolean('record_sale')) {
            $result = DB::transaction(function () use ($request, $product, $quantity, $cogsService) {
                $sale = SalesTransaction::create([
                    'invoice_number' => $request->invoice_number,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'selling_price' => $request->selling_price,
                    'total_revenue' => $quantity * $request->selling_price,
                    'sold_at' => now(),
                ]);

                $calculation = $cogsService->recordSaleCogs($sale);
                $grossProfit = (float) $sale->total_revenue - $calculation->totalHpp();

                return compact('sale', 'calculation', 'grossProfit');
            });

            return redirect()->route('cogs.history.show', $result['calculation'])
                ->with('success', 'Penjualan dan biaya pokok berhasil dicatat.');
        }

        $cogsResult = $cogsService->calculateSaleCogs(
            $product,
            $quantity,
            $request->boolean('consume_inventory', false),
        );

        session(['cogs_preview' => [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'result' => $cogsResult->toArray(),
        ]]);

        return redirect()->route('cogs.result');
    }

    public function result()
    {
        $preview = session('cogs_preview');

        if (! is_array($preview) || ! isset($preview['product_id'], $preview['quantity'], $preview['result'])) {
            return redirect()->route('cogs.calculate');
        }

        $product = Product::findOrFail($preview['product_id']);
        $result = $preview['result'];

        return view('cogs.result', [
            'product' => $product,
            'quantity' => (float) $preview['quantity'],
            'cogsResult' => new \App\DTOs\CogsResult(
                directMaterial: (float) $result['direct_material'],
                directLabor: (float) ($result['direct_labor'] ?? 0),
                manufacturingOverhead: (float) $result['manufacturing_overhead'],
                totalHpp: (float) $result['total_hpp'],
                unitHpp: (float) $result['unit_hpp'],
                calculationMethod: (string) $result['calculation_method'],
                breakdown: $result['breakdown'] ?? [],
            ),
            'isSale' => false,
            'format' => Format::class,
        ]);
    }

    public function history()
    {
        $calculations = CogsCalculation::with('product')
            ->latest('calculated_at')
            ->paginate(15);

        $summary = CogsCalculation::query()
            ->selectRaw('COUNT(*) as records, COALESCE(SUM(total_cogs), 0) as total_cost')
            ->first();

        return view('cogs.history', [
            'calculations' => $calculations,
            'summary' => $summary,
            'format' => Format::class,
        ]);
    }

    public function show(CogsCalculation $calculation)
    {
        $calculation->load('product');

        return view('cogs.show', [
            'calculation' => $calculation,
            'format' => Format::class,
        ]);
    }

    public function destroy(CogsCalculation $calculation)
    {
        $calculation->delete();

        return redirect()->route('cogs.history')->with('success', 'Riwayat perhitungan dihapus.');
    }
}
