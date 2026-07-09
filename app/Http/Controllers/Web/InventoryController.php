<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostingMethod;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Support\Format;
use App\Support\MaterialUnits;
use Illuminate\Http\Request;
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

    return view('materials.index', [
      'materials' => $materials,
      'format' => Format::class,
      'unitPresets' => MaterialUnits::presets(),
    ]);
  }

  public function storeMaterial(Request $request, InventoryCostService $inventoryService)
  {
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

    $inventoryService->receiveStock(
      product: $product,
      quantity: (float) $validated['quantity'],
      unitCost: Format::parseRupiah($validated['unit_cost']),
    );

    return redirect()->route('materials.index')
      ->with('success', "Bahan {$product->name} ditambahkan beserta stok awal.");
  }

  public function receive(StoreInventoryReceiptRequest $request, InventoryCostService $inventoryService)
  {
    $product = Product::findOrFail($request->product_id);

    $inventoryService->receiveStock(
      product: $product,
      quantity: (float) $request->quantity,
      unitCost: (float) $request->unit_cost,
      lotNumber: $request->lot_number,
    );

    return redirect()->route('materials.index')
      ->with('success', "Stok {$product->name} bertambah.");
  }

  public function update(Request $request, InventoryLot $lot)
  {
    $validated = $request->validate([
      'lot_number' => ['nullable', 'string', 'max:255'],
      'quantity_remaining' => ['required', 'numeric', 'min:0', 'max:'.$lot->quantity_received],
      'unit_cost' => ['required', 'numeric', 'min:0'],
    ]);

    $validated['unit_cost'] = Format::parseRupiah($validated['unit_cost']);

    $lot->update($validated);

    return redirect()->route('materials.index')->with('success', 'Stok berhasil diperbarui.');
  }

  public function destroy(InventoryLot $lot)
  {
    if ((float) $lot->quantity_remaining < (float) $lot->quantity_received) {
      return redirect()->route('materials.index')->with('error', 'Stok yang sudah dipakai produksi tidak bisa dihapus.');
    }

    $lot->delete();

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
