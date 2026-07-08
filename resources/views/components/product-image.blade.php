@props(['product', 'class' => '', 'eager' => false, 'decorative' => false])

<img
    src="{{ $product->imageUrl() }}"
    alt="{{ $decorative ? '' : $product->name }}"
    @if ($decorative) aria-hidden="true" @endif
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    decoding="async"
    {{ $attributes->merge(['class' => 'product-image '.$class]) }}
>
