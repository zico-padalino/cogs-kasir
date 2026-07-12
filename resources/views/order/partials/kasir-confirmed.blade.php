@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-kasir-confirmed"
    data-order-waiting-kasir
    data-order-initial-status="confirmed"
    data-order-status-url="{{ route('order.menu.status') }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">💳</div>
        <p class="order-kasir-confirmation-eyebrow">Sudah di kasir</p>
        <h2 class="order-kasir-confirmation-title">Silakan Bayar di Kasir</h2>
        <p class="order-kasir-confirmation-lead">
            Pesanan Anda sudah masuk ke kasir. Selesaikan pembayaran untuk menyelesaikan pesanan.
        </p>
    </div>

    <ol class="order-kasir-steps">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Anda sudah pesan</span>
        </li>
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">2</span>
            <span>Masuk ke kasir</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">3</span>
            <span>Bayar di kasir</span>
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
        @if ($order->customer_note)
            <div class="order-kasir-ticket-row">
                <p class="order-kasir-ticket-label">Nama Pemesan</p>
                <p class="order-kasir-ticket-value">{{ $order->customer_note }}</p>
            </div>
        @endif
        <div class="order-kasir-ticket-row order-kasir-ticket-total">
            <p class="order-kasir-ticket-label">Total Bayar</p>
            <p class="order-kasir-ticket-value text-brand-600">{{ $format::rupiah($order->total) }}</p>
        </div>
    </div>

    <div class="order-kasir-notice order-kasir-notice-confirmed">
        <p class="font-semibold text-brand-900">Menunggu pembayaran</p>
        <p class="mt-1 text-sm leading-relaxed text-brand-800">
            Halaman ini berubah otomatis setelah pembayaran selesai.
        </p>
    </div>
</section>
