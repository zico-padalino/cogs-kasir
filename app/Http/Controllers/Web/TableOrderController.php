<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PosTable;
use App\Services\PosOrderService;
use App\Support\Format;
use Illuminate\Http\Request;

class TableOrderController extends Controller
{
    public function show(string $token, PosOrderService $posService)
    {
        $table = PosTable::where('barcode_token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $order = $posService->getOrCreateOnlineOrder($table);
        $order->load(['items.product']);

        return view('order.table', [
            'table' => $table,
            'order' => $order,
            'products' => $posService->sellableProducts(),
            'format' => Format::class,
        ]);
    }

    public function addItem(string $token, Request $request, PosOrderService $posService)
    {
        $table = PosTable::where('barcode_token', $token)->where('is_active', true)->firstOrFail();
        $order = $posService->getOrCreateOnlineOrder($table);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:1'],
        ]);

        $product = \App\Models\Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem($order, $product, (float) $validated['quantity']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $product->name.' ditambahkan ke pesanan.');
    }

    public function submit(string $token, PosOrderService $posService)
    {
        $table = PosTable::where('barcode_token', $token)->where('is_active', true)->firstOrFail();
        $order = $posService->getOrCreateOnlineOrder($table);

        try {
            $posService->submitOnlineOrder($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pesanan dikirim ke kasir. Silakan tunggu konfirmasi.');
    }
}
