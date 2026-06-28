<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Services\PosOrderService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TableOrderController extends Controller
{
    private const SESSION_KEY = 'online_order_id';

    public function show(PosOrderService $posService): View
    {
        $order = $this->currentOrder($posService);
        $order->load(['items.product', 'table']);

        return view('order.table', [
            'order' => $order,
            'tables' => PosTable::query()->where('is_active', true)->orderBy('table_number')->get(),
            'products' => $posService->sellableProducts(),
            'format' => Format::class,
        ]);
    }

    public function newOrder(PosOrderService $posService): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        $order = $posService->createOnlineOrder();
        session([self::SESSION_KEY => $order->id]);

        return redirect()
            ->route('order.menu')
            ->with('success', 'Pesanan baru dibuat. Isi nama & pilih meja lalu pilih menu.');
    }

    public function updateCustomer(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        $validated = $request->validate([
            'customer_note' => ['required', 'string', 'max:255'],
        ]);

        try {
            $posService->updateOnlineCustomerNote($order, $validated['customer_note']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nama pemesan disimpan.');
    }

    public function updateTable(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        $validated = $request->validate([
            'pos_table_id' => ['required', 'exists:pos_tables,id'],
        ]);

        $table = PosTable::query()
            ->whereKey($validated['pos_table_id'])
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $posService->updateOnlineTable($order, $table);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Meja '.$table->label.' dipilih.');
    }

    public function addItem(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

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

    public function removeItem(PosOrderItem $item, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

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

    public function updateItem(PosOrderItem $item, Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

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

    public function submit(PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        try {
            $posService->submitOnlineOrder($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pesanan terkirim. Silakan bayar di kasir.');
    }

    private function currentOrder(PosOrderService $posService): \App\Models\PosOrder
    {
        $orderId = session(self::SESSION_KEY);
        $order = $posService->resolveOnlineOrder(is_numeric($orderId) ? (int) $orderId : null);
        session([self::SESSION_KEY => $order->id]);

        return $order;
    }
}
