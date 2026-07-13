@php
    $shopName = \App\Support\ShopSettings::get('shop_name', config('pos.shop_name', 'Point of Sale'));
    $logoUrl = \App\Support\ShopSettings::logoUrl();
    $initial = \App\Support\ShopSettings::initial();
    $sizeClass = $sizeClass ?? 'h-10 w-10';
    $textClass = $textClass ?? 'text-lg';
    $roundedClass = $roundedClass ?? 'rounded-xl';
@endphp
@if ($logoUrl)
    <img
        src="{{ $logoUrl }}"
        alt="{{ $shopName }}"
        class="{{ $sizeClass }} {{ $roundedClass }} shrink-0 object-cover bg-white"
    >
@else
    <div class="{{ $sizeClass }} {{ $roundedClass }} flex shrink-0 items-center justify-center bg-brand-600 font-bold text-white {{ $textClass }}">
        {{ $initial }}
    </div>
@endif
