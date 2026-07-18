<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use App\Services\ExpoPushService;
use App\Services\FcmPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'in:expo,fcm'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'client' => ['nullable', 'in:expo_go,standalone'],
        ]);

        $userId = $request->user()?->id;
        $client = $validated['client'] ?? null;
        $platform = $validated['platform']
            ?? (($client === 'standalone') ? DevicePushToken::PLATFORM_FCM : DevicePushToken::PLATFORM_EXPO);

        $deviceName = $validated['device_name'] ?? null;
        if ($client === 'standalone' && is_string($deviceName) && ! str_starts_with($deviceName, '[APK]')) {
            $deviceName = '[APK] '.$deviceName;
        } elseif ($client === 'expo_go' && is_string($deviceName) && ! str_starts_with($deviceName, '[ExpoGo]')) {
            $deviceName = '[ExpoGo] '.$deviceName;
        }

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => $platform,
                'token_hash' => DevicePushToken::hashToken($validated['token']),
            ],
            [
                'token' => $validated['token'],
                'user_id' => $userId,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Push token tersimpan.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'client' => $client,
                'token_preview' => substr($validated['token'], 0, 28).'…',
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'in:expo,fcm'],
        ]);

        $query = DevicePushToken::query()
            ->where('token_hash', DevicePushToken::hashToken($validated['token']));

        if (! empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        } else {
            $query->whereIn('platform', [
                DevicePushToken::PLATFORM_EXPO,
                DevicePushToken::PLATFORM_FCM,
            ]);
        }

        $query->delete();

        return response()->json([
            'message' => 'Push token dihapus.',
        ]);
    }

    /** Uji: server → FCM/Expo → HP (app boleh tertutup). */
    public function test(Request $request, ExpoPushService $expoPushService, FcmPushService $fcmPushService): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'in:expo,fcm'],
            'client' => ['nullable', 'in:expo_go,standalone'],
        ]);

        $client = $validated['client'] ?? null;
        $platform = $validated['platform']
            ?? (($client === 'standalone') ? DevicePushToken::PLATFORM_FCM : DevicePushToken::PLATFORM_EXPO);

        $tokens = [];
        if (! empty($validated['token'])) {
            $tokens = [$validated['token']];

            DevicePushToken::query()->updateOrCreate(
                [
                    'platform' => $platform,
                    'token_hash' => DevicePushToken::hashToken($validated['token']),
                ],
                [
                    'token' => $validated['token'],
                    'user_id' => $request->user()?->id,
                    'device_name' => $client === 'standalone' ? '[APK] tes' : ($client === 'expo_go' ? '[ExpoGo] tes' : null),
                    'last_used_at' => now(),
                ],
            );
        } else {
            $tokens = DevicePushToken::query()
                ->where('platform', $platform)
                ->when($request->user()?->id, fn ($q, $userId) => $q->where('user_id', $userId))
                ->pluck('token')
                ->all();
        }

        if ($tokens === []) {
            return response()->json([
                'message' => $platform === DevicePushToken::PLATFORM_FCM
                    ? 'Belum ada FCM token APK. Buka APK sekali setelah login/izin notifikasi.'
                    : 'Belum ada Expo token di database.',
                'code' => 'NO_PUSH_TOKEN',
            ], 422);
        }

        $payload = [
            'type' => 'new_order',
            'order_id' => '0',
            'customer_name' => 'Tes Server',
            'speak_text' => 'Pesanan baru masuk, atas nama Tes Server.',
            'client' => (string) ($client ?? ''),
        ];

        if ($platform === DevicePushToken::PLATFORM_FCM) {
            if (! $fcmPushService->isConfigured()) {
                return response()->json([
                    'message' => 'Server belum punya Firebase service account. Upload JSON ke storage/app/firebase/service-account.json lalu coba lagi.',
                    'code' => 'FCM_NOT_CONFIGURED',
                    'data' => [
                        'hint' => 'Firebase Console → Project settings → Service accounts → Generate new private key. Simpan sebagai storage/app/firebase/service-account.json di hosting.',
                    ],
                ], 503);
            }

            $send = $fcmPushService->send(
                $tokens,
                'Tes notifikasi kasir (APK/FCM)',
                'Push FCM langsung — app boleh tertutup.',
                $payload,
            );

            $firstError = $send['errors'][0] ?? null;

            return response()->json([
                'message' => $send['ok']
                    ? 'Push FCM OK ke '.$send['sent'].' perangkat. Tutup app / kunci HP lalu cek tray.'
                    : ('Push FCM gagal: '.($firstError ?: 'unknown')),
                'data' => [
                    'platform' => 'fcm',
                    'token_count' => count($tokens),
                    'token_previews' => array_map(fn (string $t) => substr($t, 0, 24).'…', $tokens),
                    'client' => $client,
                    'send' => $send,
                    'hint' => $send['ok']
                        ? 'Ini jalur FCM langsung (bukan Expo Push). Tutup APK dari Recent Apps.'
                        : null,
                ],
            ], $send['ok'] ? 200 : 502);
        }

        try {
            $ping = Http::timeout(8)->get('https://exp.host');
            $expoReachable = $ping->successful() || $ping->status() < 500;
        } catch (\Throwable $e) {
            Log::warning('Expo unreachable from server: '.$e->getMessage());

            return response()->json([
                'message' => 'Server hosting tidak bisa menghubungi Expo (exp.host).',
                'code' => 'EXPO_UNREACHABLE',
                'error' => $e->getMessage(),
            ], 503);
        }

        $send = $expoPushService->send(
            $tokens,
            'Tes notifikasi kasir (Expo Go)',
            'Push Expo — cocok untuk Expo Go saja.',
            $payload,
        );

        $firstError = $send['errors'][0] ?? null;

        return response()->json([
            'message' => $send['ok']
                ? 'Push Expo OK ke '.count($tokens).' token. Tutup app / kunci HP lalu cek tray.'
                : ('Push gagal: '.($firstError ?: 'unknown')),
            'data' => [
                'platform' => 'expo',
                'token_count' => count($tokens),
                'token_previews' => array_map(fn (string $t) => substr($t, 0, 32).'…', $tokens),
                'client' => $client,
                'expo_reachable' => $expoReachable,
                'send' => $send,
                'hint' => 'Sukses di Expo Go tidak membuktikan APK. Untuk APK pakai jalur FCM + service account di server.',
            ],
        ], $send['ok'] ? 200 : 502);
    }
}
