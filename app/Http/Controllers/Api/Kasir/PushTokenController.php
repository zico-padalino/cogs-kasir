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
            'client' => ['nullable', 'in:expo_go,standalone'],
        ]);

        $userId = $request->user()?->id;
        $client = $validated['client'] ?? null;
        $deviceName = $validated['device_name'] ?? null;

        if ($client === 'standalone' && is_string($deviceName) && ! str_starts_with($deviceName, '[APK]')) {
            $deviceName = '[APK] '.$deviceName;
        } elseif ($client === 'expo_go' && is_string($deviceName) && ! str_starts_with($deviceName, '[ExpoGo]')) {
            $deviceName = '[ExpoGo] '.$deviceName;
        }

        // Jangan hapus token perangkat lain: Expo Go & APK punya token berbeda.
        // Kalau Expo Go menimpa APK, notifikasi hanya muncul di Expo Go.
        $token = DevicePushToken::query()->updateOrCreate(
            [
                'platform' => DevicePushToken::PLATFORM_EXPO,
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
        $validated = $request->validate([
            'token' => ['nullable', 'string', 'max:768'],
            'client' => ['nullable', 'in:expo_go,standalone'],
        ]);

        $tokens = [];
        if (! empty($validated['token'])) {
            // Prioritas: token perangkat yang sedang menekan tombol tes.
            $tokens = [$validated['token']];

            DevicePushToken::query()->updateOrCreate(
                [
                    'platform' => DevicePushToken::PLATFORM_EXPO,
                    'token_hash' => DevicePushToken::hashToken($validated['token']),
                ],
                [
                    'token' => $validated['token'],
                    'user_id' => $request->user()?->id,
                    'device_name' => ($validated['client'] ?? null) === 'standalone'
                        ? '[APK] tes'
                        : (($validated['client'] ?? null) === 'expo_go' ? '[ExpoGo] tes' : null),
                    'last_used_at' => now(),
                ],
            );
        } else {
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

        $clientLabel = match ($validated['client'] ?? null) {
            'standalone' => 'APK',
            'expo_go' => 'Expo Go',
            default => 'perangkat',
        };

        $send = $expoPushService->send(
            $tokens,
            'Tes notifikasi kasir ('.$clientLabel.')',
            'Push dari server kedaitjoan.online — app boleh tertutup.',
            [
                'type' => 'new_order',
                'order_id' => 0,
                'customer_name' => 'Tes Server',
                'speak_text' => 'Pesanan baru masuk, atas nama Tes Server.',
                'client' => $validated['client'] ?? null,
            ],
        );

        $firstError = $send['errors'][0] ?? null;
        $hint = null;
        $joinedErrors = implode(' ', $send['errors'] ?? []);
        if (str_contains($joinedErrors, 'InvalidCredentials') || str_contains((string) $firstError, 'InvalidCredentials')) {
            $hint = 'FCM belum aktif untuk APK. Di EAS: Android → FCM V1 service account. Lalu rebuild APK + install ulang. Expo Go tidak butuh ini.';
        } elseif (($validated['client'] ?? null) === 'expo_go') {
            $hint = 'Ini tes dari Expo Go. Notifikasi di Expo Go tidak membuktikan APK. Install APK hasil EAS, lalu tes lagi dari APK.';
        } elseif (($validated['client'] ?? null) === 'standalone' && ($send['ok'] ?? false)) {
            $hint = 'Tes dikirim ke token APK ini. Tutup app sepenuhnya (bukan hanya minimize), kunci HP, tunggu beberapa detik.';
        }

        return response()->json([
            'message' => $send['ok']
                ? 'Push uji OK ke '.count($tokens).' token ('.$clientLabel.'). Tutup app / kunci HP lalu cek tray notifikasi.'
                : ('Push gagal: '.($firstError ?: 'unknown')),
            'data' => [
                'token_count' => count($tokens),
                'token_previews' => array_map(
                    fn (string $t) => substr($t, 0, 32).'…',
                    $tokens,
                ),
                'client' => $validated['client'] ?? null,
                'expo_reachable' => $expoReachable,
                'send' => $send,
                'hint' => $hint,
            ],
        ], $send['ok'] ? 200 : 502);
    }
}
