@props(['order', 'orderTypes', 'format'])

@php
    $activeType = $order->order_type?->value ?? 'takeaway';
    $summaryParts = array_filter([
        $order->order_type ? $order->order_type->icon().' '.$order->order_type->label() : null,
        $order->customer_note,
    ]);
    $orderSummary = $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Atur tipe pesanan';
@endphp

<form action="{{ route('kasir.order.update') }}" method="POST" class="pos-order-bar" data-pos-order-bar>
    @csrf
    @method('PATCH')

    <button
        type="button"
        class="pos-order-bar-toggle"
        data-pos-order-bar-toggle
        aria-expanded="false"
        aria-controls="pos-order-bar-body"
    >
        <span class="pos-order-bar-toggle-label">Tipe pesanan</span>
        <span class="pos-order-bar-toggle-value" data-pos-order-summary>{{ $orderSummary }}</span>
        <span class="pos-order-bar-toggle-icon" aria-hidden="true">▼</span>
    </button>

    <div id="pos-order-bar-body" class="pos-order-bar-body" data-pos-order-bar-body>
        <div class="pos-order-bar-head">
            <div>
                <p class="pos-order-bar-title">Tipe pesanan</p>
                <p class="pos-order-bar-sub">Pilih Dine In atau Take Away</p>
            </div>
            <span class="pos-order-save-status hidden" data-pos-save-status aria-live="polite"></span>
        </div>

        <div class="pos-order-type-grid" role="radiogroup" aria-label="Tipe pesanan">
            @foreach ($orderTypes as $orderType)
                <label
                    class="pos-order-type-card {{ $activeType === $orderType->value ? 'is-active' : '' }}"
                    data-pos-order-type-card="{{ $orderType->value }}"
                >
                    <input
                        type="radio"
                        name="order_type"
                        value="{{ $orderType->value }}"
                        class="sr-only"
                        data-pos-order-type
                        @checked($activeType === $orderType->value)
                    >
                    <span class="pos-order-type-icon-lg" aria-hidden="true">{{ $orderType->icon() }}</span>
                    <span class="pos-order-type-text">
                        <span class="pos-order-type-name">{{ $orderType->label() }}</span>
                        <span class="pos-order-type-hint">{{ $orderType->hint() }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        <div class="pos-order-bar-fields">
            <div class="pos-order-field-group" data-pos-customer-field>
                <label class="pos-order-bar-label" for="pos-customer-note">Nama pelanggan</label>
                <input
                    id="pos-customer-note"
                    type="text"
                    name="customer_note"
                    value="{{ old('customer_note', $order->customer_note) }}"
                    maxlength="255"
                    class="pos-order-bar-input"
                    placeholder="Contoh: Budi"
                    data-pos-customer-note
                    autocomplete="off"
                >
                <p class="pos-order-field-hint">
                    Untuk memanggil pelanggan saat pesanan siap.
                </p>
            </div>
        </div>
    </div>
</form>
<div class="pos-order-bar-backdrop lg:hidden hidden" data-pos-order-bar-backdrop aria-hidden="true"></div>
