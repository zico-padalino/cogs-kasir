@props(['order', 'tables', 'orderTypes', 'format'])

@php
    $activeType = $order->order_type?->value ?? 'takeaway';
    $isDineIn = $activeType === 'dine_in';
    $summaryParts = array_filter([
        $order->order_type ? $order->order_type->icon().' '.$order->order_type->label() : null,
        $order->table?->label,
        $order->customer_note,
    ]);
    $orderSummary = $summaryParts !== [] ? implode(' · ', $summaryParts) : 'Atur tipe pesanan';
@endphp

<form action="{{ route('kasir.order.update') }}" method="POST" class="pos-order-bar" data-pos-order-bar>
    @csrf
    @method('PATCH')

    <button
        type="button"
        class="pos-order-bar-toggle lg:hidden"
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
            @foreach ($orderTypes as $type)
                <label
                    class="pos-order-type-card {{ $activeType === $type->value ? 'is-active' : '' }}"
                    data-pos-order-type-card="{{ $type->value }}"
                >
                    <input
                        type="radio"
                        name="order_type"
                        value="{{ $type->value }}"
                        class="sr-only"
                        data-pos-order-type
                        @checked($activeType === $type->value)
                    >
                    <span class="pos-order-type-icon-lg" aria-hidden="true">{{ $type->icon() }}</span>
                    <span class="pos-order-type-name">{{ $type->label() }}</span>
                    <span class="pos-order-type-hint">{{ $type->hint() }}</span>
                </label>
            @endforeach
        </div>

        <div class="pos-order-bar-fields">
            <div
                class="pos-order-field-group {{ $isDineIn ? '' : 'hidden' }}"
                data-pos-dine-in-fields
            >
                <div class="pos-order-field-head">
                    <label class="pos-order-bar-label" for="pos-table-id">Meja</label>
                    <span class="pos-order-required">Wajib</span>
                </div>

                @if ($tables->isNotEmpty())
                    <div class="pos-table-picks" role="group" aria-label="Pilih meja">
                        @foreach ($tables as $table)
                            <button
                                type="button"
                                class="pos-table-pill {{ $order->pos_table_id === $table->id ? 'is-active' : '' }}"
                                data-pos-table-pill
                                data-table-id="{{ $table->id }}"
                                data-table-label="{{ $table->label }}"
                            >
                                <span class="pos-table-pill-num">#{{ $table->table_number }}</span>
                                <span class="pos-table-pill-label">{{ $table->label }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <select
                    id="pos-table-id"
                    name="pos_table_id"
                    class="pos-order-bar-select sr-only"
                    data-pos-table-select
                    tabindex="-1"
                    aria-hidden="true"
                >
                    <option value="">Pilih meja</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table->id }}" @selected($order->pos_table_id === $table->id)>
                            {{ $table->label }} (#{{ $table->table_number }})
                        </option>
                    @endforeach
                </select>

                <p class="pos-order-field-error hidden" data-pos-table-error>Pilih meja untuk Dine In.</p>
            </div>

            <div class="pos-order-field-group" data-pos-customer-field>
                <label class="pos-order-bar-label" for="pos-customer-note" data-pos-customer-label>
                    {{ $isDineIn ? 'Nama pelanggan' : 'Nama / nomor antrian' }}
                </label>
                <input
                    id="pos-customer-note"
                    type="text"
                    name="customer_note"
                    value="{{ old('customer_note', $order->customer_note) }}"
                    maxlength="255"
                    class="pos-order-bar-input"
                    placeholder="{{ $isDineIn ? 'Opsional — untuk panggilan' : 'Contoh: Budi / A-12' }}"
                    data-pos-customer-note
                    autocomplete="off"
                >
                <p class="pos-order-field-hint" data-pos-customer-hint>
                    {{ $isDineIn ? 'Opsional jika sudah ada meja.' : 'Memudahkan kasir memanggil saat pesanan siap.' }}
                </p>
            </div>
        </div>
    </div>
</form>
<div class="pos-order-bar-backdrop lg:hidden hidden" data-pos-order-bar-backdrop aria-hidden="true"></div>
