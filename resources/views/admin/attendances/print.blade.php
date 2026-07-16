<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Absensi — {{ $shopName }}</title>
    <style>
        :root {
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #0f172a;
            background: #fff;
            font-size: 12px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .toolbar button, .toolbar a {
            border: 1px solid #cbd5e1;
            background: #0f766e;
            color: #fff;
            border-radius: 8px;
            padding: 8px 14px;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }
        .toolbar a.secondary { background: #fff; color: #0f172a; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .meta { color: #64748b; margin-bottom: 16px; }
        .summary {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .summary div {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
        }
        .summary span { display: block; color: #64748b; font-size: 10px; text-transform: uppercase; }
        .summary strong { font-size: 18px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f8fafc; font-size: 11px; }
        img.selfie {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .late {
            display: inline-block;
            margin-left: 4px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 10px;
            font-weight: 700;
        }
        @media print {
            .toolbar { display: none !important; }
            .wrap { padding: 0; max-width: none; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
            <a class="secondary" href="{{ route('admin.attendances.index', array_filter([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'employee_id' => $employeeId,
                'status' => $statusFilter !== '' ? $statusFilter : null,
            ])) }}">← Kembali</a>
        </div>

        <h1>Laporan Absensi — {{ $shopName }}</h1>
        <p class="meta">
            Periode {{ $from->translatedFormat('d M Y') }}
            @if (! $from->isSameDay($to))
                — {{ $to->translatedFormat('d M Y') }}
            @endif
            · Jam {{ $settings['clock_in'] }}–{{ $settings['clock_out'] }}
            · Radius {{ number_format($settings['radius_meters'], 0) }} m
            · Dicetak {{ now()->translatedFormat('d M Y H:i') }}
        </p>

        <div class="summary">
            <div><span>Total</span><strong>{{ $summary['total'] }}</strong></div>
            <div><span>Hadir</span><strong>{{ $summary['hadir'] }}</strong></div>
            <div><span>Terlambat</span><strong>{{ $summary['late'] }}</strong></div>
            <div><span>Belum pulang</span><strong>{{ $summary['no_checkout'] }}</strong></div>
            <div><span>Ada selfie</span><strong>{{ $summary['with_selfie'] }}</strong></div>
            <div><span>Izin/Sakit/Alpha</span><strong>{{ $summary['izin'] + $summary['sakit'] + $summary['alpha'] }}</strong></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pegawai</th>
                    <th>Masuk</th>
                    <th>Selfie</th>
                    <th>GPS masuk</th>
                    <th>Pulang</th>
                    <th>Selfie</th>
                    <th>GPS pulang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attendances as $row)
                    <tr>
                        <td>{{ $row->work_date?->format('d/m/Y') }}</td>
                        <td>
                            {{ $row->employee?->name }}
                            <br><small>{{ $row->employee?->employee_code }}</small>
                        </td>
                        <td>
                            {{ $row->check_in ? substr((string) $row->check_in, 0, 5) : '—' }}
                            @if ($row->is_late)<span class="late">Terlambat</span>@endif
                        </td>
                        <td>
                            @if ($row->checkInPhotoUrl())
                                <img class="selfie" src="{{ $row->checkInPhotoUrl() }}" alt="">
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($row->check_in_lat !== null)
                                {{ number_format((float) $row->check_in_lat, 5) }}, {{ number_format((float) $row->check_in_lng, 5) }}
                                @if ($row->check_in_distance !== null)
                                    <br><small>{{ number_format($row->check_in_distance, 0) }} m</small>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->check_out ? substr((string) $row->check_out, 0, 5) : '—' }}</td>
                        <td>
                            @if ($row->checkOutPhotoUrl())
                                <img class="selfie" src="{{ $row->checkOutPhotoUrl() }}" alt="">
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($row->check_out_lat !== null)
                                {{ number_format((float) $row->check_out_lat, 5) }}, {{ number_format((float) $row->check_out_lng, 5) }}
                                @if ($row->check_out_distance !== null)
                                    <br><small>{{ number_format($row->check_out_distance, 0) }} m</small>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $row->status->label() }}
                            @if ($row->notes)<br><small>{{ $row->notes }}</small>@endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:24px;color:#64748b">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
