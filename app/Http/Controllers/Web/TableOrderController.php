<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PosOrderItem;
use App\Services\PosOrderService;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
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
            ->with('success', 'Pesanan baru dibuat. Pilih menu, lalu isi tipe & nama sebelum kirim.');
    }

    public function updateCustomer(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        $validated = $request->validate([
            'customer_note' => ['required', 'string', 'max:255'],
            'order_type' => ['nullable', 'in:dine_in,takeaway'],
        ]);

        try {
            $posService->updateOnlineCustomerDetails(
                $order,
                $validated['customer_note'],
                $validated['order_type'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Detail pemesan disimpan.');
    }

    public function addItem(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:product_addons,id'],
        ]);

        $product = \App\Models\Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem(
                $order,
                $product,
                (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
                addonIds: $validated['addon_ids'] ?? [],
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

            if (array_key_exists('notes', $validated)) {
                $item->update([
                    'notes' => \App\Support\PosItemNotes::preserveAddons(
                        $item->notes,
                        $validated['notes'] ?? null,
                    ),
                ]);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Catatan item diperbarui.');
    }

    public function submit(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        $validated = $request->validate([
            'customer_note' => ['required', 'string', 'max:255'],
            'order_type' => ['required', 'in:dine_in,takeaway'],
        ], [
            'customer_note.required' => 'Isi nama pemesan dulu sebelum kirim ke kasir.',
            'order_type.required' => 'Pilih Take Away atau Dine In dulu.',
        ]);

        try {
            $posService->updateOnlineCustomerDetails(
                $order,
                $validated['customer_note'],
                $validated['order_type'],
            );
            $posService->submitOnlineOrder($order->fresh());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->to(route('order.menu').'#ke-kasir')
            ->with('success', 'Pesanan terkirim. Silakan ke kasir untuk konfirmasi dan pembayaran.');
    }

    public function status(PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($posService);

        return response()->json([
            'status' => $order->status->value,
            'order_number' => $order->order_number,
            'customer_note' => $order->customer_note,
            'total' => (float) $order->total,
            'is_submitted' => $order->status->value === 'submitted',
            'is_confirmed' => $order->status->value === 'confirmed',
            'is_paid' => $order->status->value === 'paid',
            'is_served' => $order->status->value === 'served',
        ]);
    }

    private function currentOrder(PosOrderService $posService): \App\Models\PosOrder
    {
        $orderId = session(self::SESSION_KEY);
        $order = $posService->resolveOnlineOrder(is_numeric($orderId) ? (int) $orderId : null);
        session([self::SESSION_KEY => $order->id]);

        return $order;
    }
}
