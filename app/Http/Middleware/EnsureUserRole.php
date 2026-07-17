<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }

            return redirect()->guest(route('home'));
        }

        $required = UserRole::tryFrom($role);

        if (! $required || ! $user->hasModule($required)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses modul '.$role.'.',
                    'code' => 'FORBIDDEN_MODULE',
                ], 403);
            }

            return redirect()->to($user->homeUrl());
        }

        return $next($request);
    }
}
