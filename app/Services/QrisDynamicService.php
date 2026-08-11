<?php

namespace App\Services;

use App\Support\ShopSettings;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use RuntimeException;
use Throwable;

/**
 * Convert QRIS statis → dinamis (nominal ikut total), berdasarkan
 * https://github.com/verssache/qris-dinamis (MIT).
 */
class QrisDynamicService
{
    /**
     * @return array{
     *     enabled: bool,
     *     amount: int,
     *     amount_label: string,
     *     payload: string|null,
     *     qr_data_uri: string|null,
     *     merchant_name: string|null,
     *     fallback_image_url: string,
     *     mode: 'dynamic'|'static'
     * }
     */
    public function forAmount(float|int|string $amount): array
    {
        $rupiah = max(0, (int) round((float) $amount));
        $static = ShopSettings::qrisPayload();
        $fallback = ShopSettings::qrisUrl();

        if ($static === '' || $rupiah <= 0) {
            return [
                'enabled' => false,
                'amount' => $rupiah,
                'amount_label' => 'Rp '.number_format($rupiah, 0, ',', '.'),
                'payload' => null,
                'qr_data_uri' => null,
                'merchant_name' => null,
                'fallback_image_url' => $fallback,
                'mode' => 'static',
            ];
        }

        try {
            $payload = $this->convert($static, $rupiah);
            $parsed = $this->parseSummary($payload);

            return [
                'enabled' => true,
                'amount' => $rupiah,
                'amount_label' => 'Rp '.number_format($rupiah, 0, ',', '.'),
                'payload' => $payload,
                'qr_data_uri' => $this->toSvgDataUri($payload),
                'merchant_name' => $parsed['merchant_name'] ?: null,
                'fallback_image_url' => $fallback,
                'mode' => 'dynamic',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'enabled' => false,
                'amount' => $rupiah,
                'amount_label' => 'Rp '.number_format($rupiah, 0, ',', '.'),
                'payload' => null,
                'qr_data_uri' => null,
                'merchant_name' => null,
                'fallback_image_url' => $fallback,
                'mode' => 'static',
            ];
        }
    }

    public function convert(string $qrisString, int $amount, ?array $fee = null): string
    {
        $qrisString = preg_replace('/[\r\n\t]+/', '', trim($qrisString)) ?? '';
        if ($qrisString === '' || $amount <= 0) {
            throw new RuntimeException('QRIS payload atau nominal tidak valid.');
        }

        if (! $this->looksLikeQris($qrisString)) {
            throw new RuntimeException('Format string QRIS tidak dikenali.');
        }

        $elements = $this->parseTlv($qrisString);
        if ($elements === []) {
            throw new RuntimeException('Gagal parse TLV QRIS.');
        }

        $result = [];
        $amountInserted = false;
        $managed = ['54' => true, '55' => true, '56' => true, '57' => true, '63' => true];

        foreach ($elements as $el) {
            if (isset($managed[$el['tag']])) {
                continue;
            }

            if ($el['tag'] === '01') {
                $result[] = $this->makeTlv('01', '12');
                continue;
            }

            if ($el['tag'] === '58' && ! $amountInserted) {
                $result[] = $this->makeTlv('54', (string) $amount);

                if (is_array($fee) && isset($fee['type'], $fee['value'])) {
                    if ($fee['type'] === 'fixed') {
                        $result[] = $this->makeTlv('55', '02');
                        $result[] = $this->makeTlv('56', (string) $fee['value']);
                    } elseif ($fee['type'] === 'percentage') {
                        $result[] = $this->makeTlv('55', '03');
                        $result[] = $this->makeTlv('57', (string) $fee['value']);
                    }
                }

                $amountInserted = true;
            }

            $result[] = $el;
        }

        if (! $amountInserted) {
            // Tidak ada tag 58 — sisipkan amount di akhir sebelum CRC.
            $result[] = $this->makeTlv('54', (string) $amount);
        }

        $withoutCrc = $this->buildTlvString($result);
        $crcInput = $withoutCrc.'6304';

        return $crcInput.$this->crc16($crcInput);
    }

