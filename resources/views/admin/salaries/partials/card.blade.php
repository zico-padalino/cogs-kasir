@php
    $dailyRate = (float) ($salary->daily_salary ?? 0);
    $workDays = (int) ($salary->work_days ?? 0);
    $dailyTotal = $dailyRate * $workDays;
    $daysWeek = $salary->employee?->scheduledWorkDaysPerWeek() ?? 5;
    $weeklyEst = $dailyRate * $daysWeek;
    $baseSalary = (float) $salary->base_salary;
    $userNote = trim((string) preg_replace('/\s*\|\s*(Harian|Potongan|≈).*/u', '', (string) ($salary->notes ?? '')));
    $canPay = $canPay ?? ($salary->status->value === 'draft');
    $empPreview = $previews[$salary->employee_id] ?? null;
    $deductionItems = $empPreview['deduction_items'] ?? [];
    $waivedSaved = ($hasWaivers ?? false) ? array_values($salary->deduction_waivers ?? []) : [];
    $cleanNotes = $userNote;
    $periodKind = $salary->periodKind();
    $periodKindLabel = $salary->periodKindLabel();
    $periodDays = $salary->periodDayCount();
@endphp
<article class="card overflow-hidden p-0">
    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <div class="min-w-0">
            <p class="truncate text-base font-semibold text-slate-900">{{ $salary->employee?->name }}</p>
            <p class="text-[11px] text-slate-400">{{ $salary->employee?->employee_code }}</p>
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="badge shrink-0 {{ $salary->periodKindBadgeClass() }}">{{ $periodKindLabel }}</span>
                <span class="text-[11px] text-slate-500">
                    {{ $salary->periodLabel() }}
                    @if ($periodDays > 0)
                        · {{ $periodDays }} hari
                    @endif
                </span>
            </div>
            @if ($salary->status->value === 'paid' && $salary->paid_at)
                <p class="mt-1 text-[11px] text-emerald-700">Dibayar {{ $salary->paid_at->translatedFormat('d M Y H:i') }}</p>
            @endif
        </div>
        <span class="badge shrink-0 {{ $salary->status->badgeClass() }}">{{ $salary->status->label() }}</span>
    </div>

    <div class="{{ $salary->status->value === 'paid' ? 'bg-emerald-50/80' : 'bg-brand-50/70' }} px-4 py-3">
        <p class="text-[11px] font-medium uppercase tracking-wide {{ $salary->status->value === 'paid' ? 'text-emerald-800/70' : 'text-brand-800/70' }}">
            Total {{ $salary->status->value === 'paid' ? 'dibayar' : 'periode ini' }}
        </p>
        <p class="mt-0.5 text-2xl font-bold tabular-nums {{ $salary->status->value === 'paid' ? 'text-emerald-900' : 'text-brand-900' }}">
            {{ $format::rupiah($salary->total) }}
        </p>
        <p class="mt-1 text-[11px] leading-snug {{ $salary->status->value === 'paid' ? 'text-emerald-800/80' : 'text-brand-800/80' }}">
            Pokok bulanan + hitung harian + tunjangan − potongan
        </p>
    </div>

    {{-- Komponen gaji: bulanan / mingguan / harian --}}
    <div class="border-b border-slate-100 px-3 py-3 sm:px-4">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Komponen gaji</p>
        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-sky-100 bg-sky-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-700">Per bulan</p>
                <p class="mt-1 text-base font-bold tabular-nums text-sky-950">{{ $format::rupiah($baseSalary) }}</p>
                <p class="mt-0.5 text-[11px] text-sky-800/80">Gaji pokok bulanan</p>
            </div>
            <div class="rounded-xl border border-violet-100 bg-violet-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-700">Per minggu</p>
                <p class="mt-1 text-base font-bold tabular-nums text-violet-950">{{ $format::rupiah($weeklyEst) }}</p>
                <p class="mt-0.5 text-[11px] text-violet-800/80">Estimasi · {{ $daysWeek }} hari jadwal</p>
                <p class="mt-0.5 text-[10px] tabular-nums text-violet-700/70">{{ $format::rupiah($dailyRate) }} × {{ $daysWeek }}</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800">Hitung harian</p>
                <p class="mt-1 text-base font-bold tabular-nums text-amber-950">{{ $format::rupiah($dailyTotal) }}</p>
                <p class="mt-0.5 text-[11px] text-amber-900/80">{{ $workDays }} hari hadir</p>
                <p class="mt-0.5 text-[10px] tabular-nums text-amber-800/70">{{ $format::rupiah($dailyRate) }} × {{ $workDays }}</p>
            </div>
        </div>
    </div>

    <dl class="space-y-2 px-4 py-3 text-sm">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">
                <span class="font-medium text-sky-800">Per bulan</span>
                <span class="text-slate-400"> · pokok</span>
            </dt>
            <dd class="font-medium tabular-nums text-slate-900">{{ $format::rupiah($baseSalary) }}</dd>
        </div>
        @if ($hasDailyColumns ?? true)
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500">
                    <span class="font-medium text-amber-800">Hitung harian</span>
                    <span class="block text-[11px] text-slate-400">Tarif {{ $format::rupiah($dailyRate) }} × {{ $workDays }} hadir</span>
                </dt>
                <dd class="font-medium tabular-nums text-slate-900">{{ $format::rupiah($dailyTotal) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500">
                    <span class="font-medium text-violet-800">Per minggu</span>
                    <span class="block text-[11px] text-slate-400">Estimasi (tidak ditambahkan ke total)</span>
                </dt>
                <dd class="font-medium tabular-nums text-slate-500">{{ $format::rupiah($weeklyEst) }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">Tunjangan</dt>
            <dd class="font-medium tabular-nums text-slate-900">{{ $format::rupiah($salary->allowance) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500">Potongan absensi</dt>
            <dd class="font-medium tabular-nums text-red-600">− {{ $format::rupiah($salary->autoDeduction()) }}</dd>
        </div>
        @if (($hasManualDeduction ?? false) && (float) ($salary->manual_deduction ?? 0) > 0)
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500">Potongan manual</dt>
                <dd class="font-medium tabular-nums text-red-600">− {{ $format::rupiah($salary->manual_deduction) }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2">
            <dt class="font-medium text-slate-600">Total potongan</dt>
            <dd class="font-semibold tabular-nums text-red-600">− {{ $format::rupiah($salary->deduction) }}</dd>
        </div>
    </dl>

    @if (! empty($deductionItems))
        <div class="border-t border-slate-100 px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Rincian potongan</p>
            <ul class="mt-2 space-y-1.5">
                @foreach ($deductionItems as $item)
                    @php
                        $isWaived = in_array($item['key'], $waivedSaved, true) || empty($item['applied']);
                    @endphp
                    <li class="flex items-start justify-between gap-3 text-xs {{ $isWaived ? 'opacity-50 line-through' : '' }}">
                        <span class="min-w-0">
                            <span class="font-medium text-slate-700">{{ $item['label'] }}</span>
                            @if (! empty($item['detail']))
                                <span class="mt-0.5 block text-slate-400">{{ $item['detail'] }}</span>
                            @endif
                            @if ($isWaived)
                                <span class="mt-0.5 block text-amber-700">Dikecualikan</span>
                            @endif
                        </span>
                        <span class="shrink-0 tabular-nums font-medium {{ $isWaived ? 'text-slate-400' : 'text-red-600' }}">
                            {{ $format::rupiah($item['amount']) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($cleanNotes !== '')
        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">{{ $cleanNotes }}</p>
    @endif

    <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/80 p-3 sm:flex-row sm:flex-wrap sm:justify-end">
        <button
            type="button"
            class="btn-sm btn-secondary w-full sm:w-auto"
            data-edit-salary
            data-employee-id="{{ $salary->employee_id }}"
            data-allowance="{{ (float) $salary->allowance }}"
            data-manual-deduction="{{ (float) ($salary->manual_deduction ?? 0) }}"
            data-notes="{{ $cleanNotes }}"
        >Edit</button>
        <button
            type="button"
            class="btn-sm btn-secondary w-full sm:w-auto"
            data-open-attendance-detail
            data-employee-id="{{ $salary->employee_id }}"
            data-employee-name="{{ $salary->employee?->name }}"
        >Detail absensi</button>
        @if ($canPay)
            <button
                type="button"
                class="btn-sm btn-primary w-full sm:w-auto"
                data-pay-open
                data-pay-url="{{ route('admin.salaries.paid', $salary) }}"
                data-pay-name="{{ $salary->employee?->name }}"
                data-pay-period="{{ $periodKindLabel }} · {{ $salary->periodLabel() }}"
                data-pay-total="{{ $format::rupiah($salary->total) }}"
            >Konfirmasi bayar</button>
        @endif
        <form action="{{ route('admin.salaries.destroy', $salary) }}" method="POST" class="w-full sm:w-auto" onsubmit="return confirm('Hapus data gaji {{ $salary->employee?->name }}?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-sm btn-outline-danger w-full">Hapus</button>
        </form>
    </div>
</article>
