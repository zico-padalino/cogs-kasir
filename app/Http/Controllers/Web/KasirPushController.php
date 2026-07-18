<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KasirPushController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'data' => [
                'public_key' => config('pos.push.vapid_public_key'),
                'enabled' => (bool) config('pos.push.enabled', true)
                    && filled(config('pos.push.vapid_public_key'))
                    && filled(config('pos.push.vapid_private_pem')),
            ],
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
        ]);

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => DevicePushToken::PLATFORM_WEB,
                'token_hash' => DevicePushToken::hashToken($validated['endpoint']),
            ],
            [
                'token' => $validated['endpoint'],
                'user_id' => $request->user()?->id,
                'web_p256dh' => $validated['keys']['p256dh'],
                'web_auth' => $validated['keys']['auth'],
                'device_name' => 'web-kasir',
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Langganan notifikasi web aktif.',
            'data' => ['id' => $token->id],
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
        ]);

        DevicePushToken::query()
            ->where('platform', DevicePushToken::PLATFORM_WEB)
            ->where('token_hash', DevicePushToken::hashToken($validated['endpoint']))
            ->delete();

        return response()->json([
            'message' => 'Langganan notifikasi web dihentikan.',
        ]);
    }
}
