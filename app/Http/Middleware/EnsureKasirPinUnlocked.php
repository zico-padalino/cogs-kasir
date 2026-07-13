<?php

namespace App\Http\Middleware;

use App\Support\KasirPin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKasirPinUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (KasirPin::isUnlocked()) {
            return $next($request);
        }

        KasirPin::lock();

        $wantsJson = $request->expectsJson()
            || $request->ajax()
            || $request->routeIs('kasir.pending.poll', 'kasir.pin.status');

        if ($wantsJson) {
            return response()->json([
                'message' => 'Sesi PIN habis. Masukkan PIN lagi.',
                'locked' => true,
                'unlocked' => false,
                'redirect' => route('kasir.pin.unlock'),
                'remaining_seconds' => 0,
            ], 423);
        }

        return redirect()
            ->guest(route('kasir.pin.unlock'))
            ->with('error', 'Sesi PIN habis ('.KasirPin::idleMinutes().' menit). Masukkan PIN lagi.');
    }
}
