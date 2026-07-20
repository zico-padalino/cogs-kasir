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

    private function shouldConsumeFinishedGoods(Product $product, float $quantity): bool
    {
        if (! in_array($product->type, [ProductType::FinishedGood, ProductType::SemiFinished], true)) {
            return false;
        }

        return $product->availableQuantity() >= $quantity;
    }
}
