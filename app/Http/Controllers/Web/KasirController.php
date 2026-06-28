<?php

namespace App\Http\Controllers\Web;

use App\Enums\PosOrderType;
use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosTable;
use App\Models\Product;
use App\Services\PosOrderService;
use App\Support\Format;
use Illuminate\Http\Request;

class KasirController extends Controller
{
    public function index(PosOrderService $posService)
    {
        $activeOrder = $this->activeKasirOrder();

        if (! $activeOrder) {
            $activeOrder = $posService->createKasirOrder(auth()->user());
            session(['kasir_order_id' => $activeOrder->id]);
        }

        $activeOrder->load(['items.product', 'table']);

        $pendingOrders = PosOrder::with(['table', 'items'])
            ->where('status', 'submitted')
            ->latest()
            ->get();

        $products = $posService->sellableProducts();

        return view('kasir.index', [
            'order' => $activeOrder,
            'products' => $products,
            'menuCategories' => $posService->menuCategories($products),
            'orderTypes' => PosOrderType::cases(),
            'pendingOrders' => $pendingOrders,
            'tables' => PosTable::where('is_active', true)->orderBy('table_number')->get(),
            'presets' => config('pos.product_presets', []),
            'shopName' => config('pos.shop_name'),
            'format' => Format::class,
        ]);
    }

    public function orders()
    {
        $orders = PosOrder::with(['table', 'items.product', 'cashier'])
            ->whereIn('status', ['submitted', 'paid'])
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
        ]);
    }

    public function tableBarcode(PosTable $table)
    {
        abort_unless($table->is_active, 404);

        return view('kasir.table-barcode', [
            'table' => $table,
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
        $order = $posService->createKasirOrder(auth()->user());
        session(['kasir_order_id' => $order->id]);

        return redirect()->route('kasir.index')->with('success', 'Order baru dibuat.');
    }

    public function loadOrder(PosOrder $order)
    {
        if ($order->status === \App\Enums\PosOrderStatus::Paid) {
            return redirect()->route('kasir.index')->with('error', 'Order sudah lunas.');
        }

        session(['kasir_order_id' => $order->id]);

        return redirect()->route('kasir.index')->with('success', 'Order #'.$order->order_number.' dimuat.');
    }

    public function updateOrder(Request $request, PosOrderService $posService)
    {
        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        $validated = $request->validate([
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'pos_table_id' => ['nullable', 'exists:pos_tables,id'],
            'customer_note' => ['nullable', 'string', 'max:255'],
        ]);

        $orderType = PosOrderType::from($validated['order_type']);
        $tableId = $validated['pos_table_id'] ?? null;

        if ($orderType === PosOrderType::DineIn && ! $tableId && ! $order->pos_table_id) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Pilih meja untuk pesanan Dine In.'], 422);
            }

            return back()->with('error', 'Pilih meja untuk pesanan Dine In.');
        }

        try {
            $posService->updateOrderContext(
                $order,
                $orderType,
                $tableId ? (int) $tableId : $order->pos_table_id,
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
                'table_label' => $order->table?->label,
                'customer_note' => $order->customer_note,
            ]);
        }

        return back()->with('success', 'Info pesanan diperbarui.');
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

        $newOrder = $posService->createKasirOrder(auth()->user());
        session(['kasir_order_id' => $newOrder->id]);

        return redirect()->route('kasir.index')->with('success', 'Pesanan dibatalkan.');
    }

    public function addItem(Request $request, PosOrderService $posService)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->activeKasirOrder() ?? $posService->createKasirOrder(auth()->user());
        session(['kasir_order_id' => $order->id]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem(
                $order,
                $product,
                (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
                fromKasir: true,
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
                    'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
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
        ]);

        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        $paymentMethod = \App\Enums\PaymentMethod::from($validated['payment_method']);
        $amountReceived = isset($validated['amount_received']) ? (float) $validated['amount_received'] : null;

        try {
            $result = $posService->payOrder(
                $order,
                $paymentMethod,
                auth()->user(),
                $amountReceived,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session()->forget('kasir_order_id');

        return redirect()->route('kasir.receipt', $result['order'])
            ->with('success', 'Pembayaran berhasil.');
    }

    public function receipt(PosOrder $order)
    {
        if ($order->status !== \App\Enums\PosOrderStatus::Paid) {
            return redirect()->route('kasir.index');
        }

        $order->load(['items.product', 'table', 'cashier']);

        return view('kasir.receipt', [
            'order' => $order,
            'format' => Format::class,
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
            ->whereIn('status', ['open', 'submitted'])
            ->first();
    }
}
