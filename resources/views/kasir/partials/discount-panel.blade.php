@props(['order', 'format'])

@if ($order->items->isNotEmpty() && ($order->isKasirEditable() || $order->canCheckoutAtKasir()))
    @php
        $hasDiscount = $order->hasDiscount();
        $discountSummary = $hasDiscount
            ? '- '.$format::rupiah($order->discount_amount)
            : 'Tambah diskon';
    @endphp
    <div
        class="pos-discount-panel {{ $hasDiscount ? 'is-expanded' : '' }}"
        data-pos-discount-panel
        data-has-discount="{{ $hasDiscount ? '1' : '0' }}"
    >
        <button
            type="button"
            class="pos-discount-toggle"
            data-pos-discount-toggle
            aria-expanded="{{ $hasDiscount ? 'true' : 'false' }}"
            aria-controls="pos-discount-body"
        >
            <span class="pos-discount-toggle-main">
                <span class="pos-discount-title">Diskon</span>
                <span class="pos-discount-summary" data-pos-discount-summary>{{ $discountSummary }}</span>
            </span>
            <span class="pos-discount-toggle-meta">
                <span class="pos-discount-status hidden" data-pos-discount-status></span>
                <span class="pos-discount-toggle-icon" aria-hidden="true">▼</span>
            </span>
        </button>

        <div id="pos-discount-body" class="pos-discount-body" data-pos-discount-body>
            <form
                action="{{ route('kasir.discount.update') }}"
                method="POST"
                class="pos-discount-form"
                data-pos-discount-form
            >
                @csrf
                @method('PATCH')

                <div class="pos-discount-controls {{ $order->discount_type ? '' : 'is-no-discount' }}" data-pos-discount-controls>
                    <select name="discount_type" class="form-input pos-discount-type" data-pos-discount-type>
                        <option value="" @selected(! $order->discount_type)>Tanpa diskon</option>
                        <option value="amount" @selected($order->discount_type === 'amount')>Potong Rp</option>
                        <option value="percent" @selected($order->discount_type === 'percent')>Potong %</option>
                    </select>

                    <input
                        type="number"
                        name="discount_value"
                        min="0"
                        step="any"
                        inputmode="decimal"
                        class="form-input pos-discount-value"
                        data-pos-discount-value
                        placeholder="{{ $order->discount_type === 'percent' ? 'cth. 10' : 'cth. 5000' }}"
                        value="{{ $order->discount_type ? old('discount_value', $order->discount_value) : '' }}"
                        @disabled(! $order->discount_type)
                    >
                </div>

                <p class="pos-discount-hint" data-pos-discount-hint>
                    @if ($order->discount_type === 'percent')
                        Contoh: isi 10 untuk diskon 10% dari subtotal.
                    @elseif ($order->discount_type === 'amount')
                        Contoh: isi 5000 untuk potong Rp 5.000.
                    @else
                        Pilih jenis diskon, lalu isi nilainya.
                    @endif
                </p>
            </form>
        </div>
    </div>
@endif
