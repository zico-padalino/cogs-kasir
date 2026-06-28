@props(['product', 'class' => '', 'eager' => false])

<img
    src="{{ $product->imageUrl() }}"
    alt="{{ $product->name }}"
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    {{ $attributes->merge(['class' => 'product-image '.$class]) }}
>
