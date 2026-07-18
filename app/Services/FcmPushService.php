<?php

namespace App\Services;

use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim push langsung ke Android via FCM HTTP v1 (tanpa Expo Push API).
 * Butuh service account Firebase di storage/app/firebase/service-account.json
 * atau env FIREBASE_SERVICE_ACCOUNT_JSON / FIREBASE_CREDENTIALS.
 */
class FcmPushService
{
    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, sent: int, errors: list<string>}
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $result = ['ok' => false, 'sent' => 0, 'errors' => []];

        if ($tokens === []) {
            $result['errors'][] = 'Tidak ada FCM token.';

            return $result;
        }

        $credentials = $this->loadCredentials();
        if ($credentials === null) {
            $result['errors'][] = 'Firebase service account belum di-set. Simpan JSON ke storage/app/firebase/service-account.json (Project settings → Service accounts → Generate new private key).';

            return $result;
        }

        try {
            $accessToken = $this->getAccessToken($credentials);
        } catch (\Throwable $e) {
            $result['errors'][] = 'Gagal OAuth FCM: '.$e->getMessage();
            Log::warning('FCM OAuth failed: '.$e->getMessage());

            return $result;
        }

        $projectId = (string) ($credentials['project_id'] ?? '');
        if ($projectId === '') {
            $result['errors'][] = 'project_id kosong di service account.';

            return $result;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $allOk = true;
        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'kasir-orders',
                            'sound' => 'default',
                            'default_vibrate_timings' => true,
                            'notification_priority' => 'PRIORITY_MAX',
                            'visibility' => 'PUBLIC',
                        ],
                    ],
                ],
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $result['sent']++;
                    continue;
                }

                $allOk = false;
                $bodyText = mb_substr($response->body(), 0, 400);
                $result['errors'][] = 'HTTP '.$response->status().': '.$bodyText;
                Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);

                $errorCode = data_get($response->json(), 'error.details.0.errorCode')
                    ?? data_get($response->json(), 'error.status');
                if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)
                    || str_contains($bodyText, 'UNREGISTERED')
                    || str_contains($bodyText, 'Requested entity was not found')) {
                    DevicePushToken::query()
                        ->where('platform', DevicePushToken::PLATFORM_FCM)
                        ->where('token_hash', DevicePushToken::hashToken($token))
                        ->delete();
                }
            } catch (\Throwable $e) {
                $allOk = false;
                $result['errors'][] = $e->getMessage();
                Log::warning('FCM exception: '.$e->getMessage());
            }
        }

        $result['ok'] = $allOk && $result['sent'] > 0 && $result['errors'] === [];

        return $result;
    }

    public function isConfigured(): bool
    {
        return $this->loadCredentials() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCredentials(): ?array
    {
        $json = env('FIREBASE_SERVICE_ACCOUNT_JSON');
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded['private_key'], $decoded['client_email'])) {
                return $decoded;
            }
        }

        $path = env('FIREBASE_CREDENTIALS')
            ?: storage_path('app/firebase/service-account.json');

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! isset($decoded['private_key'], $decoded['client_email'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function getAccessToken(array $credentials): string
    {
        $cacheKey = 'fcm_access_token_'.hash('sha256', (string) ($credentials['client_email'] ?? ''));

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);
        if ($privateKey === false) {
            throw new \RuntimeException('private_key Firebase tidak valid.');
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new \RuntimeException('Gagal menandatangani JWT Firebase.');
        }

        $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }

        $accessToken = (string) $response->json('access_token');
        if ($accessToken === '') {
            throw new \RuntimeException('access_token kosong dari Google OAuth.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        Cache::put($cacheKey, $accessToken, max(60, $expiresIn - 120));

        return $accessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
