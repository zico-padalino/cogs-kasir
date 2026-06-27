<div class="table-card xl:sticky xl:top-24">
    <div class="table-card-header">
        <div>
            <h2 class="text-base font-semibold">Keranjang</h2>
            <p class="text-xs text-slate-500">{{ $order->items->count() }} item</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
    </div>

    @if ($order->items->isNotEmpty())
        <div class="divide-y divide-slate-100">
            @foreach ($order->items as $item)
                <div class="kasir-cart-item flex items-center gap-2 px-4 py-3 sm:gap-3" data-kasir-item>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">{{ $item->product->name }}</p>
                        <p class="text-xs text-slate-500">{{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-semibold sm:text-base">{{ $format::rupiah($item->line_total) }}</p>
                    <form action="{{ route('kasir.items.destroy', $item) }}" method="POST" class="shrink-0">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm min-h-10 min-w-10 px-0" aria-label="Hapus {{ $item->product->name }}">×</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">
            <div class="mb-4 flex items-center justify-between text-lg font-bold">
                <span>Total</span>
                <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
            </div>

            <form action="{{ route('kasir.pay') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="form-label">Metode Bayar</label>
                    <select name="payment_method" class="form-input" required>
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full py-3 text-base" onclick="return confirm('Proses pembayaran? Stok akan berkurang otomatis.')">
                    Bayar & Cetak Struk
                </button>
            </form>
        </div>
    @else
        <div class="empty-state">
            <p>Keranjang kosong</p>
            <p class="empty-hint">Klik produk untuk menambah item</p>
        </div>
    @endif
</div>
