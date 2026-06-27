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
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function steps(): array
    {
        $hasOverhead = OverheadRate::where('is_active', true)->exists();
        $hasRawMaterial = Product::where('type', 'raw_material')->exists();
        $hasFinished = Product::whereIn('type', ['semi_finished', 'finished_good'])->exists();
        $hasBom = BillOfMaterial::exists();
        $hasStock = InventoryLot::where('quantity_remaining', '>', 0)->exists();
        $hasProduction = ProductionOrder::where('status', 'completed')->exists();
        $hasCogs = CogsCalculation::exists();

        return [
            [
                'number' => 1,
                'key' => 'overhead',
                'title' => 'Biaya Overhead',
                'short' => 'Overhead',
                'description' => 'Atur biaya tidak langsung (listrik, sewa, dll) yang dibagi ke produk.',
                'route' => 'overhead-rates.index',
                'done' => $hasOverhead,
                'hint' => 'Minimal 1 tarif overhead, misal 15% dari biaya bahan.',
            ],
            [
                'number' => 2,
                'key' => 'products',
                'title' => 'Daftar Produk',
                'short' => 'Produk',
                'description' => 'Catat bahan baku (tepung, gula) dan barang jadi (roti).',
                'route' => 'products.index',
                'done' => $hasRawMaterial && $hasFinished,
                'hint' => 'Buat minimal 1 bahan baku dan 1 barang jadi.',
            ],
            [
                'number' => 3,
                'key' => 'bom',
                'title' => 'Resep Produksi (BOM)',
                'short' => 'Resep',
                'description' => 'Tentukan bahan apa saja dan berapa banyak untuk 1 unit produk jadi.',
                'route' => 'products.index',
                'done' => $hasBom,
                'hint' => 'Buka detail barang jadi → tambah komponen resep.',
            ],
            [
                'number' => 4,
                'key' => 'inventory',
                'title' => 'Stok Bahan Baku',
                'short' => 'Stok',
                'description' => 'Catat stok masuk dan harga beli bahan baku.',
                'route' => 'inventory.index',
                'done' => $hasStock,
                'hint' => 'Terima stok bahan baku beserta harga per satuan.',
            ],
            [
                'number' => 5,
                'key' => 'production',
                'title' => 'Proses Produksi',
                'short' => 'Produksi',
                'description' => 'Buat order produksi, mulai, lalu selesaikan untuk hitung biaya.',
                'route' => 'production-orders.index',
                'done' => $hasProduction,
                'hint' => 'Buat order → Mulai → Selesaikan & Hitung COGS.',
            ],
            [
                'number' => 6,
                'key' => 'result',
                'title' => 'Lihat Hasil COGS',
                'short' => 'Hasil',
                'description' => 'Cek total biaya produksi per unit barang jadi.',
                'route' => 'cogs.history',
                'done' => $hasCogs,
                'hint' => 'Hasil muncul otomatis setelah produksi selesai.',
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

        return 6;
    }

    public static function completedCount(): int
    {
        return collect(self::steps())->where('done', true)->count();
    }

    public static function percentComplete(): int
    {
        return (int) round((self::completedCount() / 6) * 100);
    }

    public static function isFullyComplete(): bool
    {
        return self::completedCount() === 6;
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
            'products.index' => 2,
            'products.create' => 2,
            'products.store' => 2,
            'products.edit' => 2,
            'products.update' => 2,
            'products.show' => 3,
            'products.bom.store' => 3,
            'products.bom.update' => 3,
            'inventory.index' => 4,
            'inventory.receive' => 4,
            'inventory.lots.update' => 4,
            'production-orders.index' => 5,
            'production-orders.create' => 5,
            'production-orders.edit' => 5,
            'production-orders.store' => 5,
            'production-orders.show' => 5,
            'cogs.history' => 6,
            'cogs.history.show' => 6,
            'cogs.calculate' => 6,
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
