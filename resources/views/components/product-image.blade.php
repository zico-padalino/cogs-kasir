@props(['product', 'class' => '', 'eager' => false, 'decorative' => false])

@php
    $fallbackSrc = asset('images/products/default-food.svg');
@endphp

<img
    src="{{ $product->imageUrl() }}"
    alt="{{ $decorative ? '' : $product->name }}"
    @if ($decorative) aria-hidden="true" @endif
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    data-fallback-src="{{ $fallbackSrc }}"
    onerror="if(this.dataset.fallbackSrc&&this.src!==this.dataset.fallbackSrc){this.src=this.dataset.fallbackSrc}else{this.onerror=null}"
    {{ $attributes->merge(['class' => 'product-image '.$class]) }}
>
