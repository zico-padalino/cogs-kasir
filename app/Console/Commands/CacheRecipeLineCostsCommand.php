<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\BomCostService;
use Illuminate\Console\Command;
use Throwable;

class CacheRecipeLineCostsCommand extends Command
{
    protected $signature = 'cogs:cache-recipe-costs {--product= : ID produk tertentu}';

    protected $description = 'Isi ulang harga satuan & biaya baris resep ke database dari stok saat ini';

    public function handle(BomCostService $bomCostService): int
    {
        $query = Product::query()
            ->whereIn('type', [ProductType::FinishedGood->value, ProductType::SemiFinished->value])
            ->whereHas('billOfMaterials')
            ->orderBy('id');

        if ($this->option('product')) {
            $query->whereKey((int) $this->option('product'));
        }

        $count = 0;
        $failed = 0;

        $query->chunkById(50, function ($products) use ($bomCostService, &$count, &$failed) {
            foreach ($products as $product) {
                try {
                    $total = $bomCostService->cacheRecipeLineCosts(
                        $product->fresh(['billOfMaterials.childProduct'])
                    );
                    $this->line("{$product->id} {$product->name}: ".number_format($total, 0, ',', '.'));
                    $count++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("{$product->id} {$product->name}: {$e->getMessage()}");
                    report($e);
                }
            }
        });

        $this->info("Selesai. Berhasil: {$count}, gagal: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
