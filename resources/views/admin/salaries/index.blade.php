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

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[12rem] flex-1">
            <label class="form-label" for="month">Bulan</label>
            <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Tampilkan</button>
    </form>

    <div class="card mb-4 space-y-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Hitung gaji bulanan</h2>
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
            <form method="POST" action="{{ route('admin.salaries.generate') }}" onsubmit="return confirm('Hitung ulang gaji semua karyawan untuk bulan ini? Data yang sudah lunas tidak diubah.')">
                @csrf
                <input type="hidden" name="period_month" value="{{ $month->format('Y-m') }}">
                <button type="submit" class="btn-secondary btn-sm">Hitung semua karyawan</button>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.salaries.store') }}" class="space-y-4" data-salary-form>
            @csrf
            <input type="hidden" name="period_month" value="{{ $month->format('Y-m') }}">

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
                    <input type="text" class="form-input bg-slate-50" value="{{ $month->translatedFormat('F Y') }}" readonly>
                    <p class="mt-1 text-xs text-slate-500">Ganti bulan lewat filter di atas, lalu pilih karyawan lagi.</p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4" data-salary-preview>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ringkasan hitungan</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] text-slate-500">Gaji pokok</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-base>—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Gaji harian</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-daily>—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Hari hadir</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-work-days>—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Akumulasi harian</p>
                        <p class="text-sm font-semibold tabular-nums text-brand-800" data-out-daily-total>—</p>
                        <p class="text-[10px] text-slate-400" data-out-daily-formula></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">≈ / minggu</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-900" data-out-weekly>—</p>
                        <p class="text-[10px] text-slate-400" data-out-weekly-hint></p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-500">Potongan</p>
                        <p class="text-sm font-semibold tabular-nums text-red-600" data-out-deduction>—</p>
                        <p class="text-[10px] text-slate-400 break-words" data-out-deduction-hint></p>
                    </div>
                </div>
                <div class="mt-3 border-t border-slate-200 pt-3">
                    <p class="text-[11px] text-slate-500">Subtotal (sebelum tunjangan)</p>
                    <p class="text-lg font-bold tabular-nums text-brand-800" data-out-subtotal>—</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-rupiah-input
                        name="allowance"
                        label="Tunjangan (opsional)"
                        :value="old('allowance', 0)"
                        placeholder="0"
                    />
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
                    <p class="mt-1 text-xs text-slate-500">Pokok + akumulasi hadir + tunjangan − potongan.</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="form-label" for="notes">Catatan (opsional)</label>
                    <input id="notes" name="notes" class="form-input" value="{{ old('notes') }}" placeholder="Kosongkan — ringkasan otomatis ditambah saat simpan">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full sm:w-auto">Simpan Gaji</button>
        </form>
    </div>

    @php
        $monthTotal = $salaries->sum('total');
        $monthPeople = $salaries->count();
        $monthHadir = $salaries->sum(fn ($s) => (int) ($s->work_days ?? 0));
    @endphp

    <div class="space-y-3">
        <div class="card flex flex-wrap items-end justify-between gap-3 p-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Gaji {{ $month->translatedFormat('F Y') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $monthPeople }} karyawan
                    @if ($hasDailyColumns ?? true)
                        · {{ $monthHadir }} hari hadir
                    @endif
                </p>
            </div>
            @if ($salaries->isNotEmpty())
                <div class="text-right">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Total semua</p>
                    <p class="text-lg font-bold tabular-nums text-brand-800">{{ $format::rupiah($monthTotal) }}</p>
                </div>
            @endif
        </div>

        @forelse ($salaries as $salary)
            @php
                $dailyRate = (float) ($salary->daily_salary ?? 0);
                $workDays = (int) ($salary->work_days ?? 0);
                $dailyTotal = $dailyRate * $workDays;
                $daysWeek = $salary->employee?->scheduledWorkDaysPerWeek() ?? 5;
                $weeklyEst = $dailyRate * $daysWeek;
                $userNote = trim((string) preg_replace('/\s*\|\s*(Harian|Potongan|≈).*/u', '', (string) ($salary->notes ?? '')) );
            @endphp
            <article class="card overflow-hidden p-0">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-slate-900">{{ $salary->employee?->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $salary->employee?->employee_code }}</p>
                    </div>
                    <span class="badge shrink-0 {{ $salary->status->badgeClass() }}">{{ $salary->status->label() }}</span>
                </div>

                <div class="bg-brand-50/70 px-4 py-3">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-brand-800/70">Total bulan ini</p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-brand-900">{{ $format::rupiah($salary->total) }}</p>
                </div>

                @if ($hasDailyColumns ?? true)
                    <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100">
                        <div class="px-3 py-3 text-center">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Hadir</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900">{{ $workDays }}</p>
                            <p class="text-[10px] text-slate-400">hari</p>
                        </div>
                        <div class="px-3 py-3 text-center">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Akumulasi</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900">{{ $format::rupiah($dailyTotal) }}</p>
                            <p class="text-[10px] text-slate-400">harian × hadir</p>
                        </div>
                        <div class="px-3 py-3 text-center">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">/ minggu</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-900">{{ $format::rupiah($weeklyEst) }}</p>
                            <p class="text-[10px] text-slate-400">{{ $daysWeek }} hari jadwal</p>
                        </div>
                    </div>
                @endif

                <dl class="space-y-2 px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Pokok bulanan</dt>
                        <dd class="font-medium tabular-nums text-slate-900">{{ $format::rupiah($salary->base_salary) }}</dd>
                    </div>
                    @if ($hasDailyColumns ?? true)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Gaji harian</dt>
                            <dd class="text-right font-medium tabular-nums text-slate-900">
                                {{ $format::rupiah($dailyRate) }}
                                <span class="block text-[11px] font-normal text-slate-400">× {{ $workDays }} hari hadir</span>
                            </dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Tunjangan</dt>
                        <dd class="font-medium tabular-nums text-slate-900">{{ $format::rupiah($salary->allowance) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Potongan</dt>
                        <dd class="font-medium tabular-nums text-red-600">− {{ $format::rupiah($salary->deduction) }}</dd>
                    </div>
                </dl>

                @if ($userNote !== '')
                    <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">{{ $userNote }}</p>
                @endif

                <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/80 p-3 sm:flex-row sm:justify-end">
                    @if ($salary->status->value === 'draft')
                        <form action="{{ route('admin.salaries.paid', $salary) }}" method="POST" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn-sm btn-primary w-full">Tandai Lunas</button>
                        </form>
                    @endif
                    <form action="{{ route('admin.salaries.destroy', $salary) }}" method="POST" class="w-full sm:w-auto" onsubmit="return confirm('Hapus data gaji?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-sm btn-outline-danger w-full">Hapus</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card px-4 py-10 text-center text-sm text-slate-500">
                Belum ada data gaji bulan ini. Pilih karyawan lalu Simpan, atau klik
                <strong>Hitung semua karyawan</strong>.
            </div>
        @endforelse
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-salary-form]');
            if (!form) return;

            const employeeSelect = form.querySelector('[data-salary-employee]');
            const totalEl = form.querySelector('[data-salary-total]');
            const allowanceHidden = form.querySelector('input[data-rupiah-target="allowance"]');
            const allowanceVisible = form.querySelector('.rupiah-input[data-rupiah-hidden="allowance"]');

            const out = {
                base: form.querySelector('[data-out-base]'),
                daily: form.querySelector('[data-out-daily]'),
                workDays: form.querySelector('[data-out-work-days]'),
                dailyTotal: form.querySelector('[data-out-daily-total]'),
                dailyFormula: form.querySelector('[data-out-daily-formula]'),
                weekly: form.querySelector('[data-out-weekly]'),
                weeklyHint: form.querySelector('[data-out-weekly-hint]'),
                deduction: form.querySelector('[data-out-deduction]'),
                deductionHint: form.querySelector('[data-out-deduction-hint]'),
                subtotal: form.querySelector('[data-out-subtotal]'),
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

            function setText(el, value) {
                if (el) el.textContent = value;
            }

            function recalc() {
                const opt = employeeSelect?.selectedOptions?.[0];
                const selected = !!(opt && opt.value);

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
                    setText(out.subtotal, '—');
                    if (totalEl) totalEl.value = '';
                    return;
                }

                const base = parseFloat(opt.getAttribute('data-base') || '0') || 0;
                const daily = parseFloat(opt.getAttribute('data-daily') || '0') || 0;
                const daysWeek = parseFloat(opt.getAttribute('data-days-week') || '5') || 0;
                const weekly = parseFloat(opt.getAttribute('data-weekly') || '0') || 0;
                const workDays = parseFloat(opt.getAttribute('data-work-days') || '0') || 0;
                const dailyTotal = parseFloat(opt.getAttribute('data-daily-total') || '0') || 0;
                const deduction = parseFloat(opt.getAttribute('data-deduction') || '0') || 0;
                const deductionSummary = opt.getAttribute('data-deduction-summary') || '';
                const allowance = parseAllowance();
                const subtotal = Math.max(0, base + dailyTotal - deduction);
                const total = Math.max(0, base + dailyTotal + allowance - deduction);

                setText(out.base, formatRp(base));
                setText(out.daily, formatRp(daily));
                setText(out.workDays, workDays + ' hari');
                setText(out.dailyTotal, formatRp(dailyTotal));
                setText(out.dailyFormula, formatRp(daily) + ' × ' + workDays + ' hari');
                setText(out.weekly, formatRp(weekly));
                setText(out.weeklyHint, formatRp(daily) + ' × ' + daysWeek + ' hari jadwal');
                setText(out.deduction, '− ' + formatRp(deduction));
                setText(out.deductionHint, deductionSummary || (deduction > 0 ? '' : 'Tidak ada potongan'));
                setText(out.subtotal, formatRp(subtotal));
                if (totalEl) totalEl.value = new Intl.NumberFormat('id-ID').format(Math.round(total));
            }

            employeeSelect?.addEventListener('change', recalc);
            allowanceVisible?.addEventListener('input', recalc);
            allowanceVisible?.addEventListener('blur', recalc);
            form.addEventListener('submit', recalc);
            recalc();
        })();
    </script>
@endsection
