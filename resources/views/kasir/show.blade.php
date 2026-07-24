@extends('layouts.kasir')

@section('title', 'Detail Pesanan')
@section('heading', $order->order_number)

@section('content')
    @php
        $canChecklist = $order->canChecklistDelivered();
        $deliveredCount = $order->items->where('is_delivered', true)->count();
        $itemCount = $order->items->count();
    @endphp

    <div class="mb-4 sm:mb-6">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('kasir.index') }}" class="page-back">← Kembali ke POS</a>
            <a href="{{ route('kasir.orders') }}" class="page-back text-slate-500">Riwayat</a>
        </div>
        <h1 class="mt-2 hidden text-2xl font-bold md:block">{{ $order->order_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $order->source->label() }} · {{ $order->table?->label ?? 'Walk-in' }}</p>
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <x-table-card title="Item Pesanan">
            @if ($canChecklist && $itemCount > 0)
                <p class="mb-3 text-xs text-slate-500" data-deliver-progress>
                    Diantar: <span data-deliver-done>{{ $deliveredCount }}</span>/<span data-deliver-total>{{ $itemCount }}</span>
                </p>
            @endif
            <table class="table-default">
                <thead>
                    <tr>
                        @if ($canChecklist)
                            <th class="w-12 text-center">Antar</th>
                        @endif
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr @class(['is-delivered' => $item->is_delivered]) data-order-item-row data-item-id="{{ $item->id }}">
                            @if ($canChecklist)
                                <td class="text-center align-middle">
                                    <label class="pos-deliver-check">
                                        <input
                                            type="checkbox"
                                            class="pos-deliver-checkbox"
                                            data-deliver-toggle
                                            data-url="{{ route('kasir.items.delivered', $item) }}"
                                            @checked($item->is_delivered)
                                            aria-label="Sudah diantar: {{ $item->product->name }}"
                                        >
                                    </label>
                                </td>
                            @endif
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$item->product" class="h-10 w-10 rounded-lg" />
                                    <div>
                                        <p class="font-medium">{{ $item->product->name }}</p>
                                        @if ($item->notes)
                                            <p class="text-xs text-amber-700">Catatan: {{ $item->notes }}</p>
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

    @if ($canChecklist)
        <script>
            (function () {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const progressDone = document.querySelector('[data-deliver-done]');
                const statusBadge = document.querySelector('[data-order-status-badge]');

                function syncProgress() {
                    if (! progressDone) return;
                    const checked = document.querySelectorAll('[data-deliver-toggle]:checked').length;
                    progressDone.textContent = String(checked);
                }

                document.querySelectorAll('[data-deliver-toggle]').forEach((input) => {
                    input.addEventListener('change', async () => {
                        const url = input.getAttribute('data-url');
                        const row = input.closest('[data-order-item-row]');
                        const next = Boolean(input.checked);
                        input.disabled = true;

                        try {
                            const res = await fetch(url, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ is_delivered: next }),
                            });
                            const payload = await res.json().catch(() => ({}));
                            if (! res.ok) {
                                input.checked = ! next;
                                alert(payload.message || 'Gagal menyimpan ceklis.');
                                return;
                            }
                            row?.classList.toggle('is-delivered', next);
                            syncProgress();
                            if (payload.data?.status_label && statusBadge) {
                                statusBadge.textContent = payload.data.status_label;
                            }
                        } catch (e) {
                            input.checked = ! next;
                            alert('Gagal menyimpan ceklis.');
                        } finally {
                            input.disabled = false;
                        }
                    });
                });
            })();
        </script>
    @endif
@endsection
