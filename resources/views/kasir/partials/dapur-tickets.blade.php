@php
    use App\Enums\PosOrderStatus;
    use App\Support\PosItemNotes;
@endphp

@forelse ($kitchenOrders as $order)
    @php
        $isOpenBill = $order->status === PosOrderStatus::Unpaid;
        $itemCount = $order->items->count();
        $doneCount = $order->items->where('is_delivered', true)->count();
        $allDone = $itemCount > 0 && $doneCount === $itemCount;
        $startedAt = $order->paid_at ?? $order->confirmed_at ?? $order->updated_at ?? $order->created_at;
        $canChecklist = $order->canChecklistDelivered();
        $canServe = $order->canMarkServed();
    @endphp
    <article
        @class([
            'kds-ticket',
            'is-open-bill' => $isOpenBill,
            'is-paid' => $order->status === PosOrderStatus::Paid,
            'is-done' => $allDone,
        ])
        data-dapur-ticket
        data-order-id="{{ $order->id }}"
        data-started-at="{{ $startedAt?->toIso8601String() }}"
    >
        <header class="kds-ticket-head">
            <div class="kds-ticket-head-main">
                <p class="kds-ticket-number">{{ $order->order_number }}</p>
                <p class="kds-ticket-customer">{{ $order->customer_note ?: 'Tanpa nama' }}</p>
            </div>
            <div class="kds-ticket-head-side">
                <span class="kds-ticket-elapsed" data-dapur-elapsed>—</span>
                <span @class(['kds-ticket-badge', 'is-bill' => $isOpenBill, 'is-paid' => ! $isOpenBill])>
                    @if ($isOpenBill)
                        Tagihan terbuka
                    @elseif ($order->paidByCustomerOnline())
                        Lunas QRIS
                    @else
                        Sudah bayar
                    @endif
                </span>
            </div>
        </header>

        <div class="kds-ticket-meta">
            @if ($order->order_type)
                <span class="kds-ticket-chip">{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</span>
            @endif
            @if ($order->table)
                <span class="kds-ticket-chip kds-ticket-chip-table">🪑 {{ $order->table->label }}</span>
            @endif
            <span class="kds-ticket-chip">{{ $doneCount }}/{{ $itemCount }} siap</span>
        </div>

        <ul class="kds-ticket-items">
            @foreach ($order->items as $item)
                @php
                    $notes = PosItemNotes::split($item->notes);
                    $qty = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
                @endphp
                <li @class(['kds-ticket-item', 'is-done' => $item->is_delivered]) data-dapur-item data-item-id="{{ $item->id }}">
                    @if ($canChecklist)
                        <button
                            type="button"
                            class="kds-item-toggle"
                            data-dapur-toggle
                            data-url="{{ route('kasir.items.delivered', $item) }}"
                            data-delivered="{{ $item->is_delivered ? '1' : '0' }}"
                            aria-pressed="{{ $item->is_delivered ? 'true' : 'false' }}"
                            aria-label="{{ $item->is_delivered ? 'Batalkan ceklis' : 'Tandai selesai' }}: {{ $item->product?->name ?? 'Item' }}"
                        >
                            <span class="kds-item-check" aria-hidden="true">{{ $item->is_delivered ? '✓' : '' }}</span>
                            <span class="kds-item-qty">{{ $qty }}×</span>
                            <span class="kds-item-body">
                                <span class="kds-item-name">{{ $item->product?->name ?? 'Item' }}</span>
                                @if ($notes['customer'])
                                    <span class="kds-item-note">{{ $notes['customer'] }}</span>
                                @endif
                                @foreach ($notes['addon_labels'] as $addon)
                                    <span class="kds-item-addon">{{ $addon }}</span>
                                @endforeach
                            </span>
                        </button>
                    @else
                        <div class="kds-item-static">
                            <span class="kds-item-qty">{{ $qty }}×</span>
                            <span class="kds-item-body">
                                <span class="kds-item-name">{{ $item->product?->name ?? 'Item' }}</span>
                                @if ($notes['customer'])
                                    <span class="kds-item-note">{{ $notes['customer'] }}</span>
                                @endif
                                @foreach ($notes['addon_labels'] as $addon)
                                    <span class="kds-item-addon">{{ $addon }}</span>
                                @endforeach
                            </span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($canServe)
            <form action="{{ route('kasir.orders.serve', $order) }}" method="POST" class="kds-ticket-foot">
                @csrf
                <button
                    type="submit"
                    class="kds-serve-btn"
                    onclick="return confirm({{ json_encode('Tandai pesanan '.$order->order_number.' selesai / siap antar?') }})"
                >
                    Tandai selesai
                </button>
            </form>
        @endif
    </article>
@empty
    <div class="kds-empty" data-dapur-empty>
        <p class="kds-empty-title">Belum ada pesanan dapur</p>
        <p class="kds-empty-hint">Pesanan muncul di sini setelah open bill atau pembayaran di kasir.</p>
    </div>
@endforelse
