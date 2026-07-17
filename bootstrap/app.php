<?php

// Stub Sanctum agar boot tidak fatal di shared hosting tanpa paket di vendor.
require_once __DIR__.'/../app/Support/sanctum_fallback.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'cogs.route' => \App\Http\Middleware\RememberCogsRoute::class,
            'kasir.pin' => \App\Http\Middleware\EnsureKasirPinUnlocked::class,
            'api.attendance' => \App\Http\Middleware\EnsureApiAttendanceChecked::class,
            'auth.api' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsurePasswordChanged::class,
            \App\Http\Middleware\EnsureAttendanceChecked::class,
        ]);

        $middleware->trustProxies(at: '*');

        // Semua route ber-middleware auth (kasir/admin/cogs) wajib login.
        // Setelah login, user dikembalikan ke URL yang semula dibuka (intended).
        $middleware->redirectGuestsTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
