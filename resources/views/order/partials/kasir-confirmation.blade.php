@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation"
    data-order-waiting-kasir
    data-order-initial-status="submitted"
    data-order-status-url="{{ route('order.menu.status') }}"
    data-order-poll-interval="{{ max(90, (int) config('pos.notifications.poll_interval_seconds', 90)) }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">🏪</div>
        <p class="order-kasir-confirmation-eyebrow">Sudah dikirim</p>
        <h2 class="order-kasir-confirmation-title">Bayar tunai di kasir</h2>
        <p class="order-kasir-confirmation-lead">
            Pesanan sudah di antrean kasir. Sebutkan nomor pesanan saat bayar tunai.
        </p>
    </div>

    <ol class="order-kasir-steps" aria-label="Status pesanan">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Pesan</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">2</span>
            <span>Kasir</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">3</span>
            <span>Proses</span>
        </li>
        <li class="order-kasir-step">
            <span class="order-kasir-step-num">4</span>
            <span>Selesai</span>
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

    <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 text-sm text-brand-900">
        <p class="font-semibold">Menunggu di kasir</p>
        <p class="mt-1 text-xs leading-relaxed text-brand-800">
            Sebutkan nomor
            <strong class="font-mono">{{ $order->order_number }}</strong>
            @if ($order->customer_note)
                atas nama <strong>{{ $order->customer_note }}</strong>
            @endif
            . Kasir akan memproses pembayaran tunai.
        </p>
    </div>

    <div class="order-kasir-notice mt-4">
        <p class="font-semibold text-amber-900">Menunggu pembayaran di kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-amber-800">
            Halaman ini berubah otomatis setelah kasir menerima pembayaran.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Ingin pesan lagi? Pesanan ini tetap menunggu di kasir.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap diproses kasir.',
    ])
</section>
