<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    public function manifest(string $app): JsonResponse
    {
        $config = match ($app) {
            'kasir' => [
                'name' => config('pos.shop_name', 'Coffee & Kitchen').' — Kasir',
                'short_name' => 'Kasir POS',
                'description' => 'Point of sale mobile untuk kasir.',
                'start_url' => '/kasir',
                'scope' => '/kasir',
            ],
            'order' => [
                'name' => config('pos.shop_name', 'Coffee & Kitchen').' — Pesan',
                'short_name' => 'Pesan Online',
                'description' => 'Pemesanan menu dari ponsel.',
                'start_url' => '/pesan',
                'scope' => '/pesan',
            ],
            default => abort(404),
        };

        $logoUrl = ShopSettings::logoUrl();

        if ($logoUrl) {
            $icons = collect([192, 512])
                ->map(fn (int $size) => [
                    'src' => $logoUrl,
                    'sizes' => "{$size}x{$size}",
                    'type' => 'image/png',
                    'purpose' => 'any',
                ])
                ->values()
                ->all();

            $maskable = [
                [
                    'src' => $logoUrl,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ];
        } else {
            $icons = collect([72, 96, 128, 144, 152, 192, 384, 512])
                ->map(fn (int $size) => [
                    'src' => asset("icons/icon-{$size}.png"),
                    'sizes' => "{$size}x{$size}",
                    'type' => 'image/png',
                    'purpose' => 'any',
                ])
                ->values()
                ->all();

            $maskable = [
                [
                    'src' => asset('icons/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ];
        }

        return response()->json([
            'id' => $config['scope'],
            'name' => $config['name'],
            'short_name' => $config['short_name'],
            'description' => $config['description'],
            'start_url' => $config['start_url'],
            'scope' => $config['scope'],
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => config('pos.pwa.background_color', '#f1f5f9'),
            'theme_color' => config('pos.pwa.theme_color', '#4f46e5'),
            'lang' => 'id',
            'dir' => 'ltr',
            'categories' => ['business', 'food'],
            'icons' => array_merge($icons, $maskable),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
