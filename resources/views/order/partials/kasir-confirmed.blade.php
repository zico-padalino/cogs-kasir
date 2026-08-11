@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-kasir-confirmed"
    data-order-waiting-kasir
    data-order-initial-status="confirmed"
    data-order-status-url="{{ route('order.menu.status') }}"
    data-order-poll-interval="{{ max(90, (int) config('pos.notifications.poll_interval_seconds', 90)) }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">💳</div>
        <p class="order-kasir-confirmation-eyebrow">Siap dibayar</p>
        <h2 class="order-kasir-confirmation-title">Bayar di kasir</h2>
        <p class="order-kasir-confirmation-lead">
            Pesanan sudah di kasir. Bayar tunai di kasir, atau gunakan QRIS di bawah lalu upload bukti.
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
            <span>Bayar</span>
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
            <p class="order-kasir-ticket-label">Total Bayar</p>
            <p class="order-kasir-ticket-value text-brand-600">{{ $format::rupiah($order->total) }}</p>
        </div>
    </div>

    @include('order.partials.payment-choice', ['order' => $order, 'format' => $format])

    <div class="order-kasir-notice order-kasir-notice-confirmed">
        <p class="font-semibold text-brand-900">Menunggu pembayaran</p>
        <p class="mt-1 text-sm leading-relaxed text-brand-800">
            Halaman ini berubah otomatis setelah pembayaran selesai.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Ingin pesan lagi? Selesaikan dulu pembayaran pesanan ini, atau buat pesanan baru terpisah.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap menunggu pembayaran.',
    ])
</section>
