@props([
    'qris' => null,
    'amountLabel' => null,
    'compact' => false,
])

@php
    $qris = is_array($qris) ? $qris : [];
    $enabled = (bool) ($qris['enabled'] ?? false);
    $src = $enabled
        ? ($qris['qr_data_uri'] ?? null)
        : ($qris['fallback_image_url'] ?? \App\Support\ShopSettings::qrisUrl());
    $amountLabel = $amountLabel ?? ($qris['amount_label'] ?? null);
    $mode = $qris['mode'] ?? ($enabled ? 'dynamic' : 'static');
@endphp

<div
    class="qris-dynamic {{ $compact ? 'qris-dynamic--compact' : '' }}"
    data-qris-dynamic
    data-qris-mode="{{ $mode }}"
    @if (! empty($qris['amount'])) data-qris-amount="{{ (int) $qris['amount'] }}" @endif
>
    <div class="qris-dynamic-frame">
        <img
            src="{{ $src }}"
            alt="QRIS pembayaran"
            class="qris-dynamic-image"
            data-qris-image
        >
    </div>
    @if ($amountLabel)
        <p class="qris-dynamic-amount" data-qris-amount-label>{{ $amountLabel }}</p>
    @endif
    <p class="qris-dynamic-hint" data-qris-hint>
        @if ($enabled)
            Scan QRIS — nominal sudah terisi otomatis.
        @else
            Scan QRIS lalu masukkan nominal manual (belum ada payload dinamis).
        @endif
    </p>
</div>
