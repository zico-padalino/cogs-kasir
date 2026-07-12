<?php

declare(strict_types=1);

/**
 * Diletakkan di ROOT project (sejajar app/, public/, deploy.zip).
 * URL (document root = public_html): https://domain/extract-deploy.php?token=...
 */
$expected = '__DEPLOY_TOKEN__';
$given = (string) ($_GET['token'] ?? '');

if ($given === '' || ! hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

$root = __DIR__;
$zipFile = $root.DIRECTORY_SEPARATOR.'deploy.zip';

if (! is_file($zipFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'deploy.zip not found in '.$root;
    exit;
}

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ZipArchive extension is not enabled on this host.';
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to open deploy.zip';
    exit;
}

if ($zip->extractTo($root) !== true) {
    $zip->close();
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Extract failed';
    exit;
}

$zip->close();
@unlink($zipFile);
@unlink(__FILE__);

header('Content-Type: text/plain; charset=UTF-8');
echo 'Deploy extracted OK';
