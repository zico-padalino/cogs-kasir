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
            return redirect()->route('login');
        }

        $required = UserRole::tryFrom($role);

        if (! $required || $user->role !== $required) {
            return redirect()->route($user->role->homeRoute());
        }

        return $next($request);
    }
}
