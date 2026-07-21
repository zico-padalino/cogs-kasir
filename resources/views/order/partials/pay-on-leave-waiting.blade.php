@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-pay-on-leave"
    data-order-waiting-kasir
    data-order-initial-status="unpaid"
    data-order-status-url="{{ route('order.menu.status') }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">☕</div>
        <p class="order-kasir-confirmation-eyebrow">Pesanan diterima</p>
        <h2 class="order-kasir-confirmation-title">Bayar Saat Pulang</h2>
        <p class="order-kasir-confirmation-lead">
            Silakan nikmati dulu. Tunjukkan nomor & nama ini ke kasir saat Anda pulang untuk membayar.
        </p>
    </div>

    <ol class="order-kasir-steps">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Anda sudah pesan</span>
        </li>
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">2</span>
            <span>Nikmati di tempat</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">3</span>
            <span>Bayar di kasir saat pulang</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">4</span>
            <span>Pesanan selesai</span>
        </li>
    </ol>

    <div class="order-kasir-ticket">
        <div class="order-kasir-ticket-row">
            <p class="order-kasir-ticket-label">Nomor Pesanan</p>
            <p class="order-kasir-ticket-value font-mono">{{ $order->order_number }}</p>
        </div>
        @if ($order->order_type)
            <div class="order-kasir-ticket-row">
                <p class="order-kasir-ticket-label">Tipe Pesanan</p>
                <p class="order-kasir-ticket-value">{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</p>
            </div>
        @endif
        @if ($order->customer_note)
            <div class="order-kasir-ticket-row">
                <p class="order-kasir-ticket-label">Nama Pemesan</p>
                <p class="order-kasir-ticket-value">{{ $order->customer_note }}</p>
            </div>
        @endif
        <div class="order-kasir-ticket-row order-kasir-ticket-total">
            <p class="order-kasir-ticket-label">Total Tagihan</p>
            <p class="order-kasir-ticket-value text-brand-600">{{ $format::rupiah($order->total) }}</p>
        </div>
    </div>

    <div class="order-kasir-notice">
        <p class="font-semibold text-amber-900">Tagihan tersimpan di kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-amber-800">
            Halaman ini berubah otomatis setelah Anda bayar di kasir saat pulang.
        </p>
    </div>
</section>
