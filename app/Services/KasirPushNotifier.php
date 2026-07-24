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
        $customer = trim((string) ($order->customer_note ?: 'Pelanggan'));
        $orderType = $order->order_type?->value ?? 'online';
        $title = 'Pesanan baru masuk';
        $body = "Atas nama {$customer}".($orderType ? " · {$orderType}" : '');
        $speakText = "Pesanan baru masuk, atas nama {$customer}.";

        $this->dispatch(
            title: $title,
            body: $body,
            data: [
                'type' => 'new_order',
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'customer_name' => $customer,
                'speak_text' => $speakText,
            ],
            wakeWeb: true,
        );
    }

    /**
     * @param  list<array{id: int, name: string, type: string, type_label: string, sku?: string|null}>  $items
     */
    public function notifyStockOut(array $items, ?PosOrder $order = null): void
    {
        if ($items === []) {
            return;
        }

        $names = collect($items)
            ->map(fn (array $item) => $item['name'].' ('.$item['type_label'].')')
            ->values()
            ->all();

        $list = implode(', ', $names);
        $orderSuffix = $order?->order_number ? " · pesanan {$order->order_number}" : '';
        $title = 'Stok habis';
        $body = $list.$orderSuffix;
        $speakText = 'Stok habis: '.implode(', ', array_column($items, 'name')).'.';

        $this->dispatch(
            title: $title,
            body: $body,
            data: [
                'type' => 'stock_out',
                'order_id' => $order ? (string) $order->id : '',
                'order_number' => $order ? (string) $order->order_number : '',
                'product_names' => $list,
                'speak_text' => $speakText,
            ],
            wakeWeb: true,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $title, string $body, array $data, bool $wakeWeb = false): void
    {
        if (! config('pos.push.enabled', true)) {
            return;
        }

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

            if ($wakeWeb) {
                $webSubs = DevicePushToken::query()
                    ->where('platform', DevicePushToken::PLATFORM_WEB)
                    ->get()
                    ->all();

                $this->webPushService->sendWakeUp($webSubs);
            }
        } catch (\Throwable $e) {
            Log::warning('Kasir push notifier failed: '.$e->getMessage());
        }
    }
}
