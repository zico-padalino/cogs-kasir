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

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Kasir terkunci. Masukkan PIN terlebih dahulu.',
                'locked' => true,
                'redirect' => route('kasir.pin.unlock'),
            ], 423);
        }

        return redirect()
            ->guest(route('kasir.pin.unlock'))
            ->with('error', 'Masukkan PIN kasir untuk melanjutkan.');
    }
}
