<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\CogsNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberCogsRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $response->isSuccessful()
            && $request->user()?->hasModule(UserRole::Cogs)
        ) {
            CogsNavigation::rememberFromRequest(
                $request->route()?->getName() ?? '',
                $request->url(),
            );
        }

        return $response;
    }
}
