@props(['app' => 'kasir'])

<div class="pwa-install hidden" data-pwa-install hidden>
    <div class="pwa-install-card">
        <div class="pwa-install-icon" aria-hidden="true">
            <img src="{{ asset('icons/icon-96.png') }}" alt="" width="48" height="48" class="h-12 w-12 rounded-2xl">
        </div>
        <div class="pwa-install-copy">
            <p class="pwa-install-title">Pasang aplikasi di Android</p>
            <p class="pwa-install-text" data-pwa-install-text>
                Tambahkan ke layar utama untuk pengalaman seperti aplikasi native.
            </p>
        </div>
        <div class="pwa-install-actions">
            <button type="button" class="pwa-install-btn" data-pwa-install-accept>
                Pasang
            </button>
            <button type="button" class="pwa-install-dismiss" data-pwa-install-dismiss aria-label="Tutup">
                ✕
            </button>
        </div>
    </div>
</div>

<div class="pwa-install-ios hidden" data-pwa-install-ios hidden>
    <div class="pwa-install-card">
        <div class="pwa-install-copy">
            <p class="pwa-install-title">Pasang di iPhone</p>
            <p class="pwa-install-text">
                Tap <strong>Bagikan</strong> lalu <strong>Tambahkan ke Layar Utama</strong>.
            </p>
        </div>
        <button type="button" class="pwa-install-dismiss" data-pwa-install-ios-dismiss aria-label="Tutup">
            ✕
        </button>
    </div>
</div>
