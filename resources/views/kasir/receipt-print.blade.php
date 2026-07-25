<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        /* Monospace + bold selektif (header/total) — selaras PDF/thermal. */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Courier New", Courier, monospace;
            color: #000;
            background: #e8e8e8;
            padding: 16px;
        }
        .sheet {
            width: 48ch;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 10px 8px 16px;
        }
        .receipt {
            margin: 0;
            font-family: inherit;
            font-size: 12px;
            font-weight: 400;
            line-height: 1.35;
            white-space: pre-wrap;
            word-break: break-word;
            color: #000;
        }
        .receipt .b { font-weight: 700; }
        .hint {
            max-width: 320px;
            margin: 12px auto 0;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #555;
        }
        .hint button {
            margin-top: 8px;
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: #5c4033;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            @page { margin: 4mm; }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .sheet {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
            }
            .receipt {
                font-size: 11px;
                line-height: 1.3;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    use App\Support\Format;
    use App\Support\PosItemNotes;

    $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
    $isKitchen = ($variant ?? 'customer') === 'kitchen';
    $paidAt = $order->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
    $width = 32;

    $pad = static function (string $left, string $right, int $w) {
        $left = preg_replace('/\s+/u', ' ', trim($left)) ?? $left;
        $right = trim($right);
        $maxLeft = max(1, $w - mb_strlen($right) - 1);
        if (mb_strlen($left) > $maxLeft) {
            $left = mb_substr($left, 0, max(1, $maxLeft - 1)).'.';
        }
        $padLen = max(1, $w - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $padLen).$right;
    };

    $center = static function (string $text, int $w) {
        $text = trim($text);
        $len = mb_strlen($text);
        if ($len >= $w) {
            return mb_substr($text, 0, $w);
        }
        $left = (int) floor(($w - $len) / 2);

        return str_repeat(' ', $left).$text;
    };

    $e = static fn (string $t) => e($t);
    $rows = [];

    $rows[] = ['html' => '<span class="b">'.$e($center($shopName, $width)).'</span>'];
    $rows[] = ['html' => $e($center($isKitchen ? 'Struk Dapur' : 'Struk Pembayaran', $width))];
    $rows[] = ['html' => '<span class="b">'.$e($center($order->order_number, $width)).'</span>'];
    $rows[] = ['html' => $e($center($paidAt, $width))];
    if ($order->order_type) {
        $rows[] = ['html' => $e($center($order->order_type->label(), $width))];
    }
    if ($order->table) {
        $rows[] = ['html' => $e($center('Meja: '.$order->table->label, $width))];
    }
    if ($order->customer_note) {
        $rows[] = ['html' => $e($center('Pelanggan: '.$order->customer_note, $width))];
    }
    $rows[] = ['html' => $e(str_repeat('-', $width))];

    foreach ($order->items as $item) {
        $qty = Format::number($item->quantity, 0);
        $name = $item->product?->name ?? 'Item';
        $noteParts = PosItemNotes::split($item->notes);

        if ($isKitchen) {
            $rows[] = ['html' => $e('[ ] '.$name.' x '.$qty)];
        } else {
            $rows[] = ['html' => $e($pad($name.' x '.$qty, Format::rupiah($item->line_total), $width))];
        }

        foreach ($noteParts['addon_labels'] as $label) {
            $rows[] = ['html' => $e('  '.$label)];
        }
        if ($noteParts['customer']) {
            $rows[] = ['html' => $e('  Catatan: '.$noteParts['customer'])];
        }
    }

    $rows[] = ['html' => $e(str_repeat('-', $width))];

    if (! $isKitchen) {
        if ($order->hasDiscount()) {
            $rows[] = ['html' => $e($pad('Subtotal', Format::rupiah($order->subtotal), $width))];
            $rows[] = ['html' => $e($pad('Diskon', '- '.Format::rupiah($order->discount_amount), $width))];
        }
        $rows[] = ['html' => '<span class="b">'.$e($pad('TOTAL', Format::rupiah($order->total), $width)).'</span>'];
        $rows[] = ['html' => $e('Bayar: '.($order->payment_method?->label() ?? '-'))];
        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $rows[] = ['html' => $e('Diterima: '.Format::rupiah($order->amount_received))];
            $rows[] = ['html' => $e('Kembalian: '.Format::rupiah($order->change_amount))];
        }
    }

    if ($order->cashierDisplayName() !== '-') {
        $rows[] = ['html' => $e('Kasir: '.$order->cashierDisplayName())];
    }

    $rows[] = ['html' => ''];
    $footer = $isKitchen ? 'Ceklis item selesai' : 'Terima kasih';
    $rows[] = ['html' => '<span class="b">'.$e($center($footer, $width)).'</span>'];
    $rows[] = ['html' => ''];
@endphp

    <div class="sheet">
        <div class="receipt">@foreach ($rows as $row){!! $row['html'] !!}
@endforeach</div>
    </div>

    <div class="hint no-print">
        Pilih printer &amp; kertas di perangkat ini, lalu cetak.
        <br>
        <button type="button" onclick="window.print()">Cetak lagi</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
</body>
</html>
