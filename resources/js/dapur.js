/**
 * Layar dapur — polling tiket + ceklis item selesai.
 */

let lastLocalEditAt = 0;
let lastPinTouchAt = 0;
const PIN_TOUCH_THROTTLE_MS = 60_000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function formatClock(date) {
    return `${pad2(date.getHours())}:${pad2(date.getMinutes())}:${pad2(date.getSeconds())}`;
}

function formatElapsed(startedAt) {
    if (!startedAt) {
        return '—';
    }

    const start = new Date(startedAt);
    if (Number.isNaN(start.getTime())) {
        return '—';
    }

    const seconds = Math.max(0, Math.floor((Date.now() - start.getTime()) / 1000));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    if (h > 0) {
        return `${h}j ${pad2(m)}m`;
    }

    return `${m}:${pad2(s)}`;
}

function elapsedClass(startedAt) {
    if (!startedAt) {
        return '';
    }

    const start = new Date(startedAt);
    if (Number.isNaN(start.getTime())) {
        return '';
    }

    const minutes = (Date.now() - start.getTime()) / 60000;
    if (minutes >= 20) {
        return 'is-late';
    }
    if (minutes >= 10) {
        return 'is-warn';
    }

    return '';
}

function updateClocks(root) {
    const clock = root.querySelector('[data-dapur-clock]');
    if (clock) {
        clock.textContent = formatClock(new Date());
    }

    root.querySelectorAll('[data-dapur-ticket]').forEach((ticket) => {
        const startedAt = ticket.getAttribute('data-started-at');
        const elapsed = ticket.querySelector('[data-dapur-elapsed]');
        if (!elapsed) {
            return;
        }

        elapsed.textContent = formatElapsed(startedAt);
        elapsed.classList.remove('is-warn', 'is-late');
        const cls = elapsedClass(startedAt);
        if (cls) {
            elapsed.classList.add(cls);
        }
    });
}

async function touchPin(root) {
    const url = root.getAttribute('data-kasir-pin-touch-url');
    if (!url) {
        return;
    }

    const now = Date.now();
    if (now - lastPinTouchAt < PIN_TOUCH_THROTTLE_MS) {
        return;
    }
    lastPinTouchAt = now;

    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
    } catch {
        // ignore — poll will redirect if PIN expired
    }
}

async function toggleItem(button) {
    if (button.disabled) {
        return;
    }

    const url = button.getAttribute('data-url');
    if (!url) {
        return;
    }

    const delivered = button.getAttribute('data-delivered') === '1';
    const next = !delivered;

    button.disabled = true;
    lastLocalEditAt = Date.now();

    try {
        const res = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                is_delivered: next,
            }),
        });

        if (res.status === 401 || res.status === 419) {
            window.location.reload();
            return;
        }

        if (!res.ok) {
            const payload = await res.json().catch(() => ({}));
            window.alert(payload.message || 'Gagal memperbarui item.');
            return;
        }

        const payload = await res.json();
        const status = payload?.data?.status;
        lastLocalEditAt = Date.now();

        // Paid + semua item ceklis → order jadi served; hapus tiket dari papan.
        if (status === 'served') {
            const ticket = button.closest('[data-dapur-ticket]');
            ticket?.classList.add('is-leaving');
            window.setTimeout(() => ticket?.remove(), 280);
            return;
        }

        button.setAttribute('data-delivered', next ? '1' : '0');
        button.setAttribute('aria-pressed', next ? 'true' : 'false');
        const check = button.querySelector('.kds-item-check');
        if (check) {
            check.textContent = next ? '✓' : '';
        }

        const row = button.closest('[data-dapur-item]');
        row?.classList.toggle('is-done', next);

        const ticket = button.closest('[data-dapur-ticket]');
        if (ticket) {
            const items = [...ticket.querySelectorAll('[data-dapur-item]')];
            const done = items.filter((el) => el.classList.contains('is-done')).length;
            ticket.classList.toggle('is-done', done === items.length && items.length > 0);
            const chip = [...ticket.querySelectorAll('.kds-ticket-chip')].find((el) =>
                /\d+\/\d+ siap/.test(el.textContent || ''),
            );
            if (chip) {
                chip.textContent = `${done}/${items.length} siap`;
            }
        }
    } catch {
        window.alert('Koneksi gagal. Coba lagi.');
    } finally {
        button.disabled = false;
    }
}

async function pollBoard(root) {
    const url = root.getAttribute('data-dapur-poll-url');
    const list = root.querySelector('[data-dapur-board-list]') || document.querySelector('[data-dapur-board-list]');
    if (!url || !list || root.dataset.dapurPolling === '1') {
        return;
    }

    root.dataset.dapurPolling = '1';

    try {
        const res = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (res.status === 401 || res.status === 419 || res.redirected) {
            window.location.href = root.getAttribute('data-kasir-pin-unlock-url') || '/kasir/pin';
            return;
        }

        if (!res.ok) {
            root.dataset.dapurLastPollOk = '0';
            return;
        }

        root.dataset.dapurLastPollOk = '1';
        const payload = await res.json();

        if (payload.unlocked === false) {
            window.location.href = root.getAttribute('data-kasir-pin-unlock-url') || '/kasir/pin';
            return;
        }

        const fingerprint = String(payload.fingerprint || '');
        const prevFingerprint = root.dataset.dapurFingerprint || '';
        const recentlyEdited = Date.now() - lastLocalEditAt < 2500;

        if (fingerprint !== prevFingerprint && !recentlyEdited && typeof payload.html === 'string') {
            list.innerHTML = payload.html;
            root.dataset.dapurFingerprint = fingerprint;
            updateClocks(document);
        } else if (fingerprint === prevFingerprint || recentlyEdited) {
            if (!recentlyEdited) {
                root.dataset.dapurFingerprint = fingerprint;
            }
        } else {
            root.dataset.dapurFingerprint = fingerprint;
        }

        const countEl = document.querySelector('[data-dapur-count]');
        if (countEl) {
            const n = Number(payload.count || 0);
            countEl.textContent = `${n} pesanan`;
        }
    } catch {
        root.dataset.dapurLastPollOk = '0';
    } finally {
        root.dataset.dapurPolling = '0';
    }
}

function initDapurBoard() {
    const root = document.querySelector('[data-dapur-board]');
    if (!root) {
        return;
    }

    let intervalSec = Math.max(20, Number(root.getAttribute('data-dapur-poll-interval') || 30));
    let timer = null;

    updateClocks(document);
    window.setInterval(() => updateClocks(document), 1000);

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dapur-toggle]');
        if (!button || !root.contains(button)) {
            return;
        }
        event.preventDefault();
        toggleItem(button);
    });

    const schedule = () => {
        if (timer) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(tick, intervalSec * 1000);
    };

    const tick = async () => {
        if (document.visibilityState === 'hidden') {
            schedule();
            return;
        }

        await pollBoard(root);
        await touchPin(root);

        if (root.dataset.dapurLastPollOk === '0') {
            intervalSec = Math.min(120, Math.round(intervalSec * 2));
        } else {
            intervalSec = Math.max(20, Number(root.getAttribute('data-dapur-poll-interval') || 30));
        }

        schedule();
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            intervalSec = Math.max(20, Number(root.getAttribute('data-dapur-poll-interval') || 30));
            tick();
        }
    });

    tick();
}

document.addEventListener('DOMContentLoaded', initDapurBoard);
