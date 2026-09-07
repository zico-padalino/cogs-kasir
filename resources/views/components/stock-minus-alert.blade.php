@props([
    'items',
    'title' => null,
    'hint' => 'Stok minus karena pemakaian/penjualan melebihi sisa. Isi ulang supaya modal & stok akurat.',
    'actionLabel' => 'Isi ulang',
    'limit' => 8,
    'anchorPrefix' => 'material-',
])

@php
    $resolveQty = static function ($item): float {
        if (is_array($item)) {
            return (float) ($item['available_qty'] ?? $item['qty'] ?? 0);
        }

        if (isset($item->available_qty)) {
            return (float) $item->available_qty;
        }

        if (is_object($item) && method_exists($item, 'availableQuantity')) {
            return (float) $item->availableQuantity();
        }

        return (float) data_get($item, 'qty', 0);
    };

    $list = collect($items)
        ->filter(fn ($item) => $resolveQty($item) < 0)
        ->sortBy(fn ($item) => $resolveQty($item))
        ->values();
    $count = $list->count();
    $heading = $title ?: ($count.' bahan stoknya minus');
@endphp

@if ($count > 0)
    <div
        {{ $attributes->class('stock-minus-alert') }}
        role="alert"
        data-stock-minus-alert
    >
        <div class="stock-minus-alert__head">
            <div class="stock-minus-alert__title-wrap">
                <span class="stock-minus-alert__icon" aria-hidden="true">!</span>
                <div class="min-w-0">
                    <p class="stock-minus-alert__title">
                        {{ $heading }}
                        <span class="stock-minus-alert__badge">{{ $count }}</span>
                    </p>
                    <p class="stock-minus-alert__hint">{{ $hint }}</p>
                </div>
            </div>
            <a href="#daftar-bahan" class="stock-minus-alert__jump">Ke daftar ↓</a>
        </div>

        <ul class="stock-minus-alert__list">
            @foreach ($list->take($limit) as $item)
                @php
                    $id = (int) data_get($item, 'id');
                    $name = (string) data_get($item, 'name', 'Bahan');
                    $unit = (string) data_get($item, 'unit', '');
                    $qty = $resolveQty($item);
                    $need = abs($qty);
                @endphp
                <li class="stock-minus-alert__item">
                    <div class="stock-minus-alert__meta">
                        <p class="stock-minus-alert__name">{{ $name }}</p>
                        <p class="stock-minus-alert__stats">
                            <span class="stock-minus-alert__qty">{{ $format::number($qty) }} {{ $unit }}</span>
                            <span class="stock-minus-alert__need">Perlu isi ≥ {{ $format::number($need) }} {{ $unit }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="stock-minus-alert__cta"
                        data-stock-minus-restock="{{ $id }}"
                        data-stock-minus-anchor="{{ $anchorPrefix.$id }}"
                    >
                        {{ $actionLabel }}
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($count > $limit)
            <p class="stock-minus-alert__more">
                +{{ $count - $limit }} bahan lain masih minus — cari di daftar di bawah.
            </p>
        @endif
    </div>
@endif
