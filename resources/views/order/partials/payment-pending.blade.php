@props(['order', 'format'])

<section
    id="ke-kasir"
    class="order-kasir-confirmation"
    data-order-waiting-kasir
    data-order-initial-status="pending_payment"
    data-order-status-url="{{ route('order.menu.status') }}"
    data-order-poll-interval="{{ max(90, (int) config('pos.notifications.poll_interval_seconds', 90)) }}"
>
    <div class="order-kasir-confirmation-hero">
        <div class="order-kasir-confirmation-icon" aria-hidden="true">💳</div>
        <p class="order-kasir-confirmation-eyebrow">Pesanan siap</p>
        <h2 class="order-kasir-confirmation-title">Pilih cara bayar</h2>
        <p class="order-kasir-confirmation-lead">
            Bayar QRIS dari meja, atau tunai agar pesanan masuk kasir.
        </p>
    </div>

    <ol class="order-kasir-steps" aria-label="Status pesanan">
        <li class="order-kasir-step is-done">
            <span class="order-kasir-step-num">1</span>
            <span>Pesan</span>
        </li>
        <li class="order-kasir-step is-current">
            <span class="order-kasir-step-num">2</span>
            <span>Bayar</span>
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

    @if ($order->items->isNotEmpty())
        <div class="order-waiting-items-preview">
            <div class="order-waiting-items-preview-head">
                <p class="order-waiting-items-preview-title">Pesanan Anda</p>
                <button type="button" class="order-waiting-items-preview-link md:hidden" data-waiting-tab-jump="order">
                    Lihat semua →
                </button>
            </div>
            <ul class="order-waiting-items-preview-list">
                @foreach ($order->items->take(4) as $item)
                    <li>
                        <span class="min-w-0 truncate">
                            <strong class="tabular-nums">{{ $format::number($item->quantity, 0) }}×</strong>
                            {{ $item->product?->name ?? 'Item' }}
                        </span>
                        <span class="shrink-0 tabular-nums text-slate-700">{{ $format::rupiah($item->line_total) }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($order->items->count() > 4)
                <p class="order-waiting-items-preview-more">+{{ $order->items->count() - 4 }} item lain — buka tab Pesanan</p>
            @endif
        </div>
    @endif

    @include('order.partials.payment-choice', ['order' => $order, 'format' => $format])

    <div class="order-kasir-notice">
        <p class="font-semibold text-amber-900">Belum masuk kasir</p>
        <p class="mt-1 text-sm leading-relaxed text-amber-800">
            Pesanan baru muncul di kasir setelah Anda upload bukti QRIS atau menekan “Kirim ke kasir” untuk tunai.
        </p>
    </div>

    @include('order.partials.new-order-button', [
        'label' => 'Buat pesanan baru',
        'hint' => 'Ingin pesan lagi? Selesaikan dulu pembayaran pesanan ini, atau buat pesanan baru terpisah.',
        'confirm' => 'Buat pesanan baru? Pesanan '.$order->order_number.' tetap menunggu pembayaran.',
    ])
</section>
