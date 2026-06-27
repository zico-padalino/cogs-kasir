@extends('layouts.order-table')

@section('title', $table->label)

@section('content')
    <div class="order-table-shell">
        <header class="order-table-header">
            <div class="order-table-header-badge">🍽️</div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Menu Meja</p>
                <h1 class="truncate text-xl font-bold text-slate-900">{{ $table->label }}</h1>
                <p class="text-xs text-slate-500">Meja #{{ $table->table_number }} · Pesan dari HP Anda</p>
            </div>
        </header>

        <main class="order-table-main">
            @if (session('success'))
                <div class="order-alert order-alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="order-alert order-alert-error">{{ session('error') }}</div>
            @endif

            @if ($order->status->value === 'submitted')
                <div class="order-status-card order-status-waiting">
                    <div class="order-status-icon">💳</div>
                    <h2 class="text-lg font-bold text-slate-900">Silakan Bayar di Kasir</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Pesanan Anda sudah masuk ke sistem kasir. Datang ke kasir dan sebutkan nomor pesanan di bawah ini.
                    </p>
                    <div class="order-status-meta">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Nomor Pesanan</p>
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Meja</p>
                            <p class="text-sm font-semibold">{{ $table->label }}</p>
                        </div>
                    </div>
                </div>

                @include('order.partials.order-summary', ['order' => $order, 'format' => $format])

                <div class="order-info-box">
                    <p class="font-semibold text-amber-900">Menunggu pembayaran</p>
                    <p class="mt-1 text-sm text-amber-800">Kasir akan memproses pesanan ini. Stok akan berkurang setelah pembayaran di kasir.</p>
                </div>
            @elseif ($order->status->value === 'paid')
                <div class="order-status-card order-status-paid">
                    <div class="order-status-icon">✅</div>
                    <h2 class="text-lg font-bold text-green-900">Pesanan Lunas</h2>
                    <p class="mt-2 text-sm text-green-800">Terima kasih! Pembayaran sudah diterima di kasir.</p>
                    <p class="mt-3 font-mono text-xs text-green-700">{{ $order->order_number }}</p>
                </div>

                @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
            @else
                <section class="order-section">
                    <div class="order-section-head">
                        <h2 class="order-section-title">Menu</h2>
                        <p class="order-section-sub">Tap + untuk menambah ke pesanan</p>
                    </div>

                    <div class="order-menu-list">
                        @forelse ($products as $product)
                            <form action="{{ route('order.table.items', $table->barcode_token) }}" method="POST" class="order-menu-item">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                    <p class="mt-0.5 text-sm font-bold text-brand-600">
                                        {{ $product->selling_price > 0 ? $format::rupiah($product->selling_price) : $format::rupiah($product->standard_cost) }}
                                    </p>
                                </div>
                                <div class="order-menu-actions">
                                    <label class="sr-only" for="qty-{{ $product->id }}">Jumlah</label>
                                    <input id="qty-{{ $product->id }}" type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) $product->availableQuantity()) }}" class="order-qty-input" inputmode="numeric">
                                    <button type="submit" class="btn-primary order-add-btn" aria-label="Tambah {{ $product->name }}">+</button>
                                </div>
                            </form>
                        @empty
                            <div class="order-empty">
                                <p>Menu belum tersedia.</p>
                                <p class="order-empty-hint">Hubungi staf atau pesan langsung di kasir.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                @if ($order->items->isNotEmpty())
                    <section class="order-section">
                        @include('order.partials.order-summary', ['order' => $order, 'format' => $format, 'editable' => true, 'table' => $table])
                    </section>

                    <form action="{{ route('order.table.submit', $table->barcode_token) }}" method="POST" class="order-submit-wrap">
                        @csrf
                        <button type="submit" class="btn-primary order-submit-btn" onclick="return confirm('Kirim pesanan ke kasir? Setelah dikirim, bayar di kasir.')">
                            Kirim Pesanan · Bayar di Kasir
                        </button>
                    </form>
                @endif
            @endif
        </main>

        <footer class="order-table-footer">
            <p>Hanya untuk {{ $table->label }} · Scan ulang QR meja jika pindah tempat duduk</p>
        </footer>
    </div>
@endsection
