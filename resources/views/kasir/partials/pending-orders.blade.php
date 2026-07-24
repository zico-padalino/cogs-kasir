@props(['pendingOrders', 'format', 'currentOrder' => null])

@php
    use App\Enums\PosOrderStatus;
    $pendingTotal = $pendingOrders->sum('total');
    $onlineWaiting = $pendingOrders->where('status', PosOrderStatus::Submitted)->count();
    $openBillCount = $pendingOrders->where('status', PosOrderStatus::Unpaid)->count();
    $awaitingServeCount = $pendingOrders->where('status', PosOrderStatus::Paid)->count();
    $currentOrderId = $currentOrder?->id;
    // Expand only when there are other orders still needing attention.
    $hasActionable = $pendingOrders->contains(
        fn ($pending) => ! $currentOrderId || (int) $pending->id !== (int) $currentOrderId
    );
    $defaultExpanded = $hasActionable;
@endphp

<div @class(['pos-pending', 'is-expanded' => $defaultExpanded]) data-pos-pending>
    <button
        type="button"
        class="pos-pending-toggle"
        data-pos-pending-toggle
        aria-expanded="{{ $defaultExpanded ? 'true' : 'false' }}"
    >
        <span class="pos-pending-toggle-main">
            <strong>{{ $pendingOrders->count() }} perlu ditangani</strong>
            <span class="pos-pending-toggle-total">{{ $format::rupiah($pendingTotal) }}</span>
        </span>
        <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
    </button>
    <div class="pos-pending-body" data-pos-pending-body>
        <div class="pos-pending-chips" aria-label="Ringkasan jenis">
            @if ($onlineWaiting > 0)
                <span class="pos-pending-chip">{{ $onlineWaiting }} online</span>
            @endif
            @if ($openBillCount > 0)
                <span class="pos-pending-chip">{{ $openBillCount }} tagihan terbuka</span>
            @endif
            @if ($awaitingServeCount > 0)
                <span class="pos-pending-chip">{{ $awaitingServeCount }} siap antar</span>
            @endif
        </div>
        <p class="pos-pending-title">Antrian pesanan ({{ $pendingOrders->count() }})</p>
        <div class="pos-pending-list">
            @foreach ($pendingOrders as $pending)
                @php
                    $isCurrent = $currentOrderId && (int) $pending->id === (int) $currentOrderId;
                    $isOpenBill = $pending->status === PosOrderStatus::Unpaid;
                    $isAwaitingServe = $pending->status === PosOrderStatus::Paid;
                    $canOpen = ! $isCurrent && ! $isAwaitingServe;
                    $actionCols = $isAwaitingServe ? 1 : ($isCurrent ? 1 : 2);
                    $openLabel = match (true) {
                        $isOpenBill => 'Lanjut isi',
                        $pending->status === PosOrderStatus::Confirmed => 'Buka di kasir',
                        default => 'Masuk kasir',
                    };
                    $deleteLabel = $isOpenBill ? 'Hapus tagihan' : 'Hapus';
                    $deleteConfirm = $isOpenBill
                        ? 'Hapus tagihan terbuka '.($pending->customer_note ?: $pending->order_number).'?'
                        : 'Hapus pesanan online '.($pending->customer_note ?: $pending->order_number).'? Pesanan akan dibatalkan.';
                    $serveConfirm = 'Tandai pesanan '.($pending->customer_note ?: $pending->order_number).' sudah selesai diantar?';
                    $itemCount = $pending->items->count();
                    $deliveredCount = $pending->items->where('is_delivered', true)->count();
                    $showDeliverProgress = $itemCount > 0 && ($isOpenBill || $isAwaitingServe);
                    $canChecklist = $pending->canChecklistDelivered() && $itemCount > 0;
                    $deliverItems = $canChecklist
                        ? $pending->items->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->product?->name ?? 'Item',
                            'qty' => (float) $item->quantity,
                            'is_delivered' => (bool) $item->is_delivered,
                            'url' => route('kasir.items.delivered', $item),
                        ])->values()
                        : collect();
                @endphp
                <div @class([
                    'pos-pending-card',
                    'is-current' => $isCurrent,
                    'is-open-bill' => $isOpenBill,
                    'is-awaiting-serve' => $isAwaitingServe,
                    'is-clickable' => $canOpen || $isAwaitingServe,
                ])>
                    @if ($canOpen)
                        <form action="{{ route('kasir.load-order', $pending) }}" method="POST" class="pos-pending-card-hit-form">
                            @csrf
                            <button type="submit" class="pos-pending-card-hit" aria-label="{{ $openLabel }}: {{ $pending->customer_note ?: $pending->order_number }}">
                                <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                                <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                                <span class="pos-pending-btn-meta">
                                    {{ $pending->order_number }}
                                    @if ($pending->table)
                                        · {{ $pending->table->label }}
                                    @endif
                                </span>
                                <span class="badge {{ $pending->status->badgeClass() }} pos-pending-status">{{ $pending->status->label() }}</span>
                            </button>
                        </form>
                    @elseif ($isAwaitingServe)
                        <a
                            href="{{ route('kasir.orders.show', $pending) }}"
                            class="pos-pending-card-hit"
                            aria-label="Lihat detail pesanan {{ $pending->customer_note ?: $pending->order_number }}"
                        >
                            <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                            <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                            <span class="pos-pending-btn-meta">
                                {{ $pending->order_number }}
                                @if ($pending->table)
                                    · {{ $pending->table->label }}
                                @endif
                            </span>
                            <span class="badge {{ $pending->status->badgeClass() }} pos-pending-status">{{ $pending->status->label() }}</span>
                        </a>
                    @else
                        <div class="pos-pending-card-head">
                            <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                            <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                            <span class="pos-pending-btn-meta">
                                {{ $pending->order_number }}
                                @if ($pending->table)
                                    · {{ $pending->table->label }}
                                @endif
                            </span>
                            <span class="badge badge-blue pos-pending-status">Sedang dibuka</span>
                        </div>
                    @endif

                    @if ($showDeliverProgress)
                        <p class="pos-pending-deliver">
                            Diantar {{ $deliveredCount }}/{{ $itemCount }}
                        </p>
                    @endif

                    <div
                        class="pos-pending-card-actions"
                        style="--pos-pending-actions: {{ $actionCols + ($canChecklist ? 1 : 0) }}"
                    >
                        @if ($canChecklist)
                            <button
                                type="button"
                                class="pos-pending-action pos-pending-action-deliver"
                                data-deliver-open
                                data-deliver-title="{{ $pending->customer_note ?: $pending->order_number }}"
                                data-deliver-items="{{ e(json_encode($deliverItems)) }}"
                            >
                                Ceklis antar
                            </button>
                        @endif
                        @if ($isAwaitingServe)
                            <form action="{{ route('kasir.orders.serve', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-serve"
                                    onclick="return confirm({{ json_encode($serveConfirm) }})"
                                >
                                    Tandai selesai
                                </button>
                            </form>
                        @elseif ($isCurrent)
                            <form action="{{ route('kasir.orders.cancel', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-delete"
                                    onclick="return confirm({{ json_encode($deleteConfirm) }})"
                                >
                                    {{ $isOpenBill ? 'Hapus tagihan' : 'Hapus pesanan' }}
                                </button>
                            </form>
                        @else
                            <form action="{{ route('kasir.load-order', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button type="submit" class="pos-pending-action pos-pending-action-open">
                                    {{ $openLabel }}
                                </button>
                            </form>
                            <form action="{{ route('kasir.orders.cancel', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-delete"
                                    onclick="return confirm({{ json_encode($deleteConfirm) }})"
                                >
                                    {{ $deleteLabel }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
