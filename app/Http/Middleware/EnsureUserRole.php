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
            return redirect()->route('home');
        }

        $required = UserRole::tryFrom($role);

        if (! $required || ! $user->hasModule($required)) {
            if ($user->accessibleModules() !== []) {
                return redirect()->route('hub');
            }

            return redirect()->route('home');
        }

        return $next($request);
    }
}
