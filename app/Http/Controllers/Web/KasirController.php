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

    public function index(PosOrderService $posService)
    {
        $activeOrder = $this->activeKasirOrder();

        if (! $activeOrder) {
            $activeOrder = $posService->createKasirOrder($this->cashier());
            session(['kasir_order_id' => $activeOrder->id]);
        }

        $activeOrder->load(['items.product', 'table']);

        $pendingOrders = PosOrder::with(['table', 'items'])
            ->where('source', PosOrderSource::Online)
            ->whereIn('status', [PosOrderStatus::Submitted, PosOrderStatus::Confirmed])
            ->latest()
            ->get();

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

    public function pendingOrdersPoll()
    {
        $pendingOrders = PosOrder::with(['table'])
            ->where('source', PosOrderSource::Online)
            ->whereIn('status', [PosOrderStatus::Submitted, PosOrderStatus::Confirmed])
            ->latest()
            ->get();

        $format = Format::class;
        $currentOrder = $this->activeKasirOrder();

        return response()->json([
            'count' => $pendingOrders->count(),
            'total' => (float) $pendingOrders->sum('total'),
            'order_ids' => $pendingOrders->pluck('id')->values(),
            'has_pending' => $pendingOrders->isNotEmpty(),
            'latest_order_id' => $pendingOrders->first()?->id,
            'html' => $pendingOrders->isNotEmpty()
                ? view('kasir.partials.pending-orders', compact('pendingOrders', 'format', 'currentOrder'))->render()
                : '',
        ]);
    }

    public function orders()
    {
        $orders = PosOrder::with(['table', 'items.product', 'cashier'])
            ->whereIn('status', ['submitted', 'confirmed', 'paid'])
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
        $order = $posService->createKasirOrder($this->cashier());
        session(['kasir_order_id' => $order->id]);

        return redirect()->route('kasir.index')->with('success', 'Order baru dibuat.');
    }

    public function loadOrder(Request $request, PosOrder $order, PosOrderService $posService)
    {
        if ($order->status === PosOrderStatus::Paid || $order->status === PosOrderStatus::Cancelled) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Order sudah selesai atau dibatalkan.'], 422);
            }

            return redirect()->route('kasir.index')->with('error', 'Order sudah selesai atau dibatalkan.');
        }

        // Online submitted → masuk ke kasir (siap bayar).
        if ($order->source === PosOrderSource::Online && $order->status === PosOrderStatus::Submitted) {
            try {
                $order = $posService->confirmOrder($order, $this->cashier());
            } catch (\Throwable $e) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                return redirect()->route('kasir.index')->with('error', $e->getMessage());
            }
        }

        session(['kasir_order_id' => $order->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Order #'.$order->order_number.' masuk ke kasir.',
                'order_number' => $order->order_number,
                'needs_confirmation' => false,
                'can_checkout' => $order->canCheckoutAtKasir(),
                'redirect' => route('kasir.index'),
            ]);
        }

        return redirect()->route('kasir.index')->with('success', 'Order #'.$order->order_number.' masuk ke kasir. Lanjut bayar.');
    }

    public function confirmOrder(PosOrder $order, PosOrderService $posService)
    {
        try {
            $posService->confirmOrder($order, $this->cashier());
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
            $newOrder = $posService->createKasirOrder($this->cashier());
            session(['kasir_order_id' => $newOrder->id]);
        }

        $message = 'Pesanan online #'.$order->order_number.' dihapus.';

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

        $newOrder = $posService->createKasirOrder($this->cashier());
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

        $order = $this->activeKasirOrder() ?? $posService->createKasirOrder($this->cashier());
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
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        session()->forget('kasir_order_id');

        return redirect()->route('kasir.receipt', $result['order'])
            ->with('success', 'Pembayaran berhasil.');
    }

    public function receipt(PosOrder $order, ReceiptPdfService $receiptPdf)
    {
        if ($order->status !== PosOrderStatus::Paid) {
            return redirect()->route('kasir.index');
        }

        $order->load(['items.product', 'table', 'cashier']);
        $pdf = $receiptPdf->store($order);

        return view('kasir.receipt', [
            'order' => $order,
            'format' => Format::class,
            'pdfUrl' => $pdf['url'],
            'pdfRoute' => route('kasir.receipt.pdf', $order),
            'waMessage' => $receiptPdf->whatsappMessage($order, $pdf['url']),
        ]);
    }

    public function receiptPdf(PosOrder $order, ReceiptPdfService $receiptPdf): Response
    {
        if ($order->status !== PosOrderStatus::Paid) {
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

    private function activeKasirOrder(): ?PosOrder
    {
        $orderId = session('kasir_order_id');

        if (! $orderId) {
            return null;
        }

        return PosOrder::with('items.product')
            ->where('id', $orderId)
            ->whereIn('status', ['open', 'submitted', 'confirmed'])
            ->first();
    }
}
