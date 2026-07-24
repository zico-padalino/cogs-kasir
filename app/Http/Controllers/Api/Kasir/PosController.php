<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Enums\PaymentMethod;
use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Kasir\MenuProductResource;
use App\Http\Resources\Kasir\PosOrderResource;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Services\PosOrderService;
use App\Services\EscPosReceiptService;
use App\Services\ReceiptPdfService;
use App\Support\KasirActiveOrder;
use App\Support\KasirPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PosController extends Controller
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

    public function index(PosOrderService $posService): JsonResponse
    {
        $activeOrder = KasirActiveOrder::find();

        if (! $activeOrder) {
            $activeOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            KasirActiveOrder::set($activeOrder);
        }

        $activeOrder->load(['items.product', 'table']);

        $posService->syncMissingOpenBillStockBookings();

        $pendingOrders = $posService->waitingOrders();

        $products = $posService->sellableProducts();
        $products->loadMissing('addons');

        return response()->json([
            'data' => [
                'order' => new PosOrderResource($activeOrder),
                'products' => MenuProductResource::collection($products),
                'menu_categories' => $posService->menuCategories($products),
                'menu_category_labels' => $posService->menuCategoryLabels(),
                'order_types' => collect(PosOrderType::cases())->map(fn (PosOrderType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'icon' => $type->icon(),
                ])->values(),
                'pending_orders' => PosOrderResource::collection($pendingOrders),
                'shop_name' => config('pos.shop_name'),
                'poll_interval_seconds' => (int) config('pos.notifications.poll_interval_seconds', 5),
                'auto_load_new_order' => (bool) config('pos.notifications.auto_load_new_order', true),
                'pin' => KasirPin::statusPayload(),
            ],
        ]);
    }

    public function pendingPoll(PosOrderService $posService): JsonResponse
    {
        $pendingOrders = $posService->waitingOrders();

        return response()->json([
            'data' => array_merge([
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
                'orders' => PosOrderResource::collection($pendingOrders),
                'active_order_id' => KasirActiveOrder::getId(),
            ], KasirPin::statusPayload()),
        ]);
    }

    public function newOrder(PosOrderService $posService): JsonResponse
    {
        $order = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        KasirActiveOrder::set($order);
        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Order baru dibuat.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function updateOrder(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = KasirActiveOrder::find();

        if (! $order) {
            return response()->json(['message' => 'Tidak ada order aktif.'], 422);
        }

        $validated = $request->validate([
            'order_type' => ['required', 'in:dine_in,takeaway'],
            'customer_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $posService->updateOrderContext(
                $order,
                PosOrderType::from($validated['order_type']),
                null,
                $validated['customer_note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Info pesanan diperbarui.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function updateDiscount(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = KasirActiveOrder::find();

        if (! $order) {
            return response()->json(['message' => 'Tidak ada order aktif.'], 422);
        }

        $validated = $request->validate([
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $discountType = $validated['discount_type'] ?? null;
        $discountValue = (float) ($validated['discount_value'] ?? 0);

        if ($discountType === 'percent' && $discountValue > 100) {
            return response()->json(['message' => 'Diskon persen maksimal 100%.'], 422);
        }

        try {
            $order = $posService->updateDiscount($order, $discountType, $discountValue);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Diskon diperbarui.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function cancelOrder(PosOrderService $posService): JsonResponse
    {
        $order = KasirActiveOrder::find();

        if (! $order) {
            return response()->json(['message' => 'Tidak ada order aktif.'], 422);
        }

        try {
            $posService->cancelOrder($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        KasirActiveOrder::forget();
        $newOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        KasirActiveOrder::set($newOrder);
        $newOrder->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pesanan dibatalkan.',
            'data' => new PosOrderResource($newOrder),
        ]);
    }

    public function loadOrder(PosOrder $order, PosOrderService $posService): JsonResponse
    {
        if ($order->status === PosOrderStatus::Paid
            || $order->status === PosOrderStatus::Served
            || $order->status === PosOrderStatus::Cancelled) {
            return response()->json(['message' => 'Order sudah selesai atau dibatalkan.'], 422);
        }

        if ($order->source === PosOrderSource::Online && $order->status === PosOrderStatus::Submitted) {
            try {
                $order = $posService->confirmOrder($order, $this->cashier(), $this->cashierAttribution());
            } catch (\Throwable $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        KasirActiveOrder::set($order);

        try {
            $posService->ensureOpenBillStockBooking($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Order #'.$order->order_number.' masuk ke kasir.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function editPaidOrder(PosOrder $order, PosOrderService $posService): JsonResponse
    {
        try {
            $order = $posService->reopenForEdit($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        KasirActiveOrder::set($order);
        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pembayaran dibatalkan. Order #'.$order->order_number.' dibuka untuk diedit. Bayar lagi setelah koreksi.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function confirmOrder(PosOrder $order, PosOrderService $posService): JsonResponse
    {
        try {
            $order = $posService->confirmOrder($order, $this->cashier(), $this->cashierAttribution());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        KasirActiveOrder::set($order);
        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pesanan #'.$order->order_number.' masuk ke kasir.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function markServed(PosOrder $order, PosOrderService $posService): JsonResponse
    {
        try {
            $order = $posService->markServed($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pesanan #'.$order->order_number.' dikonfirmasi selesai / sudah diantar.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function toggleItemDelivered(Request $request, PosOrderItem $item, PosOrderService $posService): JsonResponse
    {
        $validated = $request->validate([
            'is_delivered' => ['required', 'boolean'],
        ]);

        try {
            $order = $posService->setItemDelivered($item, (bool) $validated['is_delivered']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => $validated['is_delivered']
                ? 'Item ditandai sudah diantar.'
                : 'Ceklis antar dibatalkan.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function cancelPendingOrder(PosOrder $order, PosOrderService $posService): JsonResponse
    {
        $wasActive = KasirActiveOrder::getId() === (int) $order->id;

        try {
            $posService->cancelPendingOnlineOrder($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $active = null;
        if ($wasActive) {
            KasirActiveOrder::forget();
            $active = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            KasirActiveOrder::set($active);
            $active->load(['items.product', 'table']);
        }

        return response()->json([
            'message' => $order->source === PosOrderSource::Kasir
                ? 'Tagihan terbuka #'.$order->order_number.' dihapus.'
                : 'Pesanan online #'.$order->order_number.' dihapus.',
            'data' => [
                'active_order' => $active ? new PosOrderResource($active) : null,
            ],
        ]);
    }

    public function addItem(Request $request, PosOrderService $posService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:product_addons,id'],
        ]);

        $order = KasirActiveOrder::find()
            ?? $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
        KasirActiveOrder::set($order);

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
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => $product->name.' ditambahkan.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function updateItem(Request $request, PosOrderItem $item, PosOrderService $posService): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $order = $item->order;
            if ($order->id !== KasirActiveOrder::getId()) {
                throw new RuntimeException('Item bukan bagian dari order aktif.');
            }

            if (! $order->isKasirEditable()) {
                throw new RuntimeException('Pesanan tidak bisa diubah.');
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
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order = KasirActiveOrder::find()?->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Item diperbarui.',
            'data' => $order ? new PosOrderResource($order) : null,
        ]);
    }

    public function removeItem(PosOrderItem $item, PosOrderService $posService): JsonResponse
    {
        try {
            $order = $item->order;
            if ($order->id !== KasirActiveOrder::getId()) {
                throw new RuntimeException('Item bukan bagian dari order aktif.');
            }

            $posService->removeItem($item, fromKasir: true);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order = KasirActiveOrder::find()?->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Item dihapus.',
            'data' => $order ? new PosOrderResource($order) : null,
        ]);
    }

    public function pay(Request $request, PosOrderService $posService): JsonResponse
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

        $order = KasirActiveOrder::find();

        if (! $order) {
            return response()->json(['message' => 'Tidak ada order aktif.'], 422);
        }

        try {
            $result = $posService->payOrder(
                $order,
                PaymentMethod::from($validated['payment_method']),
                $this->cashier(),
                isset($validated['amount_received']) ? (float) $validated['amount_received'] : null,
                $request->file('payment_proof'),
                $this->cashierAttribution(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        KasirActiveOrder::forget();
        /** @var PosOrder $paid */
        $paid = $result['order'];
        $paid->load(['items.product', 'table', 'cashier']);

        return response()->json([
            'message' => 'Pembayaran berhasil.',
            'stock_out' => $result['stock_out'] ?? [],
            'stock_out_message' => $result['stock_out_message'] ?? null,
            'data' => new PosOrderResource($paid),
        ]);
    }

    public function openBill(PosOrderService $posService): JsonResponse
    {
        $order = KasirActiveOrder::find();

        if (! $order) {
            return response()->json(['message' => 'Tidak ada order aktif.'], 422);
        }

        try {
            $result = $posService->openBill($order, $this->cashierAttribution());
            $held = $result['order'];
            $merged = $result['merged'];
            $newOrder = $posService->createKasirOrder($this->cashier(), $this->cashierAttribution());
            KasirActiveOrder::set($newOrder);
            $newOrder->load(['items.product', 'table']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $who = $held->customer_note ?: $held->order_number;

        return response()->json([
            'message' => $merged
                ? 'Item ditambahkan ke tagihan terbuka '.$who.'.'
                : 'Tagihan terbuka '.$who.' disimpan. Buka lagi untuk tambah item atau bayar.',
            'data' => [
                'held_order' => new PosOrderResource($held->load(['items.product', 'table'])),
                'active_order' => new PosOrderResource($newOrder),
                'merged' => $merged,
            ],
        ]);
    }

    public function receipt(PosOrder $order, ReceiptPdfService $receiptPdf, EscPosReceiptService $escPos): JsonResponse
    {
        if ($order->status !== PosOrderStatus::Paid && $order->status !== PosOrderStatus::Served) {
            return response()->json(['message' => 'Order belum dibayar.'], 422);
        }

        $order->load(['items.product', 'table', 'cashier']);
        $pdf = $receiptPdf->store($order);
        $paper = request()->query('paper');
        $thermal = $escPos->payload($order, is_string($paper) ? $paper : null);

        return response()->json([
            'data' => [
                'order' => new PosOrderResource($order),
                'pdf_url' => $pdf['url'],
                'wa_message' => $receiptPdf->whatsappMessage($order, $pdf['url']),
                'shop_name' => config('pos.shop_name'),
                'thermal' => [
                    'paper' => $thermal['paper'],
                    'width' => $thermal['width'],
                    'base64' => $thermal['base64'],
                    'rawbt_url' => $thermal['rawbt_url'],
                    'intent_url' => $thermal['intent_url'],
                    'rawbt_play_store' => config('pos.thermal.rawbt_play_store'),
                ],
            ],
        ]);
    }

    public function receiptPdf(PosOrder $order, ReceiptPdfService $receiptPdf): \Symfony\Component\HttpFoundation\Response
    {
        if ($order->status !== PosOrderStatus::Paid && $order->status !== PosOrderStatus::Served) {
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

    public function receiptThermal(PosOrder $order, EscPosReceiptService $escPos): \Symfony\Component\HttpFoundation\Response
    {
        if ($order->status !== PosOrderStatus::Paid && $order->status !== PosOrderStatus::Served) {
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
}
