@extends('layouts.app')

@section('title', 'Ubah Biaya')
@section('heading', 'Ubah Biaya Lain')
@section('subheading', $rate->name)

@section('content')
    <div class="mx-auto max-w-xl">
        <x-step-header number="1" title="Ubah Biaya" description="Perbarui data biaya lain ini." />

        <div class="card">
            <form action="{{ route('overhead-rates.update', $rate) }}" method="POST" class="space-y-4" id="overhead-form">
                @csrf @method('PUT')

                <div>
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $rate->name) }}" required>
                </div>

                <div>
                    <p class="form-label mb-2">Pilih cara hitung</p>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50">
                            <input type="radio" name="cost_mode" value="percent" class="mt-1" @checked(old('cost_mode', $costMode) === 'percent')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Persen dari harga bahan</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Listrik, sewa, gas — isi angka persen biasa (10 = 10%).</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50">
                            <input type="radio" name="cost_mode" value="hourly" class="mt-1" @checked(old('cost_mode', $costMode) === 'hourly')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Rupiah per jam kerja</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Upah per jam, dikalikan jam kerja saat produksi.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div data-overhead-field="percent">
                    <label class="form-label">Tambah berapa persen?</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="percent_value" class="form-input max-w-[8rem]" min="0" max="100" step="0.1"
                               value="{{ old('percent_value', $percentValue) }}">
                        <span class="text-sm font-medium text-slate-600">%</span>
                    </div>
                </div>

                <div data-overhead-field="hourly" class="hidden">
                    <label class="form-label">Bayar berapa per jam?</label>
                    <x-rupiah-input name="hourly_rate" :value="old('hourly_rate', $hourlyRate)" placeholder="25.000" />
                </div>

                <div>
                    <label class="form-label">Catatan</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="Boleh kosong">{{ old('description', $rate->description) }}</textarea>
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

    <script>
        (function () {
            const form = document.getElementById('overhead-form');
            if (!form) return;

            const sync = () => {
                const mode = form.querySelector('input[name="cost_mode"]:checked')?.value || 'percent';
                form.querySelectorAll('[data-overhead-field]').forEach((el) => {
                    const show = el.dataset.overheadField === mode;
                    el.classList.toggle('hidden', !show);
                    el.querySelectorAll('input, select, textarea').forEach((input) => {
                        input.disabled = !show;
                    });
                });
            };

            form.querySelectorAll('input[name="cost_mode"]').forEach((radio) => {
                radio.addEventListener('change', sync);
            });

            sync();
        })();
    </script>
@endsection
