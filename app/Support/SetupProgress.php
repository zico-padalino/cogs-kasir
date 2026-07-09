<?php

namespace App\Support;

use App\Models\BillOfMaterial;
use App\Models\CogsCalculation;
use App\Models\InventoryLot;
use App\Models\OverheadRate;
use App\Models\Product;
use App\Models\ProductionOrder;

class SetupProgress
{
  public static function totalSteps(): int
  {
    return count(self::steps());
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public static function steps(): array
  {
    $hasOverhead = OverheadRate::where('is_active', true)->exists();
    $hasRawMaterial = Product::where('type', 'raw_material')->exists();
    $hasStock = InventoryLot::where('quantity_remaining', '>', 0)->exists();
    $hasFinished = Product::whereIn('type', ['semi_finished', 'finished_good'])->exists();
    $hasBom = BillOfMaterial::exists();
    $hasProduction = ProductionOrder::where('status', 'completed')->exists();
    $hasPricing = Product::query()
      ->whereIn('type', ['semi_finished', 'finished_good'])
      ->where('selling_price', '>', 0)
      ->exists();

    return [
      [
        'number' => 1,
        'key' => 'overhead',
        'title' => 'Biaya Lain',
        'short' => 'Biaya Lain',
        'description' => 'Listrik, sewa, air — biaya di luar bahan & upah.',
        'route' => 'overhead-rates.index',
        'done' => $hasOverhead,
        'hint' => 'Contoh: Listrik & gas → pilih persen → isi 10 (artinya 10%).',
      ],
      [
        'number' => 2,
        'key' => 'materials',
        'title' => 'Bahan',
        'short' => 'Bahan',
        'description' => 'Catat bahan baku plus stok dan harga belinya.',
        'route' => 'materials.index',
        'done' => $hasRawMaterial && $hasStock,
        'hint' => 'Nama bahan, jumlah stok, harga beli — sekali isi langsung jadi.',
      ],
      [
        'number' => 3,
        'key' => 'products',
        'title' => 'Menu & Resep',
        'short' => 'Menu',
        'description' => 'Tambah menu yang dijual, lalu tulis bahan resepnya.',
        'route' => 'products.index',
        'done' => $hasFinished && $hasBom,
        'hint' => 'Tambah menu → buka detail → isi bahan resep.',
      ],
      [
        'number' => 4,
        'key' => 'production',
        'title' => 'Produksi',
        'short' => 'Produksi',
        'description' => 'Catat berapa yang dibuat — modal dihitung otomatis.',
        'route' => 'production-orders.index',
        'done' => $hasProduction,
        'hint' => 'Pilih menu, isi jumlah, tekan Catat & Hitung Modal.',
      ],
      [
        'number' => 5,
        'key' => 'pricing',
        'title' => 'Harga Jual',
        'short' => 'Harga Jual',
        'description' => 'Tentukan harga jual menu berdasarkan modal.',
        'route' => 'menu-pricing.index',
        'done' => $hasPricing,
        'hint' => 'Isi harga jual dan centang tampil di Kasir.',
      ],
    ];
  }

  public static function currentStepNumber(): int
  {
    foreach (self::steps() as $step) {
      if (! $step['done']) {
        return $step['number'];
      }
    }

    return self::totalSteps();
  }

  public static function completedCount(): int
  {
    return collect(self::steps())->where('done', true)->count();
  }

  public static function percentComplete(): int
  {
    $total = self::totalSteps();

    return $total > 0
      ? (int) round((self::completedCount() / $total) * 100)
      : 0;
  }

  public static function isFullyComplete(): bool
  {
    return self::completedCount() === self::totalSteps();
  }

  /**
   * @return array<string, mixed>|null
   */
  public static function currentStep(): ?array
  {
    foreach (self::steps() as $step) {
      if (! $step['done']) {
        return $step;
      }
    }

    return null;
  }

  public static function stepForRoute(?string $routeName): ?int
  {
    if (! $routeName) {
      return null;
    }

    $map = [
      'overhead-rates.index' => 1,
      'overhead-rates.edit' => 1,
      'overhead-rates.update' => 1,
      'materials.index' => 2,
      'materials.store' => 2,
      'materials.receive' => 2,
      'materials.lots.update' => 2,
      'inventory.index' => 2,
      'inventory.receive' => 2,
      'inventory.lots.update' => 2,
      'products.index' => 3,
      'products.create' => 3,
      'products.store' => 3,
      'products.edit' => 3,
      'products.update' => 3,
      'products.show' => 3,
      'products.bom.store' => 3,
      'products.bom.update' => 3,
      'production-orders.index' => 4,
      'production-orders.create' => 4,
      'production-orders.edit' => 4,
      'production-orders.store' => 4,
      'production-orders.show' => 4,
      'menu-pricing.index' => 5,
      'menu-pricing.update' => 5,
      'cogs.history' => 5,
      'cogs.history.show' => 5,
      'cogs.result' => 5,
      'dashboard' => null,
    ];

    foreach ($map as $route => $step) {
      if ($routeName === $route || str_starts_with($routeName, rtrim($route, '.index').'.')) {
        return $step;
      }
    }

    return null;
  }
}
