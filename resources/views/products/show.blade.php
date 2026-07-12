@extends('layouts.app')

@section('title', $product->name)
@section('heading', $product->name)
@section('subheading', 'Resep bahan untuk 1 {{ $product->unit }}')

@section('content')
    <div class="module-page module-step-3">
        <a href="{{ route('products.index') }}" class="cogs-detail-back">← Kembali ke daftar menu</a>

        <div class="recipe-desktop">
            <div class="recipe-desktop__main">
                <x-module-tip :step="3" title="Cara isi resep">
                    Pilih bahan, isi jumlah pakai (bisa gram/kg/ml), lalu <strong>Tambah ke Resep</strong>.
                    Setelah lengkap, klik <strong>Hitung Modal</strong> di samping.
                </x-module-tip>

                <x-module-form-card
                    :step="3"
                    title="Bahan Resep"
                    :description="'Berapa bahan dipakai untuk bikin 1 '.$product->unit.' '.$product->name.'.'"
                    icon="🥗"
                >
                    @if ($allProducts->isEmpty())
                        <p class="alert-tip">
                            Belum ada bahan.
                            <a href="{{ route('materials.index') }}" class="font-semibold text-brand-700">Tambah bahan dulu →</a>
                        </p>
                    @endif

                    @if ($product->billOfMaterials->isNotEmpty())
                        <div class="recipe-table-wrap">
                            <table class="table-default">
                                <thead>
                                    <tr>
                                        <th>Bahan</th>
                                        <th>Jumlah pakai</th>
                                        <th class="col-actions">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->billOfMaterials as $bom)
                                        @php
                                            $child = $bom->childProduct;
                                            $presented = $units::present((float) $bom->quantity, $child?->unit);
                                            $editOptions = $units::recipeOptions($child?->unit);
                                            $editUnit = $presented['unit'];
                                            $qtyValue = rtrim(rtrim(number_format($presented['quantity'], 6, '.', ''), '0'), '.') ?: '0';
                                        @endphp
                                        <tr>
                                            <td>
                                                <p class="font-semibold text-slate-900">{{ $child?->name ?? 'Bahan dihapus' }}</p>
                                                <p class="mt-0.5 text-xs text-slate-400">
                                                    stok: {{ $format::number($bom->quantity, 4) }} {{ $units::label($child?->unit) }}
                                                </p>
                                            </td>
                                            <td class="font-medium tabular-nums text-slate-800">
                                                {{ $format::number($presented['quantity'], $presented['quantity'] >= 10 ? 1 : 2) }}
                                                <span class="text-slate-500">{{ $presented['label'] }}</span>
                                            </td>
                                            <td class="col-actions">
                                                <div class="recipe-row-actions">
                                                    <details class="inline-edit recipe-edit text-left">
                                                        <summary>Ubah</summary>
                                                        <form action="{{ route('products.bom.update', [$product, $bom]) }}" method="POST" class="inline-edit-panel recipe-edit-panel">
                                                            @csrf @method('PUT')
                                                            <label class="form-label">Jumlah dipakai</label>
                                                            <div class="bom-qty-row">
                                                                <input type="number" name="quantity" class="form-input" step="any" min="0.0001" value="{{ $qtyValue }}" required>
                                                                <select name="unit" class="form-input" required>
                                                                    @foreach ($editOptions as $value => $label)
                                                                        <option value="{{ $value }}" @selected($value === $editUnit)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <button type="submit" class="btn-primary btn-sm mt-2 w-full">Simpan</button>
                                                        </form>
                                                    </details>
                                                    <form action="{{ route('products.bom.destroy', [$product, $bom]) }}" method="POST" onsubmit="return confirm('Hapus dari resep?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="module-empty !py-8">
                            <p class="module-empty__title">Resep masih kosong</p>
                            <p class="module-empty__hint">Tambahkan bahan di form bawah.</p>
                        </div>
                    @endif

                    @if ($allProducts->isNotEmpty())
                        <form
                            action="{{ route('products.bom.store', $product) }}"
                            method="POST"
                            class="recipe-add-form"
                            data-bom-form
                            data-material-units='@json($materialUnits)'
                        >
                            @csrf
                            <div class="recipe-add-form__material">
                                <label class="form-label" for="bom_child_product_id">Pilih bahan</label>
                                <select id="bom_child_product_id" name="child_product_id" class="form-input" required data-bom-material>
                                    <option value="">Pilih bahan...</option>
                                    @foreach ($allProducts as $p)
                                        <option
                                            value="{{ $p->id }}"
                                            @selected((string) old('child_product_id') === (string) $p->id)
                                        >
                                            {{ $p->name }} — stok {{ $format::number($p->availableQuantity(), 1) }} {{ $units::label($p->unit) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="recipe-add-form__qty">
                                <label class="form-label" for="bom_quantity">Jumlah dipakai</label>
                                <div class="bom-qty-row">
                                    <input
                                        id="bom_quantity"
                                        type="number"
                                        name="quantity"
                                        class="form-input"
                                        step="any"
                                        min="0.0001"
                                        required
                                        placeholder="100"
                                        value="{{ old('quantity') }}"
                                        data-bom-qty
                                    >
                                    <select name="unit" class="form-input" required data-bom-unit>
                                        <option value="">Pilih bahan dulu</option>
                                    </select>
                                </div>
                                <p class="form-hint mt-1" data-bom-unit-hint>Pilih bahan dulu — satuan menyesuaikan otomatis.</p>
                            </div>
                            <div class="recipe-add-form__submit">
                                <button type="submit" class="btn-primary w-full lg:min-w-[10rem]" data-bom-submit disabled>Tambah ke Resep</button>
                            </div>
                        </form>
                    @endif
                </x-module-form-card>

                <x-module-form-card
                    :step="3"
                    title="Add-on Tambahan"
                    description="Opsional saat pesan di kasir — contoh: Telur, Keju, Sambal ekstra."
                    icon="🥚"
                >
                    @if ($product->addons->isNotEmpty())
                        <div class="recipe-table-wrap mb-4">
                            <table class="table-default">
                                <thead>
                                    <tr>
                                        <th>Add-on</th>
                                        <th>Harga jual</th>
                                        <th class="hidden sm:table-cell">Bahan terkait</th>
                                        <th class="col-actions">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->addons as $addon)
                                        @php
                                            $mat = $addon->material;
                                            $presented = $mat && $addon->material_quantity
                                                ? $units::present((float) $addon->material_quantity, $mat->unit)
                                                : null;
                                            $editOptions = $mat ? $units::recipeOptions($mat->unit) : [];
                                            $qtyValue = $presented
                                                ? (rtrim(rtrim(number_format($presented['quantity'], 6, '.', ''), '0'), '.') ?: '')
                                                : '';
                                        @endphp
                                        <tr class="{{ $addon->is_active ? '' : 'opacity-60' }}">
                                            <td>
                                                <p class="font-semibold text-slate-900">{{ $addon->name }}</p>
                                                @unless ($addon->is_active)
                                                    <p class="text-xs text-amber-700">Nonaktif</p>
                                                @endunless
                                            </td>
                                            <td class="font-medium tabular-nums">+{{ $format::rupiah($addon->selling_price, 0) }}</td>
                                            <td class="hidden text-sm text-slate-500 sm:table-cell">
                                                @if ($presented && $mat)
                                                    {{ $mat->name }} · {{ $format::number($presented['quantity'], 2) }} {{ $presented['label'] }}
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            </td>
                                            <td class="col-actions">
                                                <div class="recipe-row-actions">
                                                    <details class="inline-edit recipe-edit text-left">
                                                        <summary>Ubah</summary>
                                                        <form
                                                            action="{{ route('products.addons.update', [$product, $addon]) }}"
                                                            method="POST"
                                                            class="inline-edit-panel recipe-addon-edit-panel space-y-2"
                                                            data-addon-edit-form
                                                            data-material-units="{{ json_encode($materialUnits, JSON_UNESCAPED_UNICODE) }}"
                                                        >
                                                            @csrf @method('PUT')
                                                            <div>
                                                                <label class="form-label">Nama</label>
                                                                <input type="text" name="name" class="form-input" value="{{ $addon->name }}" required maxlength="100">
                                                            </div>
                                                            <div>
                                                                <label class="form-label">Harga jual</label>
                                                                <x-rupiah-input name="selling_price" :value="$addon->selling_price" placeholder="5.000" required />
                                                            </div>
                                                            <div>
                                                                <label class="form-label">Bahan (opsional)</label>
                                                                <select name="material_product_id" class="form-input" data-addon-edit-material>
                                                                    <option value="">Tanpa bahan</option>
                                                                    @foreach ($allProducts as $p)
                                                                        <option value="{{ $p->id }}" @selected($addon->material_product_id === $p->id)>{{ $p->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div data-addon-edit-qty-wrap class="{{ $addon->material_product_id ? '' : 'hidden' }}">
                                                                <label class="form-label">Jumlah bahan</label>
                                                                <div class="bom-qty-row">
                                                                    <input
                                                                        type="number"
                                                                        name="material_quantity"
                                                                        class="form-input"
                                                                        step="any"
                                                                        min="0.0001"
                                                                        value="{{ $qtyValue }}"
                                                                        data-addon-edit-qty
                                                                    >
                                                                    <select name="unit" class="form-input" data-addon-edit-unit>
                                                                        @if ($editOptions)
                                                                            @foreach ($editOptions as $value => $label)
                                                                                <option value="{{ $value }}" @selected($presented && $value === $presented['unit'])>{{ $label }}</option>
                                                                            @endforeach
                                                                        @else
                                                                            <option value="">—</option>
                                                                        @endif
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <label class="flex items-center gap-2 text-sm">
                                                                <input type="checkbox" name="is_active" value="1" class="rounded" @checked($addon->is_active)>
                                                                Aktif di kasir
                                                            </label>
                                                            <button type="submit" class="btn-primary btn-sm w-full">Simpan</button>
                                                        </form>
                                                    </details>
                                                    <form action="{{ route('products.addons.destroy', [$product, $addon]) }}" method="POST" onsubmit="return confirm('Hapus add-on ini?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            Belum ada add-on. Tambah di bawah — misalnya <strong>Telur</strong> +Rp 5.000.
                        </p>
                    @endif

                    <form
                        action="{{ route('products.addons.store', $product) }}"
                        method="POST"
                        class="recipe-addon-form space-y-3 border-t border-slate-100 pt-4"
                        data-material-units="{{ json_encode($materialUnits, JSON_UNESCAPED_UNICODE) }}"
                    >
                        @csrf
                        @if ($errors->hasAny(['name', 'selling_price', 'material_product_id', 'material_quantity', 'unit']))
                            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                            <div>
                                <label class="form-label" for="addon_name">Nama add-on</label>
                                <input id="addon_name" type="text" name="name" class="form-input" required maxlength="100" placeholder="Telur ceplok" value="{{ old('name') }}">
                            </div>
                            <div>
                                <label class="form-label">Harga jual (+Rp)</label>
                                <x-rupiah-input name="selling_price" :value="old('selling_price')" placeholder="5.000" required />
                            </div>
                            <div>
                                <label class="form-label" for="addon_material">Bahan terkait (opsional)</label>
                                <select id="addon_material" name="material_product_id" class="form-input" data-addon-material>
                                    <option value="">Tanpa bahan</option>
                                    @foreach ($allProducts as $p)
                                        <option value="{{ $p->id }}" @selected((string) old('material_product_id') === (string) $p->id)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div data-addon-qty-wrap class="{{ old('material_product_id') ? '' : 'hidden' }}">
                                <label class="form-label">Jumlah bahan</label>
                                <div class="bom-qty-row">
                                    <input type="number" name="material_quantity" class="form-input" step="any" min="0.0001" placeholder="1" value="{{ old('material_quantity') }}" data-addon-qty>
                                    <select name="unit" class="form-input" data-addon-unit>
                                        <option value="">—</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">+ Tambah Add-on</button>
                    </form>
                </x-module-form-card>
            </div>

            <aside class="recipe-desktop__side">
                <div class="recipe-summary-card">
                    <p class="recipe-summary-card__label">Ringkasan</p>

                    <div class="recipe-summary-card__stat">
                        <span>Bahan di resep</span>
                        <strong>{{ $product->billOfMaterials->count() }}</strong>
                    </div>
                    <div class="recipe-summary-card__stat">
                        <span>Add-on</span>
                        <strong>{{ $product->addons->where('is_active', true)->count() }}</strong>
                    </div>
                    <div class="recipe-summary-card__stat">
                        <span>Stok siap jual</span>
                        <strong>{{ $format::number($product->availableQuantity(), 0) }} {{ $product->unit }}</strong>
                    </div>
                    <div class="recipe-summary-card__modal">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Modal / {{ $product->unit }}</p>
                        <p class="mt-1 text-2xl font-bold text-brand-700">
                            {{ $product->unit_hpp > 0 ? $format::rupiah($product->unit_hpp, 0) : 'Belum dihitung' }}
                        </p>
                    </div>

                    @if ($product->billOfMaterials->isNotEmpty())
                        <form action="{{ route('products.calculate-modal', $product) }}" method="POST" class="mt-4">
                            @csrf
                            <button type="submit" class="btn-primary w-full py-3 font-semibold">Hitung Modal</button>
                        </form>
                        <a href="{{ route('menu-pricing.index') }}" class="btn-secondary mt-2 w-full justify-center py-2.5">Atur Harga Jual →</a>
                    @else
                        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Isi minimal 1 bahan resep dulu sebelum hitung modal.
                        </p>
                    @endif
                </div>

                <div class="recipe-side-actions">
                    <a href="{{ route('products.edit', $product) }}" class="btn-secondary btn-sm w-full justify-center">Ubah nama menu</a>
                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="w-full" onsubmit="return confirm('Hapus menu ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm w-full justify-center">Hapus menu</button>
                    </form>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-bom-form]');
    if (!form) return;

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

    const fillUnits = () => {
        const id = String(materialSelect.value || '');
        const meta = materialUnits[id];
        const previous = unitSelect.value;
        const oldUnit = @json(old('unit'));

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

        if (oldUnit && meta.options[oldUnit]) {
            unitSelect.value = oldUnit;
        } else if (previous && meta.options[previous]) {
            unitSelect.value = previous;
        } else if (meta.preferred && meta.options[meta.preferred]) {
            unitSelect.value = meta.preferred;
        } else {
            unitSelect.selectedIndex = 0;
        }

        setSubmitEnabled(true);

        if (hint) {
            const labels = Object.values(meta.options).join(' / ');
            hint.textContent = `Stok dalam ${meta.label || meta.unit}. Bisa isi: ${labels}.`;
        }
    };

    materialSelect.addEventListener('change', fillUnits);
    fillUnits();
})();

