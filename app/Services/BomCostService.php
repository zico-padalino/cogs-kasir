<?php

namespace App\Services;

use App\Enums\CostingMethod;
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

        // Bahan jadi sebagai komponen resep menu: pakai harga stok (sama konsumsi kasir),
        // jangan drill ke resep bahan bakunya — itu yang bikin harga/kg beda antar layar.
        if ($depth > 0 && $product->effectiveType() === ProductType::SemiFinished) {
            return $this->leafCost($product, $quantity);
        }

        $bomItems = $product->billOfMaterials()
            ->with('childProduct')
            ->orderBy('sequence')
            ->get();

        if ($bomItems->isEmpty()) {
            return $this->leafCost($product, $quantity);
        }

        $components = [];
        $totalMaterialCost = 0.0;

        foreach ($bomItems as $bomItem) {
            $child = $bomItem->childProduct;
            if (! $child) {
                continue;
            }

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
            'type' => $product->effectiveType()->value,
            'quantity' => $quantity,
            'unit_cost' => $quantity > 0 ? round($totalMaterialCost / $quantity, 4) : 0,
            'total_cost' => round($totalMaterialCost, 4),
            'is_leaf' => false,
            'components' => $components,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leafCost(Product $product, float $quantity): array
    {
        $unitCost = $product->effectiveCostingMethod() === CostingMethod::Standard
            ? $product->effectiveUnitHpp()
            : $this->inventoryCostService->getWeightedAverageCost($product);

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'type' => $product->effectiveType()->value,
            'quantity' => $quantity,
            'unit_cost' => round($unitCost, 4),
            'total_cost' => round($unitCost * $quantity, 4),
            'is_leaf' => true,
            'components' => [],
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
        if ($depth > 0 && $product->effectiveType() === ProductType::SemiFinished) {
            return [['product' => $product, 'quantity' => $quantity]];
        }

        $requirements = [];

        foreach ($bomItems as $bomItem) {
            $child = $bomItem->childProduct;
            if (! $child) {
                continue;
            }

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

    /**
     * Simpan harga satuan & biaya baris resep ke DB (dari stok saat ini).
     * Dipakai agar halaman resep tidak perlu hitung ulang lot setiap load.
     */
    public function cacheRecipeLineCosts(Product $product): float
    {
        $product->loadMissing('billOfMaterials.childProduct');

        if ($product->billOfMaterials->isEmpty()) {
            $product->forceFill(['recipe_material_cost' => 0])->save();

            return 0.0;
        }

        $rollUp = $this->rollUpCost($product, 1);
        $byChildId = [];

        foreach ($rollUp['components'] ?? [] as $component) {
            $byChildId[(int) ($component['product_id'] ?? 0)] = $component;
        }

        $total = 0.0;

        foreach ($product->billOfMaterials as $bom) {
            $component = $byChildId[(int) $bom->child_product_id] ?? null;
            $unitCost = round((float) ($component['unit_cost'] ?? 0), 4);
            $lineCost = round((float) ($component['total_cost'] ?? 0), 4);

            $bom->forceFill([
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
            ])->save();

            $total += $lineCost;
        }

        $total = round($total, 4);
        $product->forceFill(['recipe_material_cost' => $total])->save();

        return $total;
    }
}
