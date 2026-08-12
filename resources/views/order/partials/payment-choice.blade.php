@props(['order', 'format'])

@php
    $qrisPay = app(\App\Services\QrisDynamicService::class)->forAmount($order->total);
    $savedQr = null;
    if ($qrisPay['enabled'] ?? false) {
        $savedQr = app(\App\Services\QrisDynamicService::class)->persistDynamicImage(
            $order->total,
            'order-'.$order->id
        );
        if ($savedQr) {
            $qrisPay['qr_data_uri'] = $savedQr['url'];
            $qrisPay['saved_url'] = $savedQr['url'];
        }
    }
@endphp

<div class="order-pay-choice card mt-4 space-y-4 p-4" data-order-pay-choice>
    <div>
        <p class="text-sm font-semibold text-slate-900">Cara bayar</p>
        <p class="mt-1 text-xs text-slate-500">Pilih QRIS untuk bayar dari meja, atau tunai di kasir.</p>
    </div>

    <div class="order-pay-method-grid" role="group" aria-label="Metode pembayaran">
        <button type="button" class="order-pay-method is-active" data-order-pay-method="qris">
            QRIS
        </button>
        <button type="button" class="order-pay-method" data-order-pay-method="cash">
            Tunai di kasir
        </button>
    </div>

    <div class="order-pay-qris-panel" data-order-pay-panel="qris">
        @if ($qrisPay['enabled'] || ($qrisPay['fallback_image_url'] ?? null))
            <div class="flex justify-center">
                <x-qris-dynamic :qris="$qrisPay" />
            </div>

            @if (! empty($qrisPay['payload']) || ! empty($savedQr['path']))
                <div
                    class="order-qris-save mt-4 space-y-2"
                    data-order-qris-save
                    data-qris-filename="qris-{{ $order->order_number }}.png"
                    data-qris-image-url="{{ ! empty($savedQr['path']) ? url('/'.$savedQr['path']) : '' }}"
                >
                    <button type="button" class="btn-primary w-full" data-qris-save-gallery>
                        Simpan QRIS ke galeri
                    </button>
                    <p class="text-center text-xs text-slate-500">
                        Simpan dulu, lalu buka e-wallet/bank → bayar dari galeri/scan. Setelah lunas, upload bukti di bawah.
                    </p>
                    <p class="text-center text-[11px] text-brand-700" data-qris-save-hint hidden></p>
                </div>
            @endif
        @else
            <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200">
                QRIS dinamis belum diatur. Minta kasir mengisi string QRIS di Pengaturan, atau bayar tunai di kasir.
            </p>
        @endif

        <form
            action="{{ route('order.menu.pay') }}"
            method="POST"
            enctype="multipart/form-data"
            class="mt-4 space-y-3"
            data-order-qris-pay-form
        >
            @csrf
            <input type="hidden" name="payment_method" value="qris">
            <div>
                <p class="form-label">Bukti pembayaran</p>
                <div class="order-proof-pick">
                    <label class="order-proof-pick-btn">
                        <input
                            type="file"
                            accept="image/*,.heic,.heif,.jpg,.jpeg,.png,.webp"
                            class="sr-only"
                            data-order-payment-proof-pick="gallery"
                        >
                        <span>Dari galeri</span>
                    </label>
                    <label class="order-proof-pick-btn">
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            class="sr-only"
                            data-order-payment-proof-pick="camera"
                        >
                        <span>Ambil foto</span>
                    </label>
                </div>
                <input
                    id="order-payment-proof"
                    type="file"
                    name="payment_proof"
                    accept="image/*,.heic,.heif"
                    class="sr-only"
                    data-order-payment-proof
                    tabindex="-1"
                >
                <p class="mt-1.5 text-xs text-slate-500">Wajib. Pilih dari galeri atau kamera. Setelah diunggah, pesanan langsung tercatat lunas.</p>
                @error('payment_proof')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-50" data-order-proof-preview>
                <img src="" alt="Pratinjau bukti" class="max-h-48 w-full object-contain" data-order-proof-preview-img>
            </div>
            <p class="hidden text-sm text-rose-600" data-order-proof-error></p>
            <button type="submit" class="btn-primary w-full" data-order-qris-pay-submit>
                Saya sudah bayar — kirim bukti
            </button>
        </form>
    </div>

    <div class="order-pay-cash-panel hidden" data-order-pay-panel="cash">
        <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 text-sm text-brand-900">
            <p class="font-semibold">Bayar tunai di kasir</p>
            <p class="mt-1 text-xs leading-relaxed text-brand-800">
                @if ($order->status->value === 'pending_payment')
                    Setelah dikirim, datang ke kasir dan sebutkan nomor
                @else
                    Datang ke kasir dan sebutkan nomor
                @endif
                <strong class="font-mono">{{ $order->order_number }}</strong>
                @if ($order->customer_note)
                    atas nama <strong>{{ $order->customer_note }}</strong>
                @endif
                .
            </p>
        </div>
        <p class="mt-3 text-center text-xs text-slate-500">
            Total: <strong class="text-brand-700">{{ $format::rupiah($order->total) }}</strong>
        </p>
        @if ($order->status->value === 'pending_payment')
            <form
                action="{{ route('order.menu.pay-cash') }}"
                method="POST"
                class="mt-4"
                data-order-cash-send-form
            >
                @csrf
                <button type="submit" class="btn-primary w-full">
                    Kirim ke kasir (bayar tunai)
                </button>
            </form>
        @endif
    </div>
</div>
