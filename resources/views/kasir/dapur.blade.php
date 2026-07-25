@extends('layouts.dapur')

@section('title', 'Dapur')

@section('content')
    <div class="kds-shell">
        <header class="kds-topbar">
            <div class="kds-topbar-brand">
                @include('layouts.partials.shop-brand-mark')
                <div class="min-w-0">
                    <p class="kds-topbar-title">Layar Dapur</p>
                    <p class="kds-topbar-shop truncate">{{ $shopName }}</p>
                </div>
            </div>

            <div class="kds-topbar-meta">
                <span class="kds-count" data-dapur-count>
                    {{ $kitchenOrders->count() }} pesanan
                </span>
                <span class="kds-clock" data-dapur-clock aria-live="polite"></span>
                <a href="{{ route('kasir.index') }}" class="kds-link-kasir">Ke Kasir</a>
            </div>
        </header>

        <div class="kds-board" data-dapur-board-list>
            @include('kasir.partials.dapur-tickets', [
                'kitchenOrders' => $kitchenOrders,
                'format' => $format,
            ])
        </div>
    </div>
@endsection
