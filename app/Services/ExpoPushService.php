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
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return;
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
            ],
            $tokens,
        );

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::acceptJson()
                    ->timeout(12)
                    ->post(self::ENDPOINT, $chunk);

                if (! $response->successful()) {
                    Log::warning('Expo push failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $this->pruneInvalidTokens($response->json('data') ?? []);
            } catch (\Throwable $e) {
                Log::warning('Expo push exception: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     */
    private function pruneInvalidTokens(array $tickets): void
    {
        foreach ($tickets as $ticket) {
            $details = $ticket['details'] ?? null;
            $error = is_array($details) ? ($details['error'] ?? null) : null;

            if ($error === 'DeviceNotRegistered' && isset($details['expoPushToken'])) {
                DevicePushToken::query()
                    ->where('platform', DevicePushToken::PLATFORM_EXPO)
                    ->where('token', $details['expoPushToken'])
                    ->delete();
            }
        }
    }
}
