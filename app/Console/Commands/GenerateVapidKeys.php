<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateVapidKeys extends Command
{
    protected $signature = 'kasir:vapid-keys';

    protected $description = 'Generate VAPID keys for Web Push (kasir notifications when browser closed)';

    public function handle(): int
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            $this->error('openssl_pkey_new gagal. Pastikan ekstensi OpenSSL aktif.');

            return self::FAILURE;
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicDer = $details['ec']['key'] ?? null;

        if (! is_string($publicDer) || $publicDer === '') {
            // Fallback: parse PEM public
            $publicPem = $details['key'] ?? '';
            $publicDer = $this->pemToDer($publicPem);
        }

        // Uncompressed EC point is last 65 bytes of SPKI for P-256, or the raw key field.
        $publicKey = $details['ec']['x'] ?? null;
        $y = $details['ec']['y'] ?? null;

        if (is_string($publicKey) && is_string($y)) {
            $uncompressed = "\x04".$publicKey.$y;
        } else {
            $uncompressed = substr($publicDer, -65);
        }

        $publicBase64Url = rtrim(strtr(base64_encode($uncompressed), '+/', '-_'), '=');

        $this->line('Tambahkan ke file .env:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$publicBase64Url);
        $this->line('VAPID_PRIVATE_PEM="'.str_replace("\n", '\\n', trim($privatePem)).'"');
        $this->line('VAPID_SUBJECT=mailto:admin@kedaitjoan.online');
        $this->newLine();
        $this->info('Lalu jalankan: php artisan config:clear');

        return self::SUCCESS;
    }

    private function pemToDer(string $pem): string
    {
        $lines = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/', '', $pem) ?? '';

        return base64_decode($lines, true) ?: '';
    }
}
