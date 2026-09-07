<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorPages
{
    /**
     * @return array{code: int, badge: string, title: string, message: string, tone: string, hint: ?string}
     */
    public static function content(int $code): array
    {
        $catalog = [
            400 => [
                'badge' => '400 · Bad Request',
                'title' => 'Permintaan tidak valid',
                'message' => 'Data yang dikirim tidak bisa diproses. Periksa kembali lalu coba lagi.',
                'tone' => 'amber',
            ],
            401 => [
                'badge' => '401 · Unauthorized',
                'title' => 'Perlu masuk dulu',
                'message' => 'Sesi Anda belum aktif. Silakan login untuk melanjutkan.',
                'tone' => 'amber',
            ],
            403 => [
                'badge' => '403 · Forbidden',
                'title' => 'Akses ditolak',
                'message' => 'Anda tidak punya izin untuk membuka halaman ini.',
                'tone' => 'rose',
            ],
            404 => [
                'badge' => '404 · Not Found',
                'title' => 'Halaman tidak ditemukan',
                'message' => 'Alamat yang dibuka tidak ada atau sudah dipindahkan.',
                'tone' => 'slate',
            ],
            405 => [
                'badge' => '405 · Method Not Allowed',
                'title' => 'Metode tidak diizinkan',
                'message' => 'Cara membuka halaman ini tidak didukung. Kembali lalu coba lagi.',
                'tone' => 'amber',
            ],
            408 => [
                'badge' => '408 · Request Timeout',
                'title' => 'Permintaan terlalu lama',
                'message' => 'Koneksi terputus sebelum selesai. Silakan coba lagi.',
                'tone' => 'amber',
                'hint' => 'Kalau berulang, periksa koneksi internet Anda.',
            ],
            419 => [
                'badge' => '419 · Page Expired',
                'title' => 'Halaman kedaluwarsa',
                'message' => 'Form sudah tidak berlaku (token keamanan habis). Muat ulang halaman lalu kirim lagi.',
                'tone' => 'amber',
            ],
            429 => [
                'badge' => '429 · Too Many Requests',
                'title' => 'Terlalu banyak permintaan',
                'message' => 'Tunggu sebentar lalu coba lagi. Sistem membatasi permintaan beruntun.',
                'tone' => 'amber',
                'hint' => 'Biasanya cukup tunggu 30–60 detik.',
            ],
            500 => [
                'badge' => '500 · Server Error',
                'title' => 'Terjadi gangguan sementara',
                'message' => 'Server tidak bisa menyelesaikan permintaan. Silakan coba lagi sebentar.',
                'tone' => 'rose',
            ],
            502 => [
                'badge' => '502 · Bad Gateway',
                'title' => 'Gateway bermasalah',
                'message' => 'Server perantara mendapat respons buruk. Coba lagi dalam beberapa saat.',
                'tone' => 'rose',
            ],
            503 => [
                'badge' => '503 · Service Unavailable',
                'title' => 'Layanan sedang tidak tersedia',
                'message' => 'Server sedang sibuk atau dalam pemeliharaan. Silakan coba lagi nanti.',
                'tone' => 'amber',
                'hint' => 'Kalau berulang, tunggu 10–20 detik lalu refresh.',
            ],
            504 => [
                'badge' => '504 · Gateway Timeout',
                'title' => 'Halaman terlalu lama dimuat',
                'message' => 'Server tidak sempat menyelesaikan permintaan tepat waktu.',
                'tone' => 'amber',
                'hint' => 'Kalau berulang, tunggu sebentar lalu refresh. Hosting mungkin sedang penuh.',
            ],
            520 => [
                'badge' => '520 · Web Server Error',
                'title' => 'Server memberi respons kosong / error',
                'message' => 'Hosting atau CDN menerima error tidak dikenal dari server aplikasi. Silakan coba lagi.',
                'tone' => 'rose',
                'hint' => 'Sering terjadi saat server PHP crash atau timeout di sisi hosting.',
            ],
            521 => [
                'badge' => '521 · Web Server Down',
                'title' => 'Server web tidak merespons',
                'message' => 'CDN tidak bisa menghubungi server. Coba lagi sebentar.',
                'tone' => 'rose',
            ],
            522 => [
                'badge' => '522 · Connection Timed Out',
                'title' => 'Koneksi ke server timeout',
                'message' => 'CDN tidak mendapat balasan dari hosting tepat waktu.',
                'tone' => 'amber',
            ],
            523 => [
                'badge' => '523 · Origin Unreachable',
                'title' => 'Server asal tidak terjangkau',
                'message' => 'CDN tidak bisa mencapai server hosting. Silakan coba lagi nanti.',
                'tone' => 'rose',
            ],
            524 => [
                'badge' => '524 · A Timeout Occurred',
                'title' => 'Timeout di CDN',
                'message' => 'Server terlalu lama merespons. Silakan coba lagi.',
                'tone' => 'amber',
            ],
        ];

        if (isset($catalog[$code])) {
            return array_merge(['code' => $code], $catalog[$code]);
        }

        $family = (int) floor($code / 100);

        return [
            'code' => $code,
            'badge' => $code.' · Error',
            'title' => match ($family) {
                4 => 'Permintaan tidak bisa diproses',
                5 => 'Terjadi gangguan di server',
                default => 'Terjadi kesalahan',
            },
            'message' => 'Silakan coba lagi atau kembali ke beranda.',
            'tone' => $family === 5 ? 'rose' : 'amber',
            'hint' => null,
        ];
    }

    /** @return array{retryUrl: string, productsUrl: string, homeUrl: string, loginUrl: string} */
    public static function links(?Request $request = null): array
    {
        try {
            $request ??= app()->bound('request') ? request() : null;
        } catch (Throwable) {
            $request = null;
        }

        $previous = null;
        $current = null;

        try {
            $current = $request?->fullUrl();
            $prev = url()->previous();
            if ($prev && $current && $prev !== $current) {
                $previous = $prev;
            }
        } catch (Throwable) {
            $previous = null;
            $current = null;
        }

        return [
            'retryUrl' => $previous ?: ($current ?: url('/')),
            'productsUrl' => url('/products'),
            'homeUrl' => url('/'),
            'loginUrl' => url('/'),
        ];
    }

    public static function statusFrom(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }
}
