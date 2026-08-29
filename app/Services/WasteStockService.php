<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StockWaste;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WasteStockService
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
        private readonly BomCostService $bomCostService,
    ) {}

    public function record(
        Product $product,
        float $quantity,
        string $reason,
        ?string $note = null,
        ?PosOrder $posOrder = null,
        ?User $user = null,
    ): StockWaste {
        if ($quantity <= 0) {
            throw new RuntimeException('Jumlah rusak/gagal harus lebih dari 0.');
        }

        if (! array_key_exists($reason, StockWaste::REASONS)) {
            throw new RuntimeException('Alasan tidak valid.');
        }

        return DB::transaction(function () use ($product, $quantity, $reason, $note, $posOrder, $user) {
            $product->refresh();

            $totalCost = 0.0;
            $mode = 'finished_goods_inventory';
            $consumeNote = trim('Stok '.$reason.($note ? ': '.$note : ''));

            if ($this->shouldConsumeFinishedGoods($product, $quantity)) {
                $consumption = $this->inventoryCostService->consumeStock(
                    product: $product,
                    quantity: $quantity,
                    logAction: 'waste',
                    note: $consumeNote,
                );
                $totalCost = $consumption->totalCost;
            } else {
                $mode = 'bom_explosion';
                $requirements = $this->bomCostService->explodeBom($product, $quantity);

                if ($requirements === []) {
                    // Bahan baku / tanpa resep: konsumsi lot produk itu sendiri
                    if ($product->availableQuantity() < $quantity) {
                        throw new RuntimeException("Stok {$product->name} tidak cukup.");
                    }
                    $consumption = $this->inventoryCostService->consumeStock(
                        product: $product,
                        quantity: $quantity,
                        logAction: 'waste',
                        note: $consumeNote,
                    );
                    $totalCost = $consumption->totalCost;
                    $mode = 'direct_inventory';
                } else {
                    foreach ($requirements as $req) {
                        $consumption = $this->inventoryCostService->consumeStock(
                            product: $req['product'],
                            quantity: $req['quantity'],
                            logAction: 'waste',
                            note: $consumeNote,
                        );
                        $totalCost += $consumption->totalCost;
                    }
                }
            }

            $unitCost = $quantity > 0 ? round($totalCost / $quantity, 4) : 0;

            return StockWaste::query()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'pos_order_id' => $posOrder?->id,
                'unit_cost' => $unitCost,
                'total_cost' => round($totalCost, 4),
                'consumption_mode' => $mode,
                'note' => $note,
                'user_id' => $user?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Batalkan pencatatan stok rusak dan kembalikan stok yang terpotong.
     */
    public function cancel(StockWaste $waste): void
    {
        DB::transaction(function () use ($waste) {
            $waste->refresh();
            $product = $waste->product;
            $qty = (float) $waste->quantity;
            $unitCost = (float) $waste->unit_cost;
            $note = 'Batal stok rusak #'.$waste->id.($waste->note ? ' ('.$waste->note.')' : '');

            if ($product && $qty > 0) {
                $this->restoreWasteStock($waste, $product, $qty, $unitCost, $note);
            }

            $waste->delete();
        });
    }

    /**
     * Perbarui catatan. Jika produk/qty berubah: batal lama lalu catat ulang.
     *
     * @param  array{product_id?: int, quantity?: float|int|string, reason?: string, note?: string|null, pos_order_id?: int|null}  $data
     */
    public function update(StockWaste $waste, array $data, ?User $user = null): StockWaste
    {
        return DB::transaction(function () use ($waste, $data, $user) {
            $waste->refresh();
            $newProductId = (int) ($data['product_id'] ?? $waste->product_id);
            $newQty = (float) ($data['quantity'] ?? $waste->quantity);
            $newReason = (string) ($data['reason'] ?? $waste->reason);
            $newNote = array_key_exists('note', $data) ? $data['note'] : $waste->note;
            $newOrderId = array_key_exists('pos_order_id', $data) ? $data['pos_order_id'] : $waste->pos_order_id;

            if ($newQty <= 0) {
                throw new RuntimeException('Jumlah rusak/gagal harus lebih dari 0.');
            }

            if (! array_key_exists($newReason, StockWaste::REASONS)) {
                throw new RuntimeException('Alasan tidak valid.');
            }

            $stockChanged = $newProductId !== (int) $waste->product_id
                || abs($newQty - (float) $waste->quantity) > 0.000001;

            if (! $stockChanged) {
                $waste->update([
                    'reason' => $newReason,
                    'note' => $newNote,
                    'pos_order_id' => $newOrderId ?: null,
                ]);

                return $waste->fresh(['product', 'user', 'posOrder']);
            }

            $product = Product::query()->findOrFail($newProductId);
            $order = $newOrderId ? PosOrder::query()->find($newOrderId) : null;

            $this->cancel($waste);

            return $this->record(
                product: $product,
                quantity: $newQty,
                reason: $newReason,
                note: is_string($newNote) || $newNote === null ? $newNote : null,
                posOrder: $order,
                user: $user,
            );
        });
    }

    private function restoreWasteStock(
        StockWaste $waste,
        Product $product,
        float $quantity,
        float $unitCost,
        string $note,
    ): void {
        $mode = (string) ($waste->consumption_mode ?: 'direct_inventory');

        if ($mode === 'bom_explosion') {
            $requirements = $this->bomCostService->explodeBom($product, $quantity);

            if ($requirements === []) {
                $this->inventoryCostService->restoreConsumedStock(
                    product: $product,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    note: $note,
                    logAction: 'waste_void',
                );

                return;
            }

            foreach ($requirements as $req) {
                /** @var Product $reqProduct */
                $reqProduct = $req['product'];
                $reqQty = (float) $req['quantity'];
                $reqUnitCost = $this->inventoryCostService->getWeightedAverageCost($reqProduct)
                    ?: $reqProduct->effectiveUnitHpp();

                $this->inventoryCostService->restoreConsumedStock(
                    product: $reqProduct,
                    quantity: $reqQty,
                    unitCost: max(0, $reqUnitCost),
                    note: $note,
                    logAction: 'waste_void',
                );
            }

            return;
        }

        $this->inventoryCostService->restoreConsumedStock(
            product: $product,
            quantity: $quantity,
            unitCost: $unitCost,
            note: $note,
            logAction: 'waste_void',
        );
    }

    private function shouldConsumeFinishedGoods(Product $product, float $quantity): bool
    {
        if (! in_array($product->effectiveType(), [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            return false;
        }

        return $product->availableQuantity() >= $quantity;
    }
}
