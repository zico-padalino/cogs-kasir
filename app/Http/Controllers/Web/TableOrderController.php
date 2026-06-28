<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PosOrderItem;
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
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = \App\Models\Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem(
                $order,
                $product,
                (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $product->name.' ditambahkan.');
    }

    public function removeItem(string $token, PosOrderItem $item, PosOrderService $posService)
    {
        $table = PosTable::where('barcode_token', $token)->where('is_active', true)->firstOrFail();
        $order = $posService->getOrCreateOnlineOrder($table);

        if ($item->pos_order_id !== $order->id) {
            abort(404);
        }

        try {
            $posService->removeItem($item);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item dihapus dari pesanan.');
    }

    public function updateItem(string $token, PosOrderItem $item, Request $request, PosOrderService $posService)
    {
        $table = PosTable::where('barcode_token', $token)->where('is_active', true)->firstOrFail();
        $order = $posService->getOrCreateOnlineOrder($table);

        if ($item->pos_order_id !== $order->id) {
            abort(404);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if (! $order->isEditable()) {
                throw new \RuntimeException('Pesanan sudah dikirim. Silakan bayar di kasir.');
            }

            $item->update([
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ]);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Catatan item diperbarui.');
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

        return back()->with('success', 'Pesanan terkirim. Silakan bayar di kasir.');
    }
}
