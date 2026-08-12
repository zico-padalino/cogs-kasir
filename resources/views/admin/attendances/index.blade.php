@extends('layouts.admin')

@section('title', 'Laporan Absensi')
@section('heading', 'Laporan Absensi')

@section('content')
    @php
        $printQuery = array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'employee_id' => $employeeId,
            'status' => $statusFilter !== '' ? $statusFilter : null,
            'print' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp

    <div class="att-report-page">
        <div class="att-report-actions no-print">
            <a href="{{ route('admin.attendances.qr') }}" class="btn-secondary att-report-action">QR Absensi</a>
            <a href="{{ route('admin.attendances.index', $printQuery) }}" target="_blank" class="btn-primary att-report-action">
                Cetak / PDF
            </a>
        </div>

        <form method="GET" class="card att-report-filters no-print">
            <div>
                <label class="form-label" for="from">Dari tanggal</label>
                <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-input">
            </div>
            <div>
                <label class="form-label" for="to">Sampai tanggal</label>
                <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-input">
            </div>
            <div>
                <label class="form-label" for="employee_id_filter">Pegawai</label>
                <select id="employee_id_filter" name="employee_id" class="form-input">
                    <option value="">Semua pegawai</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) $employeeId === (int) $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="att-report-filters-submit">
                <button type="submit" class="btn-primary w-full">Tampilkan</button>
            </div>
        </form>

        <div class="att-report-summary">
            <div class="att-stat">
                <p class="att-stat-label">Total</p>
                <p class="att-stat-value">{{ $summary['total'] }}</p>
            </div>
            <div class="att-stat">
                <p class="att-stat-label">Hadir</p>
                <p class="att-stat-value text-emerald-700">{{ $summary['hadir'] }}</p>
            </div>
            <div class="att-stat">
                <p class="att-stat-label">Terlambat</p>
                <p class="att-stat-value text-amber-700">{{ $summary['late'] }}</p>
            </div>
            <div class="att-stat">
                <p class="att-stat-label">Belum pulang</p>
                <p class="att-stat-value text-brand-700">{{ $summary['no_checkout'] }}</p>
            </div>
            <div class="att-stat">
                <p class="att-stat-label">Ada selfie</p>
                <p class="att-stat-value text-teal-700">{{ $summary['with_selfie'] }}</p>
            </div>
            <div class="att-stat">
                <p class="att-stat-label">Izin/Sakit/Alpha</p>
                <p class="att-stat-value text-slate-700">{{ $summary['izin'] + $summary['sakit'] + $summary['alpha'] }}</p>
            </div>
        </div>

        <p class="att-report-meta">
            Periode
            <strong>{{ $from->translatedFormat('d M Y') }}</strong>
            @if (! $from->isSameDay($to))
                — <strong>{{ $to->translatedFormat('d M Y') }}</strong>
            @endif
            · Jam {{ $settings['clock_in'] }}–{{ $settings['clock_out'] }}
            · Radius {{ number_format($settings['radius_meters'], 0) }} m
        </p>

        @if ($missingToday->isNotEmpty())
            <div class="att-report-missing no-print">
                <p class="font-semibold">Belum absen hari ini ({{ $missingToday->count() }})</p>
                <p class="mt-1 text-amber-800">{{ $missingToday->pluck('name')->join(', ') }}</p>
            </div>
        @endif

        @if (($pendingCheckout ?? collect())->isNotEmpty())
            <div class="card mb-4 border border-brand-200 bg-brand-50/50 p-4 no-print">
                <div class="mb-3">
                    <p class="font-semibold text-brand-900">Belum absen pulang ({{ $pendingCheckout->count() }})</p>
                    <p class="mt-1 text-xs text-brand-800/80">
                        Pegawai sudah masuk tapi lupa pulang. Isi jam lalu klik <strong>Absen pulang</strong>.
                    </p>
                </div>
                <div class="space-y-3">
                    @foreach ($pendingCheckout as $row)
                        <form
                            method="POST"
                            action="{{ route('admin.attendances.checkout', $row) }}"
                            class="rounded-xl border border-brand-100 bg-white p-3"
                        >
                            @csrf
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900">{{ $row->employee?->name }}</p>
                                    <p class="text-[11px] text-slate-500">
                                        {{ $row->work_date?->translatedFormat('d M Y') }}
                                        · masuk {{ $row->check_in ? substr((string) $row->check_in, 0, 5) : '—' }}
                                    </p>
                                </div>
                                <div class="w-full sm:w-40">
                                    <label class="form-label" for="checkout_time_{{ $row->id }}">Jam pulang</label>
                                    <x-time-24-picker
                                        name="check_out"
                                        :id="'checkout_time_'.$row->id"
                                        :value="$row->suggested_check_out ?? '23:59'"
                                        required
                                    />
                                </div>
                                <button type="submit" class="btn-primary w-full sm:w-auto shrink-0">
                                    Absen pulang
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Mobile: kartu penuh layar (hindari tabel lebar yang bikin scroll ketahan & UI kepotong) --}}
        <div class="att-report-cards no-print">
            @forelse ($attendances as $row)
                <article class="att-report-card">
                    <div class="att-report-card-top">
                        <div class="min-w-0">
                            <p class="att-report-card-date">{{ $row->work_date?->translatedFormat('d M Y') }}</p>
                            <p class="att-report-card-name">{{ $row->employee?->name }}</p>
                            <p class="att-report-card-code">{{ $row->employee?->employee_code }}</p>
                        </div>
                        <div class="att-report-card-badges">
                            <span class="att-status">{{ $row->status->label() }}</span>
                            @if ($row->is_late)
                                <span class="att-badge-late">Terlambat</span>
                            @endif
                        </div>
                    </div>

                    <div class="att-report-card-grid">
                        <div class="att-report-card-block">
                            <p class="att-report-card-label">Masuk</p>
                            <div class="att-report-card-row">
                                <p class="att-report-card-time">
                                    {{ $row->check_in ? substr((string) $row->check_in, 0, 5) : '—' }}
                                </p>
                                @if ($row->checkInPhotoUrl())
                                    <a href="{{ $row->checkInPhotoUrl() }}" target="_blank" rel="noopener" class="att-selfie">
                                        <img src="{{ $row->checkInPhotoUrl() }}" alt="Selfie masuk">
                                    </a>
                                @endif
                            </div>
                            @if ($row->check_in_lat !== null)
                                <a
                                    class="att-report-card-loc"
                                    target="_blank"
                                    rel="noopener"
                                    href="https://www.google.com/maps?q={{ $row->check_in_lat }},{{ $row->check_in_lng }}"
                                >
                                    Lokasi masuk
                                    @if ($row->check_in_distance !== null)
                                        · {{ number_format($row->check_in_distance, 0) }} m
                                    @endif
                                </a>
                            @endif
                        </div>
                        <div class="att-report-card-block">
                            <p class="att-report-card-label">Pulang</p>
                            <div class="att-report-card-row">
                                <p class="att-report-card-time">
                                    {{ $row->check_out ? substr((string) $row->check_out, 0, 5) : '—' }}
                                </p>
                                @if ($row->checkOutPhotoUrl())
                                    <a href="{{ $row->checkOutPhotoUrl() }}" target="_blank" rel="noopener" class="att-selfie">
                                        <img src="{{ $row->checkOutPhotoUrl() }}" alt="Selfie pulang">
                                    </a>
                                @endif
                            </div>
                            @if ($row->check_out_lat !== null)
                                <a
                                    class="att-report-card-loc"
                                    target="_blank"
                                    rel="noopener"
                                    href="https://www.google.com/maps?q={{ $row->check_out_lat }},{{ $row->check_out_lng }}"
                                >
                                    Lokasi pulang
                                    @if ($row->check_out_distance !== null)
                                        · {{ number_format($row->check_out_distance, 0) }} m
                                    @endif
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($row->notes)
                        <p class="att-report-card-notes">{{ $row->notes }}</p>
                    @endif

                    <div class="att-report-card-actions">
                        @if (filled($row->check_in) && ! filled($row->check_out))
                            <form action="{{ route('admin.attendances.checkout', $row) }}" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="check_out" value="{{ $row->suggested_check_out ?? '23:59' }}">
                                <button type="submit" class="btn-sm btn-primary w-full" title="Pakai jam jadwal: {{ $row->suggested_check_out ?? '23:59' }}">
                                    Absen pulang
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('admin.attendances.destroy', $row) }}" method="POST" class="flex-1" onsubmit="return confirm('Hapus absensi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm btn-outline-danger w-full">Hapus</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="att-report-cards-empty">
                    Belum ada data absensi pada filter ini.
                </div>
            @endforelse
        </div>

        {{-- Desktop: tabel penuh --}}
        <div class="card att-report-table-wrap p-0">
            <div class="att-report-table-scroll">
                <table class="att-report-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pegawai</th>
                            <th>Masuk</th>
                            <th>Selfie</th>
                            <th>Lokasi</th>
                            <th>Pulang</th>
                            <th>Selfie</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $row)
                            <tr>
                                <td class="whitespace-nowrap">{{ $row->work_date?->translatedFormat('d M Y') }}</td>
                                <td>
                                    <p class="font-medium text-slate-900">{{ $row->employee?->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $row->employee?->employee_code }}</p>
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ $row->check_in ? substr((string) $row->check_in, 0, 5) : '—' }}
                                    @if ($row->is_late)
                                        <span class="att-badge-late">Terlambat</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row->checkInPhotoUrl())
                                        <a href="{{ $row->checkInPhotoUrl() }}" target="_blank" rel="noopener" class="att-selfie">
                                            <img src="{{ $row->checkInPhotoUrl() }}" alt="Selfie masuk">
                                        </a>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="text-[11px] text-slate-500">
                                    @if ($row->check_in_lat !== null)
                                        <a
                                            class="text-teal-700 hover:underline"
                                            target="_blank"
                                            rel="noopener"
                                            href="https://www.google.com/maps?q={{ $row->check_in_lat }},{{ $row->check_in_lng }}"
                                        >
                                            {{ number_format((float) $row->check_in_lat, 5) }}, {{ number_format((float) $row->check_in_lng, 5) }}
                                        </a>
                                        @if ($row->check_in_distance !== null)
                                            <p>{{ number_format($row->check_in_distance, 0) }} m dari toko</p>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ $row->check_out ? substr((string) $row->check_out, 0, 5) : '—' }}
                                </td>
                                <td>
                                    @if ($row->checkOutPhotoUrl())
                                        <a href="{{ $row->checkOutPhotoUrl() }}" target="_blank" rel="noopener" class="att-selfie">
                                            <img src="{{ $row->checkOutPhotoUrl() }}" alt="Selfie pulang">
                                        </a>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="text-[11px] text-slate-500">
                                    @if ($row->check_out_lat !== null)
                                        <a
                                            class="text-teal-700 hover:underline"
                                            target="_blank"
                                            rel="noopener"
                                            href="https://www.google.com/maps?q={{ $row->check_out_lat }},{{ $row->check_out_lng }}"
                                        >
                                            {{ number_format((float) $row->check_out_lat, 5) }}, {{ number_format((float) $row->check_out_lng, 5) }}
                                        </a>
                                        @if ($row->check_out_distance !== null)
                                            <p>{{ number_format($row->check_out_distance, 0) }} m dari toko</p>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="att-status">{{ $row->status->label() }}</span>
                                    @if ($row->notes)
                                        <p class="mt-0.5 text-[11px] text-slate-400">{{ $row->notes }}</p>
                                    @endif
                                </td>
                                <td class="no-print">
                                    <div class="flex flex-col items-stretch gap-1.5 min-w-[7.5rem]">
                                        @if (filled($row->check_in) && ! filled($row->check_out))
                                            <form action="{{ route('admin.attendances.checkout', $row) }}" method="POST" class="space-y-1">
                                                @csrf
                                                <input type="hidden" name="check_out" value="{{ $row->suggested_check_out ?? '23:59' }}">
                                                <button type="submit" class="btn-sm btn-primary w-full" title="Pakai jam jadwal: {{ $row->suggested_check_out ?? '23:59' }}">
                                                    Absen pulang
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('admin.attendances.destroy', $row) }}" method="POST" onsubmit="return confirm('Hapus absensi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-sm btn-outline-danger w-full">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Belum ada data absensi pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <details class="card att-report-manual no-print" id="att-report-manual">
            <summary class="att-report-manual-summary">Catat absensi manual</summary>
            <form method="POST" action="{{ route('admin.attendances.store') }}" class="att-report-manual-form">
                @csrf
                <p class="mb-3 text-xs text-slate-500">
                    Pilih jenis di dropdown: <strong>Hadir</strong> menampilkan jam masuk,
                    <strong>Pulang</strong> menampilkan jam pulang.
                    Untuk lupa pulang, bisa juga pakai panel <strong>Belum absen pulang</strong> di atas.
                </p>
                <div class="att-report-manual-grid" data-att-manual-form>
                    <div>
                        <label class="form-label" for="employee_id">Karyawan</label>
                        <select id="employee_id" name="employee_id" class="form-input" required>
                            <option value="">Pilih karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="work_date">Tanggal</label>
                        <input id="work_date" type="date" name="work_date" value="{{ $to->toDateString() }}" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" for="attendance_kind">Jenis</label>
                        <select id="attendance_kind" name="attendance_kind" class="form-input" data-att-manual-kind required>
                            <option value="hadir" @selected(old('attendance_kind', 'hadir') === 'hadir')>Hadir</option>
                            <option value="pulang" @selected(old('attendance_kind') === 'pulang')>Pulang</option>
                            @foreach ($statuses as $status)
                                @continue($status->value === 'hadir')
                                <option value="{{ $status->value }}" @selected(old('attendance_kind') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="status" id="status_manual" value="{{ old('status', 'hadir') }}" data-att-manual-status>
                    </div>
                    <div data-att-manual-field="check_in">
                        <label class="form-label" for="check_in">Jam masuk</label>
                        <x-time-24-picker name="check_in" id="check_in" value="{{ old('check_in') }}" />
                    </div>
                    <div data-att-manual-field="check_out" class="hidden">
                        <label class="form-label" for="check_out">Jam pulang</label>
                        <x-time-24-picker name="check_out" id="check_out" value="{{ old('check_out') }}" />
                    </div>
                    <div>
                        <label class="form-label" for="notes">Catatan</label>
                        <input id="notes" name="notes" class="form-input" placeholder="Opsional" value="{{ old('notes') }}">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto">Simpan Absensi</button>
            </form>
        </details>
    </div>
    <script>
        (function () {
            var panel = document.getElementById('att-report-manual');
            if (!panel) return;

            panel.addEventListener('toggle', function () {
                if (!panel.open) return;
                requestAnimationFrame(function () {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });

            var kindSelect = panel.querySelector('[data-att-manual-kind]');
            var statusInput = panel.querySelector('[data-att-manual-status]');
            var checkInField = panel.querySelector('[data-att-manual-field="check_in"]');
            var checkOutField = panel.querySelector('[data-att-manual-field="check_out"]');
            if (!kindSelect || !statusInput || !checkInField || !checkOutField) return;

            function clearTimePicker(root) {
                root.querySelectorAll('select, input').forEach(function (el) {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else if (el.type !== 'hidden') {
                        el.value = '';
                    } else if (el.name === 'check_in' || el.name === 'check_out') {
                        el.value = '';
                    }
                });
            }

            function setFieldEnabled(root, enabled) {
                root.classList.toggle('hidden', !enabled);
                root.querySelectorAll('select, input').forEach(function (el) {
                    el.disabled = !enabled;
                });
                if (!enabled) {
                    clearTimePicker(root);
                }
            }

            function syncKind() {
                var kind = kindSelect.value || 'hadir';
                var showIn = kind === 'hadir';
                var showOut = kind === 'pulang';

                statusInput.value = kind === 'pulang' ? 'hadir' : kind;
                setFieldEnabled(checkInField, showIn);
                setFieldEnabled(checkOutField, showOut);
            }

            kindSelect.addEventListener('change', syncKind);
            syncKind();
        })();
    </script>
@endsection
