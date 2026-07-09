@extends('layouts.app')

@section('title', 'Ubah Biaya')
@section('heading', 'Ubah Biaya Lain')
@section('subheading', $rate->name)

@section('content')
    <div class="mx-auto max-w-xl">
        <x-step-header number="1" title="Ubah Biaya" description="Perbarui data biaya lain ini." />

        <div class="card">
            <form action="{{ route('overhead-rates.update', $rate) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $rate->name) }}" required>
                </div>
                <div>
                    <label class="form-label">Cara hitungnya</label>
                    <select name="allocation_base" class="form-input" required>
                        @foreach ($allocationBases as $base)
                            <option value="{{ $base->value }}" @selected(old('allocation_base', $rate->allocation_base->value) === $base->value)>{{ $base->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Besarnya</label>
                    <input type="number" name="rate" class="form-input" step="0.000001" min="0" value="{{ old('rate', $rate->rate) }}" required>
                </div>
                <div>
                    <label class="form-label">Keterangan</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Catatan singkat (boleh kosong)">{{ old('description', $rate->description) }}</textarea>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $rate->is_active))>
                    <span class="text-sm">Masih dipakai saat hitung modal</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="{{ route('overhead-rates.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
