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
    $detailId = 'order-item-detail-'.$item->id;
@endphp

<div
    {{ $attributes->merge(['class' => trim($lineClass.' pos-order-item')]) }}
    data-order-item
    data-kasir-item
    data-item-id="{{ $item->id }}"
>
    <x-product-image :product="$product" class="pos-receipt-thumb" />

    <div class="pos-receipt-line-main pos-order-item-main">
        <div class="pos-order-item-head">
            <div class="min-w-0 flex-1">
                <p class="pos-receipt-line-name">{{ $product->name }}</p>
                <p class="pos-receipt-line-qty">
                    {{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}
                </p>
            </div>
            <button
                type="button"
                class="pos-order-item-toggle"
                data-order-item-toggle
                aria-expanded="false"
                aria-controls="{{ $detailId }}"
            >
                <span data-order-item-toggle-label>Detail</span>
                <span class="pos-order-item-toggle-icon" aria-hidden="true">▾</span>
            </button>
        </div>

        @if ($item->notes && ! $editable)
            <p class="pos-receipt-line-note">Catatan: {{ $item->notes }}</p>
        @endif

        <div id="{{ $detailId }}" class="pos-order-item-detail hidden" data-order-item-detail hidden>
            <div class="pos-order-item-detail-media">
                <x-product-image :product="$product" :eager="true" class="pos-order-item-detail-image" />
            </div>

            <dl class="pos-order-item-meta">
                <div>
                    <dt>SKU</dt>
                    <dd>{{ $product->sku }}</dd>
                </div>
                <div>
                    <dt>Satuan</dt>
                    <dd>{{ $product->unit }}</dd>
                </div>
                <div>
                    <dt>Jenis</dt>
                    <dd>{{ $product->type->label() }}</dd>
                </div>
                <div>
                    <dt>Stok</dt>
                    <dd>{{ $format::number($product->availableQuantity(), 0) }}</dd>
                </div>
            </dl>

            @if ($product->description)
                <div class="pos-order-item-desc-block">
                    <p class="pos-order-item-desc-label">Deskripsi menu</p>
                    <p class="pos-order-item-desc-text">{{ $product->description }}</p>
                </div>
            @endif

            <div class="pos-order-item-price-block">
                <div class="pos-order-item-price-row">
                    <span>Harga satuan</span>
                    <span>{{ $format::rupiah($item->unit_price) }}</span>
                </div>
                <div class="pos-order-item-price-row">
                    <span>Jumlah</span>
                    <span>{{ $format::number($item->quantity, 0) }} {{ $product->unit }}</span>
                </div>
                <div class="pos-order-item-price-row pos-order-item-price-total">
                    <span>Subtotal item</span>
                    <span>{{ $format::rupiah($item->line_total) }}</span>
                </div>
            </div>

            @if ($editable && $updateUrl)
                <form action="{{ $updateUrl }}" method="POST" class="pos-item-note-form">
                    @csrf
                    @method('PATCH')
                    <label class="pos-item-note-label" for="note-{{ $item->id }}">Catatan pembelian</label>
                    <textarea
                        id="note-{{ $item->id }}"
                        name="notes"
                        rows="3"
                        maxlength="255"
                        class="order-item-note-input pos-item-note-input"
                        placeholder="Contoh: tanpa gula, bungkus terpisah, level pedas..."
                    >{{ old('notes', $item->notes) }}</textarea>
                    <button type="submit" class="pos-item-note-save">Simpan catatan</button>
                </form>
            @elseif ($item->notes)
                <div class="pos-order-item-note-block">
                    <p class="pos-item-note-label">Catatan pembelian</p>
                    <p class="pos-order-item-note-text">{{ $item->notes }}</p>
                </div>
            @endif
        </div>
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
