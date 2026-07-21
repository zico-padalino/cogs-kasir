<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Services\MaterialStockLogService;
use App\Services\ProductDeletionService;
use App\Support\Format;
use App\Support\MaterialPurchase;
use App\Support\MaterialUnits;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BahanJadiController extends Controller
{
    public function index(InventoryCostService $inventoryService)
    {
        $items = $this->loadItems($inventoryService);

        return view('bahan-jadi.index', [
            'items' => $items,
            'format' => Format::class,
            'units' => MaterialUnits::class,
            'unitPresets' => MaterialUnits::presets(),
        ]);
    }

    public function store(
        Request $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $request->merge([
            'purchase_mode' => $request->input('purchase_mode', 'direct'),
        ]);

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'unit_preset' => ['required', 'string', 'max:20'],
            'unit_custom' => ['nullable', 'string', 'max:20', 'required_if:unit_preset,other'],
        ], MaterialPurchase::validationRules()), [
            'name.required' => 'Nama bahan jadi wajib diisi.',
            'unit_custom.required_if' => 'Isi satuan jika memilih Lainnya.',
            'direct_total.required_if' => 'Isi harga total.',
            'purchase_cost.required_if' => 'Isi harga total.',
        ]);

        $unit = MaterialUnits::resolve($validated['unit_preset'], $validated['unit_custom'] ?? '');
        if ($unit === '') {
            return back()->withErrors(['unit_custom' => 'Isi satuan.'])->withInput();
        }

        $purchase = MaterialPurchase::resolve($validated);
        if ($purchase['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'Jumlah stok tidak valid.'])->withInput();
        }

        $product = Product::create([
            'sku' => $this->generateSku($validated['name']),
            'name' => $validated['name'],
            'type' => ProductType::SemiFinished,
            'unit' => $unit,
            'costing_method' => CostingMethod::WeightedAverage,
            'is_active' => true,
            'is_menu_item' => false,
        ]);

        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $purchase['quantity'],
            unitCost: $purchase['unit_cost'],
        );

        $logService->log(
            action: 'create',
            product: $product,
            quantityBefore: 0,
            quantityAfter: $purchase['quantity'],
            unitCost: $purchase['unit_cost'],
            lot: $lot,
            note: 'Bahan jadi baru + stok awal'.($purchase['note'] !== '' ? ' · '.$purchase['note'] : ''),
        );

        return redirect()
            ->route('bahan-jadi.index')
            ->with('success', 'Bahan jadi '.$product->name.' ditambahkan.');
    }

    public function update(
        Request $request,
        Product $product,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $this->assertBahanJadi($product);

        $request->merge([
            'purchase_mode' => $request->input('purchase_mode', 'direct'),
        ]);

        $wantsStock = filled($request->input('direct_total'))
            || filled($request->input('purchase_cost'))
            || filled($request->input('package_cost'));

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
            'name.required' => 'Nama wajib diisi.',
        ]);

        $newUnit = MaterialUnits::resolve($validated['unit_preset'], $validated['unit_custom'] ?? '');
        if ($newUnit === '') {
            return back()->withErrors(['unit_custom' => 'Isi satuan.'])->withInput();
        }

        $product->update([
            'name' => trim($validated['name']),
            'unit' => $newUnit,
        ]);

        if ($wantsStock) {
            $purchase = MaterialPurchase::resolve($validated);
            if ($purchase['quantity'] > 0) {
                $before = $product->availableQuantity();
                $lot = $inventoryService->receiveStock(
                    product: $product,
                    quantity: $purchase['quantity'],
                    unitCost: $purchase['unit_cost'],
                );
                $logService->log(
                    action: 'receive',
                    product: $product,
                    quantityBefore: $before,
                    quantityAfter: $before + $purchase['quantity'],
                    unitCost: $purchase['unit_cost'],
                    lot: $lot,
                    note: 'Tambah stok bahan jadi'.($purchase['note'] !== '' ? ' · '.$purchase['note'] : ''),
                );
            }
        }

        return redirect()->route('bahan-jadi.index')->with('success', 'Bahan jadi diperbarui.');
    }

    public function destroy(Product $product, ProductDeletionService $deletionService)
    {
        $this->assertBahanJadi($product);

        $reason = $deletionService->canDelete($product);
        if ($reason) {
            return redirect()->route('bahan-jadi.index')->with('error', $reason);
        }

        try {
            $deletionService->delete($product);
        } catch (\Throwable $e) {
            return redirect()->route('bahan-jadi.index')->with('error', $e->getMessage());
        }

        return redirect()->route('bahan-jadi.index')->with('success', 'Bahan jadi dihapus.');
    }

    public function receive(
        Request $request,
        InventoryCostService $inventoryService,
        MaterialStockLogService $logService,
    ) {
        $request->merge([
            'purchase_mode' => $request->input('purchase_mode', 'direct'),
        ]);

        $validated = $request->validate(array_merge([
            'product_id' => ['required', 'exists:products,id'],
        ], MaterialPurchase::validationRules()));

        $product = Product::query()->findOrFail($validated['product_id']);
        $this->assertBahanJadi($product);

        $purchase = MaterialPurchase::resolve($validated);
        if ($purchase['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'Jumlah stok tidak valid.'])->withInput();
        }

        $before = $product->availableQuantity();
        $lot = $inventoryService->receiveStock(
            product: $product,
            quantity: $purchase['quantity'],
            unitCost: $purchase['unit_cost'],
        );

        $logService->log(
            action: 'receive',
            product: $product,
            quantityBefore: $before,
            quantityAfter: $before + $purchase['quantity'],
            unitCost: $purchase['unit_cost'],
            lot: $lot,
            note: 'Terima stok bahan jadi'.($purchase['note'] !== '' ? ' · '.$purchase['note'] : ''),
        );

        return redirect()->route('bahan-jadi.index')->with('success', 'Stok bahan jadi ditambahkan.');
    }

    private function loadItems(InventoryCostService $inventoryService)
    {
        return Product::query()
            ->where('type', ProductType::SemiFinished->value)
            ->where('is_active', true)
            ->withCount('billOfMaterials')
            ->with(['inventoryLots' => fn ($q) => $q->orderByDesc('received_at')])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($inventoryService) {
                $product->available_qty = $product->availableQuantity();
                $product->avg_cost = $inventoryService->getWeightedAverageCost($product);

                return $product;
            });
    }

    private function assertBahanJadi(Product $product): void
    {
        if ($product->type !== ProductType::SemiFinished) {
            abort(403, 'Hanya bahan jadi yang bisa dikelola di sini.');
        }
    }

    private function generateSku(string $name): string
    {
        $base = 'BJ-'.strtoupper(Str::slug(Str::limit($name, 24, '')));
        $sku = $base;
        $suffix = 1;

        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }
}
