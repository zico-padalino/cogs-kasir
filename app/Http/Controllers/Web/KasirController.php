<?php

namespace App\Http\Controllers\Web;

use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Models\Product;
use App\Services\PosOrderService;
use App\Services\EscPosReceiptService;
use App\Services\ReceiptPdfService;
use App\Support\Format;
use App\Support\KasirPin;
use App\Support\PosMenu;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KasirController extends Controller
{
    private function cashier()
    {
        return KasirPin::operatorOrAuth();
    }

    /** @return array{user_id: ?int, cashier_employee_id: ?int, cashier_name: string} */
    private function cashierAttribution(): array
    {
        return KasirPin::cashierAttribution();
    }

    public function index(PosOrderService $posService)
    {
        $activeOrder = $this->activeKasirOrder();

        if (! $activeOrder) {
            $activeOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            session(['kasir_order_id' => $activeOrder->id]);
        }

        $activeOrder->load(['items.product', 'table']);

        $pendingOrders = $posService->waitingOrders();

        $products = $posService->sellableProducts();

        return view('kasir.index', [
            'order' => $activeOrder,
            'products' => $products,
            'menuCategories' => $posService->menuCategories($products),
            'menuCategoryLabels' => $posService->menuCategoryLabels(),
            'orderTypes' => PosOrderType::cases(),
            'pendingOrders' => $pendingOrders,
            'presets' => config('pos.product_presets', []),
            'shopName' => config('pos.shop_name'),
            'format' => Format::class,
        ]);
    }

    public function pendingOrdersPoll(PosOrderService $posService)
    {
        $pendingOrders = $posService->waitingOrders();

        $format = Format::class;
        $currentOrder = $this->activeKasirOrder();

        return response()->json(array_merge([
            'count' => $pendingOrders->count(),
            'total' => (float) $pendingOrders->sum('total'),
            'order_ids' => $pendingOrders->pluck('id')->values(),
            'notify_order_ids' => $pendingOrders
                ->filter(fn (PosOrder $order) => $order->source === PosOrderSource::Online
                    && in_array($order->status, [PosOrderStatus::Submitted, PosOrderStatus::Confirmed], true))
                ->pluck('id')
                ->values(),
            'has_pending' => $pendingOrders->isNotEmpty(),
            'latest_order_id' => $pendingOrders->first()?->id,
            'html' => $pendingOrders->isNotEmpty()
                ? view('kasir.partials.pending-orders', compact('pendingOrders', 'format', 'currentOrder'))->render()
                : '',
        ], KasirPin::statusPayload()));
    }

    public function orders()
    {
        $orders = PosOrder::with(['table', 'items.product', 'cashier'])
            ->whereIn('status', ['submitted', 'confirmed', 'unpaid', 'paid', 'served'])
            ->latest()
            ->paginate(20);

        return view('kasir.orders', [
            'orders' => $orders,
            'format' => Format::class,
        ]);
    }

    public function showOrder(PosOrder $order)
    {
        $order->load(['items.product', 'table', 'cashier', 'salesTransactions']);

        return view('kasir.show', [
            'order' => $order,
            'format' => Format::class,
        ]);
    }

    public function tables()
    {
        $tables = PosTable::withCount([
            'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['open', 'submitted']),
        ])->orderBy('table_number')->get();

        return view('kasir.tables', [
            'tables' => $tables,
            'orderUrl' => PosMenu::orderUrl(),
            'shopName' => config('pos.shop_name'),
        ]);
    }

    public function barcode()
    {
        return view('kasir.table-barcode', [
            'orderUrl' => PosMenu::orderUrl(),
            'shopName' => config('pos.shop_name'),
            'shopTitle' => config('pos.shop_title'),
        ]);
    }

    public function storeTable(Request $request)
    {
        $validated = $request->validate([
            'table_number' => ['required', 'string', 'max:20', 'unique:pos_tables,table_number'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        PosTable::create($validated);

        return redirect()->route('kasir.tables')->with('success', 'Meja berhasil ditambahkan.');
    }

    public function newOrder(PosOrderService $posService)
    {
        $order = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        session(['kasir_order_id' => $order->id]);

        return redirect()->route('kasir.index')->with('success', 'Order baru dibuat.');
    }

    public function loadOrder(Request $request, PosOrder $order, PosOrderService $posService)
    {
        if ($order->status === PosOrderStatus::Paid
            || $order->status === PosOrderStatus::Served
            || $order->status === PosOrderStatus::Cancelled) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Order sudah selesai atau dibatalkan.'], 422);
            }

            return redirect()->route('kasir.index')->with('error', 'Order sudah selesai atau dibatalkan.');
        }

        // Online submitted → masuk ke kasir (siap bayar).
        if ($order->source === PosOrderSource::Online && $order->status === PosOrderStatus::Submitted) {
            try {
                $order = $posService->confirmOrder($order, $this->cashier(), $this->cashierAttribution());
            } catch (\Throwable $e) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                return redirect()->route('kasir.index')->with('error', $e->getMessage());
            }
        }

        session(['kasir_order_id' => $order->id]);

        if ($request->expectsJson()) {
            return response()->json($this->kasirOrderAjaxPayload($order, 'Order #'.$order->order_number.' masuk ke kasir.'));
        }

        return redirect()->route('kasir.index')->with('success', 'Order #'.$order->order_number.' dibuka. Lanjut bayar atau tambah item.');
    }

    public function openBill(PosOrderService $posService)
    {
        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        try {
            $result = $posService->openBill($order, $this->cashierAttribution());
            $held = $result['order'];
            $merged = $result['merged'];
            $newOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            session(['kasir_order_id' => $newOrder->id]);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $who = $held->customer_note ?: $held->order_number;
        $message = $merged
            ? 'Item ditambahkan ke tagihan terbuka '.$who.'.'
            : 'Tagihan terbuka '.$who.' disimpan. Buka lagi dari antrian untuk tambah item atau bayar.';

        return redirect()
            ->route('kasir.index')
            ->with('success', $message);
    }

    public function confirmOrder(PosOrder $order, PosOrderService $posService)
    {
        try {
            $posService->confirmOrder($order, $this->cashier(), $this->cashierAttribution());
        } catch (\RuntimeException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        session(['kasir_order_id' => $order->id]);

        $message = 'Pesanan #'.$order->order_number.' masuk ke kasir. Silakan proses pembayaran.';

        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'order_number' => $order->order_number,
                'redirect' => route('kasir.index'),
            ]);
        }

        return redirect()->route('kasir.index')->with('success', $message);
    }

    public function markServed(PosOrder $order, PosOrderService $posService)
    {
        try {
            $posService->markServed($order);
        } catch (\RuntimeException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = 'Pesanan #'.$order->order_number.' dikonfirmasi selesai / sudah diantar.';

        if (request()->expectsJson()) {
            return response()->json(['message' => $message, 'order_number' => $order->order_number]);
        }

        return redirect()->route('kasir.index')->with('success', $message);
    }

    public function toggleItemDelivered(Request $request, PosOrderItem $item, PosOrderService $posService)
    {
        $validated = $request->validate([
            'is_delivered' => ['required', 'boolean'],
        ]);

        try {
            $order = $posService->setItemDelivered($item, (bool) $validated['is_delivered']);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $message = $validated['is_delivered']
            ? 'Item ditandai sudah diantar.'
            : 'Ceklis antar dibatalkan.';

        if ($request->expectsJson()) {
            $order->load(['items.product', 'table']);
            $item->refresh();

            return response()->json([
                'message' => $message,
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'item' => [
                        'id' => $item->id,
                        'is_delivered' => (bool) $item->is_delivered,
                        'delivered_at' => $item->delivered_at?->toIso8601String(),
                    ],
                    'items' => $order->items->map(fn (PosOrderItem $row) => [
                        'id' => $row->id,
                        'is_delivered' => (bool) $row->is_delivered,
                    ]),
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function cancelPendingOrder(PosOrder $order, PosOrderService $posService)
    {
        $wasActive = (int) session('kasir_order_id') === (int) $order->id;

        try {
            $posService->cancelPendingOnlineOrder($order);
        } catch (\RuntimeException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($wasActive) {
            session()->forget('kasir_order_id');
            $newOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            session(['kasir_order_id' => $newOrder->id]);
        }

        $message = $order->source === PosOrderSource::Kasir
            ? 'Tagihan terbuka #'.$order->order_number.' dihapus.'
            : 'Pesanan online #'.$order->order_number.' dihapus.';

        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'order_number' => $order->order_number,
                'redirect' => route('kasir.index'),
            ]);
        }

        return redirect()->route('kasir.index')->with('success', $message);
    }

    public function updateOrder(Request $request, PosOrderService $posService)
    {
        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        $validated = $request->validate([
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'customer_note' => ['nullable', 'string', 'max:255'],
        ]);

        $orderType = PosOrderType::from($validated['order_type']);

        try {
            $posService->updateOrderContext(
                $order,
                $orderType,
                null,
                $validated['customer_note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        $order->refresh()->load('table');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Info pesanan diperbarui.',
                'order_type' => $order->order_type?->value,
                'order_type_label' => $order->order_type?->label(),
                'order_type_icon' => $order->order_type?->icon(),
                'customer_note' => $order->customer_note,
            ]);
        }

        return back()->with('success', 'Info pesanan diperbarui.');
    }

    public function updateDiscount(Request $request, PosOrderService $posService)
    {
        $order = $this->activeKasirOrder();

        if (! $order) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Tidak ada order aktif.'], 422);
            }

            return back()->with('error', 'Tidak ada order aktif.');
        }

        $validated = $request->validate([
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $discountType = $validated['discount_type'] ?? null;
        $discountValue = (float) ($validated['discount_value'] ?? 0);

        if ($discountType === 'percent' && $discountValue > 100) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Diskon persen maksimal 100%.'], 422);
            }

            return back()->with('error', 'Diskon persen maksimal 100%.');
        }

        try {
            $order = $posService->updateDiscount($order, $discountType, $discountValue);
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Diskon diperbarui.',
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'discount_type' => $order->discount_type,
                'discount_value' => (float) $order->discount_value,
                'total' => (float) $order->total,
                'subtotal_label' => Format::rupiah($order->subtotal),
                'discount_label' => $order->hasDiscount()
                    ? '- '.Format::rupiah($order->discount_amount)
                    : null,
                'total_label' => Format::rupiah($order->total),
            ]);
        }

        return back()->with('success', 'Diskon diperbarui.');
    }

    public function cancelOrder(PosOrderService $posService)
    {
        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        try {
            $posService->cancelOrder($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session()->forget('kasir_order_id');

        $newOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        session(['kasir_order_id' => $newOrder->id]);

        return redirect()->route('kasir.index')->with('success', 'Pesanan dibatalkan.');
    }

    public function addItem(Request $request, PosOrderService $posService)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:product_addons,id'],
        ]);

        $order = $this->activeKasirOrder() ?? $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        session(['kasir_order_id' => $order->id]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem(
                $order,
                $product,
                (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
                fromKasir: true,
                addonIds: $validated['addon_ids'] ?? [],
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $product->name.' ditambahkan.');
    }

    public function updateItem(Request $request, PosOrderItem $item, PosOrderService $posService)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $order = $item->order;
            if ($order->id !== ($this->activeKasirOrder()?->id)) {
                throw new \RuntimeException('Item bukan bagian dari order aktif.');
            }

            if (! $order->isKasirEditable()) {
                throw new \RuntimeException('Pesanan tidak bisa diubah.');
            }

            if (array_key_exists('quantity', $validated) && $validated['quantity'] !== null) {
                $posService->updateItemQuantity($item, (float) $validated['quantity'], fromKasir: true);
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

    public function removeItem(PosOrderItem $item, PosOrderService $posService)
    {
        try {
            $order = $item->order;
            if ($order->id !== ($this->activeKasirOrder()?->id)) {
                throw new \RuntimeException('Item bukan bagian dari order aktif.');
            }

            $posService->removeItem($item, fromKasir: true);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Item dihapus.');
    }

    public function pay(Request $request, PosOrderService $posService)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_proof' => [
                'nullable',
                'required_if:payment_method,qris,transfer',
                'image',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,heic,heif',
            ],
        ], [
            'payment_proof.required_if' => 'Upload foto bukti pembayaran untuk QRIS / Transfer.',
            'payment_proof.image' => 'Bukti pembayaran harus berupa gambar.',
            'payment_proof.max' => 'Ukuran bukti pembayaran maksimal 5 MB.',
        ]);

        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        $paymentMethod = \App\Enums\PaymentMethod::from($validated['payment_method']);
        $amountReceived = isset($validated['amount_received']) ? (float) $validated['amount_received'] : null;
        $paymentProof = $request->file('payment_proof');

        try {
            $result = $posService->payOrder(
                $order,
                $paymentMethod,
                $this->cashier(),
                $amountReceived,
                $paymentProof,
                $this->cashierAttribution(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        session()->forget('kasir_order_id');

        $redirect = redirect()->route('kasir.receipt', $result['order'])
            ->with('success', 'Pembayaran berhasil.');

        if (! empty($result['stock_out_message'])) {
            $redirect->with('warning', $result['stock_out_message']);
        }

        return $redirect;
    }

    public function receipt(PosOrder $order, ReceiptPdfService $receiptPdf, EscPosReceiptService $escPos)
    {
        if (! in_array($order->status, [PosOrderStatus::Paid, PosOrderStatus::Served], true)) {
            return redirect()->route('kasir.index');
        }

        $order->load(['items.product', 'table', 'cashier']);
        $pdf = $receiptPdf->store($order);
        $thermal = $escPos->payload($order);

        return view('kasir.receipt', [
            'order' => $order,
            'format' => Format::class,
            'pdfUrl' => $pdf['url'],
            'pdfRoute' => route('kasir.receipt.pdf', $order),
            'thermalRoute' => route('kasir.receipt.thermal', $order),
            'waMessage' => $receiptPdf->whatsappMessage($order, $pdf['url']),
            'thermal' => [
                'paper' => $thermal['paper'],
                'width' => $thermal['width'],
                'base64' => $thermal['base64'],
                'rawbt_url' => $thermal['rawbt_url'],
                'intent_url' => $thermal['intent_url'],
                'rawbt_play_store' => config('pos.thermal.rawbt_play_store'),
            ],
        ]);
    }

    public function receiptPdf(PosOrder $order, ReceiptPdfService $receiptPdf): Response
    {
        if (! in_array($order->status, [PosOrderStatus::Paid, PosOrderStatus::Served], true)) {
            abort(404);
        }

        $pdf = $receiptPdf->store($order);
        $inline = request()->boolean('print');

        return response($pdf['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Public signed PDF for WhatsApp / share links (no login required).
     */
    public function publicReceiptPdf(PosOrder $order, ReceiptPdfService $receiptPdf): Response
    {
        if (! in_array($order->status, [PosOrderStatus::Paid, PosOrderStatus::Served], true)) {
            abort(404);
        }

        $pdf = $receiptPdf->store($order);
        $inline = request()->boolean('print', true);

        return response($pdf['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function receiptThermal(PosOrder $order, EscPosReceiptService $escPos): Response
    {
        if (! in_array($order->status, [PosOrderStatus::Paid, PosOrderStatus::Served], true)) {
            abort(404);
        }

        $paper = request()->query('paper');
        $thermal = $escPos->payload($order, is_string($paper) ? $paper : null);
        $filename = 'struk-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $order->order_number).'.bin';

        return response($thermal['binary'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Thermal-Paper' => $thermal['paper'],
        ]);
    }

    private function activeKasirOrder(): ?PosOrder
    {
        $orderId = session('kasir_order_id');

        if (! $orderId) {
            return null;
        }

        return PosOrder::with('items.product')
            ->where('id', $orderId)
            ->whereIn('status', ['open', 'submitted', 'confirmed', 'unpaid'])
            ->first();
    }

    /** @return array<string, mixed> */
    private function kasirOrderAjaxPayload(PosOrder $order, string $message): array
    {
        $order->loadMissing(['items.product', 'table']);
        $format = Format::class;

        return [
            'message' => $message,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'item_count' => $order->items->count(),
            'can_checkout' => $order->canCheckoutAtKasir(),
            'fragments' => [
                'cart' => view('kasir.partials.cart-panel', compact('order', 'format'))->render(),
                'pay_modal' => view('kasir.partials.pay-modal', compact('order', 'format'))->render(),
                'mobile_checkout' => view('kasir.partials.mobile-checkout', compact('order', 'format'))->render(),
            ],
            'toolbar' => [
                'order_number' => $order->order_number,
                'order_type' => $order->order_type
                    ? $order->order_type->icon().' '.$order->order_type->label()
                    : null,
                'customer_note' => $order->customer_note,
                'status_label' => $order->status->label(),
                'status_badge' => $order->status->badgeClass(),
                'formatted_total' => $format::rupiah($order->total),
            ],
        ];
    }
}
