<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryReceiptRequest;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\InventoryCostService;
use App\Support\Format;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(InventoryCostService $inventoryService)
    {
        $lots = InventoryLot::with('product')
            ->where('quantity_remaining', '>', 0)
            ->latest('received_at')
            ->get();

        $products = Product::with(['inventoryLots' => fn ($q) => $q->where('quantity_remaining', '>', 0)])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($inventoryService) {
                $product->available_qty = $product->availableQuantity();
                $product->avg_cost = $inventoryService->getWeightedAverageCost($product);

                return $product;
            });

        $rawMaterials = Product::where('is_active', true)->orderBy('name')->get();

        return view('inventory.index', [
            'lots' => $lots,
            'products' => $products,
            'rawMaterials' => $rawMaterials,
            'format' => Format::class,
        ]);
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

        return redirect()->route('inventory.index')->with('success', "Stok {$product->name} berhasil diterima.");
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

        return redirect()->route('inventory.index')->with('success', 'Stok berhasil diperbarui.');
    }

    public function destroy(InventoryLot $lot)
    {
        if ((float) $lot->quantity_remaining < (float) $lot->quantity_received) {
            return back()->with('error', 'Stok yang sudah dipakai produksi tidak bisa dihapus.');
        }

        $lot->delete();

        return redirect()->route('inventory.index')->with('success', 'Stok berhasil dihapus.');
    }
}
