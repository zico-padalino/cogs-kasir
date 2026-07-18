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

        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => DevicePushToken::PLATFORM_EXPO,
                'token_hash' => DevicePushToken::hashToken($validated['token']),
            ],
            [
                'token' => $validated['token'],
                'user_id' => $request->user()?->id,
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Push token tersimpan.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
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

        $expoPushService->send(
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

        return response()->json([
            'message' => 'Push uji dikirim ke '.count($tokens).' perangkat. Tutup app / kunci HP lalu cek notifikasi.',
            'data' => [
                'token_count' => count($tokens),
                'expo_reachable' => $expoReachable,
            ],
        ]);
    }
}
