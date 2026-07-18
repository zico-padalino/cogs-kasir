<?php

namespace App\Http\Controllers\Api\Kasir;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use App\Services\ExpoPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:768'],
            'platform' => ['nullable', 'in:expo'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $userId = $request->user()?->id;
        $tokenHash = DevicePushToken::hashToken($validated['token']);

        // Satu user = satu token Expo aktif (hindari token Expo Go menimpa APK).
        if ($userId) {
            DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_EXPO)
                ->where('user_id', $userId)
                ->where('token_hash', '!=', $tokenHash)
                ->delete();
        }

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => DevicePushToken::PLATFORM_EXPO,
                'token_hash' => $tokenHash,
            ],
            [
                'token' => $validated['token'],
                'user_id' => $userId,
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Push token tersimpan.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'token_preview' => substr($validated['token'], 0, 28).'…',
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:768'],
        ]);

        DevicePushToken::query()
            ->where('platform', DevicePushToken::PLATFORM_EXPO)
            ->where('token_hash', DevicePushToken::hashToken($validated['token']))
            ->delete();

        return response()->json([
            'message' => 'Push token dihapus.',
        ]);
    }

    /** Uji: server → Expo → HP (app boleh tertutup). */
    public function test(Request $request, ExpoPushService $expoPushService): JsonResponse
    {
        $tokens = DevicePushToken::query()
            ->where('platform', DevicePushToken::PLATFORM_EXPO)
            ->when($request->user()?->id, fn ($q, $userId) => $q->where('user_id', $userId))
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            $tokens = DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_EXPO)
                ->pluck('token')
                ->all();
        }

        if ($tokens === []) {
            return response()->json([
                'message' => 'Belum ada Expo token di database.',
                'code' => 'NO_PUSH_TOKEN',
            ], 422);
        }

        // Cek dulu apakah hosting bisa akses Expo (sering diblok di shared hosting).
        try {
            $ping = Http::timeout(8)->get('https://exp.host');
            $expoReachable = $ping->successful() || $ping->status() < 500;
        } catch (\Throwable $e) {
            Log::warning('Expo unreachable from server: '.$e->getMessage());

            return response()->json([
                'message' => 'Server hosting tidak bisa menghubungi Expo (exp.host). Minta DomaiNesia buka akses HTTPS keluar ke exp.host.',
                'code' => 'EXPO_UNREACHABLE',
                'error' => $e->getMessage(),
            ], 503);
        }

        $send = $expoPushService->send(
            $tokens,
            'Tes notifikasi kasir',
            'Push dari server kedaitjoan.online — app boleh tertutup.',
            [
                'type' => 'new_order',
                'order_id' => 0,
                'customer_name' => 'Tes Server',
                'speak_text' => 'Pesanan baru masuk, atas nama Tes Server.',
            ],
        );

        $firstError = $send['errors'][0] ?? null;
        $hint = null;
        if (is_string($firstError) && str_contains($firstError, 'InvalidCredentials')) {
            $hint = 'FCM belum aktif di Expo/Firebase. Pastikan FCM V1 ter-upload di EAS dan Cloud Messaging API enabled di Google Cloud.';
        }

        return response()->json([
            'message' => $send['ok']
                ? 'Push uji OK ke '.count($tokens).' perangkat. Tutup app / kunci HP lalu cek tray notifikasi.'
                : ('Push gagal: '.($firstError ?: 'unknown')),
            'data' => [
                'token_count' => count($tokens),
                'token_previews' => array_map(
                    fn (string $t) => substr($t, 0, 32).'…',
                    $tokens,
                ),
                'expo_reachable' => $expoReachable,
                'send' => $send,
                'hint' => $hint,
            ],
        ], $send['ok'] ? 200 : 502);
    }
}
