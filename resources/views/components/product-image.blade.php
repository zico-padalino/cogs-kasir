@props(['product', 'class' => ''])

<img
    src="{{ $product->imageUrl() }}"
    alt="{{ $product->name }}"
    loading="lazy"
    decoding="async"
    {{ $attributes->merge(['class' => 'product-image '.$class]) }}
>
