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
        <p class="order-kasir-confirmation-eyebrow">Pesanan terkirim</p>
        <h2 class="order-kasir-confirmation-title">Silakan ke Kasir</h2>
        <p class="order-kasir-confirmation-lead">
            Tunjukkan nomor pesanan & nama Anda. Kasir akan memproses pembayaran hingga pesanan selesai.
        </p>
    </div>

    <ol class="order-kasir-steps">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Anda sudah pesan</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">2</span>
            <span>Datang ke kasir · sebutkan nomor & nama</span>
        </li>
        <li class="order-kasir-step">
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

    <div class="order-kasir-notice">
        <p class="font-semibold text-amber-900">Menunggu kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-amber-800">
            Halaman ini berubah otomatis setelah kasir menerima dan menyelesaikan pembayaran.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Ingin pesan lagi? Pesanan ini tetap menunggu di kasir.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap diproses kasir.',
    ])
</section>
