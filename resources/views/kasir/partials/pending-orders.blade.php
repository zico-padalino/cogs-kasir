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
        <span>
            {{ $pendingOrders->count() }} menunggu
            @if ($onlineWaiting > 0)
                · {{ $onlineWaiting }} online
            @endif
            @if ($openBillCount > 0)
                · {{ $openBillCount }} open bill
            @endif
            @if ($awaitingServeCount > 0)
                · {{ $awaitingServeCount }} siap antar
            @endif
            · {{ $format::rupiah($pendingTotal) }}
        </span>
        <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
    </button>
    <div class="pos-pending-body" data-pos-pending-body>
        <p class="pos-pending-title">Pesanan menunggu ({{ $pendingOrders->count() }})</p>
        <div class="pos-pending-list">
            @foreach ($pendingOrders as $pending)
                @php
                    $isCurrent = $currentOrderId && (int) $pending->id === (int) $currentOrderId;
                    $isOpenBill = $pending->status === PosOrderStatus::Unpaid;
                    $isAwaitingServe = $pending->status === PosOrderStatus::Paid;
                    $canOpen = ! $isCurrent && ! $isAwaitingServe;
                    $actionCols = $isAwaitingServe ? 1 : ($isCurrent ? 1 : 2);
                    $openLabel = match (true) {
                        $isOpenBill => 'Buka / Tambah',
                        $pending->status === PosOrderStatus::Confirmed => 'Bayar',
                        default => 'Masuk kasir',
                    };
                    $deleteLabel = $isOpenBill ? 'Hapus Open Bill' : 'Hapus';
                    $deleteConfirm = $isOpenBill
                        ? 'Hapus Open Bill '.($pending->customer_note ?: $pending->order_number).'?'
                        : 'Hapus pesanan online '.($pending->customer_note ?: $pending->order_number).'? Pesanan akan dibatalkan.';
                    $serveConfirm = 'Konfirmasi pesanan '.($pending->customer_note ?: $pending->order_number).' sudah diantar / selesai?';
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

                    <div
                        class="pos-pending-card-actions"
                        style="--pos-pending-actions: {{ $actionCols }}"
                    >
                        @if ($isAwaitingServe)
                            <form action="{{ route('kasir.orders.serve', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-serve"
                                    onclick="return confirm({{ json_encode($serveConfirm) }})"
                                >
                                    Sudah diantar / selesai
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
                                    {{ $isOpenBill ? 'Hapus Open Bill' : 'Hapus pesanan' }}
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
