@extends('layouts.admin')

@section('title', 'Gaji Karyawan')
@section('heading', 'Gaji Karyawan')

@section('content')
    @if (! empty($schemaMissing))
        <div class="card mb-4 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Tabel gaji/karyawan belum lengkap di database.
            Jalankan migrasi <code class="rounded bg-white px-1">php artisan migrate --force</code>
            atau import SQL <code class="rounded bg-white px-1">database/fix_admin_salaries.sql</code>, lalu refresh halaman ini.
        </div>
    @endif

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
        <div>
            <label class="form-label" for="from">Dari tanggal</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-input" required>
        </div>
        <div>
            <label class="form-label" for="to">Sampai tanggal</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-input" required>
        </div>
        <button type="submit" class="btn-primary w-full sm:w-auto">Tampilkan</button>
    </form>

    <div class="card mb-4 space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Hitung gaji periode</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Total = <strong>pokok</strong> + (<strong>gaji harian × hari hadir</strong>) + tunjangan − potongan.
                    Estimasi / minggu = gaji harian × hari jadwal kerja.
                    Potongan dari absensi — atur di
                    <a href="{{ route('admin.settings.edit') }}" class="font-medium text-brand-700 underline">Pengaturan</a>.
                </p>
                @php $rates = $deductionRates ?? []; @endphp
                @if (($rates['fixed'] ?? 0) > 0 || ($rates['late'] ?? 0) > 0 || ($rates['alpha'] ?? 0) > 0 || ($rates['izin'] ?? 0) > 0 || ($rates['sakit'] ?? 0) > 0)
                    <p class="mt-1 text-xs text-slate-500">
                        Tarif:
                        @if (($rates['fixed'] ?? 0) > 0)
                            rutin {{ $format::rupiah($rates['fixed']) }}
                        @endif
                        @if (($rates['late'] ?? 0) > 0)
                            · telat {{ $format::rupiah($rates['late']) }}/kali
                            @if (($rates['late_after_minutes'] ?? 0) > 0)
                                (≥{{ $rates['late_after_minutes'] }} mnt)
                            @endif
                        @endif
                        @if (($rates['alpha'] ?? 0) > 0)
                            · alpha {{ $format::rupiah($rates['alpha']) }}/kali
                        @endif
                        @if (($rates['izin'] ?? 0) > 0)
                            · izin {{ $format::rupiah($rates['izin']) }}/kali
                        @endif
                        @if (($rates['sakit'] ?? 0) > 0)
                            · sakit {{ $format::rupiah($rates['sakit']) }}/kali
                        @endif
                    </p>
                @endif
            </div>
            <button type="button" class="btn-secondary btn-sm" data-generate-open>
                Hitung gaji
            </button>
        </div>

        <form method="POST" action="{{ route('admin.salaries.store') }}" class="space-y-4" data-salary-form>
            @csrf
            <input type="hidden" name="period_from" value="{{ $from->toDateString() }}">
            <input type="hidden" name="period_to" value="{{ $to->toDateString() }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="employee_id">Karyawan</label>
                    <select id="employee_id" name="employee_id" class="form-input" required data-salary-employee data-searchable-select data-search-placeholder="Pilih karyawan" data-search-input-placeholder="Cari karyawan...">
                        <option value="">Pilih karyawan</option>
                        @foreach ($employees as $employee)
                            @php $p = $previews[$employee->id] ?? null; @endphp
                            <option
                                value="{{ $employee->id }}"
                                data-base="{{ $p['base'] ?? 0 }}"
                                data-daily="{{ $p['daily'] ?? 0 }}"
                                data-days-week="{{ $p['days_week'] ?? 5 }}"
                                data-weekly="{{ $p['weekly'] ?? 0 }}"
                                data-work-days="{{ $p['work_days'] ?? 0 }}"
                                data-daily-total="{{ $p['daily_total'] ?? 0 }}"
                                data-deduction="{{ $p['deduction'] ?? 0 }}"
                                data-deduction-summary="{{ e($p['deduction_summary'] ?? '') }}"
                            >{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Periode</label>
                    <input type="text" class="form-input bg-slate-50" value="{{ $periodLabel }}" readonly>
                    <p class="mt-1 text-xs text-slate-500">Ganti rentang tanggal lewat filter di atas, lalu pilih karyawan lagi.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4" data-salary-preview>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ringkasan hitungan</p>
                    <button type="button" class="btn-secondary btn-sm" data-attendance-detail-btn disabled>
                        Detail absensi
                    </button>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl border border-sky-100 bg-sky-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">Per bulan</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-sky-950" data-out-base>—</p>
                        <p class="mt-0.5 text-[10px] text-sky-800/80">Gaji pokok</p>
                    </div>
                    <div class="rounded-xl border border-violet-100 bg-violet-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-700">Per minggu</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-violet-950" data-out-weekly>—</p>
                        <p class="mt-0.5 text-[10px] text-violet-800/80" data-out-weekly-hint>Estimasi dari jadwal</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Hitung harian</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-amber-950" data-out-daily-total>—</p>
                        <p class="mt-0.5 text-[10px] text-amber-900/80" data-out-daily-formula>Tarif × hari hadir</p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] text-slate-500">Tarif harian</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-daily>—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Hari hadir</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-work-days>—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Potongan absensi</p>
                        <p class="text-sm font-semibold tabular-nums text-red-600" data-out-deduction>—</p>
                        <p class="text-[10px] text-slate-400 break-words" data-out-deduction-hint></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Potongan manual</p>
                        <p class="text-sm font-semibold tabular-nums text-red-600" data-out-manual-deduction>—</p>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-200 pt-3">
                    <p class="text-[11px] text-slate-500">Subtotal (pokok + hitung harian)</p>
                    <p class="text-lg font-bold tabular-nums text-brand-800" data-out-subtotal>—</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 p-4" data-deduction-panel hidden>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konfirmasi potongan</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Centang = potongan dihitung. Hilangkan centang jika hari itu seharusnya tidak dipotong (misalnya bukan telat).
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-sm" data-deduction-check-all>Centang semua</button>
                        <button type="button" class="btn-secondary btn-sm" data-deduction-uncheck-all>Kosongkan</button>
                    </div>
                </div>
                <div class="mt-3 space-y-2" data-deduction-list></div>
                <p class="mt-2 hidden text-xs text-slate-400" data-deduction-empty>Tidak ada potongan absensi pada periode ini.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-rupiah-input
                        name="allowance"
                        label="Tunjangan (opsional)"
                        :value="old('allowance', 0)"
                        placeholder="0"
                    />
                </div>
                <div>
                    <x-rupiah-input
                        name="manual_deduction"
                        label="Potongan manual (opsional)"
                        :value="old('manual_deduction', '')"
                        placeholder="0"
                    />
                    <p class="mt-1 text-xs text-slate-500">Kosongkan jika tidak ada. Contoh: kasbon, denda, dll.</p>
                </div>
                <div>
                    <label class="form-label" for="total_display">Total dibayar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-brand-700">Rp</span>
                        <input
                            id="total_display"
                            type="text"
                            class="form-input pl-10 bg-brand-50 font-semibold text-brand-800"
                            value=""
                            readonly
                            data-salary-total
                            placeholder="0"
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Pokok bulanan + hitung harian + tunjangan − semua potongan.</p>
                </div>
                <div>
                    <label class="form-label" for="notes">Catatan (opsional)</label>
                    <input id="notes" name="notes" class="form-input" value="{{ old('notes') }}" placeholder="Alasan potongan manual, dll.">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full sm:w-auto">Hitung & simpan</button>
        </form>
    </div>

    @php
        $draftSalaries = $salaries->filter(fn ($s) => $s->status->value === 'draft')->values();
        $paidSalariesInPeriod = $salaries->filter(fn ($s) => $s->status->value === 'paid')->values();
        $paidSalaries = ($paidSalariesHistory ?? collect())->values();
        $periodTotal = $salaries->sum('total');
        $draftTotal = $draftSalaries->sum('total');
        $paidTotal = $paidSalariesInPeriod->sum('total');
        $periodPeople = $salaries->count();
        $periodHadir = $salaries->sum(fn ($s) => (int) ($s->work_days ?? 0));
    @endphp

    <div class="space-y-6">
        <div class="card flex flex-wrap items-end justify-between gap-3 p-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Gaji {{ $periodLabel }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $periodPeople }} karyawan
                    @if ($hasDailyColumns ?? true)
                        · {{ $periodHadir }} hari hadir
                    @endif
                    · Hitung dulu, lalu konfirmasi bayar
                </p>
            </div>
            @if ($salaries->isNotEmpty())
                <div class="flex flex-wrap gap-4 text-right">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-amber-700/80">Belum bayar</p>
                        <p class="text-sm font-bold tabular-nums text-amber-800">{{ $format::rupiah($draftTotal) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-emerald-700/80">Sudah bayar</p>
                        <p class="text-sm font-bold tabular-nums text-emerald-800">{{ $format::rupiah($paidTotal) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Total semua</p>
                        <p class="text-lg font-bold tabular-nums text-brand-800">{{ $format::rupiah($periodTotal) }}</p>
                    </div>
                </div>
            @endif
        </div>

        <section class="space-y-3" id="salary-pending">
            <div class="flex items-center justify-between gap-2 px-0.5">
                <h3 class="text-sm font-semibold text-slate-900">Menunggu pembayaran</h3>
                <span class="text-xs text-slate-500">{{ $draftSalaries->count() }} data</span>
            </div>

            @forelse ($draftSalaries as $salary)
                @include('admin.salaries.partials.card', ['salary' => $salary, 'canPay' => true])
            @empty
                <div class="card px-4 py-8 text-center text-sm text-slate-500">
                    Belum ada gaji yang dihitung. Gunakan <strong>Hitung gaji</strong> atau form di atas.
                </div>
            @endforelse
        </section>

        <section class="space-y-3" id="salary-paid-record">
            <div class="flex items-center justify-between gap-2 px-0.5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Riwayat pembayaran</h3>
                    <p class="text-xs text-slate-500">Semua gaji yang sudah dikonfirmasi bayar, tanpa filter periode.</p>
                </div>
                <span class="text-xs text-slate-500">{{ $paidSalaries->count() }} data</span>
            </div>

            @forelse ($paidSalaries as $salary)
                @include('admin.salaries.partials.card', ['salary' => $salary, 'canPay' => false])
            @empty
                <div class="card px-4 py-8 text-center text-sm text-slate-500">
                    Belum ada pembayaran. Setelah konfirmasi bayar, data muncul di sini.
                </div>
            @endforelse
        </section>
    </div>

    {{-- Modal konfirmasi bayar --}}
    <div
        id="salary-pay-modal"
        class="salary-gen-modal"
        data-pay-modal
        aria-hidden="true"
        hidden
    >
        <div class="salary-gen-modal__backdrop" data-pay-close></div>
        <div class="salary-gen-modal__panel" role="dialog" aria-modal="true" aria-labelledby="salary-pay-title">
            <div class="salary-gen-modal__head">
                <div>
                    <h3 id="salary-pay-title" class="salary-gen-modal__title">Konfirmasi pembayaran</h3>
                    <p class="salary-gen-modal__sub">Setelah dikonfirmasi, gaji dipotong dari Dana Usaha (omzet bersih) dengan keterangan gaji.</p>
                </div>
                <button type="button" class="btn-secondary btn-sm shrink-0" data-pay-close>Tutup</button>
            </div>
            <form method="POST" class="salary-gen-modal__body" data-pay-form>
                @csrf
                <div class="salary-gen-preview">
                    <p class="salary-gen-label">Karyawan</p>
                    <p class="salary-gen-preview__value" data-pay-name>—</p>
                </div>
                <div class="salary-gen-preview">
                    <p class="salary-gen-label">Periode</p>
                    <p class="salary-gen-preview__value" data-pay-period>—</p>
                </div>
                <div class="salary-gen-preview" style="background:#ecfdf5;border-color:#a7f3d0;">
                    <p class="salary-gen-label">Jumlah dibayar</p>
                    <p class="salary-gen-preview__value" style="color:#065f46;font-size:1.25rem;" data-pay-total>—</p>
                </div>
                <div class="salary-gen-modal__actions">
                    <button type="button" class="btn-secondary" data-pay-close>Batal</button>
                    <button type="submit" class="btn-primary">Ya, sudah dibayar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal generate gaji: bulanan / mingguan / rentang tanggal --}}
    <div
        id="salary-generate-modal"
        class="salary-gen-modal"
        data-generate-modal
        aria-hidden="true"
        hidden
    >
        <div class="salary-gen-modal__backdrop" data-generate-close></div>
        <div class="salary-gen-modal__panel" role="dialog" aria-modal="true" aria-labelledby="salary-generate-title">
            <div class="salary-gen-modal__head">
                <div>
                    <h3 id="salary-generate-title" class="salary-gen-modal__title">Hitung gaji karyawan</h3>
                    <p class="salary-gen-modal__sub">Pilih pegawai dan periode. Setelah dihitung, konfirmasi bayar per karyawan.</p>
                </div>
                <button type="button" class="btn-secondary btn-sm shrink-0" data-generate-close>Tutup</button>
            </div>

            <form method="POST" action="{{ route('admin.salaries.generate') }}" class="salary-gen-modal__body" data-generate-form>
                @csrf

                <fieldset class="salary-gen-modes">
                    <legend class="salary-gen-label">Hitung untuk</legend>
                    <label class="salary-gen-mode">
                        <input type="radio" name="scope" value="all" checked data-generate-scope>
                        <span>
                            <strong>Semua karyawan</strong>
                            <small>Hitung gaji seluruh pegawai aktif</small>
                        </span>
                    </label>
                    <label class="salary-gen-mode">
                        <input type="radio" name="scope" value="one" data-generate-scope>
                        <span>
                            <strong>Per pegawai</strong>
                            <small>Pilih satu karyawan saja</small>
                        </span>
                    </label>
                </fieldset>

                <div class="salary-gen-fields" data-generate-employee-wrap hidden>
                    <label class="salary-gen-label" for="generate_employee_id">Karyawan</label>
                    <select
                        id="generate_employee_id"
                        name="employee_id"
                        class="form-input"
                        data-generate-employee
                        data-searchable-select
                        data-search-placeholder="Pilih karyawan"
                        data-search-input-placeholder="Cari karyawan..."
                    >
                        <option value="">Pilih karyawan</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="salary-gen-modes">
                    <legend class="salary-gen-label">Jenis periode</legend>
                    <label class="salary-gen-mode">
                        <input type="radio" name="mode" value="month" checked data-generate-mode>
                        <span>
                            <strong>Per bulan</strong>
                            <small>Awal bulan s/d akhir bulan</small>
                        </span>
                    </label>
                    <label class="salary-gen-mode">
                        <input type="radio" name="mode" value="week" data-generate-mode>
                        <span>
                            <strong>Per minggu</strong>
                            <small>Senin s/d Minggu</small>
                        </span>
                    </label>
                    <label class="salary-gen-mode">
                        <input type="radio" name="mode" value="range" data-generate-mode>
                        <span>
                            <strong>Rentang tanggal</strong>
                            <small>Dari tanggal ke tanggal</small>
                        </span>
                    </label>
                </fieldset>

                <div class="salary-gen-fields" data-generate-panel="month">
                    <label class="salary-gen-label" for="generate_month">Bulan</label>
                    <input
                        id="generate_month"
                        type="month"
                        name="month"
                        class="form-input"
                        value="{{ $from->format('Y-m') }}"
                        data-generate-month
                    >
                </div>

                <div class="salary-gen-fields" data-generate-panel="week" hidden>
                    <label class="salary-gen-label" for="generate_week_date">Tanggal di minggu itu</label>
                    <input
                        id="generate_week_date"
                        type="date"
                        name="week_date"
                        class="form-input"
                        value="{{ $from->toDateString() }}"
                        data-generate-week
                    >
                    <p class="salary-gen-hint" data-generate-week-preview></p>
                </div>

                <div class="salary-gen-fields" data-generate-panel="range" hidden>
                    <div class="salary-gen-range">
                        <div>
                            <label class="salary-gen-label" for="generate_from">Dari tanggal</label>
                            <input
                                id="generate_from"
                                type="date"
                                name="period_from"
                                class="form-input"
                                value="{{ $from->toDateString() }}"
                                data-generate-from
                            >
                        </div>
                        <div>
                            <label class="salary-gen-label" for="generate_to">Sampai tanggal</label>
                            <input
                                id="generate_to"
                                type="date"
                                name="period_to"
                                class="form-input"
                                value="{{ $to->toDateString() }}"
                                data-generate-to
                            >
                        </div>
                    </div>
                </div>

                <div class="salary-gen-preview">
                    <p class="salary-gen-label">Periode yang akan dihitung</p>
                    <p class="salary-gen-preview__value" data-generate-preview>—</p>
                </div>

                <div class="salary-gen-modal__actions">
                    <button type="button" class="btn-secondary" data-generate-close>Batal</button>
                    <button type="submit" class="btn-primary">Hitung sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .salary-gen-modal {
            position: fixed !important;
            inset: 0 !important;
            z-index: 10060 !important;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
            margin: 0;
        }
        .salary-gen-modal.is-open { display: flex !important; }
        .salary-gen-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(28, 20, 16, 0.55);
            backdrop-filter: blur(2px);
        }
        .salary-gen-modal__panel {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 28rem;
            max-height: min(92dvh, 40rem);
            overflow: auto;
            border-radius: 1.25rem 1.25rem 0 0;
            background: #fff;
            box-shadow: 0 -8px 40px rgba(28, 20, 16, 0.18);
        }
        .salary-gen-modal__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1rem 0.75rem;
            border-bottom: 1px solid #f1ebe3;
        }
        .salary-gen-modal__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }
        .salary-gen-modal__sub {
            margin: 0.25rem 0 0;
            font-size: 0.75rem;
            color: #64748b;
            line-height: 1.4;
        }
        .salary-gen-modal__body {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
            padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        }
        .salary-gen-label {
            display: block;
            margin: 0 0 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
        }
        .salary-gen-modes {
            margin: 0;
            padding: 0;
            border: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .salary-gen-mode {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            padding: 0.7rem 0.75rem;
            border: 1px solid #e8e0d4;
            border-radius: 0.75rem;
            background: #fbf8f3;
            cursor: pointer;
        }
        .salary-gen-mode:has(input:checked) {
            border-color: #5c4033;
            background: #f3ebe3;
            box-shadow: inset 0 0 0 1px #5c4033;
        }
        .salary-gen-mode input {
            margin-top: 0.2rem;
            accent-color: #5c4033;
        }
        .salary-gen-mode strong {
            display: block;
            font-size: 0.875rem;
            color: #0f172a;
        }
        .salary-gen-mode small {
            display: block;
            margin-top: 0.15rem;
            font-size: 0.75rem;
            color: #64748b;
        }
        .salary-gen-fields[hidden] { display: none !important; }
        .salary-gen-range {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: 1fr;
        }
        .salary-gen-hint {
            margin: 0.4rem 0 0;
            font-size: 0.75rem;
            color: #64748b;
        }
        .salary-gen-preview {
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .salary-gen-preview__value {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 650;
            color: #5c4033;
        }
        .salary-gen-modal__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.5rem;
            padding-top: 0.25rem;
        }
        @media (min-width: 640px) {
            .salary-gen-modal {
                align-items: center;
                padding: 1rem;
            }
            .salary-gen-modal__panel {
                border-radius: 1rem;
                box-shadow: 0 25px 50px -12px rgba(28, 20, 16, 0.28);
            }
            .salary-gen-range {
                grid-template-columns: 1fr 1fr;
            }
        }
        body.salary-gen-modal-open {
            overflow: hidden !important;
        }
    </style>

    {{-- Modal detail absensi: CSS sendiri + portal ke body agar tidak bentrok layout admin --}}
    <div
        id="attendance-detail-modal"
        class="salary-att-modal"
        data-attendance-modal
        aria-hidden="true"
        hidden
    >
        <div class="salary-att-modal__backdrop" data-attendance-modal-close></div>
        <div
            class="salary-att-modal__panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="attendance-detail-title"
        >
            <div class="salary-att-modal__head">
                <div class="salary-att-modal__grab" aria-hidden="true"></div>
                <div class="salary-att-modal__head-row">
                    <div class="min-w-0 flex-1">
                        <h3 id="attendance-detail-title" class="salary-att-modal__title" data-attendance-modal-title>Detail absensi</h3>
                        <p class="salary-att-modal__sub">Periode {{ $periodLabel }}</p>
                    </div>
                    <button type="button" class="btn-secondary btn-sm shrink-0" data-attendance-modal-close>Tutup</button>
                </div>
                <div class="salary-att-modal__stats" data-attendance-modal-stats></div>
            </div>

            <div class="salary-att-modal__body">
                <div class="salary-att-modal__hint" data-attendance-late-hint hidden>
                    Centang hari telat yang <strong>ingin dipotong</strong>. Hilangkan centang jika hari itu seharusnya tidak dihitung.
                </div>
                <div class="salary-att-modal__list" data-attendance-modal-body></div>
                <div class="salary-att-modal__empty" data-attendance-modal-empty hidden>
                    <p class="text-sm font-medium text-slate-700">Tidak ada data absensi</p>
                    <p class="mt-1 text-xs text-slate-500">Belum ada absensi di periode ini untuk karyawan ini.</p>
                </div>
            </div>
            <div class="salary-att-modal__foot" data-attendance-modal-foot hidden>
                <p class="salary-att-modal__foot-text" data-attendance-late-summary></p>
                <button type="button" class="btn-primary btn-sm" data-attendance-modal-close>Terapkan</button>
            </div>
        </div>
    </div>

    <style>
        .salary-att-modal {
            position: fixed !important;
            inset: 0 !important;
            z-index: 10050 !important;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
            margin: 0;
        }
        .salary-att-modal.is-open {
            display: flex !important;
        }
        .salary-att-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(28, 20, 16, 0.55);
            backdrop-filter: blur(2px);
        }
        .salary-att-modal__panel {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 40rem;
            height: min(88dvh, 40rem);
            max-height: min(88dvh, 40rem);
            overflow: hidden;
            border-radius: 1.25rem 1.25rem 0 0;
            background: #fbf8f3;
            box-shadow: 0 -8px 40px rgba(28, 20, 16, 0.18);
        }
        .salary-att-modal__grab {
            display: block;
            width: 2.5rem;
            height: 0.25rem;
            margin: 0.4rem auto 0.65rem;
            border-radius: 999px;
            background: #d6cfc4;
        }
        .salary-att-modal__head {
            flex-shrink: 0;
            padding: 0.25rem 1rem 0.85rem;
            border-bottom: 1px solid #ebe3d6;
            background: #fff;
        }
        .salary-att-modal__head-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .salary-att-modal__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }
        .salary-att-modal__sub {
            margin: 0.2rem 0 0;
            font-size: 0.75rem;
            color: #64748b;
        }
        .salary-att-modal__stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.75rem;
        }
        .salary-att-modal__chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.6875rem;
            font-weight: 600;
        }
        .salary-att-modal__body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding: 0.85rem 1rem 1.25rem;
        }
        .salary-att-modal__hint {
            margin: 0 0 0.75rem;
            padding: 0.65rem 0.75rem;
            border-radius: 0.65rem;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            font-size: 0.75rem;
            color: #9a3412;
            line-height: 1.4;
        }
        .salary-att-modal__list {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .salary-att-modal__empty {
            border: 1px dashed #e2e8f0;
            border-radius: 0.85rem;
            background: #fff;
            padding: 2.5rem 1rem;
            text-align: center;
        }
        .salary-att-modal__foot {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid #ebe3d6;
            background: #fff;
        }
        .salary-att-modal__foot-text {
            margin: 0;
            font-size: 0.75rem;
            color: #475569;
            line-height: 1.35;
        }
        .salary-att-day {
            display: block;
            border: 1px solid #e8e0d4;
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.85rem;
        }
        .salary-att-day.is-late {
            border-color: #f5d0a0;
            background: #fffbeb;
        }
        .salary-att-day.is-no-out {
            border-color: #fdba74;
            background: #fff7ed;
        }
        .salary-att-day.is-waived {
            opacity: 0.72;
        }
        .salary-att-day__top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .salary-att-day__date {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #0f172a;
        }
        .salary-att-day__dow {
            margin: 0.1rem 0 0;
            font-size: 0.75rem;
            color: #64748b;
        }
        .salary-att-day__badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.35rem;
        }
        .salary-att-day__badge {
            display: inline-flex;
            border-radius: 0.4rem;
            padding: 0.15rem 0.45rem;
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .salary-att-day__grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.45rem;
            margin-top: 0.75rem;
        }
        .salary-att-day__cell {
            min-width: 0;
            border-radius: 0.55rem;
            background: rgba(255, 255, 255, 0.9);
            padding: 0.5rem 0.55rem;
            box-shadow: inset 0 0 0 1px #f1ebe3;
        }
        .salary-att-day__label {
            margin: 0;
            font-size: 0.625rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .salary-att-day__value {
            margin: 0.15rem 0 0;
            font-size: 1rem;
            font-weight: 650;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
            line-height: 1.2;
        }
        .salary-att-day__value.is-warn { color: #92400e; }
        .salary-att-day__value.is-alert { color: #c2410c; }
        .salary-att-day__value.is-muted { color: #334155; font-size: 0.8125rem; }
        .salary-att-day__note {
            margin: 0.55rem 0 0;
            font-size: 0.75rem;
            color: #475569;
        }
        .salary-att-day__toggle {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            margin-top: 0.7rem;
            padding: 0.6rem 0.65rem;
            border-radius: 0.6rem;
            background: #fff;
            border: 1px solid #f0e6d8;
            cursor: pointer;
            user-select: none;
        }
        .salary-att-day__toggle input {
            margin-top: 0.15rem;
            width: 1rem;
            height: 1rem;
            accent-color: #5c4033;
            flex-shrink: 0;
        }
        .salary-att-day__toggle-copy {
            min-width: 0;
            flex: 1;
        }
        .salary-att-day__toggle-title {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1e293b;
        }
        .salary-att-day__toggle-sub {
            margin: 0.15rem 0 0;
            font-size: 0.6875rem;
            color: #64748b;
        }
        .salary-att-day__toggle-amount {
            flex-shrink: 0;
            font-size: 0.8125rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #b91c1c;
        }
        @media (min-width: 640px) {
            .salary-att-modal {
                align-items: center;
                padding: 1rem;
            }
            .salary-att-modal__panel {
                border-radius: 1rem;
                height: min(85dvh, 42rem);
                max-height: min(85dvh, 42rem);
                box-shadow: 0 25px 50px -12px rgba(28, 20, 16, 0.28);
            }
            .salary-att-modal__grab {
                display: none;
            }
            .salary-att-modal__head {
                padding: 1rem 1.15rem 0.9rem;
            }
        }
        @media (max-width: 380px) {
            .salary-att-day__grid {
                grid-template-columns: 1fr;
            }
            .salary-att-day__value {
                font-size: 1.05rem;
            }
        }
        body.salary-att-modal-open {
            overflow: hidden !important;
        }
    </style>

    <script type="application/json" id="salary-previews-json">{!! json_encode($previews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script>
        (function () {
            const form = document.querySelector('[data-salary-form]');
            const employeeSelect = form?.querySelector('[data-salary-employee]');
            const totalEl = form?.querySelector('[data-salary-total]');
            const allowanceHidden = form?.querySelector('input[data-rupiah-target="allowance"]');
            const allowanceVisible = form?.querySelector('.rupiah-input[data-rupiah-hidden="allowance"]');
            const manualHidden = form?.querySelector('input[data-rupiah-target="manual_deduction"]');
            const manualVisible = form?.querySelector('.rupiah-input[data-rupiah-hidden="manual_deduction"]');
            const panel = form?.querySelector('[data-deduction-panel]');
            const listEl = form?.querySelector('[data-deduction-list]');
            const emptyEl = form?.querySelector('[data-deduction-empty]');
            const checkAllBtn = form?.querySelector('[data-deduction-check-all]');
            const uncheckAllBtn = form?.querySelector('[data-deduction-uncheck-all]');
            const detailBtn = form?.querySelector('[data-attendance-detail-btn]');
            const modal = document.querySelector('[data-attendance-modal]');
            const modalTitle = document.querySelector('[data-attendance-modal-title]');
            const modalBody = document.querySelector('[data-attendance-modal-body]');
            const modalEmpty = document.querySelector('[data-attendance-modal-empty]');
            const modalStats = document.querySelector('[data-attendance-modal-stats]');
            const modalFoot = document.querySelector('[data-attendance-modal-foot]');
            const modalLateHint = document.querySelector('[data-attendance-late-hint]');
            const modalLateSummary = document.querySelector('[data-attendance-late-summary]');

            // Pindahkan modal ke <body> agar fixed tidak rusak oleh layout admin.
            if (modal && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            let previews = {};
            try {
                const raw = document.getElementById('salary-previews-json')?.textContent || '{}';
                previews = JSON.parse(raw);
            } catch (e) {
                previews = {};
            }

            const out = {
                base: form?.querySelector('[data-out-base]'),
                daily: form?.querySelector('[data-out-daily]'),
                workDays: form?.querySelector('[data-out-work-days]'),
                dailyTotal: form?.querySelector('[data-out-daily-total]'),
                dailyFormula: form?.querySelector('[data-out-daily-formula]'),
                weekly: form?.querySelector('[data-out-weekly]'),
                weeklyHint: form?.querySelector('[data-out-weekly-hint]'),
                deduction: form?.querySelector('[data-out-deduction]'),
                deductionHint: form?.querySelector('[data-out-deduction-hint]'),
                manualDeduction: form?.querySelector('[data-out-manual-deduction]'),
                subtotal: form?.querySelector('[data-out-subtotal]'),
            };

            const typeBadge = {
                late: 'bg-amber-100 text-amber-800',
                alpha: 'bg-red-100 text-red-800',
                izin: 'bg-sky-100 text-sky-800',
                sakit: 'bg-violet-100 text-violet-800',
                fixed: 'bg-slate-100 text-slate-700',
            };

            const typeLabel = {
                late: 'Telat',
                alpha: 'Alpha',
                izin: 'Izin',
                sakit: 'Sakit',
                fixed: 'Rutin',
            };

            const statusTone = {
                hadir: 'background:#d1fae5;color:#065f46',
                izin: 'background:#e0f2fe;color:#075985',
                sakit: 'background:#ede9fe;color:#5b21b6',
                alpha: 'background:#fee2e2;color:#991b1b',
                cuti: 'background:#f1f5f9;color:#334155',
            };

            function formatRp(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(n || 0)));
            }

            function parseAllowance() {
                if (allowanceHidden && allowanceHidden.value !== '') {
                    return parseFloat(allowanceHidden.value) || 0;
                }
                const raw = (allowanceVisible?.value || '').replace(/[^\d]/g, '');
                return parseFloat(raw) || 0;
            }

            function parseManualDeduction() {
                if (manualHidden && manualHidden.value !== '') {
                    return parseFloat(manualHidden.value) || 0;
                }
                const raw = (manualVisible?.value || '').replace(/[^\d]/g, '');
                return parseFloat(raw) || 0;
            }

            function setRupiahField(hiddenEl, visibleEl, amount) {
                const n = Math.max(0, Math.round(amount || 0));
                if (hiddenEl) hiddenEl.value = String(n);
                if (visibleEl) {
                    visibleEl.value = n > 0 ? new Intl.NumberFormat('id-ID').format(n) : '';
                }
            }

            function setText(el, value) {
                if (el) el.textContent = value;
            }

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function currentItems() {
                const id = employeeSelect?.value;
                if (!id) return [];
                return (previews[id] && previews[id].deduction_items) ? previews[id].deduction_items : [];
            }

            function itemByKey(key) {
                return currentItems().find(function (item) { return item.key === key; }) || null;
            }

            function isDeductionApplied(key) {
                const formCb = findDeductionCheck(key);
                if (formCb) return !!formCb.checked;
                const item = itemByKey(key);
                return item ? item.applied !== false : true;
            }

            function findDeductionCheck(key) {
                if (!form) return null;
                return Array.from(form.querySelectorAll('[data-deduction-check]')).find(function (el) {
                    return el.value === key;
                }) || null;
            }

            function setDeductionApplied(key, applied, amount) {
                if (!form) return;
                let formCb = findDeductionCheck(key);
                if (!formCb) {
                    const holder = form.querySelector('[data-deduction-list]') || form;
                    const label = document.createElement('label');
                    label.className = 'hidden';
                    label.innerHTML = '<input type="checkbox" name="apply_deduction[]" value="' + escapeHtml(key) + '" data-deduction-check data-amount="' + (amount || 0) + '">';
                    holder.appendChild(label);
                    formCb = label.querySelector('[data-deduction-check]');
                }
                if (formCb) {
                    formCb.checked = !!applied;
                    if (amount != null) formCb.setAttribute('data-amount', String(amount || 0));
                }
                const item = itemByKey(key);
                if (item) item.applied = !!applied;
                // Sync tampilan panel potongan tanpa menghancurkan state.
                const panelCb = findDeductionCheck(key);
                if (panelCb && panelCb !== formCb) panelCb.checked = !!applied;
                recalc();
                updateLateSummary();
                syncDayCardState(key, applied);
            }

            function syncDayCardState(key, applied) {
                const card = modalBody?.querySelector('[data-late-key="' + key.replace(/"/g, '') + '"]');
                if (!card) return;
                card.classList.toggle('is-waived', !applied);
                // Refresh badge "Tidak dipotong"
                const badges = card.querySelector('.salary-att-day__badges');
                if (!badges) return;
                const existing = badges.querySelector('[data-waived-badge]');
                if (!applied && !existing) {
                    const span = document.createElement('span');
                    span.className = 'salary-att-day__badge';
                    span.setAttribute('data-waived-badge', '1');
                    span.style.cssText = 'background:#e2e8f0;color:#475569';
                    span.textContent = 'Tidak dipotong';
                    badges.appendChild(span);
                } else if (applied && existing) {
                    existing.remove();
                }
            }

            function cleanNote(note) {
                if (!note) return '';
                let text = String(note);
                text = text
                    .replace(/\bTerlambat\b/gi, '')
                    .replace(/\bTidak absen pulang\b/gi, '')
                    .replace(/\bShift sebelumnya tidak absen pulang\b/gi, '')
                    .replace(/\bShift sebelumnya\b/gi, '')
                    .replace(/\s*[·|,]\s*/g, ' · ')
                    .replace(/(?:^|\s)·(?:\s|$)/g, ' ')
                    .replace(/\s{2,}/g, ' ')
                    .replace(/^[·\s]+|[·\s]+$/g, '')
                    .trim();
                return text;
            }

            function updateLateSummary() {
                if (!modalBody || !modalLateSummary) return;
                if (modalLateHint && !modalLateHint.dataset.defaultHtml) {
                    modalLateHint.dataset.defaultHtml = modalLateHint.innerHTML;
                }
                const toggles = modalBody.querySelectorAll('[data-late-potongan]');
                if (!toggles.length) {
                    if (modalFoot) modalFoot.hidden = true;
                    if (modalLateHint) {
                        modalLateHint.hidden = false;
                        modalLateHint.innerHTML = 'Tidak ada hari telat di periode ini. Jika absensi ditandai telat tapi tidak muncul, refresh halaman atau cek data absensi.';
                    }
                    return;
                }
                let on = 0;
                let off = 0;
                let amount = 0;
                toggles.forEach(function (el) {
                    if (el.checked) {
                        on++;
                        amount += parseFloat(el.getAttribute('data-amount') || '0') || 0;
                    } else {
                        off++;
                    }
                });
                if (modalLateHint) {
                    modalLateHint.hidden = false;
                    modalLateHint.innerHTML = modalLateHint.dataset.defaultHtml || modalLateHint.innerHTML;
                }
                if (modalFoot) modalFoot.hidden = false;
                modalLateSummary.textContent = on + ' hari dipotong' + (off ? ' · ' + off + ' dikecualikan' : '') + (amount > 0 ? ' · ' + formatRp(amount) : '');
            }

            function renderStats(days) {
                if (!modalStats) return;
                let hadir = 0, late = 0, noOut = 0, other = 0;
                days.forEach(function (d) {
                    if (d.status === 'hadir') {
                        hadir++;
                        if (d.is_late || d.can_toggle_potongan) late++;
                        if (!d.check_out) noOut++;
                    } else {
                        other++;
                    }
                });
                const chips = [
                    { label: days.length + ' hari', bg: '#f1f5f9', color: '#334155' },
                    { label: hadir + ' hadir', bg: '#d1fae5', color: '#065f46' },
                    { label: late + ' telat', bg: late ? '#fde68a' : '#f1f5f9', color: late ? '#92400e' : '#64748b' },
                    { label: noOut + ' belum pulang', bg: noOut ? '#ffedd5' : '#f1f5f9', color: noOut ? '#9a3412' : '#64748b' },
                ];
                if (other > 0) {
                    chips.push({ label: other + ' non-hadir', bg: '#e0f2fe', color: '#075985' });
                }
                modalStats.innerHTML = chips.map(function (c) {
                    return '<span class="salary-att-modal__chip" style="background:' + c.bg + ';color:' + c.color + '">' + escapeHtml(c.label) + '</span>';
                }).join('');
            }

            function openAttendanceModal(employeeId, employeeName) {
                if (!modal || !modalBody) return;
                const preview = previews[String(employeeId)] || previews[employeeId] || {};
                const days = preview.attendance_days || [];
                const name = employeeName || preview.employee_name || 'Karyawan';

                if (modalTitle) {
                    modalTitle.textContent = 'Detail absensi · ' + name;
                }

                // Pastikan checkbox potongan form sudah ada sebelum sync.
                renderDeductionList();
                renderStats(days);

                if (!days.length) {
                    modalBody.innerHTML = '';
                    if (modalEmpty) {
                        modalEmpty.hidden = false;
                        modalEmpty.classList.remove('hidden');
                    }
                    if (modalFoot) modalFoot.hidden = true;
                    if (modalLateHint) modalLateHint.hidden = true;
                } else {
                    if (modalEmpty) {
                        modalEmpty.hidden = true;
                        modalEmpty.classList.add('hidden');
                    }
                    modalBody.innerHTML = days.map(function (day) {
                        const isHadir = day.status === 'hadir';
                        const noCheckout = isHadir && !!day.check_in && !day.check_out;
                        const note = cleanNote(day.note);
                        const canToggle = !!(day.can_toggle_potongan && day.deduction_key);
                        const lateKey = day.deduction_key || ('late:' + day.date);
                        const lateAmount = parseFloat(day.deduction_amount || 0) || 0;
                        const applied = canToggle ? isDeductionApplied(lateKey) : true;

                        let dayClass = 'salary-att-day';
                        if (day.is_late || canToggle) dayClass += ' is-late';
                        else if (noCheckout) dayClass += ' is-no-out';
                        if (canToggle && !applied) dayClass += ' is-waived';

                        const badges = [];
                        const tone = statusTone[day.status] || statusTone.hadir;
                        badges.push('<span class="salary-att-day__badge" style="' + tone + '">' + escapeHtml(day.status_label || day.status) + '</span>');
                        if (day.is_late || canToggle) {
                            badges.push(
                                '<span class="salary-att-day__badge" style="background:#fde68a;color:#78350f">Telat' +
                                (day.minutes_late != null ? ' +' + day.minutes_late + ' mnt' : '') +
                                '</span>'
                            );
                            if (!applied) {
                                badges.push('<span class="salary-att-day__badge" style="background:#e2e8f0;color:#475569">Tidak dipotong</span>');
                            }
                        } else if (isHadir && day.check_in) {
                            badges.push('<span class="salary-att-day__badge" style="background:#ecfdf5;color:#047857">Tepat waktu</span>');
                        }
                        if (noCheckout) {
                            badges.push('<span class="salary-att-day__badge" style="background:#ffedd5;color:#9a3412">Belum pulang</span>');
                        }

                        const toggleHtml = canToggle
                            ? (
                                '<label class="salary-att-day__toggle">' +
                                    '<input type="checkbox" data-late-potongan value="' + escapeHtml(lateKey) + '" data-amount="' + lateAmount + '" ' + (applied ? 'checked' : '') + '>' +
                                    '<span class="salary-att-day__toggle-copy">' +
                                        '<p class="salary-att-day__toggle-title">Masukkan ke potongan gaji</p>' +
                                        '<p class="salary-att-day__toggle-sub">Centang = dipotong. Kosongkan jika telat ini tidak dihitung.</p>' +
                                    '</span>' +
                                    '<span class="salary-att-day__toggle-amount">' + formatRp(lateAmount) + '</span>' +
                                '</label>'
                            )
                            : '';

                        return (
                            '<article class="' + dayClass + '" data-day-card data-late-key="' + escapeHtml(canToggle ? lateKey : '') + '">' +
                                '<div class="salary-att-day__top">' +
                                    '<div>' +
                                        '<p class="salary-att-day__date">' + escapeHtml(day.date_label) + '</p>' +
                                        '<p class="salary-att-day__dow">' + escapeHtml(day.day) + '</p>' +
                                    '</div>' +
                                    '<div class="salary-att-day__badges">' + badges.join('') + '</div>' +
                                '</div>' +
                                '<div class="salary-att-day__grid">' +
                                    '<div class="salary-att-day__cell">' +
                                        '<p class="salary-att-day__label">Masuk</p>' +
                                        '<p class="salary-att-day__value' + ((day.is_late || canToggle) ? ' is-warn' : '') + '">' + escapeHtml(day.check_in || '—') + '</p>' +
                                    '</div>' +
                                    '<div class="salary-att-day__cell">' +
                                        '<p class="salary-att-day__label">Pulang</p>' +
                                        '<p class="salary-att-day__value' + (noCheckout ? ' is-alert' : '') + '">' + escapeHtml(day.check_out || '—') + '</p>' +
                                    '</div>' +
                                    '<div class="salary-att-day__cell">' +
                                        '<p class="salary-att-day__label">Jadwal</p>' +
                                        '<p class="salary-att-day__value is-muted">' + escapeHtml((day.schedule_in || '—') + '–' + (day.schedule_out || '—')) + '</p>' +
                                    '</div>' +
                                '</div>' +
                                toggleHtml +
                                (note ? '<p class="salary-att-day__note"><strong>Catatan:</strong> ' + escapeHtml(note) + '</p>' : '') +
                            '</article>'
                        );
                    }).join('');
                }

                updateLateSummary();
                modal.hidden = false;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('salary-att-modal-open');
                modalBody?.scrollTo?.(0, 0);
            }

            function closeAttendanceModal() {
                if (!modal) return;
                modal.classList.remove('is-open');
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('salary-att-modal-open');
            }

            function renderDeductionList() {
                if (!panel || !listEl) return;
                const items = currentItems();
                const selected = !!(employeeSelect?.value);

                if (!selected) {
                    panel.hidden = true;
                    listEl.innerHTML = '';
                    return;
                }

                panel.hidden = false;
                if (!items.length) {
                    listEl.innerHTML = '';
                    if (emptyEl) emptyEl.classList.remove('hidden');
                    return;
                }

                if (emptyEl) emptyEl.classList.add('hidden');
                listEl.innerHTML = items.map(function (item) {
                    const checked = item.applied !== false ? 'checked' : '';
                    const badge = typeBadge[item.type] || typeBadge.fixed;
                    const tip = typeLabel[item.type] || item.type;
                    return (
                        '<label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 hover:border-brand-300">' +
                            '<input type="checkbox" name="apply_deduction[]" value="' + escapeHtml(item.key) + '" class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500" data-deduction-check data-amount="' + (item.amount || 0) + '" ' + checked + '>' +
                            '<span class="min-w-0 flex-1">' +
                                '<span class="inline-flex flex-wrap items-center gap-2">' +
                                    '<span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ' + badge + '">' + escapeHtml(tip) + '</span>' +
                                    '<span class="text-sm font-medium text-slate-800">' + escapeHtml(item.label) + '</span>' +
                                '</span>' +
                                (item.detail ? '<span class="mt-0.5 block text-xs text-slate-500">' + escapeHtml(item.detail) + '</span>' : '') +
                            '</span>' +
                            '<span class="shrink-0 text-sm font-semibold tabular-nums text-red-600">' + formatRp(item.amount) + '</span>' +
                        '</label>'
                    );
                }).join('');
            }

            function selectedDeductionTotal() {
                let sum = 0;
                form?.querySelectorAll('[data-deduction-check]:checked').forEach(function (el) {
                    sum += parseFloat(el.getAttribute('data-amount') || '0') || 0;
                });
                return sum;
            }

            function recalc() {
                if (!form) return;
                const opt = employeeSelect?.selectedOptions?.[0];
                const selected = !!(opt && opt.value);
                const preview = selected ? (previews[opt.value] || {}) : {};

                if (detailBtn) {
                    detailBtn.disabled = !selected;
                }

                if (!selected) {
                    setText(out.base, '—');
                    setText(out.daily, '—');
                    setText(out.workDays, '—');
                    setText(out.dailyTotal, '—');
                    setText(out.dailyFormula, '');
                    setText(out.weekly, '—');
                    setText(out.weeklyHint, '');
                    setText(out.deduction, '—');
                    setText(out.deductionHint, '');
                    setText(out.manualDeduction, '—');
                    setText(out.subtotal, '—');
                    if (totalEl) totalEl.value = '';
                    renderDeductionList();
                    return;
                }

                const base = parseFloat(preview.base ?? (opt.getAttribute('data-base') || '0')) || 0;
                const daily = parseFloat(preview.daily ?? (opt.getAttribute('data-daily') || '0')) || 0;
                const daysWeek = parseFloat(preview.days_week ?? (opt.getAttribute('data-days-week') || '5')) || 0;
                const weekly = parseFloat(preview.weekly ?? (opt.getAttribute('data-weekly') || '0')) || 0;
                const workDays = parseFloat(preview.work_days ?? (opt.getAttribute('data-work-days') || '0')) || 0;
                const dailyTotal = parseFloat(preview.daily_total ?? (opt.getAttribute('data-daily-total') || '0')) || 0;
                const autoDeduction = selectedDeductionTotal();
                const manualDeduction = parseManualDeduction();
                const deduction = autoDeduction + manualDeduction;
                const checkedCount = form.querySelectorAll('[data-deduction-check]:checked').length;
                const totalItems = form.querySelectorAll('[data-deduction-check]').length;
                const waivedCount = Math.max(0, totalItems - checkedCount);
                const allowance = parseAllowance();
                const subtotal = Math.max(0, base + dailyTotal - deduction);
                const total = Math.max(0, base + dailyTotal + allowance - deduction);

                let hint = '';
                if (totalItems === 0) {
                    hint = 'Tidak ada potongan absensi';
                } else {
                    hint = checkedCount + ' item dipotong';
                    if (waivedCount > 0) hint += ' · ' + waivedCount + ' dikecualikan';
                }

                setText(out.base, formatRp(base));
                setText(out.daily, formatRp(daily));
                setText(out.workDays, workDays + ' hari');
                setText(out.dailyTotal, formatRp(dailyTotal));
                setText(out.dailyFormula, formatRp(daily) + ' × ' + workDays + ' hari hadir');
                setText(out.weekly, formatRp(weekly));
                setText(out.weeklyHint, 'Estimasi · ' + formatRp(daily) + ' × ' + daysWeek + ' jadwal');
                setText(out.deduction, '− ' + formatRp(autoDeduction));
                setText(out.deductionHint, hint);
                setText(out.manualDeduction, '− ' + formatRp(manualDeduction));
                setText(out.subtotal, formatRp(subtotal));
                if (totalEl) totalEl.value = new Intl.NumberFormat('id-ID').format(Math.round(total));
            }

            function onEmployeeChange() {
                const id = employeeSelect?.value;
                const preview = id ? (previews[id] || {}) : {};
                // Hanya isi jika sudah pernah disimpan; selain itu biarkan kosong (opsional).
                const savedManual = parseFloat(preview.manual_deduction || 0) || 0;
                setRupiahField(manualHidden, manualVisible, savedManual > 0 ? savedManual : 0);
                if (savedManual <= 0) {
                    if (manualHidden) manualHidden.value = '';
                    if (manualVisible) manualVisible.value = '';
                }
                renderDeductionList();
                recalc();
            }

            employeeSelect?.addEventListener('change', onEmployeeChange);
            allowanceVisible?.addEventListener('input', recalc);
            allowanceVisible?.addEventListener('blur', recalc);
            manualVisible?.addEventListener('input', recalc);
            manualVisible?.addEventListener('blur', recalc);
            listEl?.addEventListener('change', function (e) {
                if (e.target && e.target.matches('[data-deduction-check]')) recalc();
            });
            checkAllBtn?.addEventListener('click', function () {
                form?.querySelectorAll('[data-deduction-check]').forEach(function (el) { el.checked = true; });
                recalc();
            });
            uncheckAllBtn?.addEventListener('click', function () {
                form?.querySelectorAll('[data-deduction-check]').forEach(function (el) { el.checked = false; });
                recalc();
            });
            form?.addEventListener('submit', recalc);

            detailBtn?.addEventListener('click', function () {
                const id = employeeSelect?.value;
                if (!id) return;
                const name = employeeSelect?.selectedOptions?.[0]?.textContent?.trim() || '';
                openAttendanceModal(id, name);
            });

            document.querySelectorAll('[data-open-attendance-detail]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openAttendanceModal(
                        btn.getAttribute('data-employee-id'),
                        btn.getAttribute('data-employee-name')
                    );
                });
            });

            document.querySelectorAll('[data-edit-salary]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = btn.getAttribute('data-employee-id') || '';
                    const allowance = parseFloat(btn.getAttribute('data-allowance') || '0') || 0;
                    const manual = parseFloat(btn.getAttribute('data-manual-deduction') || '0') || 0;
                    const notes = btn.getAttribute('data-notes') || '';
                    if (!id || !employeeSelect) return;

                    employeeSelect.value = id;
                    employeeSelect.dispatchEvent(new Event('change', { bubbles: true }));

                    setRupiahField(allowanceHidden, allowanceVisible, allowance);
                    setRupiahField(manualHidden, manualVisible, manual);
                    if (manual <= 0) {
                        if (manualHidden) manualHidden.value = '';
                        if (manualVisible) manualVisible.value = '';
                    }

                    const notesInput = form?.querySelector('#notes');
                    if (notesInput) notesInput.value = notes;

                    recalc();
                    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            modal?.querySelectorAll('[data-attendance-modal-close]').forEach(function (el) {
                el.addEventListener('click', closeAttendanceModal);
            });
            modalBody?.addEventListener('change', function (e) {
                const el = e.target;
                if (!el || !el.matches || !el.matches('[data-late-potongan]')) return;
                setDeductionApplied(el.value, el.checked, parseFloat(el.getAttribute('data-amount') || '0') || 0);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAttendanceModal();
            });

            if (form) onEmployeeChange();
        })();

        (function () {
            const modal = document.querySelector('[data-pay-modal]');
            const form = document.querySelector('[data-pay-form]');
            if (!modal || !form) return;

            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            const nameEl = modal.querySelector('[data-pay-name]');
            const periodEl = modal.querySelector('[data-pay-period]');
            const totalEl = modal.querySelector('[data-pay-total]');

            function openPay(btn) {
                form.action = btn.getAttribute('data-pay-url') || '';
                if (nameEl) nameEl.textContent = btn.getAttribute('data-pay-name') || '—';
                if (periodEl) periodEl.textContent = btn.getAttribute('data-pay-period') || '—';
                if (totalEl) totalEl.textContent = btn.getAttribute('data-pay-total') || '—';
                modal.hidden = false;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('salary-gen-modal-open');
            }

            function closePay() {
                modal.classList.remove('is-open');
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('salary-gen-modal-open');
            }

            document.querySelectorAll('[data-pay-open]').forEach(function (btn) {
                btn.addEventListener('click', function () { openPay(btn); });
            });
            modal.querySelectorAll('[data-pay-close]').forEach(function (el) {
                el.addEventListener('click', closePay);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closePay();
                }
            });
        })();

        (function () {
            const modal = document.querySelector('[data-generate-modal]');
            const openBtn = document.querySelector('[data-generate-open]');
            const form = document.querySelector('[data-generate-form]');
            if (!modal || !form) return;

            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }

            const modeInputs = form.querySelectorAll('[data-generate-mode]');
            const scopeInputs = form.querySelectorAll('[data-generate-scope]');
            const employeeWrap = form.querySelector('[data-generate-employee-wrap]');
            const employeeSelect = form.querySelector('[data-generate-employee]');
            const monthInput = form.querySelector('[data-generate-month]');
            const weekInput = form.querySelector('[data-generate-week]');
            const fromInput = form.querySelector('[data-generate-from]');
            const toInput = form.querySelector('[data-generate-to]');
            const weekPreview = form.querySelector('[data-generate-week-preview]');
            const previewEl = form.querySelector('[data-generate-preview]');

            function pad(n) { return String(n).padStart(2, '0'); }

            function toDateStr(d) {
                return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
            }

            function parseYmd(str) {
                if (!str) return null;
                const parts = String(str).split('-').map(Number);
                if (parts.length < 3 || parts.some(isNaN)) return null;
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function startOfWeek(d) {
                const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
                const day = x.getDay(); // 0 Sun .. 6 Sat
                const diff = day === 0 ? -6 : 1 - day; // Monday start
                x.setDate(x.getDate() + diff);
                return x;
            }

            function endOfWeek(d) {
                const s = startOfWeek(d);
                const e = new Date(s.getFullYear(), s.getMonth(), s.getDate() + 6);
                return e;
            }

            function endOfMonth(y, m) {
                return new Date(y, m, 0); // m is 1-based month number via day 0 of next month
            }

            function formatId(d) {
                try {
                    return new Intl.DateTimeFormat('id-ID', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                    }).format(d);
                } catch (e) {
                    return toDateStr(d);
                }
            }

            function currentMode() {
                const checked = form.querySelector('[data-generate-mode]:checked');
                return checked ? checked.value : 'month';
            }

            function currentScope() {
                const checked = form.querySelector('[data-generate-scope]:checked');
                return checked ? checked.value : 'all';
            }

            function selectedEmployeeLabel() {
                if (!employeeSelect || !employeeSelect.value) return '';
                const opt = employeeSelect.options[employeeSelect.selectedIndex];
                return opt ? String(opt.textContent || '').trim() : '';
            }

            function syncScope() {
                const scope = currentScope();
                const isOne = scope === 'one';
                if (employeeWrap) employeeWrap.hidden = !isOne;
                if (employeeSelect) {
                    employeeSelect.disabled = !isOne;
                    employeeSelect.required = isOne;
                    if (!isOne) employeeSelect.value = '';
                }
            }

            function syncPanels() {
                const mode = currentMode();
                form.querySelectorAll('[data-generate-panel]').forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-generate-panel') !== mode;
                });
                // Enable/disable unused fields so browser validation doesn't block submit.
                if (monthInput) monthInput.disabled = mode !== 'month';
                if (weekInput) weekInput.disabled = mode !== 'week';
                if (fromInput) fromInput.disabled = mode !== 'range';
                if (toInput) toInput.disabled = mode !== 'range';
                if (mode === 'month' && monthInput) monthInput.required = true;
                if (mode === 'week' && weekInput) weekInput.required = true;
                if (mode === 'range') {
                    if (fromInput) fromInput.required = true;
                    if (toInput) toInput.required = true;
                }
                syncScope();
                updatePreview();
            }

            function resolveRange() {
                const mode = currentMode();
                if (mode === 'month' && monthInput?.value) {
                    const [y, m] = monthInput.value.split('-').map(Number);
                    const from = new Date(y, m - 1, 1);
                    const to = endOfMonth(y, m);
                    return { from: from, to: to };
                }
                if (mode === 'week' && weekInput?.value) {
                    const day = parseYmd(weekInput.value);
                    if (!day) return null;
                    return { from: startOfWeek(day), to: endOfWeek(day) };
                }
                if (mode === 'range') {
                    const from = parseYmd(fromInput?.value);
                    const to = parseYmd(toInput?.value);
                    if (!from || !to) return null;
                    if (to < from) return { from: to, to: from };
                    return { from: from, to: to };
                }
                return null;
            }

            function updatePreview() {
                const range = resolveRange();
                if (!range) {
                    if (previewEl) previewEl.textContent = '—';
                    if (weekPreview) weekPreview.textContent = '';
                    return;
                }
                const label = formatId(range.from) + ' – ' + formatId(range.to);
                const who = currentScope() === 'one'
                    ? (selectedEmployeeLabel() || 'satu pegawai')
                    : 'semua karyawan';
                if (previewEl) previewEl.textContent = who + ' · ' + label;
                if (weekPreview && currentMode() === 'week') {
                    weekPreview.textContent = 'Minggu terpilih: ' + label;
                }
            }

            function openModal() {
                syncPanels();
                modal.hidden = false;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('salary-gen-modal-open');
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('salary-gen-modal-open');
            }

            openBtn?.addEventListener('click', openModal);
            modal.querySelectorAll('[data-generate-close]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            modeInputs.forEach(function (el) {
                el.addEventListener('change', syncPanels);
            });
            scopeInputs.forEach(function (el) {
                el.addEventListener('change', function () {
                    syncScope();
                    updatePreview();
                });
            });
            employeeSelect?.addEventListener('change', updatePreview);
            monthInput?.addEventListener('change', updatePreview);
            weekInput?.addEventListener('change', updatePreview);
            fromInput?.addEventListener('change', updatePreview);
            toInput?.addEventListener('change', updatePreview);

            form.addEventListener('submit', function (e) {
                const range = resolveRange();
                if (!range) {
                    e.preventDefault();
                    alert('Lengkapi tanggal periode terlebih dahulu.');
                    return;
                }
                if (currentScope() === 'one' && (!employeeSelect || !employeeSelect.value)) {
                    e.preventDefault();
                    alert('Pilih karyawan terlebih dahulu.');
                    return;
                }
                const label = formatId(range.from) + ' – ' + formatId(range.to);
                const who = currentScope() === 'one'
                    ? selectedEmployeeLabel()
                    : 'semua karyawan';
                if (!confirm('Hitung ulang gaji ' + who + ' untuk periode ' + label + '?\nSetelah dihitung, konfirmasi bayar per karyawan jika sudah dibayarkan.')) {
                    e.preventDefault();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });

            syncPanels();
        })();
    </script>
@endsection
