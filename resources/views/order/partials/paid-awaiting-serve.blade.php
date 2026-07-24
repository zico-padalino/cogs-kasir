@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-awaiting-serve"
    data-order-waiting-kasir
    data-order-initial-status="paid"
    data-order-status-url="{{ route('order.menu.status') }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">💳</div>
        <p class="order-kasir-confirmation-eyebrow">Pembayaran diterima</p>
        <h2 class="order-kasir-confirmation-title">Menunggu Diantar / Selesai</h2>
        <p class="order-kasir-confirmation-lead">
            Terima kasih! Pesanan Anda sudah dibayar. Mohon tunggu hingga kasir mengantar atau menandai pesanan selesai.
        </p>
    </div>

    <ol class="order-kasir-steps">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Anda sudah pesan</span>
        </li>
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">2</span>
            <span>Sudah bayar di kasir</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">3</span>
            <span>Diantar / diselesaikan</span>
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
            <p class="order-kasir-ticket-label">Total Dibayar</p>
            <p class="order-kasir-ticket-value text-brand-600">{{ $format::rupiah($order->total) }}</p>
        </div>
    </div>

    <div class="order-kasir-notice order-kasir-notice-confirmed">
        <p class="font-semibold text-brand-900">Menunggu konfirmasi kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-brand-800">
            Halaman ini berubah otomatis setelah pesanan diantar / ditandai selesai.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Ingin pesan lagi sambil menunggu? Pesanan yang sudah dibayar tetap diproses.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap menunggu diantar.',
    ])
</section>