(() => {
    const parseUnits = (el) => {
        try {
            return JSON.parse(el?.getAttribute('data-material-units') || '{}');
        } catch (_) {
            return {};
        }
    };

    const fillUnitSelect = (unitSelect, meta, preferredValue = '') => {
        if (! unitSelect) return;

        unitSelect.innerHTML = '';

        if (! meta || ! meta.options || Object.keys(meta.options).length === 0) {
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '—';
            unitSelect.appendChild(empty);
            return;
        }

        Object.entries(meta.options).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            unitSelect.appendChild(option);
        });

        if (preferredValue && meta.options[preferredValue]) {
            unitSelect.value = preferredValue;
        } else if (meta.preferred && meta.options[meta.preferred]) {
            unitSelect.value = meta.preferred;
        } else if (meta.unit && meta.options[meta.unit]) {
            unitSelect.value = meta.unit;
        } else {
            unitSelect.selectedIndex = 0;
        }
    };

    const form = document.querySelector('.recipe-addon-form');
    if (form) {
        const materialSelect = form.querySelector('[data-addon-material]');
        const qtyWrap = form.querySelector('[data-addon-qty-wrap]');
        const unitSelect = form.querySelector('[data-addon-unit]');
        const materialUnits = parseUnits(form);

        const syncAddonUnits = () => {
            const id = String(materialSelect?.value || '');
            const meta = materialUnits[id];

            if (! id || ! meta) {
                qtyWrap?.classList.add('hidden');
                fillUnitSelect(unitSelect, null);
                return;
            }

            qtyWrap?.classList.remove('hidden');
            fillUnitSelect(unitSelect, meta, unitSelect?.value || @json(old('unit')));
        };

        materialSelect?.addEventListener('change', syncAddonUnits);
        syncAddonUnits();
    }

    document.querySelectorAll('[data-addon-edit-form]').forEach((editForm) => {
        const materialSelect = editForm.querySelector('[data-addon-edit-material]');
        const qtyWrap = editForm.querySelector('[data-addon-edit-qty-wrap]');
        const qtyInput = editForm.querySelector('[data-addon-edit-qty]');
        const unitSelect = editForm.querySelector('[data-addon-edit-unit]');
        const materialUnits = parseUnits(editForm);

        const syncEditUnits = () => {
            const id = String(materialSelect?.value || '');
            const meta = materialUnits[id];
            const previousUnit = unitSelect?.value || '';

            if (! id || ! meta) {
                qtyWrap?.classList.add('hidden');
                if (qtyInput) qtyInput.value = '';
                fillUnitSelect(unitSelect, null);
                return;
            }

            qtyWrap?.classList.remove('hidden');
            fillUnitSelect(unitSelect, meta, previousUnit);
        };

        materialSelect?.addEventListener('change', syncEditUnits);
    });
})();
</script>
@endpush
