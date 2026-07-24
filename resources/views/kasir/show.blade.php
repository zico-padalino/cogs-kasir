@extends('layouts.kasir')

@section('title', 'Detail Pesanan')
@section('heading', $order->order_number)

@section('content')
    @php
        $canChecklist = $order->canChecklistDelivered();
        $deliveredCount = $order->items->where('is_delivered', true)->count();
        $itemCount = $order->items->count();
        $deliverItems = $canChecklist && $itemCount > 0
            ? $order->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => $item->product?->name ?? 'Item',
                'qty' => (float) $item->quantity,
                'is_delivered' => (bool) $item->is_delivered,
                'url' => route('kasir.items.delivered', $item),
            ])->values()->all()
            : [];
    @endphp

    <div class="mb-4 sm:mb-6">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('kasir.index') }}" class="page-back page-back-primary">← Kembali ke POS</a>
            <a href="{{ route('kasir.orders') }}" class="page-back page-back-secondary">Riwayat</a>
        </div>
        <h1 class="mt-2 text-xl font-bold sm:text-2xl md:block">{{ $order->order_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $order->source->label() }} · {{ $order->table?->label ?? 'Walk-in' }}</p>
        @if ($order->customer_note)
            <p class="mt-0.5 text-sm font-medium text-slate-700">{{ $order->customer_note }}</p>
        @endif
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <x-table-card title="Item Pesanan">
            @if ($canChecklist && $itemCount > 0)
                <button
                    type="button"
                    class="pos-deliver-open-btn mb-3"
                    data-deliver-open
                    data-deliver-title="{{ $order->customer_note ?: $order->order_number }}"
                >
                    <span hidden data-deliver-payload>@json($deliverItems)</span>
                    <span class="pos-deliver-open-btn-label">Ceklis antar</span>
                    <span class="pos-deliver-open-btn-progress" data-deliver-progress>
                        <span data-deliver-done>{{ $deliveredCount }}</span>/<span data-deliver-total>{{ $itemCount }}</span>
                    </span>
                </button>
                <p class="mb-3 text-xs text-slate-500">Tandai item yang sudah diantar. Progress: {{ $deliveredCount }}/{{ $itemCount }}</p>
            @endif

            {{-- Mobile: kartu item --}}
            <div class="order-item-cards md:hidden">
                @foreach ($order->items as $item)
                    <article @class(['order-item-card', 'is-delivered' => $item->is_delivered]) data-order-item-row data-item-id="{{ $item->id }}">
                        <div class="order-item-card-main">
                            <x-product-image :product="$item->product" class="h-12 w-12 rounded-lg" />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-900">{{ $item->product->name }}</p>
                                @if ($item->notes)
                                    <p class="text-xs text-amber-700">Catatan: {{ $item->notes }}</p>
                                @endif
                                <p class="mt-0.5 text-sm text-slate-600">
                                    {{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}
                                </p>
                            </div>
                            <p class="shrink-0 text-sm font-bold text-slate-900">{{ $format::rupiah($item->line_total) }}</p>
                        </div>
                        @if ($item->is_delivered)
                            <p class="order-item-card-status">✓ Sudah diantar</p>
                        @elseif ($canChecklist)
                            <p class="order-item-card-status is-pending">Belum diantar</p>
                        @endif
                    </article>
                @endforeach
            </div>

            {{-- Desktop: tabel --}}
            <div class="hidden md:block">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr @class(['is-delivered' => $item->is_delivered]) data-order-item-row data-item-id="{{ $item->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-product-image :product="$item->product" class="h-10 w-10 rounded-lg" />
                                        <div>
                                            <p class="font-medium">{{ $item->product->name }}</p>
                                            @if ($item->notes)
                                                <p class="text-xs text-amber-700">Catatan: {{ $item->notes }}</p>
                                            @endif
                                            @if ($item->is_delivered)
                                                <p class="text-xs font-medium text-emerald-700">✓ Sudah diantar</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $format::number($item->quantity, 0) }}</td>
                                <td>{{ $format::rupiah($item->unit_price) }}</td>
                                <td class="cell-money">{{ $format::rupiah($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="w-full space-y-1">
                    @if ($order->hasDiscount())
                        <p class="text-sm text-slate-500">
                            Harga normal: <span class="line-through">{{ $format::rupiah($order->subtotal) }}</span>
                        </p>
                        <p class="text-sm font-medium text-amber-700">
                            Diskon: -{{ $format::rupiah($order->discount_amount) }}
                        </p>
                    @endif
                    <p class="text-lg font-bold">Total bayar: {{ $format::rupiah($order->total) }}</p>
                </div>
                @if (in_array($order->status->value, ['paid', 'served'], true))
                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-primary w-full sm:w-auto">Lihat Struk</a>
                @endif
            </x-slot:footer>
        </x-table-card>

        <div class="card">
            <h2 class="font-semibold">Info Pembayaran</h2>
            <dl class="detail-meta mt-4">
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Status</dt>
                    <dd><span class="badge {{ $order->status->badgeClass() }}" data-order-status-badge>{{ $order->status->label() }}</span></dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Metode</dt>
                    <dd>{{ $order->payment_method?->label() ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Kasir</dt>
                    <dd>{{ $order->cashierDisplayName() }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Dibayar</dt>
                    <dd>{{ $order->paid_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @include('kasir.partials.deliver-modal')
@endsection
