@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-kasir-confirmed"
    data-order-waiting-kasir
    data-order-initial-status="confirmed"
    data-order-status-url="{{ route('order.menu.status') }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">✅</div>
        <p class="order-kasir-confirmation-eyebrow">Pesanan dikonfirmasi</p>
        <h2 class="order-kasir-confirmation-title">Silakan ke Kasir untuk Bayar</h2>
        <p class="order-kasir-confirmation-lead">
            Kasir sudah mengonfirmasi pesanan Anda. Datang ke kasir untuk menyelesaikan pembayaran.
        </p>
    </div>

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
        <p class="font-semibold text-brand-900">Menunggu pembayaran di kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-brand-800">
            Halaman ini akan otomatis berubah setelah kasir memproses pembayaran Anda.
        </p>
    </div>
</section>
