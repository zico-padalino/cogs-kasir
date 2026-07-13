<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekapan Sisa Bahan — {{ $shopName }}</title>
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

        .header-side {
            text-align: right;
            flex-shrink: 0;
        }

        .date-chip {
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

        .printed {
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
        }

        th, td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            text-align: left;
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

        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #94a3b8; }
        .zero { color: #b91c1c; }
        .mono { font-variant-numeric: tabular-nums; }

        .total-row td {
            background: #f1f5f9 !important;
            font-weight: 800;
            border-top: 2px solid #0f172a;
            border-bottom: none;
        }

        .empty {
            margin: 12px 0;
            padding: 16px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            text-align: center;
            color: #64748b;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #64748b;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar { display: none !important; }

            .sheet {
                max-width: none;
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="{{ route('materials.index') }}" class="btn">Kembali</a>
    </div>

    <div class="sheet">
        <div class="header">
            <div>
                <p class="eyebrow">{{ $shopName }}</p>
                <h1>Rekapan Sisa Bahan</h1>
                <p class="header-meta">Stok bahan baku yang tersisa saat ini (harga rata-rata tertimbang)</p>
            </div>
            <div class="header-side">
                <div class="date-chip">{{ $printedAt->format('d/m/Y') }}</div>
                <p class="printed">Dicetak {{ $printedAt->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        <div class="summary">
            <div class="summary-card">
                <span class="label">Total bahan</span>
                <span class="value">{{ $format::number($itemCount, 0) }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Masih ada stok</span>
                <span class="value">{{ $format::number($inStockCount, 0) }}</span>
            </div>
            <div class="summary-card">
                <span class="label">Nilai stok</span>
                <span class="value">{{ $format::rupiah($totalValue) }}</span>
            </div>
        </div>

        <h2 class="section-title">Daftar sisa bahan</h2>

        @if ($materials->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th style="width: 36px;" class="center">No</th>
                        <th>Nama bahan</th>
                        <th style="width: 70px;" class="center">Satuan</th>
                        <th style="width: 100px;" class="right">Stok sisa</th>
                        <th style="width: 110px;" class="right">Harga rata-rata</th>
                        <th style="width: 120px;" class="right">Nilai stok</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($materials as $index => $material)
                        @php
                            $qty = (float) $material->available_qty;
                            $avg = (float) $material->avg_cost;
                            $value = $qty * $avg;
                        @endphp
                        <tr>
                            <td class="center mono">{{ $index + 1 }}</td>
                            <td>{{ $material->name }}</td>
                            <td class="center">{{ $material->unit ?: '-' }}</td>
                            <td class="right mono {{ $qty <= 0 ? 'zero' : '' }}">
                                {{ $format::number($qty, 2) }}
                            </td>
                            <td class="right mono">{{ $format::rupiah($avg) }}</td>
                            <td class="right mono">{{ $format::rupiah($value) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="5">Total nilai sisa bahan</td>
                        <td class="right">{{ $format::rupiah($totalValue) }}</td>
                    </tr>
                </tbody>
            </table>
        @else
            <p class="empty">Belum ada data bahan aktif.</p>
        @endif

        <div class="footer">
            <span>Rekapan sisa bahan · {{ $shopName }}</span>
            <span>{{ $format::number($itemCount, 0) }} item · {{ $format::rupiah($totalValue) }}</span>
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
