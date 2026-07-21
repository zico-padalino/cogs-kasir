<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\BillOfMaterial;
use App\Models\Product;
use RuntimeException;

class BomCostService
{
    public function __construct(
        private readonly InventoryCostService $inventoryCostService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rollUpCost(Product $product, float $quantity = 1, int $depth = 0): array
    {
        if ($depth > 20) {
            throw new RuntimeException("BOM terlalu dalam untuk produk {$product->sku}");
        }

        $bomItems = $product->billOfMaterials()
            ->with('childProduct')
            ->orderBy('sequence')
            ->get();

        if ($bomItems->isEmpty()) {
            $unitCost = $product->costing_method->value === 'standard'
                ? $product->effectiveUnitHpp()
                : $this->inventoryCostService->getWeightedAverageCost($product);

            return [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type->value,
                'quantity' => $quantity,
                'unit_cost' => round($unitCost, 4),
                'total_cost' => round($unitCost * $quantity, 4),
                'is_leaf' => true,
                'components' => [],
            ];
        }

        $components = [];
        $totalMaterialCost = 0.0;

        foreach ($bomItems as $bomItem) {
            $child = $bomItem->childProduct;
            $requiredQty = $bomItem->effectiveQuantity() * $quantity;
            $componentCost = $this->rollUpCost($child, $requiredQty, $depth + 1);

            $components[] = array_merge($componentCost, [
                'bom_quantity' => (float) $bomItem->quantity,
                'scrap_percentage' => (float) $bomItem->scrap_percentage,
                'effective_quantity' => $requiredQty,
            ]);

            $totalMaterialCost += $componentCost['total_cost'];
        }

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'type' => $product->type->value,
            'quantity' => $quantity,
            'unit_cost' => $quantity > 0 ? round($totalMaterialCost / $quantity, 4) : 0,
            'total_cost' => round($totalMaterialCost, 4),
            'is_leaf' => false,
            'components' => $components,
        ];
    }

    /**
     * @return array<int, array{product: Product, quantity: float}>
     */
    public function explodeBom(Product $product, float $quantity): array
    {
        return $this->explodeBomRecursive($product, $quantity);
    }

    /**
     * @return array<int, array{product: Product, quantity: float}>
     */
    private function explodeBomRecursive(Product $product, float $quantity, int $depth = 0): array
    {
        if ($depth > 20) {
            throw new RuntimeException("BOM terlalu dalam untuk produk {$product->sku}");
        }

        $bomItems = $product->billOfMaterials()->with('childProduct')->get();

        if ($bomItems->isEmpty()) {
            return [['product' => $product, 'quantity' => $quantity]];
        }

        // Bahan jadi sebagai komponen resep: konsumsi stok bahan jadi (jangan drill ke bahan baku).
        // depth 0 = produksi/root bahan jadi itu sendiri → tetap explode ke bahan baku.
        if ($depth > 0 && $product->type === ProductType::SemiFinished) {
            return [['product' => $product, 'quantity' => $quantity]];
        }

        $requirements = [];

        foreach ($bomItems as $bomItem) {
            $child = $bomItem->childProduct;
            $requiredQty = $bomItem->effectiveQuantity() * $quantity;
            $childRequirements = $this->explodeBomRecursive($child, $requiredQty, $depth + 1);

            foreach ($childRequirements as $req) {
                $productId = $req['product']->id;
                if (isset($requirements[$productId])) {
                    $requirements[$productId]['quantity'] += $req['quantity'];
                } else {
                    $requirements[$productId] = $req;
                }
            }
        }

        return array_values($requirements);
    }
}
