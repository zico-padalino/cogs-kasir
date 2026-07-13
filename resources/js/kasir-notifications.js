/**
 * Notifikasi kasir — polling pesanan online + suara + auto load.
 */
const SOUND_STORAGE_KEY = 'kasir_sound_enabled';

let audioContext = null;
let knownOrderIds = null;
let isHandlingNewOrder = false;

function isSoundEnabled() {
    return localStorage.getItem(SOUND_STORAGE_KEY) !== '0';
}

function setSoundEnabled(enabled) {
    localStorage.setItem(SOUND_STORAGE_KEY, enabled ? '1' : '0');
    syncSoundToggleUi();
}

function ensureAudioContext() {
    if (! audioContext) {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (! AudioCtx) {
            return null;
        }

        audioContext = new AudioCtx();
    }

    if (audioContext.state === 'suspended') {
        audioContext.resume();
    }

    return audioContext;
}

function playTone(frequency, startAt, duration, volume = 0.22) {
    const ctx = ensureAudioContext();
    if (! ctx) {
        return;
    }

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.05);
}

function playNewOrderSound() {
    if (! isSoundEnabled()) {
        return;
    }

    try {
        const ctx = ensureAudioContext();
        if (! ctx) {
            return;
        }

        const t = ctx.currentTime;
        playTone(880, t, 0.28);
        playTone(1174.66, t + 0.14, 0.32);
        playTone(880, t + 0.32, 0.24, 0.18);
    } catch {
        // Browser memblokir audio tanpa interaksi pengguna.
    }
}

function playSuccessSound() {
    if (! isSoundEnabled()) {
        return;
    }

    try {
        const ctx = ensureAudioContext();
        if (! ctx) {
            return;
        }

        playTone(659.25, ctx.currentTime, 0.2, 0.16);
    } catch {
        //
    }
}

function unlockAudio() {
    ensureAudioContext();
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function syncSoundToggleUi() {
    document.querySelectorAll('[data-kasir-sound-toggle]').forEach((button) => {
        const enabled = isSoundEnabled();
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        button.classList.toggle('is-muted', ! enabled);

        const label = button.querySelector('[data-kasir-sound-label]');
        if (label) {
            label.textContent = enabled ? 'Suara aktif' : 'Suara mati';
        }

        button.title = enabled ? 'Matikan suara notifikasi' : 'Nyalakan suara notifikasi';
    });
}

function showKasirToast(message) {
    const existing = document.querySelector('[data-kasir-toast]');
    existing?.remove();

    const toast = document.createElement('div');
    toast.className = 'kasir-toast';
    toast.setAttribute('data-kasir-toast', '');
    toast.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.className = 'kasir-toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '🔔';

    const text = document.createElement('span');
    text.className = 'kasir-toast-text';
    text.textContent = message;

    toast.append(icon, text);
    document.body.append(toast);

    window.requestAnimationFrame(() => {
        toast.classList.add('is-visible');
    });
}

function updatePendingPanel(html) {
    const wrap = document.querySelector('[data-pos-pending-wrap]');
    if (! wrap) {
        return;
    }

    wrap.innerHTML = html;
}

async function loadOrderIntoKasir(orderId) {
    const token = csrfToken();
    if (! token || ! orderId) {
        return false;
    }

    const formData = new FormData();
    formData.append('_token', token);

    const response = await fetch(`/kasir/load-order/${orderId}`, {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    return response.ok;
}

function hasActiveKasirDraftWithItems() {
    const root = document.getElementById('kasir-pos');
    if (! root) {
        return false;
    }

    // Order online aktif punya form konfirmasi / status submitted.
    // Draft kasir punya form bayar langsung — jangan ditimpa auto-load.
    const isOnlineConfirm = Boolean(root.querySelector('[data-pos-receipt-confirm]'));
    const itemCount = root.querySelectorAll('[data-kasir-item]').length;

    return itemCount > 0 && ! isOnlineConfirm;
}

async function handleIncomingOrders(newIds, data, shell) {
    if (isHandlingNewOrder || newIds.length === 0) {
        return;
    }

    isHandlingNewOrder = true;

    const autoLoadWanted = shell.dataset.kasirAutoLoad !== '0';
    const preserveKasirDraft = hasActiveKasirDraftWithItems();
    const autoLoad = autoLoadWanted && ! preserveKasirDraft;
    const indexUrl = shell.dataset.kasirIndexUrl || '/kasir';
    const orderId = newIds.includes(Number(data.latest_order_id))
        ? Number(data.latest_order_id)
        : Math.max(...newIds);

    playNewOrderSound();
    showKasirToast(preserveKasirDraft
        ? 'Pesanan online baru masuk — cek banner atas, lanjutkan transaksi kasir dulu'
        : (autoLoad
            ? 'Pesanan baru masuk — memuat ke kasir…'
            : 'Pesanan baru menunggu — buka dari daftar online'));

    if (autoLoad && orderId) {
        try {
            await loadOrderIntoKasir(orderId);
        } catch {
            //
        }

        window.setTimeout(() => {
            const target = new URL(indexUrl, window.location.origin);
            target.searchParams.set('tab', 'cart');
            window.location.assign(target.toString());
        }, 450);

        return;
    }

    // Hanya refresh daftar pending, jangan pindah order aktif kasir
    updatePendingPanel(data.html ?? '');
    window.setTimeout(() => {
        isHandlingNewOrder = false;
    }, 300);
}

async function pollPendingOrders(pollUrl, shell) {
    const response = await fetch(pollUrl, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        redirect: 'manual',
    });

    if (shouldForcePinLock(response)) {
        goToPinUnlock(shell);
        return;
    }

    if (! response.ok) {
        return;
    }

    const data = await response.json();
    syncPinExpiryFromPayload(shell, data);

    if (data.unlocked === false) {
        goToPinUnlock(shell, data.redirect);
        return;
    }

    const currentIds = new Set((data.order_ids ?? []).map((id) => Number(id)));

    if (knownOrderIds === null) {
        knownOrderIds = currentIds;
        updatePendingPanel(data.html ?? '');

        return;
    }

    const newIds = [...currentIds].filter((id) => ! knownOrderIds.has(id));

    if (newIds.length > 0) {
        await handleIncomingOrders(newIds, data, shell);
    } else if (currentIds.size !== knownOrderIds.size) {
        updatePendingPanel(data.html ?? '');
    }

    knownOrderIds = currentIds;
}

function shouldForcePinLock(response) {
    if (response.status === 423) {
        return true;
    }

    // Redirect ke halaman PIN (fetch manual) atau opaqueredirect
    if (response.type === 'opaqueredirect') {
        return true;
    }

    if (response.status >= 300 && response.status < 400) {
        const location = response.headers.get('Location') || '';
        return location.includes('/kasir/pin');
    }

    return false;
}

function goToPinUnlock(shell, redirectUrl) {
    const url = redirectUrl || shell.dataset.kasirPinUnlockUrl || '/kasir/pin';
    if (window.location.pathname.includes('/kasir/pin')) {
        return;
    }
    window.location.assign(url);
}

let pinExpiryTimer = null;
let pinStatusTimer = null;

function syncPinExpiryFromPayload(shell, data) {
    if (! data || typeof data.remaining_seconds !== 'number') {
        return;
    }

    if (data.unlocked === false || data.remaining_seconds <= 0) {
        goToPinUnlock(shell, data.redirect);
        return;
    }

    schedulePinExpiryRedirect(shell, data.remaining_seconds, data.server_now, data.expires_at);
}

function schedulePinExpiryRedirect(shell, remainingSeconds, serverNow, expiresAt) {
    const unlockUrl = shell.dataset.kasirPinUnlockUrl || '/kasir/pin';
    let delayMs;

    if (typeof remainingSeconds === 'number' && Number.isFinite(remainingSeconds)) {
        delayMs = Math.max(0, remainingSeconds) * 1000;
    } else {
        const expires = parseInt(expiresAt || shell.dataset.kasirPinExpiresAt || '', 10);
        const server = parseInt(serverNow || shell.dataset.kasirServerNow || '', 10);
        const client = Math.floor(Date.now() / 1000);

        if (! expires) {
            return;
        }

        // Koreksi selisih jam perangkat vs server
        const offset = Number.isFinite(server) ? (server - client) : 0;
        const remaining = expires - (client + offset);
        delayMs = Math.max(0, remaining) * 1000;
    }

    if (pinExpiryTimer) {
        window.clearTimeout(pinExpiryTimer);
    }

    pinExpiryTimer = window.setTimeout(() => {
        goToPinUnlock(shell, unlockUrl);
    }, delayMs + 300);
}

async function pollPinStatus(shell) {
    const statusUrl = shell.dataset.kasirPinStatusUrl;
    if (! statusUrl) {
        return;
    }

    try {
        const response = await fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            redirect: 'manual',
        });

        if (! response.ok) {
            if (response.status === 401 || response.status === 419) {
                return;
            }
            return;
        }

        const data = await response.json();
        syncPinExpiryFromPayload(shell, data);
    } catch {
        //
    }
}

