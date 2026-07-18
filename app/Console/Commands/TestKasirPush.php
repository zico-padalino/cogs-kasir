<?php

namespace App\Console\Commands;

use App\Models\DevicePushToken;
use App\Services\ExpoPushService;
use Illuminate\Console\Command;

class TestKasirPush extends Command
{
    protected $signature = 'kasir:test-push {--message=Tes notifikasi kasir}';

    protected $description = 'Kirim push uji ke semua Expo token kasir yang terdaftar';

    public function handle(ExpoPushService $expoPushService): int
    {
        $tokens = DevicePushToken::query()
            ->where('platform', DevicePushToken::PLATFORM_EXPO)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            $this->error('Belum ada Expo push token. Buka app kasir (login) dulu sampai izin notifikasi diizinkan.');

            return self::FAILURE;
        }

        $this->info('Mengirim ke '.count($tokens).' perangkat…');

        $expoPushService->send(
            $tokens,
            'Tes notifikasi kasir',
            (string) $this->option('message'),
            [
                'type' => 'new_order',
                'order_id' => 0,
                'customer_name' => 'Tes',
                'speak_text' => 'Pesanan baru masuk, atas nama Tes.',
            ],
        );

        $this->info('Selesai. Cek HP (app boleh tertutup / terkunci).');

        return self::SUCCESS;
    }
}
