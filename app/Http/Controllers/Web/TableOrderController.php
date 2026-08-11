<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Services\PosOrderService;
use App\Services\QrisDynamicService;
use App\Support\Format;
use App\Support\SessionPressure;
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
        // Lepas session lock sebelum query menu berat — kurangi antrian EP saat banyak QR.
        SessionPressure::releaseEarly();

        $order->load(['items.product', 'table']);

        // Setelah dikirim ke kasir, tidak perlu load katalog menu (berat + sering reload dari poll).
        $needsMenu = $order->status === PosOrderStatus::Open;

        $products = $needsMenu ? $posService->sellableProducts() : collect();

        return view('order.table', [
            'order' => $order,
            'products' => $products,
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

    public function status(): JsonResponse
    {
        // Poll ringan: baca session sekali, lepas lock, jangan create order baru.
        $orderId = session(self::SESSION_KEY);
        SessionPressure::releaseEarly();

        $order = null;
        if (is_numeric($orderId)) {
            $order = PosOrder::query()
                ->select(['id', 'order_number', 'customer_note', 'total', 'status', 'source'])
                ->whereKey((int) $orderId)
                ->where('source', PosOrderSource::Online)
                ->first();
        }

        if (! $order) {
            return response()->json([
                'status' => 'missing',
                'order_number' => null,
                'customer_note' => null,
                'total' => 0,
                'is_submitted' => false,
                'is_confirmed' => false,
                'is_paid' => false,
                'is_served' => false,
            ])->header('Cache-Control', 'private, max-age=10');
        }

        $status = $order->status->value;

        return response()->json([
            'status' => $status,
            'order_number' => $order->order_number,
            'customer_note' => $order->customer_note,
            'total' => (float) $order->total,
            'is_submitted' => $status === 'submitted',
            'is_confirmed' => $status === 'confirmed',
            'is_paid' => $status === 'paid',
            'is_served' => $status === 'served',
        ])->header('Cache-Control', 'private, max-age=10');
    }

    public function qrisDynamic(QrisDynamicService $qrisDynamic): JsonResponse
    {
        $orderId = session(self::SESSION_KEY);
        SessionPressure::releaseEarly();

        $order = null;
        if (is_numeric($orderId)) {
            $order = PosOrder::query()
                ->select(['id', 'total', 'status', 'source'])
                ->whereKey((int) $orderId)
                ->where('source', PosOrderSource::Online)
                ->first();
        }

        if (! $order || ! in_array($order->status, [PosOrderStatus::Submitted, PosOrderStatus::Confirmed], true)) {
            return response()->json([
                'message' => 'Pesanan belum siap dibayar.',
                'data' => $qrisDynamic->forAmount(0),
            ], 404);
        }

        $data = $qrisDynamic->forAmount($order->total);
        $saved = $qrisDynamic->persistDynamicImage($order->total, 'order-'.$order->id);
        if ($saved) {
            $data['saved_url'] = $saved['url'];
            $data['qr_data_uri'] = $saved['url'];
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function pay(Request $request, PosOrderService $posService): RedirectResponse
    {
        $order = $this->currentOrder($posService);

        if ($order->source !== PosOrderSource::Online) {
            return back()->with('error', 'Pesanan tidak valid.');
        }

        if (! in_array($order->status, [PosOrderStatus::Submitted, PosOrderStatus::Confirmed], true)) {
            return redirect()
                ->to(route('order.menu').'#ke-kasir')
                ->with('error', 'Pesanan sudah dibayar atau belum siap dibayar.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:qris'],
            'payment_proof' => [
                'required',
                'image',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,heic,heif',
            ],
        ], [
            'payment_proof.required' => 'Unggah bukti pembayaran QRIS dulu.',
            'payment_proof.image' => 'Bukti pembayaran harus berupa gambar.',
            'payment_proof.max' => 'Ukuran bukti maksimal 5 MB.',
        ]);

        try {
            $posService->payOrder(
                $order,
                PaymentMethod::Qris,
                null,
                null,
                $request->file('payment_proof'),
                [
                    'cashier_name' => 'Pelanggan (QRIS meja)',
                ],
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->to(route('order.menu').'#ke-kasir')
            ->with('success', 'Bukti diterima. Pembayaran tercatat lunas. Mohon tunggu pesanan diantar.');
    }

    private function currentOrder(PosOrderService $posService): PosOrder
    {
        $orderId = session(self::SESSION_KEY);
        $order = $posService->resolveOnlineOrder(is_numeric($orderId) ? (int) $orderId : null);

        if ((int) session(self::SESSION_KEY) !== (int) $order->id) {
            session([self::SESSION_KEY => $order->id]);
        }

        return $order;
    }
}
