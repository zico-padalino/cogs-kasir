<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\MenuImageService;
use Illuminate\Console\Command;

class ConvertMenuImagesToWebp extends Command
{
    protected $signature = 'menu:convert-images-webp
        {--keep-originals : Simpan file JPG/PNG lama setelah konversi}
        {--dry-run : Tampilkan file yang akan diproses tanpa mengubah file atau database}';

    protected $description = 'Konversi gambar menu yang sudah di-upload menjadi WebP';

    public function handle(MenuImageService $imageService): int
    {
        $products = Product::query()
            ->whereNotNull('image_path')
            ->where('image_path', 'like', 'uploads/menu/%')
            ->get(['id', 'name', 'image_path']);

        $converted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($products as $product) {
            $relativePath = (string) $product->image_path;
            $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

            if ($extension === 'webp') {
                $skipped++;
                continue;
            }

            $sourcePath = public_path($relativePath);
            $targetRelativePath = substr($relativePath, 0, -strlen($extension)).'webp';
            $targetPath = public_path($targetRelativePath);

            if ($this->option('dry-run')) {
                $this->line("Akan dikonversi: {$relativePath} -> {$targetRelativePath}");
                $converted++;
                continue;
            }

            if (! is_file($sourcePath)) {
                $this->warn("File tidak ditemukan: {$relativePath} ({$product->name})");
                $failed++;
                continue;
            }

            try {
                $imageService->convert($sourcePath, $targetPath);
                Product::query()->where('image_path', $relativePath)->update([
                    'image_path' => $targetRelativePath,
                ]);

                if (! $this->option('keep-originals')) {
                    @unlink($sourcePath);
                }

                $this->info("Selesai: {$relativePath} -> {$targetRelativePath}");
                $converted++;
            } catch (\Throwable $exception) {
                $this->error("Gagal {$relativePath}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->table(
            ['Dikonversi', 'Dilewati', 'Gagal'],
            [[$converted, $skipped, $failed]],
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}