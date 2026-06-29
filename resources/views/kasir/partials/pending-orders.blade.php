@props(['pendingOrders', 'format'])

@php
    use App\Enums\PosOrderStatus;
    $pendingTotal = $pendingOrders->sum('total');
    $waitingCount = $pendingOrders->where('status', PosOrderStatus::Submitted)->count();
@endphp

<div class="pos-pending" data-pos-pending>
    <button type="button" class="pos-pending-toggle lg:hidden" data-pos-pending-toggle aria-expanded="false">
        <span>
            {{ $pendingOrders->count() }} pesanan online
            @if ($waitingCount > 0)
                · {{ $waitingCount }} perlu konfirmasi
            @endif
            · {{ $format::rupiah($pendingTotal) }}
        </span>
        <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
    </button>
    <div class="pos-pending-body" data-pos-pending-body>
        <p class="pos-pending-title">Pesanan online masuk ({{ $pendingOrders->count() }})</p>
        <div class="pos-pending-list">
            @foreach ($pendingOrders as $pending)
                <div class="pos-pending-card">
                    <div class="pos-pending-card-head">
                        <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                        <span class="pos-pending-btn-meta">{{ $pending->order_number }}</span>
                        <span class="badge {{ $pending->status->badgeClass() }} pos-pending-status">{{ $pending->status->label() }}</span>
                        <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                    </div>
                    <div class="pos-pending-card-actions">
                        @if ($pending->status === PosOrderStatus::Submitted)
                            <form action="{{ route('kasir.orders.confirm', $pending) }}" method="POST">
                                @csrf
                                <button
                                    type="submit"
                                    class="pos-pending-action pos-pending-action-confirm"
                                    onclick="return confirm('Konfirmasi pesanan {{ $pending->customer_note ?: $pending->order_number }} sudah selesai?')"
                                >
                                    Konfirmasi
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('kasir.load-order', $pending) }}" method="POST">
                            @csrf
                            <button type="submit" class="pos-pending-action pos-pending-action-open">
                                {{ $pending->status === PosOrderStatus::Confirmed ? 'Bayar' : 'Buka' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
