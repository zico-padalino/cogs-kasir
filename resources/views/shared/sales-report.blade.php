@props([
    'filterAction',
    'pdfUrl' => null,
    'showOrderActions' => false,
    'showOrderList' => true,
])

<div class="page-toolbar">
    <p class="text-sm text-slate-500">
        {{ $periodLabel }} · {{ $rangeLabel }}
        · {{ $count }} transaksi
    </p>
    @if ($pdfUrl)
        <a href="{{ $pdfUrl }}" target="_blank" class="btn-outline btn-sm shrink-0">
            Cetak PDF
        </a>
    @endif
</div>

<form method="GET" action="{{ $filterAction }}" class="card mb-4 p-4" data-pembukuan-filter>
    <div>
        <label class="form-label" for="pembukuan-period">Periode</label>
        <select id="pembukuan-period" name="period" class="form-input w-full" data-pembukuan-period>
            <option value="day" @selected($period === 'day')>Harian</option>
            <option value="week" @selected($period === 'week')>Mingguan</option>
            <option value="month" @selected($period === 'month')>Bulanan</option>
        </select>
    </div>

    <div class="mt-4" data-pembukuan-field="day" @if($period !== 'day') hidden @endif>
        <label class="form-label" for="pembukuan-date">Tanggal</label>
        <input
            id="pembukuan-date"
            type="date"
            name="date"
            value="{{ $filters['date'] }}"
            class="form-input w-full"
        >
    </div>

    <div class="mt-4" data-pembukuan-field="week" @if($period !== 'week') hidden @endif>
        <label class="form-label" for="pembukuan-week">Minggu</label>
        <input
            id="pembukuan-week"
            type="week"
            name="week"
            value="{{ $filters['week'] }}"
            class="form-input w-full"
        >
        <p class="mt-1.5 text-xs text-slate-500">Senin–Minggu sesuai kalender ISO.</p>
    </div>

    <div class="mt-4" data-pembukuan-field="month" @if($period !== 'month') hidden @endif>
        <label class="form-label" for="pembukuan-month">Bulan</label>
        <input
            id="pembukuan-month"
            type="month"
            name="month"
            value="{{ $filters['month'] }}"
            class="form-input w-full"
        >
    </div>

    <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn-primary w-full justify-center">
            Tampilkan
        </button>
        @if ($period !== 'day' || ! $rangeStart->isToday())
            <a
                href="{{ $filterAction }}"
                class="btn-outline w-full justify-center"
                style="margin-top: 0.75rem; display: inline-flex;"
            >
                Periode ini
            </a>
        @endif
    </div>
</form>

<div class="pembukuan-stats mb-4">
    <x-stat-card label="Omzet" :value="$format::rupiah($omzet)" color="green" />
    <x-stat-card label="Transaksi" :value="$format::number($count, 0)" color="brand" />
    <x-stat-card label="Rata-rata" :value="$format::rupiah($average)" color="slate" />
</div>

@if ($byDay->isNotEmpty())
    <div class="card mb-4 p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Ringkasan per hari</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $rangeLabel }}</p>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($byDay as $day)
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ $day['date']->translatedFormat('l, d M') }}</p>
                        <p class="text-xs text-slate-500">{{ $day['count'] }} transaksi</p>
                    </div>
                    <p class="text-sm font-semibold text-slate-900">{{ $format::rupiah($day['total']) }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="card mb-4 p-0 overflow-hidden">
    <div class="border-b border-slate-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-900">Metode bayar</h2>
    </div>
    <div class="divide-y divide-slate-100">
        @foreach ($byPayment as $payment)
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-slate-800">{{ $payment['label'] }}</p>
                    <p class="text-xs text-slate-500">{{ $payment['count'] }} transaksi</p>
                </div>
                <p class="text-sm font-semibold text-slate-900">{{ $format::rupiah($payment['total']) }}</p>
            </div>
        @endforeach
    </div>
</div>

@if ($showOrderList ?? true)
    <div class="card p-0 overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Pesanan lunas</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ $rangeLabel }}</p>
        </div>

        @forelse ($orders as $order)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">{{ $format::rupiah($order->total) }}</p>
                    <p class="mt-0.5 font-mono text-xs text-slate-600">{{ $order->order_number }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $order->paid_at?->format($period === 'day' ? 'H:i' : 'd/m H:i') ?? '-' }}
                        · {{ $order->payment_method?->label() ?? '-' }}
                        · {{ $order->source->label() }}
                        @if ($order->table)
                            · {{ $order->table->label }}
                        @endif
                    </p>
                </div>
                @if ($showOrderActions)
                    <div class="flex shrink-0 flex-col gap-1">
                        <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                        <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                    </div>
                @endif
            </div>
        @empty
            <div class="empty-state px-4 py-8">
                <p>Belum ada penjualan lunas.</p>
                <p class="empty-hint">Pilih periode lain atau selesaikan pembayaran di POS.</p>
            </div>
        @endforelse
    </div>
@endif

<script>
    (function () {
        var form = document.querySelector('[data-pembukuan-filter]');
        if (!form) {
            return;
        }

        var periodSelect = form.querySelector('[data-pembukuan-period]');
        var fields = form.querySelectorAll('[data-pembukuan-field]');

        function syncFields() {
            var period = periodSelect.value;
            fields.forEach(function (field) {
                var show = field.getAttribute('data-pembukuan-field') === period;
                field.hidden = !show;
                field.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !show;
                });
            });
        }

        periodSelect.addEventListener('change', syncFields);
        syncFields();
    })();
</script>
