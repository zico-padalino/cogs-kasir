@extends('layouts.app')

@section('title', 'Bahan Jadi')
@section('heading', 'Bahan Jadi')
@section('subheading', 'Hasil olahan dari bahan baku — bisa dipakai di resep menu')

@section('content')
    <div class="module-page">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif

        <x-module-form-card :step="2" icon="🥣" title="Tambah Bahan Jadi" description="Contoh: bumbu nasgor, adonan, sirup — pilih bahan baku yang dipakai beserta jumlahnya.">
            <form action="{{ route('bahan-jadi.store') }}" method="POST" class="material-add-form">
                @csrf
                <div>
                    <label class="form-label">Nama bahan jadi</label>
                    <input type="text" name="name" class="form-input" required placeholder="cth. Bumbu nasgor" value="{{ old('name') }}">
                </div>

                <x-unit-picker
                    :selected="old('unit_preset', 'gr')"
                    :custom-value="old('unit_custom', '')"
                />

                <div
                    class="bj-recipe-builder"
                    data-bj-recipe-builder
                    data-material-units='@json($materialUnits)'
                >
                    <div class="bj-recipe-builder__head">
                        <div>
                            <p class="form-label mb-0">Resep dari Bahan baku</p>
                            <p class="form-hint mt-1">Pilih bahan baku &amp; jumlah yang dipakai untuk <strong>1 satuan</strong> bahan jadi ini.</p>
                        </div>
                    </div>

                    @if ($rawMaterials->isNotEmpty())
                        <div class="bj-recipe-builder__rows" data-bj-recipe-rows>
                            @php
                                $oldIngredients = old('ingredients', [['child_product_id' => '', 'quantity' => '', 'unit' => '']]);
                                if ($oldIngredients === []) {
                                    $oldIngredients = [['child_product_id' => '', 'quantity' => '', 'unit' => '']];
                                }
                            @endphp
                            @foreach ($oldIngredients as $idx => $ingredient)
                                <div class="bj-recipe-row" data-bj-recipe-row>
                                    <div class="bj-recipe-row__material">
                                        <label class="form-label">Bahan baku</label>
                                        <select
                                            name="ingredients[{{ $idx }}][child_product_id]"
                                            class="form-input"
                                            data-bom-material
                                            data-searchable-select
                                            data-search-placeholder="Pilih bahan baku..."
                                            data-search-input-placeholder="Cari nama bahan..."
                                        >
                                            <option value="">Pilih bahan baku...</option>
                                            @foreach ($rawMaterials as $p)
                                                <option value="{{ $p->id }}" @selected((string) ($ingredient['child_product_id'] ?? '') === (string) $p->id)>
                                                    {{ $p->name }} — stok {{ $format::number($p->availableQuantity()) }} {{ $units::label($p->unit) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="bj-recipe-row__qty">
                                        <label class="form-label">Jumlah dipakai</label>
                                        <div class="bom-qty-row">
                                            <input
                                                type="number"
                                                name="ingredients[{{ $idx }}][quantity]"
                                                class="form-input"
                                                step="any"
                                                min="0.0001"
                                                placeholder="100"
                                                value="{{ $ingredient['quantity'] ?? '' }}"
                                                data-bom-qty
                                            >
                                            <select name="ingredients[{{ $idx }}][unit]" class="form-input" data-bom-unit>
                                                <option value="">Pilih bahan dulu</option>
                                            </select>
                                        </div>
                                        <p class="form-hint mt-1" data-bom-unit-hint>Pilih bahan dulu — satuan menyesuaikan otomatis.</p>
                                    </div>
                                    <div class="bj-recipe-row__remove">
                                        <button type="button" class="btn-outline-danger btn-sm w-full" data-bj-recipe-remove @if ($loop->count === 1) disabled @endif>Hapus</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn-outline w-full" data-bj-recipe-add>+ Tambah bahan baku</button>

                        <template data-bj-recipe-row-template>
                            <div class="bj-recipe-row" data-bj-recipe-row>
                                <div class="bj-recipe-row__material">
                                    <label class="form-label">Bahan baku</label>
                                    <select
                                        name="ingredients[__INDEX__][child_product_id]"
                                        class="form-input"
                                        data-bom-material
                                        data-searchable-select
                                        data-search-placeholder="Pilih bahan baku..."
                                        data-search-input-placeholder="Cari nama bahan..."
                                    >
                                        <option value="">Pilih bahan baku...</option>
                                        @foreach ($rawMaterials as $p)
                                            <option value="{{ $p->id }}">
                                                {{ $p->name }} — stok {{ $format::number($p->availableQuantity()) }} {{ $units::label($p->unit) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="bj-recipe-row__qty">
                                    <label class="form-label">Jumlah dipakai</label>
                                    <div class="bom-qty-row">
                                        <input
                                            type="number"
                                            name="ingredients[__INDEX__][quantity]"
                                            class="form-input"
                                            step="any"
                                            min="0.0001"
                                            placeholder="100"
                                            data-bom-qty
                                        >
                                        <select name="ingredients[__INDEX__][unit]" class="form-input" data-bom-unit>
                                            <option value="">Pilih bahan dulu</option>
                                        </select>
                                    </div>
                                    <p class="form-hint mt-1" data-bom-unit-hint>Pilih bahan dulu — satuan menyesuaikan otomatis.</p>
                                </div>
                                <div class="bj-recipe-row__remove">
                                    <button type="button" class="btn-outline-danger btn-sm w-full" data-bj-recipe-remove>Hapus</button>
                                </div>
                            </div>
                        </template>
                    @else
                        <p class="alert-tip">
                            Belum ada bahan baku.
                            <a href="{{ route('materials.index') }}" class="font-semibold text-brand-700">Tambah bahan baku dulu →</a>
                        </p>
                    @endif

                    @error('ingredients')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-material-purchase-fields />

                <button type="submit" class="btn-primary w-full py-3 font-semibold">Simpan Bahan Jadi</button>
            </form>
        </x-module-form-card>

        <x-table-card title="Daftar Bahan Jadi" :subtitle="$items->count().' item'">
            @if ($items->isNotEmpty())
                <div class="space-y-3 p-4 sm:p-5">
                    @foreach ($items as $item)
                        <div class="module-item-card material-card">
                            <div class="material-card__top">
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-bold text-slate-900">{{ $item->name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="module-stat-pill module-stat-pill--stock">
                                            {{ $format::number($item->available_qty) }} {{ $item->unit }}
                                        </span>
                                        @if ($item->avg_cost > 0)
                                            <span class="module-stat-pill module-stat-pill--price">
                                                {{ $format::rupiah($item->avg_cost) }}/{{ $item->unit }}
                                            </span>
                                        @endif
                                        @if ($item->bill_of_materials_count > 0)
                                            <span class="module-stat-pill">Resep {{ $item->bill_of_materials_count }} bahan</span>
                                        @else
                                            <span class="badge badge-amber">Belum ada resep</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50/80 open:bg-white" @if ($item->bill_of_materials_count === 0 || ($errors->any() && (int) old('bj_recipe_for') === (int) $item->id)) open @endif>
                                <summary class="cursor-pointer list-none px-3 py-2.5 text-sm font-semibold text-brand-800">
                                    Resep dari Bahan baku
                                    <span class="ml-1 font-normal text-slate-500">(untuk 1 {{ $item->unit }})</span>
                                </summary>

                                <div class="space-y-3 border-t border-slate-200 px-3 py-3">
                                    @if ($item->billOfMaterials->isNotEmpty())
                                        <ul class="space-y-2">
                                            @foreach ($item->billOfMaterials as $bom)
                                                @php
                                                    $child = $bom->childProduct;
                                                    $presented = $units::present((float) $bom->quantity, $child?->unit);
                                                    $editOptions = $units::recipeOptions($child?->unit);
                                                    $qtyValue = rtrim(rtrim(number_format($presented['quantity'], 6, '.', ''), '0'), '.') ?: '0';
                                                @endphp
                                                <li class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-2.5 sm:flex-row sm:items-center">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="font-semibold text-slate-900">{{ $child?->name ?? 'Bahan dihapus' }}</p>
                                                        <p class="text-xs text-slate-400">stok: {{ $format::number($bom->quantity) }} {{ $units::label($child?->unit) }}</p>
                                                    </div>
                                                    <form action="{{ route('bahan-jadi.bom.update', [$item, $bom]) }}" method="POST" class="flex flex-wrap items-center gap-2">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="number" name="quantity" class="form-input w-24" step="any" min="0.0001" required value="{{ $qtyValue }}">
                                                        <select name="unit" class="form-input w-28" required>
                                                            @foreach ($editOptions as $value => $label)
                                                                <option value="{{ $value }}" @selected($value === $presented['unit'])>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="btn-outline btn-sm">Simpan</button>
                                                    </form>
                                                    <form action="{{ route('bahan-jadi.bom.destroy', [$item, $bom]) }}" method="POST" onsubmit="return confirm('Hapus dari resep?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-sm text-slate-500">Belum ada bahan. Tambah dari daftar Bahan baku di bawah.</p>
                                    @endif

                                    @if ($rawMaterials->isNotEmpty())
                                        <form
                                            action="{{ route('bahan-jadi.bom.store', $item) }}"
                                            method="POST"
                                            class="recipe-add-form"
                                            data-bom-form
                                            data-material-units='@json($materialUnits)'
                                        >
                                            @csrf
                                            <input type="hidden" name="bj_recipe_for" value="{{ $item->id }}">
                                            <div class="recipe-add-form__material">
                                                <label class="form-label">Pilih bahan baku</label>
                                                <select
                                                    name="child_product_id"
                                                    class="form-input"
                                                    required
                                                    data-bom-material
                                                    data-searchable-select
                                                    data-search-placeholder="Pilih bahan baku..."
                                                    data-search-input-placeholder="Cari nama bahan..."
                                                >
                                                    <option value="">Pilih bahan baku...</option>
                                                    @foreach ($rawMaterials as $p)
                                                        <option value="{{ $p->id }}">
                                                            {{ $p->name }} — stok {{ $format::number($p->availableQuantity()) }} {{ $units::label($p->unit) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="recipe-add-form__qty">
                                                <label class="form-label">Jumlah dipakai</label>
                                                <div class="bom-qty-row">
                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        class="form-input"
                                                        step="any"
                                                        min="0.0001"
                                                        required
                                                        placeholder="100"
                                                        data-bom-qty
                                                    >
                                                    <select name="unit" class="form-input" required data-bom-unit>
                                                        <option value="">Pilih bahan dulu</option>
                                                    </select>
                                                </div>
                                                <p class="form-hint mt-1" data-bom-unit-hint>Pilih bahan dulu — satuan menyesuaikan otomatis.</p>
                                            </div>
                                            <div class="recipe-add-form__submit">
                                                <button type="submit" class="btn-primary w-full" data-bom-submit disabled>Tambah ke Resep</button>
                                            </div>
                                        </form>
                                    @else
                                        <p class="alert-tip">
                                            Belum ada bahan baku.
                                            <a href="{{ route('materials.index') }}" class="font-semibold text-brand-700">Tambah bahan baku dulu →</a>
                                        </p>
                                    @endif
                                </div>
                            </details>

                            <div class="material-card__actions">
                                <details class="material-card__action">
                                    <summary class="btn-outline btn-sm cursor-pointer list-none text-center">Edit / Stok</summary>
                                    <form action="{{ route('bahan-jadi.update', $item) }}" method="POST" class="material-panel">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="form-label">Nama</label>
                                            <input type="text" name="name" class="form-input" required value="{{ old('name', $item->name) }}">
                                        </div>
                                        <x-unit-picker
                                            :selected="old('unit_preset', $units::guessPreset($item->unit))"
                                            :custom-value="old('unit_custom', $units::guessPreset($item->unit) === 'other' ? $item->unit : '')"
                                        />
                                        <p class="form-hint">Isi pembelian di bawah hanya jika menambah stok.</p>
                                        <x-material-purchase-fields />
                                        <button type="submit" class="btn-primary w-full">Simpan</button>
                                    </form>
                                </details>

                                <form action="{{ route('bahan-jadi.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus bahan jadi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-4 py-10 text-center text-sm text-slate-500 sm:px-5">Belum ada bahan jadi. Tambah di form atas.</p>
            @endif
        </x-table-card>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const bindBomForm = (form) => {
        const materialSelect = form.querySelector('[data-bom-material]');
        const unitSelect = form.querySelector('[data-bom-unit]');
        const submitBtn = form.querySelector('[data-bom-submit]');
        const hint = form.querySelector('[data-bom-unit-hint]');
        let materialUnits = {};

        try {
            materialUnits = JSON.parse(form.getAttribute('data-material-units') || '{}');
        } catch (_) {
            materialUnits = {};
        }

        const setSubmitEnabled = (enabled) => {
            if (!submitBtn) return;
            submitBtn.disabled = !enabled;
        };

        const fillUnits = (preferredUnit = null) => {
            const id = String(materialSelect.value || '');
            const meta = materialUnits[id];
            const previous = preferredUnit || unitSelect.value;

            unitSelect.innerHTML = '';

            if (!meta || !meta.options || Object.keys(meta.options).length === 0) {
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Pilih bahan dulu';
                unitSelect.appendChild(empty);
                setSubmitEnabled(false);
                if (hint) hint.textContent = 'Pilih bahan dulu — satuan menyesuaikan otomatis.';
                return;
            }

            Object.entries(meta.options).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                unitSelect.appendChild(option);
            });

            if (previous && meta.options[previous]) {
                unitSelect.value = previous;
            } else if (meta.preferred && meta.options[meta.preferred]) {
                unitSelect.value = meta.preferred;
            } else {
                unitSelect.selectedIndex = 0;
            }

            setSubmitEnabled(true);
            if (hint) hint.textContent = `Satuan stok: ${meta.label || meta.unit || ''}`;
        };

        materialSelect?.addEventListener('change', () => fillUnits());
        fillUnits();
    };

    const bindRecipeRow = (row, materialUnits, preferredUnit = null) => {
        const materialSelect = row.querySelector('[data-bom-material]');
        const unitSelect = row.querySelector('[data-bom-unit]');
        const hint = row.querySelector('[data-bom-unit-hint]');

        if (!materialSelect || !unitSelect || materialSelect.dataset.bjBound === '1') {
            return;
        }

        materialSelect.dataset.bjBound = '1';

        const fillUnits = (preferred = null) => {
            const id = String(materialSelect.value || '');
            const meta = materialUnits[id];
            const previous = preferred || unitSelect.value;

            unitSelect.innerHTML = '';

            if (!meta || !meta.options || Object.keys(meta.options).length === 0) {
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Pilih bahan dulu';
                unitSelect.appendChild(empty);
                if (hint) hint.textContent = 'Pilih bahan dulu — satuan menyesuaikan otomatis.';
                return;
            }

            Object.entries(meta.options).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                unitSelect.appendChild(option);
            });

            if (previous && meta.options[previous]) {
                unitSelect.value = previous;
            } else if (meta.preferred && meta.options[meta.preferred]) {
                unitSelect.value = meta.preferred;
            } else {
                unitSelect.selectedIndex = 0;
            }

            if (hint) hint.textContent = `Satuan stok: ${meta.label || meta.unit || ''}`;
        };

        materialSelect.addEventListener('change', () => fillUnits());
        fillUnits(preferredUnit);
    };

    const initRecipeBuilder = (builder) => {
        const rowsWrap = builder.querySelector('[data-bj-recipe-rows]');
        const template = builder.querySelector('[data-bj-recipe-row-template]');
        const addBtn = builder.querySelector('[data-bj-recipe-add]');
        let materialUnits = {};
        let nextIndex = rowsWrap ? rowsWrap.querySelectorAll('[data-bj-recipe-row]').length : 0;

        try {
            materialUnits = JSON.parse(builder.getAttribute('data-material-units') || '{}');
        } catch (_) {
            materialUnits = {};
        }

        const oldUnits = @json(collect(old('ingredients', []))->pluck('unit')->values());

        const syncRemoveButtons = () => {
            if (!rowsWrap) return;
            const rows = rowsWrap.querySelectorAll('[data-bj-recipe-row]');
            rows.forEach((row) => {
                const removeBtn = row.querySelector('[data-bj-recipe-remove]');
                if (removeBtn) {
                    removeBtn.disabled = rows.length <= 1;
                }
            });
        };

        const bindRow = (row, preferredUnit = null) => {
            bindRecipeRow(row, materialUnits, preferredUnit);
            row.querySelector('[data-bj-recipe-remove]')?.addEventListener('click', () => {
                const rows = rowsWrap.querySelectorAll('[data-bj-recipe-row]');
                if (rows.length <= 1) return;
                row.remove();
                syncRemoveButtons();
            });
        };

        rowsWrap?.querySelectorAll('[data-bj-recipe-row]').forEach((row, index) => {
            bindRow(row, oldUnits[index] || null);
        });

        addBtn?.addEventListener('click', () => {
            if (!template || !rowsWrap) return;

            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-bj-recipe-row]');
            const index = nextIndex++;

            row.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', String(index));
            });

            rowsWrap.appendChild(row);
            if (typeof window.initSearchableSelects === 'function') {
                window.initSearchableSelects(row);
            }
            bindRow(row);
            syncRemoveButtons();
        });

        syncRemoveButtons();
    };

    document.querySelectorAll('[data-bom-form]').forEach(bindBomForm);
    document.querySelectorAll('[data-bj-recipe-builder]').forEach(initRecipeBuilder);
})();
</script>
@endpush
