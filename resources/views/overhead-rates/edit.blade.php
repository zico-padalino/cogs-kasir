@extends('layouts.app')

@section('title', 'Edit Overhead')
@section('heading', 'Edit Tarif Overhead')
@section('subheading', $rate->name)

@section('content')
    <div class="mx-auto max-w-xl">
        <x-step-header number="1" title="Edit Overhead" description="Perbarui tarif biaya tidak langsung." />

        <div class="card">
            <form action="{{ route('overhead-rates.update', $rate) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $rate->name) }}" required>
                </div>
                <div>
                    <label class="form-label">Dihitung dari</label>
                    <select name="allocation_base" class="form-input" required>
                        @foreach ($allocationBases as $base)
                            <option value="{{ $base->value }}" @selected(old('allocation_base', $rate->allocation_base->value) === $base->value)>{{ $base->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Nilai</label>
                    <input type="number" name="rate" class="form-input" step="0.000001" min="0" value="{{ old('rate', $rate->rate) }}" required>
                </div>
                <div>
                    <label class="form-label">Keterangan</label>
                    <textarea name="description" class="form-input" rows="2">{{ old('description', $rate->description) }}</textarea>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $rate->is_active))>
                    <span class="text-sm">Aktif</span>
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="{{ route('overhead-rates.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
