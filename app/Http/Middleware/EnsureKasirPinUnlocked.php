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
            // Idle timer hanya diperpanjang oleh aktivitas nyata (sentuhan / aksi user),
            // bukan oleh setiap request HTTP (polling/status).
            return $next($request);
        }

        KasirPin::lock();

        $wantsJson = $request->expectsJson()
            || $request->ajax()
            || $request->is('api/*')
            || $request->routeIs('kasir.pending.poll', 'kasir.pin.status');

        if ($wantsJson) {
            return response()->json([
                'message' => 'Sesi PIN habis. Masukkan PIN lagi.',
                'code' => 'PIN_LOCKED',
                'locked' => true,
                'unlocked' => false,
                'redirect' => $request->is('api/*') ? null : route('kasir.pin.unlock'),
                'remaining_seconds' => 0,
            ], 423);
        }

        return redirect()
            ->guest(route('kasir.pin.unlock'))
            ->with('error', 'Sesi PIN habis ('.KasirPin::idleMinutes().' menit). Masukkan PIN lagi.');
    }
}
