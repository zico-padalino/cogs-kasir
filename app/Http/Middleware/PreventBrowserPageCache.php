<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBrowserPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldApply($request, $response)) {
            return $response;
        }

        // Hindari bfcache/cache browser menyimpan halaman sesi/absensi lama.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Surrogate-Control', 'no-store');

        return $response;
    }

    private function shouldApply(Request $request, Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        $isHtml = str_contains(strtolower($contentType), 'text/html');
        if (! $isHtml) {
            return false;
        }

        if ($request->routeIs('attendance.*', 'login', 'home', 'hub', 'hub.switch', 'logout')) {
            return true;
        }

        return $request->user() !== null;
    }
}
