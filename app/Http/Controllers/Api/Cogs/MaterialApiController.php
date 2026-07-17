<?php

namespace App\Http\Controllers\Api\Cogs;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryLot;
use App\Models\MaterialStockLog;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Services\MaterialStockLogService;
use App\Services\ProductDeletionService;
use App\Support\Format;
use App\Support\MaterialPurchase;
use App\Support\MaterialUnits;
use App\Support\StockQuantity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MaterialApiController extends Controller
{
    public function index(InventoryCostService $inventoryService): JsonResponse
    {
        $materials = $this->loadMaterials($inventoryService);

        $stockLogs = collect();
        $historyPeriod = 'day';
        $historyDate = now()->toDateString();

        if (Schema::hasTable('material_stock_logs')) {
            $stockLogs = $this->queryStockLogs('day', $historyDate);
        }

        return response()->json([
            'message' => 'Daftar bahan baku berhasil dimuat.',
            'data' => [
                'materials' => $materials,
                'stock_logs' => $stockLogs,
                'history_period' => $historyPeriod,
                'history_date' => $historyDate,
                'unit_presets' => MaterialUnits::presets(),
            ],
        ]);
    }

    public function pdf(InventoryCostService $inventoryService): JsonResponse
    {
        $materials = $this->loadMaterials($inventoryService);
        $totalValue = $materials->sum(fn (Product $m) => (float) $m->available_qty * (float) $m->avg_cost);

        return response()->json([
            'message' => 'Laporan stok bahan baku.',
            'data' => [
                'materials' => $materials,
                'total_value' => $totalValue,
                'item_count' => $materials->count(),
                'in_stock_count' => $materials->filter(fn (Product $m) => (float) $m->available_qty > 0)->count(),
                'shop_name' => config('pos.shop_name', 'Coffee & Kitchen'),
                'printed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if (! Schema::hasTable('material_stock_logs')) {
            return response()->json([
                'message' => 'Belum ada tabel riwayat.',
                'data' => [
                    'period' => $request->input('period', 'day'),
                    'date' => $request->input('date', now()->toDateString()),
                    'label' => 'Belum ada tabel riwayat',
                    'count' => 0,
                    'items' => [],
                ],
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
                'message' => 'Tanggal tidak valid.',
                'data' => [
                    'period' => $period,
                    'date' => $date,
                    'label' => 'Tanggal tidak valid',
                    'count' => 0,
                    'items' => [],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Riwayat stok berhasil dimuat.',
            'data' => [
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
                    'quantity_before' => $log->quantity_before !== null ? Format::number($log->quantity_before) : null,
                    'quantity_after' => $log->quantity_after !== null ? Format::number($log->quantity_after) : null,
                    'quantity_delta' => $log->quantity_delta !== null ? (float) $log->quantity_delta : null,
                    'quantity_delta_label' => $log->quantity_delta !== null
                        ? ((float) $log->quantity_delta > 0 ? '+' : '').Format::number($log->quantity_delta)
                        : null,
                    'unit_cost' => $log->unit_cost !== null ? Format::rupiah($log->unit_cost) : null,
                    'lot_number' => $log->lot_number,
                    'note' => $log->note,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at?->format('d/m/Y H:i'),
                    'created_at_iso' => $log->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function storeMaterial(
        Request $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ): JsonResponse {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'unit_preset' => ['required', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
        ], MaterialPurchase::validationRules()), [
            'package_custom.required_if' => 'Isi nama kemasan jika memilih Lainnya.',
            'units_per_package.required_if' => 'Isi berapa jumlah stok dalam 1 kemasan.',
            'package_qty.required_if' => 'Isi berapa kemasan yang dibeli.',
            'package_cost.required_if' => 'Isi harga per wadah.',
            'direct_total.required_if' => 'Isi harga total pembelian.',
            'purchase_cost.required_if' => 'Isi harga total pembelian.',
        ]);

        $unit = MaterialUnits::resolve($validated['unit_preset'], $validated['unit_custom'] ?? '');

        if ($unit === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'unit_custom' => 'Isi satuan bahan.',
            ]);
        }

        $purchase = MaterialPurchase::resolve($validated);

        if ($purchase['quantity'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => 'Jumlah stok masuk tidak valid.',
            ]);
        }

        if ($purchase['unit_cost'] < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'direct_total' => 'Harga tidak valid.',
            ]);
        }

        $product = Product::create([
            'sku' => $this->generateMaterialSku($validated['name']),
            'name' => $validated['name'],
            'type' => ProductType::RawMaterial,
            'unit' => $unit,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
        ]);

        $qty = $purchase['quantity'];
        $unitCost = $purchase['unit_cost'];

        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $qty,
            unitCost: $unitCost,
        );

        $note = $purchase['note'] !== ''
            ? 'Bahan baru + stok awal · '.$purchase['note']
            : 'Bahan baru + stok awal';

        $logService->log(
            action: 'create',
            product: $product,
            quantityBefore: 0,
            quantityAfter: $qty,
            unitCost: $unitCost,
            lot: $lot,
            note: $note,
        );

        $message = sprintf(
            'Bahan %s ditambahkan. Stok masuk %s %s @ %s/%s.',
            $product->name,
            Format::number($qty),
            $unit,
            Format::rupiah($unitCost, 0),
            $unit,
        );

        return response()->json([
            'message' => $message,
            'data' => $product->fresh()->load('inventoryLots'),
        ], 201);
    }

    public function updateMaterial(
        Request $request,
        Product $product,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ): JsonResponse {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa diubah di sini.');
        }

        $request->merge([
            'purchase_mode' => $request->input('purchase_mode', 'direct'),
        ]);

        $wantsStock = $this->materialEditHasPurchase($request);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'unit_preset' => ['required', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
        ];

        if ($wantsStock) {
            $rules = array_merge($rules, MaterialPurchase::validationRules());
        } else {
            $rules['purchase_mode'] = ['nullable', 'in:direct,pack,portion'];
        }

        $validated = $request->validate($rules, [
            'name.required' => 'Nama bahan wajib diisi.',
            'unit_custom.required_if' => 'Isi satuan bahan jika memilih Lainnya.',
            'package_custom.required_if' => 'Isi nama kemasan jika memilih Lainnya.',
            'units_per_package.required_if' => 'Isi berapa jumlah stok dalam 1 kemasan.',
            'package_qty.required_if' => 'Isi berapa kemasan yang dibeli.',
            'package_cost.required_if' => 'Isi harga per wadah.',
            'direct_total.required_if' => 'Isi harga total pembelian.',
            'purchase_cost.required_if' => 'Isi harga total pembelian.',
        ]);

        $newName = trim($validated['name']);
        $newUnit = MaterialUnits::resolve($validated['unit_preset'], $validated['unit_custom'] ?? '');

        if ($newUnit === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'unit_custom' => 'Isi satuan bahan.',
            ]);
        }

        $oldName = $product->name;
        $oldUnit = (string) $product->unit;
        $changes = [];

        if ($newName !== $oldName) {
            $changes[] = sprintf('nama %s → %s', $oldName, $newName);
        }

        if (MaterialUnits::normalize($newUnit) !== MaterialUnits::normalize($oldUnit)
            && strtolower(trim($newUnit)) !== strtolower(trim($oldUnit))) {
            $changes[] = sprintf(
                'satuan %s → %s',
                MaterialUnits::label($oldUnit),
                MaterialUnits::label($newUnit),
            );
        }

        $product->update([
            'name' => $newName,
            'unit' => $newUnit,
        ]);

        if ($wantsStock) {
            $purchase = MaterialPurchase::resolve($validated);

            if ($purchase['quantity'] <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'Jumlah stok masuk tidak valid.',
                ]);
            }

            if ($purchase['unit_cost'] < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'direct_total' => 'Harga tidak valid.',
                ]);
            }

            $before = $product->availableQuantity();
            $qty = $purchase['quantity'];
            $unitCost = $purchase['unit_cost'];

            $lot = $inventoryService->receiveStock(
                product: $product,
                quantity: $qty,
                unitCost: $unitCost,
                lotNumber: $validated['lot_number'] ?? null,
            );

            $note = $purchase['note'] !== ''
                ? 'Edit bahan + stok · '.$purchase['note']
                : 'Edit bahan + stok masuk';

            $logService->log(
                action: 'receive',
                product: $product,
                quantityBefore: $before,
                quantityAfter: $before + $qty,
                unitCost: $unitCost,
                lot: $lot,
                note: $note,
            );

            $changes[] = sprintf(
                'stok +%s %s @ %s/%s',
                Format::number($qty),
                $newUnit,
                Format::rupiah($unitCost, 0),
                $newUnit,
            );
        }

        $message = $changes === []
            ? 'Data bahan tidak berubah.'
            : 'Bahan diperbarui: '.implode(', ', $changes).'.';

        return response()->json([
            'message' => $message,
            'data' => $product->fresh()->load('inventoryLots'),
        ]);
    }

    public function destroyMaterial(
        Product $product,
        ProductDeletionService $deletionService,
        MaterialStockLogService $logService,
    ): JsonResponse {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa dihapus di sini.');
        }

        if ($reason = $deletionService->canDelete($product)) {
            return response()->json([
                'message' => $reason,
            ], 422);
        }

        $name = $product->name;
        $before = $product->availableQuantity();
        $usedInRecipes = $product->usedInBillOfMaterials()->count();

        if (Schema::hasTable('material_stock_logs')) {
            $logService->log(
                action: 'remove',
                product: $product,
                quantityBefore: $before,
                quantityAfter: 0,
                note: $usedInRecipes > 0
                    ? sprintf('Hapus bahan (dipakai di %d resep)', $usedInRecipes)
                    : 'Hapus bahan',
            );
        }

        $deletionService->delete($product);

        $suffix = $usedInRecipes > 0
            ? sprintf(' Juga dihapus dari %d resep menu.', $usedInRecipes)
            : '';

        return response()->json([
            'message' => sprintf('Bahan %s dihapus.%s', $name, $suffix),
        ]);
    }

    public function receive(
        StoreInventoryReceiptRequest $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ): JsonResponse {
        $product = Product::findOrFail($request->product_id);
        $before = $product->availableQuantity();
        $purchase = MaterialPurchase::resolve($request->validated());

        if ($purchase['quantity'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => 'Jumlah stok masuk tidak valid.',
            ]);
        }

        $qty = $purchase['quantity'];
        $unitCost = $purchase['unit_cost'];

        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $qty,
            unitCost: $unitCost,
            lotNumber: $request->lot_number,
        );

        $noteParts = [];
        if ($request->lot_number) {
            $noteParts[] = 'Batch '.$request->lot_number;
        }
        if ($purchase['note'] !== '') {
            $noteParts[] = $purchase['note'];
        }
        if ($noteParts === []) {
            $noteParts[] = 'Stok masuk';
        }

        $logService->log(
            action: 'receive',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $before + $qty,
            unitCost: $unitCost,
            lot: $lot,
            note: implode(' · ', $noteParts),
        );

        return response()->json([
            'message' => sprintf(
                'Stok %s bertambah %s %s @ %s/%s.',
                $product->name,
                Format::number($qty),
                $product->unit,
                Format::rupiah($unitCost, 0),
                $product->unit,
            ),
            'data' => [
                'product' => $product->fresh()->load('inventoryLots'),
                'lot' => $lot,
            ],
        ], 201);
    }

    public function adjust(
        Request $request,
        Product $product,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ): JsonResponse {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa diubah stok sisanya di sini.');
        }

        $request->merge([
            'adjust_mode' => $request->input('adjust_mode', 'direct'),
        ]);

        $request->validate(StockQuantity::validationRules());

        try {
            $resolved = StockQuantity::resolveRemaining($request->all(), (string) $product->unit);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $target = $resolved['quantity'];
        $before = $product->availableQuantity();

        $inventoryService->syncAvailableQuantity($product, $target);

        $after = $product->fresh()->availableQuantity();
        $unit = $product->unit;

        $logService->log(
            action: 'adjust',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $after,
            note: 'Stock opname stok sisa · '.$resolved['note'],
        );

        return response()->json([
            'message' => sprintf(
                'Stok sisa %s diperbarui: %s → %s %s (%s).',
                $product->name,
                Format::number($before),
                Format::number($after),
                $unit,
                $resolved['note'],
            ),
            'data' => $product->fresh()->load('inventoryLots'),
        ]);
    }

    public function updateLot(
        Request $request,
        InventoryLot $lot,
        MaterialStockLogService $logService,
    ): JsonResponse {
        $request->merge([
            'adjust_mode' => $request->input('adjust_mode', 'direct'),
        ]);

        $validated = $request->validate(array_merge(
            [
                'lot_number' => ['nullable', 'string', 'max:255'],
                'unit_cost' => ['required'],
            ],
            StockQuantity::validationRules(),
        ));

        $product = $lot->product;

        try {
            $resolved = StockQuantity::resolveRemaining($request->all(), (string) $product->unit);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $newRemaining = $resolved['quantity'];
        $maxReceived = (float) $lot->quantity_received;

        if ($newRemaining > $maxReceived + 0.000001) {
            return response()->json([
                'message' => sprintf(
                    'Sisa batch tidak boleh lebih dari jumlah masuk (%s %s).',
                    Format::number($maxReceived),
                    $product->unit,
                ),
            ], 422);
        }

        $beforeRemaining = (float) $lot->quantity_remaining;
        $productBefore = $product->availableQuantity();
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
                'Batch sisa %s → %s (%s)',
                Format::number($beforeRemaining),
                Format::number($newRemaining),
                $resolved['note'],
            ),
        );

        return response()->json([
            'message' => 'Batch stok berhasil diperbarui.',
            'data' => $lot->fresh()->load('product'),
        ]);
    }

    public function destroyLot(InventoryLot $lot, MaterialStockLogService $logService): JsonResponse
    {
        if ((float) $lot->quantity_remaining < (float) $lot->quantity_received) {
            return response()->json([
                'message' => 'Stok yang sudah dipakai produksi tidak bisa dihapus.',
            ], 422);
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

        return response()->json([
            'message' => 'Batch stok dihapus.',
        ]);
    }

    private function loadMaterials(InventoryCostService $inventoryService)
    {
        return Product::query()
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

    private function materialEditHasPurchase(Request $request): bool
    {
        $mode = (string) $request->input('purchase_mode', 'direct');

        return match ($mode) {
            'pack' => $request->filled('package_qty') || $request->filled('units_per_package'),
            'portion' => $request->filled('portion_size') || $request->filled('purchase_qty'),
            default => $request->filled('quantity'),
        };
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
