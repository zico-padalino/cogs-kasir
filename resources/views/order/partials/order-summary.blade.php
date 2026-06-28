@props(['editable' => false, 'table' => null])

<div class="order-cart-card" data-order-cart>
    <div class="order-cart-head">
        <div>
            <h2 class="font-semibold text-slate-900">Pesanan Anda</h2>
            <p class="text-xs text-slate-500">{{ $order->order_number }}</p>
        </div>
        <span class="badge badge-slate">{{ $order->items->count() }} item</span>
    </div>

    <div class="order-cart-items">
        @forelse ($order->items as $item)
            <div class="order-cart-row">
                <x-product-image :product="$item->product" class="order-cart-thumb" />

                <div class="order-cart-row-body">
                    <div class="order-cart-row-top">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">{{ $item->product->name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}
                            </p>
                        </div>
                        <p class="shrink-0 font-semibold text-slate-900">{{ $format::rupiah($item->line_total) }}</p>
                    </div>

                    @if ($editable && $table)
                        <form
                            action="{{ route('order.table.items.update', [$table->barcode_token, $item]) }}"
                            method="POST"
                            class="order-item-note-form"
                        >
                            @csrf
                            @method('PATCH')
                            <label class="order-item-note-label" for="notes-{{ $item->id }}">Catatan item</label>
                            <textarea
                                id="notes-{{ $item->id }}"
                                name="notes"
                                rows="2"
                                maxlength="255"
                                class="order-item-note-input"
                                placeholder="Contoh: tanpa gula, extra pedas, bungkus terpisah..."
                            >{{ old('notes', $item->notes) }}</textarea>
                            <div class="order-item-note-actions">
                                <button type="submit" class="order-item-note-save">Simpan catatan</button>
                            </div>
                        </form>

                        <form action="{{ route('order.table.items.destroy', [$table->barcode_token, $item]) }}" method="POST" class="order-item-remove-form">
                            @csrf @method('DELETE')
                            <button type="submit" class="order-item-remove">Hapus item</button>
                        </form>
                    @elseif ($item->notes)
                        <p class="order-item-note-display">
                            <span class="order-item-note-label">Catatan:</span> {{ $item->notes }}
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <div class="order-cart-empty">
                <p>Belum ada item dipilih</p>
                <p class="order-cart-empty-hint">Pilih menu lalu tambahkan ke pesanan</p>
            </div>
        @endforelse
    </div>

    @if ($order->items->isNotEmpty())
        <div class="order-cart-total">
            <span>Total</span>
            <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
        </div>
    @endif
</div>
