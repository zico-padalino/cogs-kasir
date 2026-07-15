@props(['pendingOrders', 'format', 'currentOrder' => null])

@php
    use App\Enums\PosOrderStatus;
    $pendingTotal = $pendingOrders->sum('total');
    $waitingCount = $pendingOrders->where('status', PosOrderStatus::Submitted)->count();
    $currentOrderId = $currentOrder?->id;
    // Expand only when there are other online orders still needing attention.
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
            {{ $pendingOrders->count() }} pesanan online
            @if ($waitingCount > 0)
                · {{ $waitingCount }} menunggu kasir
            @endif
            · {{ $format::rupiah($pendingTotal) }}
        </span>
        <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
    </button>
    <div class="pos-pending-body" data-pos-pending-body>
        <p class="pos-pending-title">Pesanan online masuk ({{ $pendingOrders->count() }})</p>
        <div class="pos-pending-list">
            @foreach ($pendingOrders as $pending)
                @php
                    $isCurrent = $currentOrderId && (int) $pending->id === (int) $currentOrderId;
                    $actionCols = $isCurrent ? 1 : 2;
                @endphp
                <div @class(['pos-pending-card', 'is-current' => $isCurrent])>
                    <div class="pos-pending-card-head">
                        <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                        <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                        <span class="pos-pending-btn-meta">{{ $pending->order_number }}</span>
                        @if ($isCurrent)
                            <span class="badge badge-blue pos-pending-status">Sedang dibuka</span>
                        @else
                            <span class="badge {{ $pending->status->badgeClass() }} pos-pending-status">{{ $pending->status->label() }}</span>
                        @endif
                    </div>
                    <div
                        class="pos-pending-card-actions"
                        style="--pos-pending-actions: {{ $actionCols }}"
                    >
                        @if ($isCurrent)
                            <form action="{{ route('kasir.orders.cancel', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-delete"
                                    onclick="return confirm('Hapus pesanan online {{ $pending->customer_note ?: $pending->order_number }}? Pesanan akan dibatalkan.')"
                                >
                                    Hapus pesanan
                                </button>
                            </form>
                        @else
                            <form action="{{ route('kasir.load-order', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button type="submit" class="pos-pending-action pos-pending-action-open">
                                    {{ $pending->status === PosOrderStatus::Confirmed ? 'Bayar' : 'Masuk kasir' }}
                                </button>
                            </form>
                            <form action="{{ route('kasir.orders.cancel', $pending) }}" method="POST" class="pos-pending-action-form">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-delete"
                                    onclick="return confirm('Hapus pesanan online {{ $pending->customer_note ?: $pending->order_number }}? Pesanan akan dibatalkan.')"
                                >
                                    Hapus
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