    public function validate(string $qrisString): array
    {
        $qrisString = preg_replace('/[\r\n\t]+/', '', trim($qrisString)) ?? '';
        $errors = [];

        if ($qrisString === '') {
            return ['valid' => false, 'errors' => ['String QRIS kosong.']];
        }

        if (! $this->looksLikeQris($qrisString)) {
            $errors[] = 'String tidak diawali format QRIS (000201...).';
        }

        $elements = $this->parseTlv($qrisString);
        if ($elements === []) {
            $errors[] = 'TLV tidak bisa di-parse.';
        }

        $givenCrc = null;
        foreach ($elements as $el) {
            if ($el['tag'] === '63') {
                $givenCrc = strtoupper($el['value']);
            }
        }

        if ($givenCrc) {
            $withoutCrc = preg_replace('/6304[0-9A-Fa-f]{4}$/', '', $qrisString) ?? $qrisString;
            $expected = $this->crc16($withoutCrc.'6304');
            if ($givenCrc !== $expected) {
                $errors[] = "CRC mismatch: expected {$expected}, got {$givenCrc}";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'summary' => $errors === [] ? $this->parseSummary($qrisString) : null,
        ];
    }

    /**
     * @return array{merchant_name: string, merchant_city: string, method: string}
     */
    public function parseSummary(string $qrisString): array
    {
        $elements = $this->parseTlv($qrisString);
        $find = function (string $tag) use ($elements): string {
            foreach ($elements as $el) {
                if ($el['tag'] === $tag) {
                    return (string) $el['value'];
                }
            }

            return '';
        };

        $method = $find('01');

        return [
            'merchant_name' => $find('59'),
            'merchant_city' => $find('60'),
            'method' => $method === '12' ? 'dynamic' : 'static',
        ];
    }

    public function toSvgDataUri(string $payload, int $size = 280): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function looksLikeQris(string $value): bool
    {
        return (bool) preg_match('/^000201/', $value);
    }

    /**
     * @return list<array{tag: string, length: int, value: string, children?: list<array<string, mixed>>}>
     */
    private function parseTlv(string $data): array
    {
        $elements = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos + 4 <= $len) {
            $tag = substr($data, $pos, 2);
            $length = (int) substr($data, $pos + 2, 2);

            if ($length < 0 || $pos + 4 + $length > $len) {
                break;
            }

            $value = substr($data, $pos + 4, $length);
            $element = [
                'tag' => $tag,
                'length' => $length,
                'value' => $value,
            ];

            $tagNum = (int) $tag;
            if (($tagNum >= 26 && $tagNum <= 51) || $tag === '62') {
                $element['children'] = $this->parseTlv($value);
            }

            $elements[] = $element;
            $pos += 4 + $length;
        }

        return $elements;
    }

    /**
     * @param  list<array{tag: string, length?: int, value: string, children?: list<array<string, mixed>>}>  $elements
     */
    private function buildTlvString(array $elements): string
    {
        $out = '';
        foreach ($elements as $el) {
            $value = isset($el['children']) && is_array($el['children']) && $el['children'] !== []
                ? $this->buildTlvString($el['children'])
                : (string) $el['value'];
            $out .= $el['tag'].str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        }

        return $out;
    }

    /**
     * @return array{tag: string, length: int, value: string}
     */
    private function makeTlv(string $tag, string $value): array
    {
        return [
            'tag' => $tag,
            'length' => strlen($value),
            'value' => $value,
        ];
    }

    private function crc16(string $str): string
    {
        $crc = 0xFFFF;
        $length = strlen($str);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($str[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc & 0xFFFF), 4, '0', STR_PAD_LEFT));
    }
}
