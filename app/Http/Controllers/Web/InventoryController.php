<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryLot;
use App\Models\InventoryReservation;
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
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        @set_time_limit(60);

        try {
            $validated = $request->validate([
                'q' => ['nullable', 'string', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
                'focus' => ['nullable', 'integer', 'min:1'],
            ]);

            $search = trim((string) ($validated['q'] ?? ''));
            $perPage = (int) ($validated['per_page'] ?? 20);
            $focusId = isset($validated['focus']) ? (int) $validated['focus'] : null;

            $materials = $this->paginateMaterials($search, $perPage, $focusId);
            $minusMaterials = $this->loadMinusMaterials(20);

            $stockLogs = collect();
            $historyPeriod = 'day';
            $historyDate = now()->toDateString();

            if (Schema::hasTable('material_stock_logs')) {
                $stockLogs = $this->queryStockLogs('day', $historyDate);
            }

            return view('materials.index', [
                'materials' => $materials,
                'minusMaterials' => $minusMaterials,
                'searchQuery' => $search,
                'focusId' => $focusId,
                'stockLogs' => $stockLogs,
                'historyPeriod' => $historyPeriod,
                'historyDate' => $historyDate,
                'historyUrl' => route('materials.history'),
                'format' => Format::class,
                'units' => MaterialUnits::class,
                'unitPresets' => MaterialUnits::presets(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->view('errors.timeout', [
                'retryUrl' => route('materials.index'),
                'productsUrl' => route('products.index'),
                'homeUrl' => url('/'),
            ], \App\Support\ServerBusy::isServerBusy($e) ? 504 : 500);
        }
    }

    /**
     * Paket halaman daftar bahan — jangan load seluruh katalog sekaligus.
     */
    private function paginateMaterials(
        string $search,
        int $perPage,
        ?int $focusId,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->where('type', ProductType::RawMaterial->value)
            ->where('is_active', true)
            ->withSum('inventoryLots as lots_sum', 'quantity_remaining')
            ->with(['inventoryLots' => fn ($q) => $q
                ->where('quantity_remaining', '>', 0)
                ->orderByDesc('received_at')
                ->orderByDesc('id')])
            ->orderBy('name');

        if ($focusId) {
            $query->where('id', $focusId);
        } elseif ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('unit', 'like', $like);
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();
        $this->hydrateMaterials($paginator->getCollection());

        return $paginator;
    }

    /**
     * Alert stok minus — hanya ambil bahan yang lot-nya negatif (ringan).
     */
    private function loadMinusMaterials(int $limit = 20): Collection
    {
        $items = Product::query()
            ->where('type', ProductType::RawMaterial->value)
            ->where('is_active', true)
            ->whereRaw(
                '(select coalesce(sum(quantity_remaining), 0) from inventory_lots where inventory_lots.product_id = products.id) < 0'
            )
            ->withSum('inventoryLots as lots_sum', 'quantity_remaining')
            ->orderByRaw(
                '(select coalesce(sum(quantity_remaining), 0) from inventory_lots where inventory_lots.product_id = products.id) asc'
            )
            ->limit($limit)
            ->get(['id', 'name', 'unit', 'type', 'is_active', 'unit_hpp', 'standard_cost']);

        $this->hydrateMaterials($items, loadLots: false);

        return $items->filter(fn (Product $product) => (float) $product->available_qty < 0)->values();
    }

    /**
     * @param  Collection<int, Product>  $materials
     */
    private function hydrateMaterials(Collection $materials, bool $loadLots = true): void
    {
        if ($materials->isEmpty()) {
            return;
        }

        $ids = $materials->pluck('id')->all();
        $reservedByProduct = [];

        if (Product::inventoryReservationsEnabled() && Schema::hasTable('inventory_reservations')) {
            $reservedByProduct = InventoryReservation::query()
                ->whereIn('product_id', $ids)
                ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as qty')
                ->groupBy('product_id')
                ->pluck('qty', 'product_id')
                ->all();
        }

        foreach ($materials as $product) {
            $onHand = $product->getAttribute('lots_sum');
            if ($onHand === null) {
                $onHand = $loadLots
                    ? (float) $product->inventoryLots->sum('quantity_remaining')
                    : (float) $product->inventoryLots()->sum('quantity_remaining');
            }
            $onHand = (float) $onHand;
            $reserved = (float) ($reservedByProduct[$product->id] ?? 0);
            $available = round($onHand - $reserved, 6);

            if (! config('pos.allow_negative_stock', true)) {
                $available = max(0.0, $available);
            }

            $product->available_qty = $available;

            $positiveLots = $loadLots
                ? $product->inventoryLots->where('quantity_remaining', '>', 0)
                : collect();

            $totalQty = (float) $positiveLots->sum('quantity_remaining');
            if ($totalQty > 0) {
                $totalValue = (float) $positiveLots->sum(
                    fn (InventoryLot $lot) => (float) $lot->quantity_remaining * (float) $lot->unit_cost
                );
                $product->avg_cost = $totalValue / $totalQty;
            } else {
                $product->avg_cost = $product->effectiveUnitHpp();
            }

            $product->is_minus = $available < 0;
        }
    }

    public function pdf(Request $request, InventoryCostService $inventoryService)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'autoprint' => ['nullable'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $report = $this->periodStockReport($from, $to, $inventoryService);

        return view('materials.pdf', [
            'items' => $report['items'],
            'totalValue' => $report['total_value'],
            'totalIn' => $report['total_in'],
            'totalOut' => $report['total_out'],
            'itemCount' => $report['count'],
            'inStockCount' => $report['in_stock'],
            'from' => $from,
            'to' => $to,
            'periodLabel' => $from->toDateString() === $to->toDateString()
                ? $from->translatedFormat('d M Y')
                : $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y'),
            'shopName' => config('pos.shop_name', 'Coffee & Kitchen'),
            'printedAt' => now(),
            'format' => Format::class,
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
            return back()->withErrors(['unit_custom' => 'Isi satuan bahan.'])->withInput();
        }

        $purchase = MaterialPurchase::resolve($validated);

        if ($purchase['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'Jumlah stok masuk tidak valid.'])->withInput();
        }

        if ($purchase['unit_cost'] < 0) {
            return back()->withErrors(['direct_total' => 'Harga tidak valid.'])->withInput();
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
            ? 'Bahan baku baru + stok awal · '.$purchase['note']
            : 'Bahan baku baru + stok awal';

        $logService->log(
            action: 'create',
            product: $product,
            quantityBefore: 0,
            quantityAfter: $qty,
            unitCost: $unitCost,
            lot: $lot,
            note: $note,
        );

        $success = sprintf(
            'Bahan baku %s ditambahkan. Stok masuk %s %s @ %s/%s.',
            $product->name,
            Format::number($qty),
            $unit,
            Format::rupiah($unitCost, 0),
            $unit,
        );

        return redirect()->route('materials.index')->with('success', $success);
    }

    public function updateMaterial(
        Request $request,
        Product $product,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa diubah di sini.');
        }

        $request->merge([
            'purchase_mode' => $request->input('purchase_mode', 'direct'),
            'adjust_mode' => $request->input('adjust_mode', 'direct'),
        ]);

        $wantsStock = $this->materialEditHasPurchase($request);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'unit_preset' => ['required', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
        ];

        $rules = array_merge($rules, StockQuantity::validationRules());

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
            return back()->withErrors(['unit_custom' => 'Isi satuan bahan.'])->withInput();
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

        try {
            $resolvedRemaining = StockQuantity::resolveRemaining($request->all(), $newUnit);
            $target = $resolvedRemaining['quantity'];
            $beforeRemaining = $product->availableQuantity();

            if (abs($target - $beforeRemaining) > 0.000001) {
                $inventoryService->syncAvailableQuantity($product, $target);
                $afterRemaining = $product->fresh()->availableQuantity();
                $logService->log(
                    action: 'adjust',
                    product: $product,
                    quantityBefore: $beforeRemaining,
                    quantityAfter: $afterRemaining,
                    note: 'Stock opname stok sisa · '.$resolvedRemaining['note'],
                );
                $changes[] = sprintf(
                    'stok sisa %s → %s %s',
                    Format::number($beforeRemaining),
                    Format::number($afterRemaining),
                    $newUnit,
                );
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($wantsStock) {
            $purchase = MaterialPurchase::resolve($validated);

            if ($purchase['quantity'] <= 0) {
                return back()->withErrors(['quantity' => 'Jumlah stok masuk tidak valid.'])->withInput();
            }

            if ($purchase['unit_cost'] < 0) {
                return back()->withErrors(['direct_total' => 'Harga tidak valid.'])->withInput();
            }

            $before = $product->fresh()->availableQuantity();
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

        if ($changes === []) {
            return redirect()->route('materials.index')->with('success', 'Data bahan tidak berubah.');
        }

        return redirect()->route('materials.index')->with(
            'success',
            'Bahan baku diperbarui: '.implode(', ', $changes).'.',
        );
    }

    public function destroyMaterial(
        Product $product,
        ProductDeletionService $deletionService,
        MaterialStockLogService $logService,
    ) {
        if ($product->type !== ProductType::RawMaterial) {
            abort(403, 'Hanya bahan baku yang bisa dihapus di sini.');
        }

        if ($reason = $deletionService->canDelete($product)) {
            return redirect()->route('materials.index')->with('error', $reason);
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

        return redirect()->route('materials.index')->with(
            'success',
            sprintf('Bahan baku %s dihapus.%s', $name, $suffix),
        );
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

    public function receive(
        StoreInventoryReceiptRequest $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $product = Product::findOrFail($request->product_id);
        $before = $product->availableQuantity();
        $purchase = MaterialPurchase::resolve($request->validated());

        if ($purchase['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'Jumlah stok masuk tidak valid.'])->withInput();
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

        return redirect()->route('materials.index')->with(
            'success',
            sprintf(
                'Stok %s bertambah %s %s @ %s/%s.',
                $product->name,
                Format::number($qty),
                $product->unit,
                Format::rupiah($unitCost, 0),
                $product->unit,
            ),
        );
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

        $request->merge([
            'adjust_mode' => $request->input('adjust_mode', 'direct'),
        ]);

        $request->validate(StockQuantity::validationRules());

        try {
            $resolved = StockQuantity::resolveRemaining($request->all(), (string) $product->unit);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('materials.index')->with('error', $e->getMessage());
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

        return redirect()->route('materials.index')->with(
            'success',
            sprintf(
                'Stok sisa %s diperbarui: %s → %s %s (%s).',
                $product->name,
                Format::number($before),
                Format::number($after),
                $unit,
                $resolved['note'],
            ),
        );
    }

    public function update(
        Request $request,
        InventoryLot $lot,
        MaterialStockLogService $logService,
    ) {
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
            return redirect()->route('materials.index')->with('error', $e->getMessage());
        }

        $newRemaining = $resolved['quantity'];
        $maxReceived = (float) $lot->quantity_received;

        if ($newRemaining > $maxReceived + 0.000001) {
            return redirect()->route('materials.index')->with(
                'error',
                sprintf(
                    'Sisa batch tidak boleh lebih dari jumlah masuk (%s %s).',
                    Format::number($maxReceived),
                    $product->unit,
                ),
            );
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

    /**
     * @return array{
     *     items: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     count: int,
     *     in_stock: int,
     *     total_value: float,
     *     total_in: float,
     *     total_out: float
     * }
     */
    private function periodStockReport(Carbon $from, Carbon $to, InventoryCostService $inventoryService): array
    {
        $materials = Product::query()
            ->where('type', ProductType::RawMaterial)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $logsByProduct = collect();
        if (Schema::hasTable('material_stock_logs') && $materials->isNotEmpty()) {
            $logsByProduct = MaterialStockLog::query()
                ->whereIn('product_id', $materials->pluck('id'))
                ->where('created_at', '<=', $to)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('product_id');
        }

        $toIsCurrent = $to->isSameDay(now()) || $to->isFuture();

        $items = $materials->map(function (Product $product) use ($logsByProduct, $from, $toIsCurrent, $inventoryService) {
            $logs = $logsByProduct->get($product->id, collect());
            $before = $logs->filter(fn (MaterialStockLog $log) => $log->created_at?->lt($from));
            $inPeriod = $logs->filter(fn (MaterialStockLog $log) => $log->created_at && $log->created_at->gte($from));

            $opening = $before->isNotEmpty()
                ? (float) $before->last()->quantity_after
                : (float) ($inPeriod->first()?->quantity_before ?? 0);

            $inQty = 0.0;
            $outQty = 0.0;
            foreach ($inPeriod as $log) {
                $delta = $this->stockLogDelta($log);
                if ($delta > 0) {
                    $inQty += $delta;
                } elseif ($delta < 0) {
                    $outQty += abs($delta);
                }
            }

            if ($inPeriod->isNotEmpty()) {
                $closing = (float) $inPeriod->last()->quantity_after;
            } elseif ($before->isNotEmpty()) {
                $closing = $opening;
            } else {
                $closing = $toIsCurrent ? $product->availableQuantity() : 0.0;
            }

            $avgCost = $inventoryService->getWeightedAverageCost($product);
            $value = round($closing * $avgCost, 4);

            return [
                'name' => $product->name,
                'unit' => $product->unit ?: 'unit',
                'opening' => $opening,
                'in_qty' => $inQty,
                'out_qty' => $outQty,
                'closing' => $closing,
                'avg_cost' => $avgCost,
                'value' => $value,
            ];
        })->values();

        return [
            'items' => $items,
            'count' => $items->count(),
            'in_stock' => $items->filter(fn (array $row) => $row['closing'] > 0)->count(),
            'total_value' => round((float) $items->sum('value'), 4),
            'total_in' => round((float) $items->sum('in_qty'), 4),
            'total_out' => round((float) $items->sum('out_qty'), 4),
        ];
    }

    private function stockLogDelta(MaterialStockLog $log): float
    {
        if ($log->quantity_delta !== null) {
            return (float) $log->quantity_delta;
        }

        if ($log->quantity_before !== null && $log->quantity_after !== null) {
            return round((float) $log->quantity_after - (float) $log->quantity_before, 6);
        }

        return 0.0;
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
