<?php

namespace App\Services;

use App\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, ticket_count: int, errors: list<string>, tickets: list<array<string, mixed>>}
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        $result = [
            'ok' => false,
            'ticket_count' => 0,
            'errors' => [],
            'tickets' => [],
        ];

        if ($tokens === []) {
            $result['errors'][] = 'Tidak ada Expo token.';

            return $result;
        }

        $messages = array_map(
            fn (string $token) => [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'channelId' => 'kasir-orders',
                'priority' => 'high',
                '_displayInForeground' => true,
                'mutableContent' => true,
                'interruptionLevel' => 'timeSensitive',
                'ttl' => 3600,
            ],
            $tokens,
        );

        $allOk = true;

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post(self::ENDPOINT, $chunk);

                if (! $response->successful()) {
                    $allOk = false;
                    $msg = 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300);
                    $result['errors'][] = $msg;
                    Log::warning('Expo push failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $tickets = $response->json('data') ?? [];
                if (! is_array($tickets)) {
                    $tickets = [$tickets];
                }

                // Normalisasi: satu ticket vs list
                if (isset($tickets['status'])) {
                    $tickets = [$tickets];
                }

                foreach ($tickets as $ticket) {
                    if (! is_array($ticket)) {
                        continue;
                    }
                    $result['tickets'][] = $ticket;
                    $result['ticket_count']++;

                    if (($ticket['status'] ?? null) === 'error') {
                        $allOk = false;
                        $error = is_array($ticket['details'] ?? null)
                            ? (string) ($ticket['details']['error'] ?? 'error')
                            : 'error';
                        $message = (string) ($ticket['message'] ?? $error);
                        $result['errors'][] = $error.': '.$message;
                        Log::warning('Expo push ticket error', [
                            'message' => $message,
                            'error' => $error,
                        ]);
                    }
                }

                $this->pruneInvalidTokens($tickets);
            } catch (\Throwable $e) {
                $allOk = false;
                $result['errors'][] = $e->getMessage();
                Log::warning('Expo push exception: '.$e->getMessage());
            }
        }

        $result['ok'] = $allOk && $result['ticket_count'] > 0 && $result['errors'] === [];

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     */
    private function pruneInvalidTokens(array $tickets): void
    {
        foreach ($tickets as $ticket) {
            $details = $ticket['details'] ?? null;
            $error = is_array($details) ? ($details['error'] ?? null) : null;
            $expoToken = is_array($details) ? ($details['expoPushToken'] ?? null) : null;

            if ($error === 'DeviceNotRegistered' && is_string($expoToken) && $expoToken !== '') {
                DevicePushToken::query()
                    ->where('platform', DevicePushToken::PLATFORM_EXPO)
                    ->where('token_hash', DevicePushToken::hashToken($expoToken))
                    ->delete();
            }
        }
    }
}
