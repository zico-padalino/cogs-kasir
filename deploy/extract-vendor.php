<?php

declare(strict_types=1);

/**
 * Extract vendor-deploy.zip di root project (sejajar app/, vendor/).
 * URL contoh (document root = public_html):
 *   https://kedaitjoan.online/extract-vendor.php?token=TOKEN
 *
 * Token diganti saat deploy (placeholder __VENDOR_EXTRACT_TOKEN__).
 */
$expected = '__VENDOR_EXTRACT_TOKEN__';
$given = (string) ($_GET['token'] ?? '');

header('Content-Type: text/plain; charset=UTF-8');

if ($expected === '' || $expected === '__VENDOR_EXTRACT_TOKEN__' || $given === '' || ! hash_equals($expected, $given)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$root = __DIR__;
$zipFile = $root.DIRECTORY_SEPARATOR.'vendor-deploy.zip';

if (! is_file($zipFile)) {
    http_response_code(404);
    echo 'vendor-deploy.zip not found in '.$root;
    exit;
}

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive extension is not enabled on this host.';
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    http_response_code(500);
    echo 'Unable to open vendor-deploy.zip';
    exit;
}

if ($zip->extractTo($root) !== true) {
    $zip->close();
    http_response_code(500);
    echo 'Extract failed';
    exit;
}

$zip->close();
@unlink($zipFile);
@unlink(__FILE__);

echo 'Vendor extracted OK';
