<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembukuan {{ $date->format('d-m-Y') }} — {{ $shopName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            font-size: 13px;
            line-height: 1.4;
        }
        .sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
        }
        .toolbar {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }
        h1 { margin: 0; font-size: 22px; }
        .muted { color: #64748b; }
        .header { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            background: #f8fafc;
        }
        .summary-card strong { display: block; font-size: 18px; margin-top: 4px; }
        .section-title {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
            background: #f8fafc;
        }
        .right { text-align: right; }
        .total-row td {
            border-bottom: none;
            font-weight: 700;
            padding-top: 12px;
        }
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 11px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .sheet {
                max-width: none;
                border: none;
                border-radius: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="{{ route('kasir.pembukuan.index', ['date' => $date->toDateString()]) }}" class="btn">Kembali</a>
    </div>

    <div class="sheet">
        <div class="header">
            <p class="muted" style="margin:0 0 4px; font-size:12px; text-transform:uppercase; letter-spacing:.06em;">Laporan Pembukuan Harian</p>
            <h1>{{ $shopName }}</h1>
            <p class="muted" style="margin:6px 0 0;">
                Tanggal bayar: <strong>{{ $date->translatedFormat('l, d F Y') }}</strong>
            </p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <span class="muted">Omzet</span>
                <strong>{{ $format::rupiah($omzet) }}</strong>
            </div>
            <div class="summary-card">
                <span class="muted">Transaksi</span>
                <strong>{{ $format::number($count, 0) }}</strong>
            </div>
            <div class="summary-card">
                <span class="muted">Rata-rata</span>
                <strong>{{ $format::rupiah($average) }}</strong>
            </div>
        </div>

        <h2 class="section-title">Ringkasan metode bayar</h2>
        <table>
            <thead>
                <tr>
                    <th>Metode</th>
                    <th class="right">Transaksi</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byPayment as $payment)
                    <tr>
                        <td>{{ $payment['label'] }}</td>
                        <td class="right">{{ $payment['count'] }}</td>
                        <td class="right">{{ $format::rupiah($payment['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total</td>
                    <td class="right">{{ $count }}</td>
                    <td class="right">{{ $format::rupiah($omzet) }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="section-title">Rincian transaksi</h2>
        @if ($orders->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>No. Order</th>
                        <th>Sumber</th>
                        <th>Metode</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>{{ $order->paid_at?->format('H:i') ?? '-' }}</td>
                            <td>{{ $order->order_number }}</td>
                            <td>
                                {{ $order->source->label() }}
                                @if ($order->table)
                                    · {{ $order->table->label }}
                                @endif
                            </td>
                            <td>{{ $order->payment_method?->label() ?? '-' }}</td>
                            <td class="right">{{ $format::rupiah($order->total) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4">Total omzet</td>
                        <td class="right">{{ $format::rupiah($omzet) }}</td>
                    </tr>
                </tbody>
            </table>
        @else
            <p class="muted">Tidak ada transaksi lunas pada tanggal ini.</p>
        @endif

        <div class="footer">
            Dibuat {{ now()->format('d/m/Y H:i') }} · Modul Kasir
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
