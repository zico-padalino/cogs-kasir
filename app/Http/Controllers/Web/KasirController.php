<?php

namespace App\Http\Controllers\Web;

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

        return view('kasir.index', [
            'order' => $activeOrder,
            'products' => $posService->sellableProducts(),
            'pendingOrders' => $pendingOrders,
            'tables' => PosTable::where('is_active', true)->orderBy('table_number')->get(),
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

    public function addItem(Request $request, PosOrderService $posService)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $order = $this->activeKasirOrder() ?? $posService->createKasirOrder(auth()->user());
        session(['kasir_order_id' => $order->id]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem($order, $product, (float) $validated['quantity'], fromKasir: true);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $product->name.' ditambahkan.');
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
        ]);

        $order = $this->activeKasirOrder();

        if (! $order) {
            return back()->with('error', 'Tidak ada order aktif.');
        }

        try {
            $result = $posService->payOrder(
                $order,
                \App\Enums\PaymentMethod::from($validated['payment_method']),
                auth()->user(),
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
