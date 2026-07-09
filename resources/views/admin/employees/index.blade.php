@extends('layouts.admin')

@section('title', 'Data Karyawan')
@section('heading', 'Data Karyawan')

@section('content')
    <div class="page-toolbar mb-4">
        <p class="text-sm text-slate-500">{{ $employees->count() }} karyawan</p>
        <a href="{{ route('admin.employees.create') }}" class="btn-primary btn-sm">+ Tambah</a>
    </div>

    <div class="card p-0 overflow-hidden">
        @forelse ($employees as $employee)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold text-slate-900">{{ $employee->name }}</p>
                        <span class="badge {{ $employee->status->badgeClass() }}">{{ $employee->status->label() }}</span>
                    </div>
                    <p class="mt-0.5 font-mono text-xs text-slate-600">{{ $employee->employee_code }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $employee->position ?: '—' }}
                        @if ($employee->department) · {{ $employee->department }} @endif
                        · Gaji pokok {{ $format::rupiah($employee->base_salary) }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <a href="{{ route('admin.employees.edit', $employee) }}" class="btn-sm btn-outline">Edit</a>
                    <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST" onsubmit="return confirm('Hapus karyawan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state px-4 py-10">
                <p>Belum ada data karyawan.</p>
            </div>
        @endforelse
    </div>
@endsection
