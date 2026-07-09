@extends('layouts.app')

@section('title', 'Biaya Lain')
@section('heading', 'Langkah 1: Biaya Lain')
@section('subheading', 'Listrik, sewa, air — di luar bahan & upah kerja')

@section('content')
    <div class="space-y-4">
        <div class="card overhead-add-card">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-900">Tambah Biaya Baru</h2>
                <p class="mt-0.5 text-xs text-slate-500">Pilih salah satu cara di bawah — yang paling sering dipakai: <strong>persen dari harga bahan</strong>.</p>
            </div>

            <form action="{{ route('overhead-rates.store') }}" method="POST" class="space-y-4" id="overhead-form">
                @csrf

                <div>
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" required placeholder="Listrik & gas dapur" value="{{ old('name') }}">
                </div>

                <div>
                    <p class="form-label mb-2">Pilih cara hitung</p>
                    <div class="space-y-2">
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50">
                            <input type="radio" name="cost_mode" value="percent" class="mt-1" @checked(old('cost_mode', 'percent') === 'percent')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Persen dari harga bahan</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Cocok untuk listrik, sewa, gas. Contoh: bahan Rp 100.000 + 10% = tambah Rp 10.000.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50">
                            <input type="radio" name="cost_mode" value="hourly" class="mt-1" @checked(old('cost_mode') === 'hourly')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Rupiah per jam kerja</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Cocok untuk tukang/operator. Contoh: Rp 25.000 × 2 jam = Rp 50.000.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div data-overhead-field="percent">
                    <label class="form-label">Tambah berapa persen?</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="percent_value" class="form-input max-w-[8rem]" min="0" max="100" step="0.1"
                               placeholder="10" value="{{ old('percent_value') }}">
                        <span class="text-sm font-medium text-slate-600">%</span>
                    </div>
                    <p class="form-hint">Ketik angka biasa: 10 artinya 10%, bukan 0,10.</p>
                </div>

                <div data-overhead-field="hourly" class="hidden">
                    <label class="form-label">Bayar berapa per jam?</label>
                    <x-rupiah-input name="hourly_rate" placeholder="25.000" :value="old('hourly_rate')" />
                    <p class="form-hint">Nanti dikalikan jumlah jam kerja saat produksi (kalau diisi).</p>
                </div>

                <div>
                    <label class="form-label">Catatan <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <input type="text" name="description" class="form-input" placeholder="Misal: listrik bulan Juli" value="{{ old('description') }}">
                </div>

                <button type="submit" class="btn-primary w-full">Simpan</button>
            </form>
        </div>

        <x-table-card title="Sudah Dicatat" subtitle="{{ $rates->count() }} biaya">
            @if ($rates->isNotEmpty())
                <table class="table-default table-compact">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Aturan</th>
                            <th>Nilai</th>
                            <th class="col-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $rate->name }}</p>
                                    @if ($rate->description)
                                        <p class="mt-0.5 text-xs cell-muted">{{ $rate->description }}</p>
                                    @endif
                                </td>
                                <td class="text-sm text-slate-600">{{ $rate->allocation_base->plainRule() }}</td>
                                <td class="font-semibold text-slate-900">
                                    {{ $rate->allocation_base->formatRate((float) $rate->rate) }}
                                </td>
                                <td class="col-actions">
                                    <x-crud-actions
                                        :edit="route('overhead-rates.edit', $rate)"
                                        :delete="route('overhead-rates.destroy', $rate)"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-slot:footer>
                    <p class="text-sm text-slate-500">Sudah cukup? Lanjut isi daftar bahan.</p>
                    <a href="{{ route('materials.index') }}" class="btn-primary btn-sm">Lanjut ke Bahan →</a>
                </x-slot:footer>
            @else
                <div class="empty-state py-10">
                    <p>Belum ada biaya yang dicatat.</p>
                    <p class="empty-hint">Contoh cepat: nama <em>Listrik & gas</em>, pilih persen, isi <em>10</em> lalu Simpan.</p>
                </div>
            @endif
        </x-table-card>
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
