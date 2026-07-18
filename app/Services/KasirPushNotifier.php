<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Models\PosOrder;
use Illuminate\Support\Facades\Log;

class KasirPushNotifier
{
    public function __construct(
        private readonly ExpoPushService $expoPushService,
        private readonly FcmPushService $fcmPushService,
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
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'customer_name' => $customer,
            'speak_text' => $speakText,
        ];

        try {
            $fcmTokens = DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_FCM)
                ->pluck('token')
                ->all();

            if ($fcmTokens !== []) {
                $fcmSend = $this->fcmPushService->send($fcmTokens, $title, $body, $data);
                if (! ($fcmSend['ok'] ?? false)) {
                    Log::warning('Kasir FCM push tidak sukses', $fcmSend);
                }
            }

            $expoTokens = DevicePushToken::query()
                ->where('platform', DevicePushToken::PLATFORM_EXPO)
                ->pluck('token')
                ->all();

            if ($expoTokens === [] && $fcmTokens === []) {
                Log::warning('Kasir push: tidak ada token FCM/Expo. Buka APK kasir sekali setelah login.');
            }

            if ($expoTokens !== []) {
                $send = $this->expoPushService->send($expoTokens, $title, $body, $data);
                if (! ($send['ok'] ?? false)) {
                    Log::warning('Kasir Expo push tidak sukses', $send);
                }
            }

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
