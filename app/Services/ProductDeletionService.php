<?php

namespace App\Services;

use App\Models\CogsCalculation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductDeletionService
{
    public function canDelete(Product $product): ?string
    {
        if ($product->productionOrders()->where('status', 'completed')->exists()) {
            return 'Produk sudah pernah diproduksi (order selesai). Gunakan Reset Data untuk menghapus semua.';
        }

        $usedInCompletedOrder = ProductionOrderMaterial::query()
            ->where('product_id', $product->id)
            ->whereHas('productionOrder', fn ($q) => $q->where('status', 'completed'))
            ->exists();

        if ($usedInCompletedOrder) {
            return 'Bahan ini sudah dipakai di produksi selesai. Gunakan Reset Data untuk menghapus semua.';
        }

        return null;
    }

    public function delete(Product $product): void
    {
        if ($reason = $this->canDelete($product)) {
            throw new RuntimeException($reason);
        }

        DB::transaction(function () use ($product) {
            $product->productionOrders()
                ->whereIn('status', ['draft', 'in_progress', 'cancelled'])
                ->each(function (ProductionOrder $order) {
                    CogsCalculation::query()
                        ->where('reference_type', ProductionOrder::class)
                        ->where('reference_id', $order->id)
                        ->delete();

                    $order->materials()->delete();
                    $order->labors()->delete();
                    $order->delete();
                });

            ProductionOrderMaterial::query()
                ->where('product_id', $product->id)
                ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', ['draft', 'in_progress']))
                ->delete();

            $product->cogsCalculations()->delete();
            $product->salesTransactions()->delete();
            $product->delete();
        });
    }
}
