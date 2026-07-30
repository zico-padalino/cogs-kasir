@props([
    'rawMaterials',
    'semiFinishedMaterials',
    'selectedId' => null,
    'mode' => 'create', // create | edit
])

@php
    $selectedId = $selectedId !== null && $selectedId !== '' ? (string) $selectedId : null;
    $isRaw = $selectedId && $rawMaterials->contains(fn ($p) => (string) $p->id === $selectedId);
    $isJadi = $selectedId && $semiFinishedMaterials->contains(fn ($p) => (string) $p->id === $selectedId);
    $rawEmpty = $mode === 'edit' ? 'Tanpa bahan baku' : 'Tidak potong / pilih bahan baku...';
    $jadiEmpty = $mode === 'edit' ? 'Tanpa bahan jadi' : 'Tidak potong / pilih bahan jadi...';
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <input type="hidden" name="material_product_id" value="{{ $selectedId }}" data-addon-material-id>
    <div class="recipe-add-form__material-split">
        <div>
            <label class="form-label">Bahan baku</label>
            @if ($mode === 'edit')
                <select class="form-input" data-addon-edit-material>
            @else
                <select class="form-input" data-addon-material>
            @endif
                <option value="">{{ $rawEmpty }}</option>
                @foreach ($rawMaterials as $p)
                    <option value="{{ $p->id }}" @selected($isRaw && (string) $selectedId === (string) $p->id)>
                        {{ $p->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Bahan jadi</label>
            @if ($mode === 'edit')
                <select class="form-input" data-addon-edit-material>
            @else
                <select class="form-input" data-addon-material>
            @endif
                <option value="">{{ $jadiEmpty }}</option>
                @foreach ($semiFinishedMaterials as $p)
                    <option value="{{ $p->id }}" @selected($isJadi && (string) $selectedId === (string) $p->id)>
                        {{ $p->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="form-hint">Pilih salah satu — kosongkan keduanya jika tidak potong stok.</p>
</div>
