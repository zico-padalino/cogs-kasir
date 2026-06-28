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
    $maxQty = max(1, (int) $product->availableQuantity());
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
        <p class="pos-receipt-line-name">{{ $product->name }}</p>
        <p class="pos-receipt-line-qty">{{ $format::rupiah($item->unit_price) }} / {{ $product->unit }}</p>

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
                    <input type="hidden" name="quantity" value="{{ min($maxQty, $item->quantity + 1) }}">
                    <button type="submit" class="pos-cart-qty-btn" @disabled($item->quantity >= $maxQty) aria-label="Tambah">+</button>
                </form>
            </div>

            <form action="{{ $updateUrl }}" method="POST" class="pos-item-note-form pos-order-item-note-form">
                @csrf
                @method('PATCH')
                <textarea
                    name="notes"
                    rows="2"
                    maxlength="255"
                    class="order-item-note-input pos-item-note-input"
                    placeholder="Catatan: less sugar, hot, dll."
                >{{ old('notes', $item->notes) }}</textarea>
                <button type="submit" class="pos-item-note-save">Simpan catatan</button>
            </form>
        @else
            <p class="pos-receipt-line-qty-secondary">
                {{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}
            </p>
            @if ($item->notes)
                <p class="pos-receipt-line-note">Catatan: {{ $item->notes }}</p>
            @endif
        @endif
    </div>

    <div class="pos-receipt-line-side">
        <span class="pos-receipt-line-total">{{ $format::rupiah($item->line_total) }}</span>
        @if ($editable && $destroyUrl)
            <form action="{{ $destroyUrl }}" method="POST">
                @csrf @method('DELETE')
                <button type="submit" class="pos-line-remove" aria-label="Hapus {{ $product->name }}">×</button>
            </form>
        @endif
    </div>
</div>
