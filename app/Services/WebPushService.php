<?php

namespace App\Services;

use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Web Push wake-up (VAPID, no encrypted payload).
 * Service worker shows a local notification after the push event.
 */
class WebPushService
{
    /**
     * @param  list<DevicePushToken>  $subscriptions
     */
    public function sendWakeUp(array $subscriptions): void
    {
        $publicKey = (string) config('pos.push.vapid_public_key');
        $privatePem = (string) config('pos.push.vapid_private_pem');
        $subject = (string) config('pos.push.vapid_subject', 'mailto:admin@localhost');

        if ($publicKey === '' || $privatePem === '' || $subscriptions === []) {
            return;
        }

        $privateKey = openssl_pkey_get_private($privatePem);

        if ($privateKey === false) {
            Log::warning('Web push: VAPID private key PEM tidak valid.');

            return;
        }

        foreach ($subscriptions as $subscription) {
            try {
                $endpoint = $subscription->token;
                $audience = $this->audienceFromEndpoint($endpoint);
                $jwt = $this->createVapidJwt($audience, $subject, $privateKey);

                $response = Http::withHeaders([
                    'TTL' => '120',
                    'Urgency' => 'high',
                    'Authorization' => 'vapid t='.$jwt.', k='.$publicKey,
                ])
                    ->timeout(12)
                    ->withBody('', 'application/octet-stream')
                    ->post($endpoint);

                if (in_array($response->status(), [404, 410], true)) {
                    $subscription->delete();

                    continue;
                }

                if (! $response->successful()) {
                    Log::warning('Web push failed', [
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Web push exception: '.$e->getMessage());
            }
        }
    }

    private function audienceFromEndpoint(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }

    /** @param  \OpenSSLAsymmetricKey  $privateKey */
    private function createVapidJwt(string $audience, string $subject, $privateKey): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $subject,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$payload;
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new \RuntimeException('Gagal menandatangani VAPID JWT.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($this->derToJose($signature));
    }

    private function derToJose(string $der): string
    {
        $offset = 0;

        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('Signature DER invalid.');
        }

        $seqLen = ord($der[$offset++]);
        if ($seqLen & 0x80) {
            $n = $seqLen & 0x7F;
            $offset += $n;
        }

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('Signature DER missing R.');
        }

        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('Signature DER missing S.');
        }

        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        return $this->pad32($r).$this->pad32($s);
    }

    private function pad32(string $value): string
    {
        $value = ltrim($value, "\x00");

        if (strlen($value) > 32) {
            $value = substr($value, -32);
        }

        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
