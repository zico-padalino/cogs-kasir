<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs(
            'password.edit',
            'password.update',
            'logout',
            'home',
            'login',
            'login.store',
        )) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Anda harus mengubah password sementara terlebih dahulu.',
                    'redirect' => route('password.edit'),
                ], 403);
            }

            return redirect()
                ->route('password.edit')
                ->with('error', 'Akun baru wajib mengganti password sementara sebelum lanjut.');
        }

        return $next($request);
    }
}
