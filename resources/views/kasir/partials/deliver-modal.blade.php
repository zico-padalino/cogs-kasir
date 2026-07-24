{{-- Popup ceklis item sudah diantar (Open Bill / sudah bayar). Diisi via JS. --}}
<div class="pos-deliver-modal hidden" data-kasir-deliver-modal aria-hidden="true">
    <div class="pos-add-modal-backdrop" data-kasir-close-deliver></div>
    <div class="pos-deliver-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kasir-deliver-modal-title">
        <div class="pos-deliver-modal-head">
            <div class="min-w-0 flex-1">
                <p class="pos-deliver-modal-eyebrow">Ceklis antar</p>
                <h2 id="kasir-deliver-modal-title" class="pos-deliver-modal-title" data-deliver-modal-title>Item pesanan</h2>
                <p class="pos-deliver-modal-progress" data-deliver-modal-progress>Diantar 0/0</p>
            </div>
            <button type="button" class="pos-add-modal-close" data-kasir-close-deliver aria-label="Tutup">×</button>
        </div>

        <div class="pos-deliver-modal-list" data-deliver-modal-list>
            <p class="pos-deliver-modal-empty">Tidak ada item.</p>
        </div>

        <button type="button" class="btn-primary w-full" data-kasir-close-deliver>Selesai</button>
    </div>
</div>
