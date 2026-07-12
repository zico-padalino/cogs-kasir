<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryLot;
use App\Models\MaterialStockLog;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Services\MaterialStockLogService;
use App\Support\Format;
use App\Support\MaterialUnits;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(InventoryCostService $inventoryService)
    {
        $materials = Product::query()
            ->where('type', ProductType::RawMaterial->value)
            ->where('is_active', true)
            ->with(['inventoryLots' => fn ($q) => $q->orderByDesc('received_at')])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($inventoryService) {
                $product->available_qty = $product->availableQuantity();
                $product->avg_cost = $inventoryService->getWeightedAverageCost($product);

                return $product;
            });

        $stockLogs = collect();
        $historyPeriod = 'day';
        $historyDate = now()->toDateString();

        if (Schema::hasTable('material_stock_logs')) {
            $stockLogs = $this->queryStockLogs('day', $historyDate);
        }

        return view('materials.index', [
            'materials' => $materials,
            'stockLogs' => $stockLogs,
            'historyPeriod' => $historyPeriod,
            'historyDate' => $historyDate,
            'historyUrl' => route('materials.history'),
            'format' => Format::class,
            'unitPresets' => MaterialUnits::presets(),
        ]);
    }

    public function history(Request $request)
    {
        if (! Schema::hasTable('material_stock_logs')) {
            return response()->json([
                'period' => $request->input('period', 'day'),
                'date' => $request->input('date', now()->toDateString()),
                'label' => 'Belum ada tabel riwayat',
                'count' => 0,
                'items' => [],
            ]);
        }

        $validated = $request->validate([
            'period' => ['nullable', 'in:day,month'],
            'date' => ['nullable', 'string'],
        ]);

        $period = $validated['period'] ?? 'day';
        $date = $validated['date'] ?? ($period === 'month' ? now()->format('Y-m') : now()->toDateString());

        try {
            $logs = $this->queryStockLogs($period, $date);
        } catch (\Throwable) {
            return response()->json([
                'period' => $period,
                'date' => $date,
                'label' => 'Tanggal tidak valid',
                'count' => 0,
                'items' => [],
            ], 422);
        }

        return response()->json([
            'period' => $period,
            'date' => $date,
            'label' => $this->historyLabel($period, $date),
            'count' => $logs->count(),
            'items' => $logs->map(fn (MaterialStockLog $log) => [
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'action_badge' => $log->actionBadgeClass(),
                'product_name' => $log->product_name,
                'product_unit' => $log->product_unit,
                'quantity_before' => $log->quantity_before !== null ? Format::number($log->quantity_before, 2) : null,
                'quantity_after' => $log->quantity_after !== null ? Format::number($log->quantity_after, 2) : null,
                'quantity_delta' => $log->quantity_delta !== null ? (float) $log->quantity_delta : null,
                'quantity_delta_label' => $log->quantity_delta !== null
                    ? ((float) $log->quantity_delta > 0 ? '+' : '').Format::number($log->quantity_delta, 2)
                    : null,
                'unit_cost' => $log->unit_cost !== null ? Format::rupiah($log->unit_cost) : null,
                'lot_number' => $log->lot_number,
                'note' => $log->note,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
                'created_at_iso' => $log->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    private function queryStockLogs(string $period, string $date)
    {
        $query = MaterialStockLog::query()->with('user')->latest();

        if ($period === 'month') {
            $month = Carbon::createFromFormat('Y-m', strlen($date) === 7 ? $date : Carbon::parse($date)->format('Y-m'))->startOfMonth();
            $query->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
        } else {
            $day = Carbon::parse($date)->startOfDay();
            $query->whereDate('created_at', $day->toDateString());
        }

        return $query->limit(200)->get();
    }

    private function historyLabel(string $period, string $date): string
    {
        if ($period === 'month') {
            $month = Carbon::createFromFormat('Y-m', strlen($date) === 7 ? $date : Carbon::parse($date)->format('Y-m'));

            return $month->translatedFormat('F Y');
        }

        $day = Carbon::parse($date);

        if ($day->isToday()) {
            return 'Hari ini · '.$day->translatedFormat('d M Y');
        }

        return $day->translatedFormat('d M Y');
    }

    public function storeMaterial(
        Request $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_preset' => ['required', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required'],
        ]);

        $unit = MaterialUnits::resolve($validated['unit_preset'], $validated['unit_custom'] ?? '');

        if ($unit === '') {
            return back()->withErrors(['unit_custom' => 'Isi satuan bahan.'])->withInput();
        }

        $product = Product::create([
            'sku' => $this->generateMaterialSku($validated['name']),
            'name' => $validated['name'],
            'type' => ProductType::RawMaterial,
            'unit' => $unit,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
        ]);

        $qty = (float) $validated['quantity'];
        $unitCost = Format::parseRupiah($validated['unit_cost']);

        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $qty,
            unitCost: $unitCost,
        );

        $logService->log(
            action: 'create',
            product: $product,
            quantityBefore: 0,
            quantityAfter: $qty,
            unitCost: $unitCost,
            lot: $lot,
            note: 'Bahan baru + stok awal',
        );

        return redirect()->route('materials.index')
            ->with('success', "Bahan {$product->name} ditambahkan beserta stok awal.");
    }

    public function receive(
        StoreInventoryReceiptRequest $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $product = Product::findOrFail($request->product_id);
        $before = $product->availableQuantity();
        $qty = (float) $request->quantity;
        $unitCost = (float) $request->unit_cost;

        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $qty,
            unitCost: $unitCost,
            lotNumber: $request->lot_number,
        );

        $logService->log(
            action: 'receive',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $before + $qty,
            unitCost: $unitCost,
            lot: $lot,
            note: $request->lot_number ? 'Batch '.$request->lot_number : 'Stok masuk',
        );

        return redirect()->route('materials.index')
            ->with('success', "Stok {$product->name} bertambah.");
    }

    public function adjust(
        Request $request,
        Product $product,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa diubah stok sisanya di sini.');
        }

        $validated = $request->validate([
            'quantity_remaining' => ['required', 'numeric', 'min:0'],
        ]);

        $target = (float) $validated['quantity_remaining'];
        $before = $product->availableQuantity();

        $inventoryService->syncAvailableQuantity($product, $target);

        $after = $product->fresh()->availableQuantity();
        $unit = $product->unit;

        $logService->log(
            action: 'adjust',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $after,
            note: 'Stock opname stok sisa',
        );

        return redirect()->route('materials.index')->with(
            'success',
            sprintf(
                'Stok sisa %s diperbarui: %s → %s %s.',
                $product->name,
                Format::number($before, 2),
                Format::number($after, 2),
                $unit,
            ),
        );
    }

    public function update(
        Request $request,
        InventoryLot $lot,
        MaterialStockLogService $logService,
    ) {
        $validated = $request->validate([
            'lot_number' => ['nullable', 'string', 'max:255'],
            'quantity_remaining' => ['required', 'numeric', 'min:0', 'max:'.$lot->quantity_received],
            'unit_cost' => ['required'],
        ]);

        $product = $lot->product;
        $beforeRemaining = (float) $lot->quantity_remaining;
        $productBefore = $product->availableQuantity();
        $newRemaining = (float) $validated['quantity_remaining'];
        $unitCost = Format::parseRupiah($validated['unit_cost']);

        $lot->update([
            'lot_number' => $validated['lot_number'] ?: null,
            'quantity_remaining' => $newRemaining,
            'unit_cost' => $unitCost,
        ]);

        $productAfter = $productBefore - $beforeRemaining + $newRemaining;

        $logService->log(
            action: 'update',
            product: $product,
            quantityBefore: $productBefore,
            quantityAfter: $productAfter,
            unitCost: $unitCost,
            lot: $lot->fresh(),
            note: sprintf(
                'Batch sisa %s → %s',
                Format::number($beforeRemaining, 2),
                Format::number($newRemaining, 2),
            ),
        );

        return redirect()->route('materials.index')->with('success', 'Batch stok berhasil diperbarui.');
    }

    public function destroy(InventoryLot $lot, MaterialStockLogService $logService)
    {
        if ((float) $lot->quantity_remaining < (float) $lot->quantity_received) {
            return redirect()->route('materials.index')->with('error', 'Stok yang sudah dipakai produksi tidak bisa dihapus.');
        }

        $product = $lot->product;
        $before = $product->availableQuantity();
        $removed = (float) $lot->quantity_remaining;
        $lotNumber = $lot->lot_number;
        $unitCost = (float) $lot->unit_cost;

        $lot->delete();

        $logService->log(
            action: 'delete',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $before - $removed,
            unitCost: $unitCost,
            note: $lotNumber ? 'Hapus batch '.$lotNumber : 'Hapus batch stok',
        );

        return redirect()->route('materials.index')->with('success', 'Batch stok dihapus.');
    }

    private function generateMaterialSku(string $name): string
    {
        $base = 'BM-'.strtoupper(Str::slug(Str::limit($name, 24, '')));
        $sku = $base;
        $suffix = 1;

        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }
}
