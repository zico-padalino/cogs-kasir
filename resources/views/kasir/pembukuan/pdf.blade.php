<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembukuan {{ $date->format('d-m-Y') }} — {{ $shopName }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px 16px 40px;
            font-family: "Segoe UI", Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #eef2f7;
            font-size: 12px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            max-width: 210mm;
            margin: 0 auto 14px;
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

        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            padding: 22px 24px 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #0f172a;
        }

        .eyebrow {
            margin: 0 0 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
        }

        h1 {
            margin: 0;
            font-size: 22px;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .header-meta {
            margin: 6px 0 0;
            color: #475569;
            font-size: 12px;
        }

        .header-meta strong {
            color: #0f172a;
        }

        .header-side {
            text-align: right;
            flex-shrink: 0;
        }

        .header-side .date-chip {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            white-space: nowrap;
        }

        .header-side .printed {
            margin: 8px 0 0;
            font-size: 10px;
            color: #94a3b8;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f8fafc;
        }

        .summary-card .label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #64748b;
        }

        .summary-card .value {
            display: block;
            margin-top: 4px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            word-break: break-word;
        }

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #475569;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            text-align: left;
            word-wrap: break-word;
        }

        th {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #64748b;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
        }

        tbody tr:nth-child(even) td {
            background: #fafbfc;
        }

        .col-time { width: 12%; }
        .col-order { width: 28%; }
        .col-source { width: 24%; }
        .col-method { width: 16%; }
        .col-total { width: 20%; }

        .col-method-pay { width: 46%; }
        .col-count { width: 24%; }
        .col-amount { width: 30%; }

        .right { text-align: right; }
        .mono { font-family: ui-monospace, "Courier New", monospace; font-size: 11px; }
        .muted { color: #64748b; }

        .total-row td {
            border-bottom: none;
            border-top: 2px solid #0f172a;
            background: #fff !important;
            font-weight: 800;
            padding-top: 10px;
            padding-bottom: 2px;
        }

        .empty {
            margin: 0;
            padding: 14px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: #64748b;
            text-align: center;
            background: #f8fafc;
        }

        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: #94a3b8;
            font-size: 10px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none !important;
            }

            .sheet {
                max-width: none;
                border: none;
                border-radius: 0;
                padding: 0;
                box-shadow: none;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }

            .section {
                page-break-inside: avoid;
            }

            .tx-section {
                page-break-inside: auto;
            }

            .tx-section table {
                page-break-inside: auto;
            }
        }

        @media (max-width: 640px) {
            body { padding: 12px 10px 28px; }
            .sheet { padding: 16px; }
            .header { flex-direction: column; }
            .header-side { text-align: left; }
            .summary { grid-template-columns: 1fr; }
            .summary-card .value { font-size: 15px; }
            th, td { padding: 6px 4px; font-size: 11px; }
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
            <div>
                <p class="eyebrow">Laporan Pembukuan Harian</p>
                <h1>{{ $shopName }}</h1>
                <p class="header-meta">
                    Tanggal bayar:
                    <strong>{{ $date->translatedFormat('l, d F Y') }}</strong>
                </p>
            </div>
            <div class="header-side">
                <div class="date-chip">{{ $date->format('d/m/Y') }}</div>
                <p class="printed">Dicetak {{ now()->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        <div class="summary">
            <div class="summary-card">
                <span class="label">Omzet</span>
                <span class="value">{{ $format::rupiah($omzet) }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Transaksi</span>
                <span class="value">{{ $format::number($count, 0) }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Rata-rata</span>
                <span class="value">{{ $format::rupiah($average) }}</span>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Ringkasan metode bayar</h2>
            <table>
                <colgroup>
                    <col class="col-method-pay">
                    <col class="col-count">
                    <col class="col-amount">
                </colgroup>
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
                            <td class="right">{{ $format::number($payment['count'], 0) }}</td>
                            <td class="right">{{ $format::rupiah($payment['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td>Total</td>
                        <td class="right">{{ $format::number($count, 0) }}</td>
                        <td class="right">{{ $format::rupiah($omzet) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section tx-section">
            <h2 class="section-title">Rincian transaksi</h2>
            @if ($orders->isNotEmpty())
                <table>
                    <colgroup>
                        <col class="col-time">
                        <col class="col-order">
                        <col class="col-source">
                        <col class="col-method">
                        <col class="col-total">
                    </colgroup>
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
                                <td class="mono">{{ $order->order_number }}</td>
                                <td>
                                    {{ $order->source->label() }}
                                    @if ($order->table)
                                        <span class="muted">· {{ $order->table->label }}</span>
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
                <p class="empty">Tidak ada transaksi lunas pada tanggal ini.</p>
            @endif
        </div>

        <div class="footer">
            <span>{{ $shopName }} · Modul Kasir</span>
            <span>{{ $format::number($count, 0) }} transaksi · {{ $format::rupiah($omzet) }}</span>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
