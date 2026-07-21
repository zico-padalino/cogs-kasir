@extends('layouts.app')

@section('title', 'Biaya Lain')
@section('heading', 'Langkah 1: Biaya Lain')
@section('subheading', 'Listrik, sewa, air — di luar bahan & upah kerja')

@section('content')
    <div class="module-page module-step-1">
        <x-module-form-card :step="1" title="Tambah Biaya Baru" description="Pilih cara hitung, isi angkanya, lalu simpan. Yang paling sering: persen dari harga bahan.">
            <form action="{{ route('overhead-rates.store') }}" method="POST" class="space-y-4" id="overhead-form">
                @csrf

                <div>
                    <label class="form-label">Nama biaya</label>
                    <input type="text" name="name" class="form-input" required placeholder="Listrik & gas dapur" value="{{ old('name') }}">
                </div>

                <div>
                    <p class="form-label mb-2">Pilih cara hitung</p>
                    <div class="space-y-2">
                        <label class="module-choice">
                            <input type="radio" name="cost_mode" value="percent" class="mt-1" @checked(old('cost_mode', 'percent') === 'percent')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Persen dari harga bahan</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Listrik, sewa, gas. Bahan Rp 100.000 + 10% = tambah Rp 10.000.</span>
                            </span>
                        </label>
                        <label class="module-choice">
                            <input type="radio" name="cost_mode" value="hourly" class="mt-1" @checked(old('cost_mode') === 'hourly')>
                            <span class="text-sm">
                                <strong class="text-slate-900">Rupiah per jam kerja</strong>
                                <span class="mt-0.5 block text-xs text-slate-500">Tukang/operator. Rp 25.000 × 2 jam = Rp 50.000.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div data-overhead-field="percent">
                    <label class="form-label">Tambah berapa persen?</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="percent_value" class="form-input max-w-[8rem] text-lg font-semibold" min="0" max="100" step="0.1"
                               placeholder="10" value="{{ old('percent_value') }}">
                        <span class="text-lg font-bold text-amber-600">%</span>
                    </div>
                    <p class="form-hint">Ketik 10 untuk 10% — bukan 0,10.</p>
                </div>

                <div data-overhead-field="hourly" class="hidden">
                    <label class="form-label">Bayar berapa per jam?</label>
                    <x-rupiah-input name="hourly_rate" placeholder="25.000" :value="old('hourly_rate')" />
                </div>

                <div>
                    <label class="form-label">Catatan <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <input type="text" name="description" class="form-input" placeholder="Misal: listrik bulan Juli" value="{{ old('description') }}">
                </div>

                <button type="submit" class="btn-primary w-full py-3 text-base font-semibold">Simpan Biaya</button>
            </form>
        </x-module-form-card>

        <x-table-card :step="1" title="Sudah Dicatat" :subtitle="$rates->count() . ' biaya'">
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
                                <td>
                                    <span class="module-stat-pill module-stat-pill--price">{{ $rate->allocation_base->formatRate((float) $rate->rate) }}</span>
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
                    <p class="text-sm font-medium text-slate-600">Sudah cukup? Lanjut isi daftar bahan.</p>
                    <a href="{{ route('materials.index') }}" class="btn-primary btn-sm">Lanjut ke Bahan Baku →</a>
                </x-slot:footer>
            @else
                <div class="module-empty">
                    <span class="module-empty__icon" aria-hidden="true">⚡</span>
                    <p class="module-empty__title">Belum ada biaya dicatat</p>
                    <p class="module-empty__hint">Contoh: <em>Listrik & gas</em> → persen → isi <strong>10</strong> → Simpan.</p>
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
                    el.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !show; });
                });
            };
            form.querySelectorAll('input[name="cost_mode"]').forEach((r) => r.addEventListener('change', sync));
            sync();
        })();
    </script>
@endsection
