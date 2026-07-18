<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Models\PosOrder;
use Illuminate\Support\Facades\Log;

class KasirPushNotifier
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
        private readonly WebPushService $webPushService,
    ) {}

    public function notifyNewOnlineOrder(PosOrder $order): void
    {
        if (! config('pos.push.enabled', true)) {
            return;
        }

        $customer = trim((string) ($order->customer_note ?: 'Pelanggan'));
        $orderType = $order->order_type?->value ?? 'online';
        $title = 'Pesanan baru masuk';
        $body = "Atas nama {$customer}".($orderType ? " · {$orderType}" : '');
        $speakText = "Pesanan baru masuk, atas nama {$customer}.";

        $data = [
            'type' => 'new_order',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $customer,
            'speak_text' => $speakText,
        ];

        try {
            $expoTokens = DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_EXPO)
                ->pluck('token')
                ->all();

            if ($expoTokens === []) {
                Log::warning('Kasir push: tidak ada Expo token terdaftar. Buka app kasir sekali setelah login agar token tersimpan.');
            }

            $this->expoPushService->send($expoTokens, $title, $body, $data);

            $webSubs = DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_WEB)
                ->get()
                ->all();

            $this->webPushService->sendWakeUp($webSubs);
        } catch (\Throwable $e) {
            Log::warning('Kasir push notifier failed: '.$e->getMessage());
        }
    }
}
