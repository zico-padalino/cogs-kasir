/**
 * Notifikasi kasir — polling pesanan online + suara + auto load.
 */
import { refreshKasirOrderUi } from './kasir';

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

/** Volume notifikasi kasir — selalu maksimal. */
const NOTIFICATION_VOLUME = 1;

function playTone(frequency, startAt, duration, volume = NOTIFICATION_VOLUME, type = 'square') {
    const ctx = ensureAudioContext();
    if (! ctx) {
        return;
    }

    const peak = Math.min(1, Math.max(0.85, volume));
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(peak, startAt + 0.006);
    gain.gain.setValueAtTime(peak, startAt + duration * 0.55);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.04);
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
        const v = NOTIFICATION_VOLUME;

        // Alert kasir: ding-ding-DING! (lebih keras & jelas dari chime halus sebelumnya)
        const pattern = [
            [880, 0, 0.2],
            [1100, 0.14, 0.2],
            [1400, 0.28, 0.32],
            [880, 0.5, 0.16],
            [1100, 0.64, 0.16],
            [1760, 0.78, 0.55],
        ];

        pattern.forEach(([freq, offset, duration]) => {
            playTone(freq, t + offset, duration, v, 'square');
            playTone(freq * 2, t + offset, duration * 0.85, v * 0.45, 'triangle');
        });
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

        const t = ctx.currentTime;
        playTone(988, t, 0.18, NOTIFICATION_VOLUME, 'square');
        playTone(1318, t + 0.12, 0.22, NOTIFICATION_VOLUME, 'square');
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

function flashPendingPanel() {
    const pending = document.querySelector('[data-pos-pending]');
    if (! pending) {
        return;
    }

    pending.classList.add('is-new-alert', 'is-expanded');
    const toggle = pending.querySelector('[data-pos-pending-toggle]');
    toggle?.setAttribute('aria-expanded', 'true');

    window.setTimeout(() => {
        pending.classList.remove('is-new-alert');
    }, 2800);
}

async function loadOrderIntoKasir(orderId) {
    const token = csrfToken();
    if (! token || ! orderId) {
        return null;
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

    if (! response.ok) {
        return null;
    }

    return response.json();
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
    const orderId = newIds.includes(Number(data.latest_order_id))
        ? Number(data.latest_order_id)
        : Math.max(...newIds);

    playNewOrderSound();
    showKasirToast(preserveKasirDraft
        ? 'Pesanan online baru masuk — cek banner atas, lanjutkan transaksi kasir dulu'
        : (autoLoad
            ? 'Pesanan baru masuk ke kasir'
            : 'Pesanan baru menunggu — buka dari daftar online'));

    updatePendingPanel(data.html ?? '');
    flashPendingPanel();

    if (autoLoad && orderId) {
        try {
            const payload = await loadOrderIntoKasir(orderId);
            if (payload && refreshKasirOrderUi(payload)) {
                window.setTimeout(() => {
                    isHandlingNewOrder = false;
                }, 300);

                return;
            }
        } catch {
            //
        }

        const indexUrl = shell.dataset.kasirIndexUrl || '/kasir';
        const target = new URL(indexUrl, window.location.origin);
        target.searchParams.set('tab', 'cart');
        window.location.assign(target.toString());

        return;
    }

    window.setTimeout(() => {
        isHandlingNewOrder = false;
    }, 300);
}

function isPinManagementPage() {
    const path = (window.location.pathname || '').replace(/\/+$/, '') || '/';

    return path === '/pin';
}

async function pollPendingOrders(pollUrl, shell) {
    const pinPollOnly = isPinPollOnly(shell);

    const response = await fetch(pollUrl, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        redirect: 'manual',
    });

    if (shouldForcePinLock(response)) {
        if (! pinPollOnly) {
            goToPinUnlock(shell);
        }

        return;
    }

    if (! response.ok) {
        return;
    }

    const data = await response.json();

    if (! pinPollOnly) {
        syncPinExpiryFromPayload(shell, data);

        if (data.unlocked === false) {
            goToPinUnlock(shell, data.redirect);

            return;
        }
    }

    const currentIds = new Set((data.order_ids ?? []).map((id) => Number(id)));

    if (knownOrderIds === null) {
        knownOrderIds = currentIds;

        if (! pinPollOnly) {
            updatePendingPanel(data.html ?? '');
        }

        return;
    }

    const newIds = [...currentIds].filter((id) => ! knownOrderIds.has(id));

    if (newIds.length > 0) {
        if (pinPollOnly) {
            playNewOrderSound();
            showKasirToast('Pesanan baru masuk — masukkan PIN untuk membuka kasir');
            knownOrderIds = currentIds;

            return;
        }

        await handleIncomingOrders(newIds, data, shell);
    } else if (! pinPollOnly && currentIds.size !== knownOrderIds.size) {
        updatePendingPanel(data.html ?? '');
        knownOrderIds = currentIds;
    } else {
        knownOrderIds = currentIds;
    }
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
    if (isPinUnlockPage()) {
        return;
    }

    // Jangan paksa keluar dari halaman atur/ubah PIN.
    if (isPinManagementPage()) {
        return;
    }

    const path = window.location.pathname || '';
    if (path.includes('/kasir/pin')) {
        return;
    }

    const url = redirectUrl || shell?.dataset?.kasirPinUnlockUrl || '/kasir/pin';
    window.location.assign(url);
}

function isPinUnlockPage() {
    const path = (window.location.pathname || '').replace(/\/+$/, '') || '/';

    return path.endsWith('/kasir/pin') || path === '/kasir/pin';
}

function isPinPollOnly(shell) {
    return shell?.dataset?.kasirPinPollOnly === '1' || isPinUnlockPage();
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

    // Halaman /pin (atur PIN) share layout kasir — jangan auto-redirect ke unlock.
    if (isPinManagementPage()) {
        syncSoundToggleUi();
        return;
    }

    const pollUrl = shell.dataset.kasirPollUrl;
    const intervalSeconds = Math.max(5, parseInt(shell.dataset.kasirPollInterval || '5', 10));
    const pinPollOnly = isPinPollOnly(shell);

    syncSoundToggleUi();
    if (! pinPollOnly) {
        openCartTabFromQuery();
        schedulePinExpiryRedirect(shell);
        pollPinStatus(shell);

        if (pinStatusTimer) {
            window.clearInterval(pinStatusTimer);
        }
        pinStatusTimer = window.setInterval(() => pollPinStatus(shell), 20_000);
    }

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