function openCartTabFromQuery() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') !== 'cart') {
        return;
    }

    const root = document.getElementById('kasir-pos');
    const cartTab = root?.querySelector('[data-kasir-tab="cart"]');
    cartTab?.click();

    params.delete('tab');
    const nextQuery = params.toString();
    const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);
}

function initKasirNotifications() {
    const shell = document.querySelector('[data-kasir-notifications]');
    if (! shell) {
        return;
    }

    const pollUrl = shell.dataset.kasirPollUrl;
    const intervalSeconds = Math.max(5, parseInt(shell.dataset.kasirPollInterval || '12', 10));

    syncSoundToggleUi();
    openCartTabFromQuery();
    schedulePinExpiryRedirect(shell);
    pollPinStatus(shell);

    if (pinStatusTimer) {
        window.clearInterval(pinStatusTimer);
    }
    // Cek status PIN tiap 20 detik (terpisah dari poll order)
    pinStatusTimer = window.setInterval(() => pollPinStatus(shell), 20_000);

    document.querySelectorAll('[data-kasir-sound-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = ! isSoundEnabled();
            setSoundEnabled(next);

            if (next) {
                unlockAudio();
                playSuccessSound();
            }
        });
    });

    const unlockOnce = () => {
        unlockAudio();
        document.removeEventListener('pointerdown', unlockOnce, true);
        document.removeEventListener('keydown', unlockOnce, true);
    };

    document.addEventListener('pointerdown', unlockOnce, true);
    document.addEventListener('keydown', unlockOnce, true);

    if (document.querySelector('[data-pos-flash-success]')) {
        playSuccessSound();
    }

    if (! pollUrl) {
        return;
    }

    const runPoll = () => {
        if (isHandlingNewOrder) {
            return;
        }

        pollPendingOrders(pollUrl, shell).catch(() => {
            //
        });
    };

    runPoll();
    window.setInterval(runPoll, intervalSeconds * 1000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && ! isHandlingNewOrder) {
            runPoll();
            pollPinStatus(shell);
        }
    });
}

document.addEventListener('DOMContentLoaded', initKasirNotifications);
