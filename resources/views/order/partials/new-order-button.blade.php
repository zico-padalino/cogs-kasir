@props([
    'label' => 'Buat pesanan baru',
    'hint' => 'Pesanan sebelumnya tetap diproses kasir. Tombol ini membuka keranjang kosong untuk pesan lagi.',
    'confirm' => 'Buat pesanan baru? Pesanan yang sudah dikirim tetap berjalan di kasir.',
])

<form action="{{ route('order.menu.new') }}" method="POST" class="order-new-order-wrap">
    @csrf
    @if ($hint)
        <p class="order-new-order-hint">{{ $hint }}</p>
    @endif
    <button
        type="submit"
        class="btn-secondary order-new-order-btn w-full"
        onclick="return confirm({{ json_encode($confirm) }})"
    >
        {{ $label }}
    </button>
</form>
