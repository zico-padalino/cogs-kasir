@props(['order', 'tables', 'orderTypes', 'format'])

<form action="{{ route('kasir.order.update') }}" method="POST" class="pos-order-bar" data-pos-order-bar>
    @csrf
    @method('PATCH')

    <div class="pos-order-bar-types">
        @foreach ($orderTypes as $type)
            <label class="pos-order-type {{ ($order->order_type?->value ?? 'takeaway') === $type->value ? 'is-active' : '' }}">
                <input
                    type="radio"
                    name="order_type"
                    value="{{ $type->value }}"
                    class="sr-only"
                    data-pos-order-type
                    @checked(($order->order_type?->value ?? 'takeaway') === $type->value)
                >
                <span class="pos-order-type-icon">{{ $type->icon() }}</span>
                <span>{{ $type->label() }}</span>
            </label>
        @endforeach
    </div>

    <div
        class="pos-order-bar-table {{ ($order->order_type?->value ?? 'takeaway') === 'dine_in' || $order->table ? '' : 'hidden' }}"
        data-pos-table-field
    >
        <label class="pos-order-bar-label" for="pos-table-id">Meja</label>
        <select id="pos-table-id" name="pos_table_id" class="pos-order-bar-select" data-pos-table-select>
            <option value="">Pilih meja</option>
            @foreach ($tables as $table)
                <option value="{{ $table->id }}" @selected($order->pos_table_id === $table->id)>
                    {{ $table->label }} (#{{ $table->table_number }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="pos-order-bar-customer">
        <label class="pos-order-bar-label" for="pos-customer-note">Nama pelanggan</label>
        <input
            id="pos-customer-note"
            type="text"
            name="customer_note"
            value="{{ old('customer_note', $order->customer_note) }}"
            maxlength="255"
            class="pos-order-bar-input"
            placeholder="Opsional — panggilan / nomor antrian"
            data-pos-customer-note
        >
    </div>

    <button type="submit" class="pos-order-bar-save">Simpan</button>
</form>
