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
                    Gaji harian diakumulasi tiap hari hadir (gaji harian × hari hadir).
                    Estimasi per minggu = gaji harian × hari jadwal; per bulan ≈ 4 minggu atau dari absensi aktual.
                    Pokok bulanan (jika ada) ditambah. Potongan dari absensi — atur di
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="form-label" for="employee_id">Karyawan</label>
                    <select id="employee_id" name="employee_id" class="form-input" required data-salary-employee>
                        <option value="">Pilih karyawan</option>
                        @foreach ($employees as $employee)
                            <option
                                value="{{ $employee->id }}"
                                data-base="{{ (float) $employee->base_salary }}"
                                data-daily="{{ (float) ($employee->daily_salary ?? 0) }}"
                                data-days-week="{{ $employee->scheduledWorkDaysPerWeek() }}"
                            >{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="period_month">Periode</label>
                    <input id="period_month" type="month" name="period_month" value="{{ $month->format('Y-m') }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="base_salary_display">Gaji pokok bulanan</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input id="base_salary_display" type="text" class="form-input pl-10 bg-slate-50" value="" readonly data-salary-base placeholder="Otomatis">
                    </div>
                </div>
                <div>
                    <label class="form-label" for="daily_salary_display">Gaji harian</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input id="daily_salary_display" type="text" class="form-input pl-10 bg-slate-50" value="" readonly data-salary-daily placeholder="Otomatis">
                    </div>
                </div>
                <div>
                    <label class="form-label" for="weekly_display">Estimasi / minggu</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input id="weekly_display" type="text" class="form-input pl-10 bg-slate-50" value="" readonly data-salary-weekly placeholder="Harian × hari jadwal">
                    </div>
                    <p class="mt-1 text-xs text-slate-500" data-salary-weekly-hint>Gaji harian × hari kerja terjadwal.</p>
                </div>
                <div>
                    <label class="form-label" for="monthly_daily_display">Estimasi harian / bulan (±4 minggu)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input id="monthly_daily_display" type="text" class="form-input pl-10 bg-slate-50" value="" readonly data-salary-monthly-daily placeholder="≈ 4 minggu">
                    </div>
                </div>
                <div>
                    <x-rupiah-input
                        name="allowance"
                        label="Tunjangan (opsional)"
                        :value="old('allowance', 0)"
                        placeholder="0"
                    />
                </div>
                <div>
                    <label class="form-label" for="deduction_display">Potongan</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input
                            id="deduction_display"
                            type="text"
                            class="form-input pl-10 bg-slate-50"
                            value="{{ $format::inputValue($defaultDeduction) }}"
                            readonly
                            data-salary-deduction
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Otomatis dari absensi + pengaturan. Detail ada di catatan setelah simpan.</p>
                </div>
                <div>
                    <label class="form-label" for="total_display">Estimasi total bulan</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-brand-700">Rp</span>
                        <input
                            id="total_display"
                            type="text"
                            class="form-input pl-10 bg-brand-50 font-semibold text-brand-800"
                            value=""
                            readonly
                            data-salary-total
                            placeholder="Pokok + harian − potongan"
                        >
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Pokok + estimasi harian 4 minggu + tunjangan − potongan rutin. Saat simpan, harian diganti × hari hadir aktual.</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="form-label" for="notes">Catatan</label>
                    <input id="notes" name="notes" class="form-input" value="{{ old('notes') }}">
                </div>
            </div>
            <button type="submit" class="btn-primary">Simpan Gaji</button>
        </form>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold">Gaji {{ $month->translatedFormat('F Y') }}</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Karyawan</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Pokok</th>
                        @if ($hasDailyColumns ?? true)
                            <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Gaji harian</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Hari hadir</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Akumulasi harian</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">≈ / minggu</th>
                        @endif
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Tunjangan</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Potongan</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Total / bulan</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold">Status</th>
                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salaries as $salary)
                        @php
                            $dailyRate = (float) ($salary->daily_salary ?? 0);
                            $workDays = (int) ($salary->work_days ?? 0);
                            $dailyTotal = $dailyRate * $workDays;
                            $daysWeek = $salary->employee?->scheduledWorkDaysPerWeek() ?? 5;
                            $weeklyEst = $dailyRate * $daysWeek;
                        @endphp
                        <tr class="border-b border-slate-100 last:border-b-0">
                            <td class="px-4 py-3 align-top">
                                <p class="font-semibold text-slate-900">{{ $salary->employee?->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $salary->employee?->employee_code }}</p>
                                @if ($salary->notes)
                                    <p class="mt-1 text-xs text-slate-500">{{ $salary->notes }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $format::rupiah($salary->base_salary) }}</td>
                            @if ($hasDailyColumns ?? true)
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $format::rupiah($dailyRate) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $workDays }} hari</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $format::rupiah($dailyTotal) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                    {{ $format::rupiah($weeklyEst) }}
                                    <span class="block text-[10px] font-normal text-slate-400">{{ $daysWeek }} hari jadwal</span>
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $format::rupiah($salary->allowance) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-red-600">− {{ $format::rupiah($salary->deduction) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-brand-700">{{ $format::rupiah($salary->total) }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="badge {{ $salary->status->badgeClass() }}">{{ $salary->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-col items-end gap-1">
                                    @if ($salary->status->value === 'draft')
                                        <form action="{{ route('admin.salaries.paid', $salary) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn-sm btn-primary">Tandai Lunas</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.salaries.destroy', $salary) }}" method="POST" onsubmit="return confirm('Hapus data gaji?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-slate-500">Belum ada data gaji bulan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($salaries->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-900">Jumlah</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ $format::rupiah($salaries->sum('base_salary')) }}</td>
                            @if ($hasDailyColumns ?? true)
                                <td class="px-4 py-3"></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ $salaries->sum(fn ($s) => (int) ($s->work_days ?? 0)) }} hari</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">
                                    {{ $format::rupiah($salaries->sum(fn ($s) => (float) ($s->daily_salary ?? 0) * (int) ($s->work_days ?? 0))) }}
                                </td>
                                <td class="px-4 py-3"></td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums">{{ $format::rupiah($salaries->sum('allowance')) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-red-600">− {{ $format::rupiah($salaries->sum('deduction')) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-brand-800">{{ $format::rupiah($salaries->sum('total')) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <script>
        (function () {
            const form = document.querySelector('[data-salary-form]');
            if (! form) return;

            const employeeSelect = form.querySelector('[data-salary-employee]');
            const baseEl = form.querySelector('[data-salary-base]');
            const dailyEl = form.querySelector('[data-salary-daily]');
            const weeklyEl = form.querySelector('[data-salary-weekly]');
            const weeklyHint = form.querySelector('[data-salary-weekly-hint]');
            const monthlyDailyEl = form.querySelector('[data-salary-monthly-daily]');
            const totalEl = form.querySelector('[data-salary-total]');
            const deduction = {{ (float) $defaultDeduction }};
            const allowanceHidden = form.querySelector('input[data-rupiah-target="allowance"]');
            const allowanceVisible = form.querySelector('.rupiah-input[data-rupiah-hidden="allowance"]');

            function formatId(n) {
                return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(n || 0)));
            }

            function parseAllowance() {
                if (allowanceHidden && allowanceHidden.value !== '') {
                    return parseFloat(allowanceHidden.value) || 0;
                }
                const raw = (allowanceVisible?.value || '').replace(/[^\d]/g, '');
                return parseFloat(raw) || 0;
            }

            function recalc() {
                const opt = employeeSelect?.selectedOptions?.[0];
                const selected = !!(opt && opt.value);
                const base = selected ? parseFloat(opt.getAttribute('data-base') || '0') : 0;
                const daily = selected ? parseFloat(opt.getAttribute('data-daily') || '0') : 0;
                const daysWeek = selected ? parseFloat(opt.getAttribute('data-days-week') || '5') : 0;
                const weekly = daily * daysWeek;
                const monthlyDaily = weekly * 4;
                const allowance = parseAllowance();

                if (baseEl) baseEl.value = selected ? formatId(base) : '';
                if (dailyEl) dailyEl.value = selected ? formatId(daily) : '';
                if (weeklyEl) weeklyEl.value = selected ? formatId(weekly) : '';
                if (monthlyDailyEl) monthlyDailyEl.value = selected ? formatId(monthlyDaily) : '';
                if (weeklyHint) {
                    weeklyHint.textContent = selected
                        ? ('Gaji harian × ' + daysWeek + ' hari jadwal kerja.')
                        : 'Gaji harian × hari kerja terjadwal.';
                }
                // Estimasi preview: pokok + harian 4 minggu + tunjangan − potongan rutin
                if (totalEl) {
                    totalEl.value = selected
                        ? formatId(Math.max(0, base + monthlyDaily + allowance - deduction))
                        : '';
                }
            }

            employeeSelect?.addEventListener('change', recalc);
            allowanceVisible?.addEventListener('input', recalc);
            allowanceVisible?.addEventListener('blur', recalc);
            form.addEventListener('submit', recalc);
            recalc();
        })();
    </script>
@endsection
