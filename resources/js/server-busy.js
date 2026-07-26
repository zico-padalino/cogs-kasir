/**
 * Overlay ramah saat server 503/429 (shared hosting penuh).
 * Dipakai QR /pesan dan kasir (fetch global).
 */

let overlayEl = null;
let dismissed = false;

function ensureStylesInjected() {
    // Class utama ada di app.css; fallback inline jika CSS lama belum di-build.
}

function buildOverlay() {
    if (overlayEl) {
        return overlayEl;
    }

    const root = document.createElement('div');
    root.className = 'server-busy-overlay';
    root.setAttribute('data-server-busy', '');
    root.setAttribute('role', 'alertdialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-labelledby', 'server-busy-title');
    root.innerHTML = `
        <div class="server-busy-card">
            <div class="server-busy-icon" aria-hidden="true">⏳</div>
            <h2 id="server-busy-title" class="server-busy-title">Server sedang sibuk</h2>
            <p class="server-busy-text">
                Koneksi penuh sementara (503). Tunggu sebentar, lalu muat ulang halaman.
            </p>
            <div class="server-busy-actions">
                <button type="button" class="server-busy-btn server-busy-btn-primary" data-server-busy-refresh>
                    Muat ulang
                </button>
                <button type="button" class="server-busy-btn server-busy-btn-ghost" data-server-busy-dismiss>
                    Tutup
                </button>
            </div>
        </div>
    `;

    root.querySelector('[data-server-busy-refresh]')?.addEventListener('click', () => {
        window.location.reload();
    });

    root.querySelector('[data-server-busy-dismiss]')?.addEventListener('click', () => {
        dismissed = true;
        hideServerBusy();
    });

    document.body.append(root);
    overlayEl = root;
    ensureStylesInjected();

    return root;
}

export function showServerBusy(options = {}) {
    if (dismissed && !options.force) {
        return;
    }

    const root = buildOverlay();
    const text = root.querySelector('.server-busy-text');
    if (text && options.message) {
        text.textContent = options.message;
    }

    root.classList.add('is-visible');
    root.setAttribute('aria-hidden', 'false');
}

export function hideServerBusy() {
    if (!overlayEl) {
        return;
    }
    overlayEl.classList.remove('is-visible');
    overlayEl.setAttribute('aria-hidden', 'true');
}

export function isServerBusyStatus(status) {
    return status === 503 || status === 429 || status === 508;
}

/** Patch fetch sekali — deteksi 503/429 di QR & kasir. */
export function installServerBusyFetchGuard() {
    if (typeof window === 'undefined' || window.__serverBusyFetchPatched) {
        return;
    }
    window.__serverBusyFetchPatched = true;

    const originalFetch = window.fetch.bind(window);

    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        if (isServerBusyStatus(response.status) && !isBackgroundNoiseRequest(args[0])) {
            showServerBusy({
                message:
                    response.status === 429
                        ? 'Terlalu banyak permintaan. Tunggu sebentar, lalu muat ulang halaman.'
                        : 'Server sedang penuh. Tunggu sebentar, lalu muat ulang halaman.',
            });
        }
        return response;
    };
}

/** Poll PIN / status diam-diam — jangan paksa overlay tiap interval. */
function isBackgroundNoiseRequest(input) {
    try {
        const raw = typeof input === 'string' ? input : input?.url || '';
        const path = String(raw);
        return /\/pin\/(status|touch)|\/pesan\/status|pending-orders\/poll|dapur\/poll/i.test(path);
    } catch {
        return false;
    }
}

function boot() {
    installServerBusyFetchGuard();
    window.showServerBusy = showServerBusy;
    window.hideServerBusy = hideServerBusy;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
