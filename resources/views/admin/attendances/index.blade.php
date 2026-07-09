@extends('layouts.admin')

@section('title', 'Absensi')
@section('heading', 'Absensi Karyawan')

@section('content')
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[12rem] flex-1">
            <label class="form-label" for="date">Tanggal</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Tampilkan</button>
    </form>

    <form method="POST" action="{{ route('admin.attendances.store') }}" class="card mb-4 space-y-4 p-4">
        @csrf
        <h2 class="text-sm font-semibold text-slate-900">Catat absensi</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                <input id="work_date" type="date" name="work_date" value="{{ $date->toDateString() }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="check_in">Jam masuk</label>
                <input id="check_in" type="time" name="check_in" class="form-input">
            </div>
            <div>
                <label class="form-label" for="check_out">Jam pulang</label>
                <input id="check_out" type="time" name="check_out" class="form-input">
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label class="form-label" for="notes">Catatan</label>
                <input id="notes" name="notes" class="form-input" placeholder="Opsional">
            </div>
        </div>
        <button type="submit" class="btn-primary">Simpan Absensi</button>
    </form>

    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold">Absensi {{ $date->translatedFormat('d M Y') }}</h2>
        </div>
        @forelse ($attendances as $row)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div>
                    <p class="font-medium text-slate-900">{{ $row->employee->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $row->status->label() }}
                        @if ($row->check_in) · Masuk {{ substr($row->check_in, 0, 5) }} @endif
                        @if ($row->check_out) · Pulang {{ substr($row->check_out, 0, 5) }} @endif
                    </p>
                </div>
                <form action="{{ route('admin.attendances.destroy', $row) }}" method="POST" onsubmit="return confirm('Hapus absensi?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                </form>
            </div>
        @empty
            <div class="empty-state px-4 py-8"><p>Belum ada absensi pada tanggal ini.</p></div>
        @endforelse
    </div>
@endsection
