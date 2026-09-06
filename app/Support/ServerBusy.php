<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ServerBusy
{
    public static function isTimeout(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'maximum execution time')
            || str_contains($message, 'max execution time')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'execution time exceeded')
            || str_contains($message, 'network connect timeout')
            || str_contains($message, 'gateway time-out')
            || str_contains($message, '504')) {
            return true;
        }

        if ($e instanceof HttpExceptionInterface) {
            return in_array($e->getStatusCode(), [408, 504], true);
        }

        return false;
    }

    public static function isServerBusy(Throwable $e): bool
    {
        if (self::isTimeout($e)) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'allowed memory size')
            || str_contains($message, 'out of memory')
            || str_contains($message, 'too many connections')
            || str_contains($message, 'server has gone away');
    }

    /** @return array{retryUrl: ?string, productsUrl: string, homeUrl: string} */
    public static function pageData(?Request $request = null): array
    {
        $request ??= request();
        $retryUrl = null;

        if ($request) {
            $path = trim($request->path(), '/');
            if (preg_match('#^products/(\d+)$#', $path, $m)) {
                $retryUrl = url('/products/'.$m[1]);
            } elseif ($path === 'products/create' || $request->routeIs('products.create')) {
                $retryUrl = url('/products/create');
            } elseif ($request->routeIs('products.store') || $request->routeIs('products.bom.store')) {
                $retryUrl = url()->previous();
            }
        }

        return [
            'retryUrl' => $retryUrl,
            'productsUrl' => url('/products'),
            'homeUrl' => url('/'),
        ];
    }
}
