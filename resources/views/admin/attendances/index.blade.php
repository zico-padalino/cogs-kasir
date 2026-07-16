@extends('layouts.admin')

@section('title', 'Absensi')
@section('heading', 'Absensi Karyawan')

@section('content')
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.attendances.qr') }}" class="btn-primary">QR Absensi (PNG / PDF)</a>
    </div>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-[12rem] flex-1">
            <label class="form-label" for="date">Tanggal</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Tampilkan</button>
    </form>

    <form method="POST" action="{{ route('admin.attendances.store') }}" class="card mb-4 space-y-4 p-4">
        @csrf
        <h2 class="text-sm font-semibold text-slate-900">Catat absensi manual</h2>
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
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-slate-900">
                        {{ $row->employee->name }}
                        @if ($row->is_late)
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">Terlambat</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $row->status->label() }}
                        @if ($row->check_in) · Masuk {{ substr((string) $row->check_in, 0, 5) }} @endif
                        @if ($row->check_out) · Pulang {{ substr((string) $row->check_out, 0, 5) }} @endif
                        @if ($row->notes) · {{ $row->notes }} @endif
                    </p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @if ($row->checkInPhotoUrl())
                            <div class="text-center">
                                <img src="{{ $row->checkInPhotoUrl() }}" alt="Foto masuk" class="h-14 w-14 rounded-lg object-cover ring-1 ring-slate-200">
                                <p class="mt-0.5 text-[10px] text-slate-500">Masuk</p>
                                @if ($row->check_in_lat)
                                    <p class="text-[10px] text-slate-400">{{ number_format($row->check_in_lat, 5) }}, {{ number_format($row->check_in_lng, 5) }}</p>
                                @endif
                            </div>
                        @endif
                        @if ($row->checkOutPhotoUrl())
                            <div class="text-center">
                                <img src="{{ $row->checkOutPhotoUrl() }}" alt="Foto pulang" class="h-14 w-14 rounded-lg object-cover ring-1 ring-slate-200">
                                <p class="mt-0.5 text-[10px] text-slate-500">Pulang</p>
                                @if ($row->check_out_lat)
                                    <p class="text-[10px] text-slate-400">{{ number_format($row->check_out_lat, 5) }}, {{ number_format($row->check_out_lng, 5) }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
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
