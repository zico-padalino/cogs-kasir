@props(['pendingOrders', 'format'])

@php
    $pendingTotal = $pendingOrders->sum('total');
@endphp

<div class="pos-pending" data-pos-pending>
    <button type="button" class="pos-pending-toggle lg:hidden" data-pos-pending-toggle aria-expanded="false">
        <span>{{ $pendingOrders->count() }} pesanan menunggu bayar · {{ $format::rupiah($pendingTotal) }}</span>
        <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
    </button>
    <div class="pos-pending-body" data-pos-pending-body>
        <p class="pos-pending-title">Pesanan online menunggu bayar ({{ $pendingOrders->count() }})</p>
        <div class="pos-pending-list">
            @foreach ($pendingOrders as $pending)
                <form action="{{ route('kasir.load-order', $pending) }}" method="POST">
                    @csrf
                    <button type="submit" class="pos-pending-btn">
                        <span class="pos-pending-btn-main">
                            <span class="pos-pending-btn-name">{{ $pending->customer_note ?: 'Tanpa nama' }}</span>
                            <span class="pos-pending-btn-meta">{{ $pending->table?->label }} · {{ $pending->order_number }}</span>
                        </span>
                        <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</div>
