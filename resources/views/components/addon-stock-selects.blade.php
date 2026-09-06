@props([
    'remoteUrl',
    'selectedMaterial' => null,
    'mode' => 'create', // create | edit
    'allowSemiFinished' => true,
])

@php
    $selectedId = $selectedMaterial?->id !== null ? (string) $selectedMaterial->id : null;
    $isRaw = $selectedMaterial && $selectedMaterial->effectiveType() === \App\Enums\ProductType::RawMaterial;
    $isJadi = $selectedMaterial && $selectedMaterial->effectiveType() === \App\Enums\ProductType::SemiFinished;
    $rawEmpty = $mode === 'edit' ? 'Tanpa bahan baku' : 'Tidak potong / pilih bahan baku...';
    $jadiEmpty = $mode === 'edit' ? 'Tanpa bahan jadi' : 'Tidak potong / pilih bahan jadi...';
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <input type="hidden" name="material_product_id" value="{{ $selectedId }}" data-addon-material-id>
    <div class="recipe-add-form__material-split">
        <div>
            <label class="form-label">Bahan baku</label>
            <select
                class="form-input"
                @if ($mode === 'edit') data-addon-edit-material @else data-addon-material @endif
                data-searchable-select
                data-search-placeholder="{{ $rawEmpty }}"
                data-search-input-placeholder="Cari bahan baku..."
                data-remote-url="{{ $remoteUrl }}"
                data-remote-type="raw_material"
                data-remote-per-page="20"
            >
                <option value="">{{ $rawEmpty }}</option>
                @if ($isRaw && $selectedMaterial)
                    <option value="{{ $selectedMaterial->id }}" selected>
                        {{ $selectedMaterial->name }} ({{ \App\Support\MaterialUnits::label($selectedMaterial->unit) }})
                    </option>
                @endif
            </select>
        </div>
        @if ($allowSemiFinished)
            <div>
                <label class="form-label">Bahan jadi</label>
                <select
                    class="form-input"
                    @if ($mode === 'edit') data-addon-edit-material @else data-addon-material @endif
                    data-searchable-select
                    data-search-placeholder="{{ $jadiEmpty }}"
                    data-search-input-placeholder="Cari bahan jadi..."
                    data-remote-url="{{ $remoteUrl }}"
                    data-remote-type="semi_finished"
                    data-remote-per-page="20"
                >
                    <option value="">{{ $jadiEmpty }}</option>
                    @if ($isJadi && $selectedMaterial)
                        <option value="{{ $selectedMaterial->id }}" selected>
                            {{ $selectedMaterial->name }} ({{ \App\Support\MaterialUnits::label($selectedMaterial->unit) }})
                        </option>
                    @endif
                </select>
            </div>
        @endif
    </div>
    <p class="form-hint">Pilih salah satu — kosongkan keduanya jika tidak potong stok. Cari nama, lalu muat halaman berikutnya bila perlu.</p>
</div>
