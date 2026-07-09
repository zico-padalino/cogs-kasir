@extends('layouts.admin')

@section('title', 'Gaji Karyawan')
@section('heading', 'Gaji Karyawan')

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[12rem] flex-1">
            <label class="form-label" for="month">Bulan</label>
            <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Tampilkan</button>
    </form>

    <form method="POST" action="{{ route('admin.salaries.store') }}" class="card mb-4 space-y-4 p-4">
        @csrf
        <h2 class="text-sm font-semibold text-slate-900">Input gaji bulanan</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="form-label" for="employee_id">Karyawan</label>
                <select id="employee_id" name="employee_id" class="form-input" required>
                    <option value="">Pilih karyawan</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" data-base="{{ $employee->base_salary }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="period_month">Periode</label>
                <input id="period_month" type="month" name="period_month" value="{{ $month->format('Y-m') }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="base_salary">Gaji pokok</label>
                <input id="base_salary" type="number" min="0" name="base_salary" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="allowance">Tunjangan</label>
                <input id="allowance" type="number" min="0" name="allowance" value="0" class="form-input">
            </div>
            <div>
                <label class="form-label" for="deduction">Potongan</label>
                <input id="deduction" type="number" min="0" name="deduction" value="0" class="form-input">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label" for="notes">Catatan</label>
                <input id="notes" name="notes" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn-primary">Simpan Gaji</button>
    </form>

    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold">Gaji {{ $month->translatedFormat('F Y') }}</h2>
        </div>
        @forelse ($salaries as $salary)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div>
                    <p class="font-semibold text-slate-900">{{ $salary->employee->name }}</p>
                    <p class="text-xs text-slate-500">
                        Pokok {{ $format::rupiah($salary->base_salary) }}
                        · Tunjangan {{ $format::rupiah($salary->allowance) }}
                        · Potongan {{ $format::rupiah($salary->deduction) }}
                    </p>
                    <p class="mt-1 text-sm font-bold text-brand-700">{{ $format::rupiah($salary->total) }}</p>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <span class="badge {{ $salary->status->badgeClass() }}">{{ $salary->status->label() }}</span>
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
            </div>
        @empty
            <div class="empty-state px-4 py-8"><p>Belum ada data gaji bulan ini.</p></div>
        @endforelse
    </div>

    <script>
        document.getElementById('employee_id')?.addEventListener('change', function () {
            var opt = this.selectedOptions[0];
            var base = opt ? opt.getAttribute('data-base') : '';
            if (base) document.getElementById('base_salary').value = base;
        });
    </script>
@endsection
