<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

// Stub Sanctum sebelum boot (shared hosting sering tanpa paket di vendor).
if (is_file(__DIR__.'/app/Support/sanctum_fallback.php')) {
    require_once __DIR__.'/app/Support/sanctum_fallback.php';
}

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

// Document root = public_html (bukan public_html/public).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
