<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class MenuImageService
{
    public function store(UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Server tidak mendukung konversi gambar ke WebP.');
        }

        $dir = public_path('uploads/menu');
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0755);
        }

        $name = Str::uuid()->toString().'.webp';
        $fullPath = $dir.'/'.$name;
        $this->convert($file->getRealPath(), $fullPath);

        return 'uploads/menu/'.$name;
    }

    public function convert(string $sourcePath, string $targetPath): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Server tidak mendukung konversi gambar ke WebP.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($source === false) {
            throw new RuntimeException('Gambar tidak dapat diproses.');
        }

        $targetDir = dirname($targetPath);
        if (! is_dir($targetDir)) {
            File::ensureDirectoryExists($targetDir, 0755);
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $stored = imagewebp($source, $targetPath, 85);
        imagedestroy($source);

        if (! $stored) {
            @unlink($targetPath);
            throw new RuntimeException('Gambar WebP tidak dapat disimpan.');
        }
    }
}