@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation order-awaiting-serve"
    data-order-waiting-kasir
    data-order-initial-status="paid"
    data-order-status-url="{{ route('order.menu.status') }}"
    data-order-poll-interval="{{ max(90, (int) config('pos.notifications.poll_interval_seconds', 90)) }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">✅</div>
        <p class="order-kasir-confirmation-eyebrow">Sudah dibayar</p>
        <h2 class="order-kasir-confirmation-title">Menunggu diantar</h2>
        <p class="order-kasir-confirmation-lead">
            @if ($order->paidByCustomerOnline())
                Pembayaran QRIS diterima. Kasir sedang menyiapkan pesanan Anda.
            @else
                Pembayaran diterima. Kasir sedang menyiapkan pesanan Anda.
            @endif
        </p>
    </div>

    <ol class="order-kasir-steps" aria-label="Status pesanan">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Pesan</span>
        </li>
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">2</span>
            <span>Bayar</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">3</span>
            <span>Diantar</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">4</span>
            <span>Selesai</span>
        </li>
    </ol>

    <div class="order-kasir-ticket">
        <div class="order-kasir-ticket-row">
            <p class="order-kasir-ticket-label">Nomor</p>
            <p class="order-kasir-ticket-value font-mono">{{ $order->order_number }}</p>
        </div>
        @if ($order->order_type)
            <div class="order-kasir-ticket-row">
                <p class="order-kasir-ticket-label">Tipe</p>
                <p class="order-kasir-ticket-value">{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</p>
            </div>
        @endif
        @if ($order->customer_note)
            <div class="order-kasir-ticket-row">
                <p class="order-kasir-ticket-label">Nama</p>
                <p class="order-kasir-ticket-value">{{ $order->customer_note }}</p>
            </div>
        @endif
        <div class="order-kasir-ticket-row order-kasir-ticket-total">
            <p class="order-kasir-ticket-label">Total dibayar</p>
            <p class="order-kasir-ticket-value text-brand-600">{{ $format::rupiah($order->total) }}</p>
        </div>
    </div>

    @if ($order->paymentProofUrl())
        <div class="order-proof-card">
            <p class="order-proof-card-title">Bukti pembayaran</p>
            <a href="{{ $order->paymentProofUrl() }}" target="_blank" rel="noopener" class="order-proof-card-frame">
                <img src="{{ $order->paymentProofUrl() }}" alt="Bukti pembayaran">
            </a>
        </div>
    @endif

    <div class="order-kasir-notice order-kasir-notice-confirmed">
        <p class="font-semibold">Menunggu kasir</p>
        <p class="mt-1 text-sm leading-relaxed">
            Halaman ini berubah otomatis setelah pesanan diantar.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Pesanan yang sudah dibayar tetap diproses.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap menunggu diantar.',
    ])
</section>
