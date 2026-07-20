<?php

/**
 * Generates the COGS app icon set (icon, adaptive foreground, splash) at 1024x1024.
 * Run: php mobile/scripts/generate-app-icon.php
 */

$size = 1024;
$assets = __DIR__ . '/../assets';

$fontCandidates = [
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/segoeuib.ttf',
    'C:/Windows/Fonts/Arialbd.ttf',
];
$font = null;
foreach ($fontCandidates as $candidate) {
    if (is_file($candidate)) {
        $font = $candidate;
        break;
    }
}
if ($font === null) {
    fwrite(STDERR, "No bold TTF font found.\n");
    exit(1);
}

function allocate($img, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');
    return imagecolorallocatealpha(
        $img,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        $alpha
    );
}

function roundedRect($img, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    $d = $radius * 2;
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $d, $d, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $d, $d, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $d, $d, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $d, $d, $color);
}

function centeredText($img, string $font, string $text, float $fontSize, int $color, int $canvas): void
{
    $box = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = $box[2] - $box[0];
    $textHeight = $box[1] - $box[7];
    $x = (int) (($canvas - $textWidth) / 2 - $box[0]);
    $y = (int) (($canvas - $textHeight) / 2 - $box[7]);
    imagettftext($img, $fontSize, 0, $x, $y, $color, $font, $text);
}

// ── App icon + adaptive foreground (full-bleed indigo tile) ──────────────────
$tile = imagecreatetruecolor($size, $size);
imagesavealpha($tile, true);
imagealphablending($tile, true);

$brand = allocate($tile, '#5c4033');
imagefilledrectangle($tile, 0, 0, $size, $size, $brand);

$inner = allocate($tile, '#4338ca');
$pad = (int) ($size * 0.17);
roundedRect($tile, $pad, $pad, $size - $pad, $size - $pad, (int) ($size * 0.14), $inner);

$white = allocate($tile, '#ffffff');
centeredText($tile, $font, 'C', $size * 0.5, $white, $size);

imagepng($tile, $assets . '/icon.png');
imagepng($tile, $assets . '/adaptive-icon.png');

// ── Splash icon (transparent background, white C only) ───────────────────────
$splash = imagecreatetruecolor($size, $size);
imagesavealpha($splash, true);
imagealphablending($splash, false);
$transparent = imagecolorallocatealpha($splash, 0, 0, 0, 127);
imagefilledrectangle($splash, 0, 0, $size, $size, $transparent);
imagealphablending($splash, true);
$splashWhite = allocate($splash, '#ffffff');
centeredText($splash, $font, 'C', $size * 0.62, $splashWhite, $size);
imagepng($splash, $assets . '/splash-icon.png');

echo "Icons generated: icon.png, adaptive-icon.png, splash-icon.png\n";
