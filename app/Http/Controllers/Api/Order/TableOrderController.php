<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kasir\MenuProductResource;
use App\Http\Resources\Kasir\PosOrderResource;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Product;
use App\Services\PosOrderService;
use App\Support\PosItemNotes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TableOrderController extends Controller
{
    public function show(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);
        $order->load(['items.product', 'table']);
        $products = $posService->sellableProducts();
        $products->loadMissing('addons');

        return response()->json([
            'data' => [
                'order' => new PosOrderResource($order),
                'products' => MenuProductResource::collection($products),
                'menu_categories' => $posService->menuCategories($products),
                'menu_category_labels' => $posService->menuCategoryLabels(),
                'shop_name' => config('pos.shop_name'),
            ],
        ]);
    }

    public function newOrder(PosOrderService $posService): JsonResponse
    {
        $order = $posService->createOnlineOrder();
        $order->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pesanan baru dibuat.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function updateCustomer(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);

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
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Detail pemesan disimpan.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function addItem(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'exists:product_addons,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        try {
            $posService->addItem(
                $order,
                $product,
                (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
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
        $order = $this->currentOrder($request, $posService);

        if ($item->pos_order_id !== $order->id) {
            abort(404);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:1'],
        ]);

        try {
            if (! $order->isEditable()) {
                throw new RuntimeException('Pesanan sudah dikirim. Silakan bayar di kasir.');
            }

            if (array_key_exists('quantity', $validated) && $validated['quantity'] !== null) {
                $posService->updateItemQuantity($item, (float) $validated['quantity']);
            }

            if (array_key_exists('notes', $validated)) {
                $item->update([
                    'notes' => PosItemNotes::preserveAddons(
                        $item->notes,
                        $validated['notes'] ?? null,
                    ),
                ]);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Item diperbarui.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function removeItem(Request $request, PosOrderItem $item, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);

        if ($item->pos_order_id !== $order->id) {
            abort(404);
        }

        try {
            $posService->removeItem($item);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Item dihapus dari pesanan.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function submit(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);

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
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['items.product', 'table']);

        return response()->json([
            'message' => 'Pesanan terkirim. Silakan ke kasir untuk konfirmasi dan pembayaran.',
            'data' => new PosOrderResource($order),
        ]);
    }

    public function status(Request $request, PosOrderService $posService): JsonResponse
    {
        $order = $this->currentOrder($request, $posService);

        return response()->json([
            'data' => [
                'status' => $order->status->value,
                'order_number' => $order->order_number,
                'customer_note' => $order->customer_note,
                'total' => (float) $order->total,
                'is_submitted' => $order->status->value === 'submitted',
                'is_confirmed' => $order->status->value === 'confirmed',
                'is_paid' => $order->status->value === 'paid',
                'is_served' => $order->status->value === 'served',
                'order' => new PosOrderResource($order->load(['items.product', 'table'])),
            ],
        ]);
    }

    private function currentOrder(Request $request, PosOrderService $posService): PosOrder
    {
        $orderId = $request->integer('order_id')
            ?: (int) $request->header('X-Order-Id')
            ?: null;

        return $posService->resolveOnlineOrder($orderId ?: null);
    }
}
