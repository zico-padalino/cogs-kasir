<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root.'/public/icons';
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);

    $brand = imagecolorallocate($image, 79, 70, 229);
    $brandDark = imagecolorallocate($image, 67, 56, 202);
    $white = imagecolorallocate($image, 255, 255, 255);

    $radius = (int) round($size * 0.22);
    drawRoundedRect($image, 0, 0, $size - 1, $size - 1, $radius, $brand);

    $inset = (int) round($size * 0.125);
    drawRoundedRect(
        $image,
        $inset,
        $inset,
        $size - 1 - $inset,
        $size - 1 - $inset,
        (int) round($radius * 0.72),
        $brandDark
    );

    $font = 5;
    $text = 'K';
    $fontPath = 'C:\\Windows\\Fonts\\arialbd.ttf';
    if ($size >= 96 && is_file($fontPath)) {
        $fontSize = $size * 0.42;
        $box = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);
        $x = (int) (($size - $textWidth) / 2);
        $y = (int) (($size + $textHeight) / 2) - (int) ($size * 0.04);
        imagettftext($image, $fontSize, 0, $x, $y, $white, $fontPath, $text);
    } elseif ($size >= 128) {
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = (int) (($size - $textWidth) / 2);
        $y = (int) (($size - $textHeight) / 2);
        imagestring($image, $font, $x, $y, $text, $white);
    }

    $path = sprintf('%s/icon-%d.png', $outDir, $size);
    imagepng($image, $path);
    imagedestroy($image);

    echo "Wrote {$path}\n";
}

copy($outDir.'/icon-192.png', $outDir.'/apple-touch-icon.png');
copy($outDir.'/icon-192.png', $root.'/public/favicon.png');

echo "Done.\n";

function drawRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
