@props([
    'item',
    'format',
    'editable' => false,
    'updateUrl' => null,
    'destroyUrl' => null,
    'lineClass' => 'pos-receipt-line',
])

@php
    $product = $item->product;
    $noteParts = \App\Support\PosItemNotes::split($item->notes);
@endphp

<div
    {{ $attributes->merge(['class' => trim($lineClass.' pos-order-item')]) }}
    data-order-item
    data-kasir-item
    data-item-id="{{ $item->id }}"
>
    <button
        type="button"
        class="pos-order-item-thumb-btn"
        data-order-item-image-open
        data-image-url="{{ $product->imageUrl() }}"
        data-image-title="{{ $product->name }}"
        aria-label="Lihat gambar {{ $product->name }}"
    >
        <x-product-image :product="$product" class="pos-receipt-thumb" />
    </button>

    <div class="pos-receipt-line-main pos-order-item-main">
        <div class="pos-order-item-row1">
            <p class="pos-receipt-line-name">{{ $product->name }}</p>
            <span class="pos-receipt-line-total">{{ $format::rupiah($item->line_total) }}</span>
            @if ($editable && $destroyUrl)
                <form action="{{ $destroyUrl }}" method="POST" class="pos-order-item-remove-form">
                    @csrf @method('DELETE')
                    <button type="submit" class="pos-line-remove" aria-label="Hapus {{ $product->name }}">×</button>
                </form>
            @endif
        </div>

        <div class="pos-order-item-row2">
            <span class="pos-receipt-line-qty">{{ $format::rupiah($item->unit_price) }}/{{ $product->unit }}</span>

            @if ($editable && $updateUrl)
                <div class="pos-cart-qty">
                    <form action="{{ $updateUrl }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="quantity" value="{{ max(1, $item->quantity - 1) }}">
                        <button type="submit" class="pos-cart-qty-btn" @disabled($item->quantity <= 1) aria-label="Kurangi">−</button>
                    </form>
                    <span class="pos-cart-qty-value">{{ $format::number($item->quantity, 0) }}</span>
                    <form action="{{ $updateUrl }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="quantity" value="{{ $item->quantity + 1 }}">
                        <button type="submit" class="pos-cart-qty-btn" aria-label="Tambah">+</button>
                    </form>
                </div>

                <details class="pos-order-item-note-details" @if ($noteParts['customer']) open @endif>
                    <summary class="pos-order-item-note-summary">
                        {{ $noteParts['customer'] ? '✎' : '+ catatan' }}
                    </summary>
                    <form action="{{ $updateUrl }}" method="POST" class="pos-item-note-form pos-order-item-note-form">
                        @csrf
                        @method('PATCH')
                        <textarea
                            name="notes"
                            rows="2"
                            maxlength="255"
                            class="order-item-note-input pos-item-note-input"
                            placeholder="Contoh: less sugar, hot"
                        >{{ old('notes', $noteParts['customer']) }}</textarea>
                        <button type="submit" class="pos-item-note-save">Simpan</button>
                    </form>
                </details>
            @else
                <span class="pos-receipt-line-qty-secondary">
                    ×{{ $format::number($item->quantity, 0) }}
                </span>
            @endif

            @if ($noteParts['addon_labels'] !== [])
                <span class="pos-addon-chips pos-addon-chips--inline" aria-label="Add-on">
                    @foreach ($noteParts['addon_labels'] as $label)
                        <span class="pos-addon-chip">{{ $label }}</span>
                    @endforeach
                </span>
            @endif

            @if ($noteParts['customer'] && ! ($editable && $updateUrl))
                <span class="pos-receipt-line-note pos-receipt-line-note--inline">{{ $noteParts['customer'] }}</span>
            @endif
        </div>
    </div>
</div>
