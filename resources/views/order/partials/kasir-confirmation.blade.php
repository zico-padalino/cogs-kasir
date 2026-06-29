@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation"
    data-order-waiting-kasir
    data-order-initial-status="submitted"
    data-order-status-url="{{ route('order.menu.status') }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">🏪</div>
        <p class="order-kasir-confirmation-eyebrow">Pesanan selesai</p>
        <h2 class="order-kasir-confirmation-title">Silakan ke Kasir</h2>
        <p class="order-kasir-confirmation-lead">
            Konfirmasi pesanan dan pembayaran dilakukan di kasir. Tunjukkan nomor pesanan & nama Anda kepada kasir.
        </p>
    </div>

    <ol class="order-kasir-steps">
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">1</span>
            <span>Datang ke kasir</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">2</span>
            <span>Sebutkan <strong>nomor pesanan</strong> & <strong>nama Anda</strong></span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">3</span>
            <span>Kasir konfirmasi pesanan Anda</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">4</span>
            <span>Bayar sesuai total tagihan</span>
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

    <div class="order-kasir-notice">
        <p class="font-semibold text-amber-900">Menunggu konfirmasi kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-amber-800">
            Kasir akan memverifikasi pesanan Anda. Halaman ini akan berubah setelah dikonfirmasi atau dibayar.
        </p>
    </div>
</section>
