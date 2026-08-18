/**
 * Public QR attendance: mode (masuk/pulang), employee select, selfie camera, GPS.
 */
import { geolocationErrorMessage, queryGeolocationPermission, readGps } from './geolocation';

function setText(el, text, isError = false) {
    if (! el) return;
    el.textContent = text;
    el.classList.toggle('is-error', isError);
}

async function startCamera(video) {
    const previous = video.srcObject;
    if (previous) {
        previous.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: { ideal: 'user' },
            width: { ideal: 1280 },
            height: { ideal: 720 },
        },
    });
    video.srcObject = stream;
    video.setAttribute('playsinline', 'true');
    video.muted = true;
    await video.play();
}

function stopCamera(video) {
    const stream = video?.srcObject;
    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    }
}

function capturePhoto(video, canvas) {
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.translate(width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, width, height);
    return canvas.toDataURL('image/jpeg', 0.82);
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function bindClock(el) {
    if (! el) return;
    const tick = () => {
        const now = new Date();
        el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    };
    tick();
    window.setInterval(tick, 1000);
}

function actionLabel(action) {
    return {
        check_in: 'Absen Masuk',
        check_out: 'Absen Pulang',
        done: 'Sudah absen masuk & pulang',
        closed: 'Belum waktunya / di luar jam',
    }[action] || 'Pilih pegawai';
}

function scheduleHint(option) {
    if (! option?.value) return '';
    if (option.dataset.isOff === '1') return 'Libur hari ini';
    const clockIn = option.dataset.clockIn || '';
    const clockOut = option.dataset.clockOut || '';
    if (! clockIn || ! clockOut) return '';
    return `Jadwal ${clockIn} – ${clockOut}`;
}

function optionActions(option) {
    const raw = option?.dataset?.actions || '';
    const listed = raw.split(',').map((s) => s.trim()).filter(Boolean);
    if (listed.length) return listed;
    const legacy = option?.dataset?.action || '';
    return (legacy === 'check_in' || legacy === 'check_out') ? [legacy] : [];
}

function bindScan(root) {
    const form = root.querySelector('[data-scan-form]');
    const video = root.querySelector('[data-scan-video]');
    const canvas = root.querySelector('[data-scan-canvas]');
    const employeeSelect = root.querySelector('[data-scan-employee]');
    const modeInput = root.querySelector('[data-scan-mode]');
    const modeLabel = root.querySelector('[data-scan-mode-label]');
    const employeeHint = root.querySelector('[data-scan-employee-hint]');
    const missedWarn = root.querySelector('[data-scan-missed-warn]');
    const modeButtons = root.querySelectorAll('[data-scan-mode-btn]');
    const latInput = root.querySelector('[data-scan-lat]');
    const lngInput = root.querySelector('[data-scan-lng]');
    const photoInput = root.querySelector('[data-scan-photo]');
    const submit = root.querySelector('[data-scan-submit]');
    const gpsStatus = root.querySelector('[data-scan-gps]');
    const gpsEnable = root.querySelector('[data-scan-gps-enable]');
    const gpsPanel = root.querySelector('[data-scan-gps-panel]');
    const clockEl = root.querySelector('[data-scan-clock]');
    const hasLocation = root.getAttribute('data-has-location') === '1';

    if (! form || ! video || ! canvas || ! employeeSelect) {
        return;
    }

    let cameraReady = false;
    let gpsReady = false;
    let gpsBusy = false;
    let selectedMode = modeInput?.value === 'check_out' ? 'check_out' : 'check_in';

    bindClock(clockEl);

    const showGpsPrompt = (message, { error = false, showButton = true } = {}) => {
        setText(gpsStatus, message, error);
        if (gpsEnable) {
            gpsEnable.hidden = ! showButton;
            gpsEnable.disabled = gpsBusy;
            gpsEnable.textContent = gpsReady ? 'Perbarui lokasi' : 'Izinkan lokasi';
        }
        if (gpsPanel) {
            gpsPanel.classList.toggle('is-denied', error);
            gpsPanel.classList.toggle('is-ready', gpsReady);
        }
    };

    const applyGps = (gps) => {
        latInput.value = String(gps.lat);
        lngInput.value = String(gps.lng);
        gpsReady = true;
        const accuracy = Number.isFinite(gps.accuracy) ? ` ±${Math.round(gps.accuracy)} m` : '';
        showGpsPrompt(`Lokasi siap (${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)}${accuracy})`, {
            error: false,
            showButton: true,
        });
        refreshSubmit();
    };

    const requestGps = async ({ userInitiated = false } = {}) => {
        if (! hasLocation || gpsBusy) {
            return;
        }

        gpsBusy = true;
        if (gpsEnable) {
            gpsEnable.disabled = true;
            gpsEnable.textContent = 'Membaca lokasi…';
        }

        showGpsPrompt(
            userInitiated
                ? 'Meminta izin lokasi… Ketuk Izinkan jika browser menanyakan.'
                : 'Membaca lokasi GPS…',
            { error: false, showButton: false },
        );

        try {
            const permission = await queryGeolocationPermission();
            if (permission === 'denied') {
                throw Object.assign(new Error('Akses lokasi ditolak.'), { code: 1 });
            }

            const gps = await readGps({ requireFresh: true });
            applyGps(gps);
        } catch (error) {
            gpsReady = false;
            latInput.value = '';
            lngInput.value = '';
            showGpsPrompt(geolocationErrorMessage(error), { error: true, showButton: true });
            refreshSubmit();
        } finally {
            gpsBusy = false;
            if (gpsEnable) {
                gpsEnable.disabled = false;
                gpsEnable.textContent = gpsReady ? 'Perbarui lokasi' : 'Izinkan lokasi';
            }
        }
    };

    const setMode = (mode, { preserveEmployee = false } = {}) => {
        selectedMode = mode === 'check_out' ? 'check_out' : 'check_in';
        if (modeInput) modeInput.value = selectedMode;

        modeButtons.forEach((btn) => {
            const active = btn.getAttribute('data-scan-mode-btn') === selectedMode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const currentId = employeeSelect.value;
        Array.from(employeeSelect.options).forEach((opt) => {
            if (! opt.value) {
                opt.hidden = false;
                return;
            }
            const actions = optionActions(opt);
            const matches = actions.includes(selectedMode);
            opt.hidden = ! matches && (! preserveEmployee || opt.value !== currentId);
        });

        refreshSubmit();
    };

    const refreshSubmit = () => {
        const option = employeeSelect.selectedOptions[0];
        const actions = optionActions(option);
        const hasEmployee = !!(option && option.value);
        const canAct = hasEmployee && actions.includes(selectedMode);
        const missed = hasEmployee && option.dataset.missedCheckout === '1';
        const showMissedWarn = canAct && selectedMode === 'check_in' && missed;

        if (modeInput) modeInput.value = selectedMode;

        if (missedWarn) {
            missedWarn.classList.toggle('hidden', ! showMissedWarn);
        }

        if (modeLabel) {
            let text = actionLabel(selectedMode);
            if (! hasEmployee) {
                text = `${actionLabel(selectedMode)} · pilih nama pegawai`;
            } else if (canAct) {
                const hint = scheduleHint(option);
                text = hint ? `${actionLabel(selectedMode)} · ${hint}` : actionLabel(selectedMode);
                if (showMissedWarn) {
                    text += ' · belum absen pulang sebelumnya';
                }
            } else if (actions.includes('check_out') && selectedMode === 'check_in') {
                text = 'Pilih Absen Pulang, atau Absen Masuk (pulang terlewat akan tercatat)';
            } else if (actions.includes('check_in') && selectedMode === 'check_out') {
                text = 'Pegawai ini belum absen masuk / belum waktunya pulang';
            } else if ((option?.dataset?.action || '') === 'done') {
                text = 'Sudah absen masuk & pulang hari ini';
            } else {
                text = actionLabel(option?.dataset?.action || 'closed');
            }

            modeLabel.textContent = text;
            modeLabel.classList.toggle('is-in', selectedMode === 'check_in' && canAct);
            modeLabel.classList.toggle('is-out', selectedMode === 'check_out' && canAct);
            modeLabel.classList.toggle('is-blocked', hasEmployee && ! canAct);
        }

        if (employeeHint) {
            if (selectedMode === 'check_in') {
                employeeHint.textContent = 'Daftar: pegawai yang bisa absen masuk (termasuk yang belum pulang kemarin).';
            } else {
                employeeHint.textContent = 'Daftar: pegawai yang siap absen pulang.';
            }
        }

        if (submit) {
            submit.disabled = ! (canAct && cameraReady && gpsReady && hasLocation);
            submit.textContent = actionLabel(selectedMode);
            submit.classList.toggle('scan-submit-out', selectedMode === 'check_out');
        }
    };

    modeButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            setMode(btn.getAttribute('data-scan-mode-btn'));
        });
    });

    employeeSelect.addEventListener('change', () => {
        const option = employeeSelect.selectedOptions[0];
        const actions = optionActions(option);
        if (actions.length && ! actions.includes(selectedMode)) {
            setMode(actions[0], { preserveEmployee: true });
            return;
        }
        refreshSubmit();
    });

    gpsEnable?.addEventListener('click', () => {
        void requestGps({ userInitiated: true });
    });

    const bootCamera = async () => {
        try {
            await startCamera(video);
            cameraReady = true;
            refreshSubmit();
        } catch (_) {
            cameraReady = false;
            setText(gpsStatus, 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser.', true);
            refreshSubmit();
        }
    };

    const bootGps = async () => {
        if (! hasLocation) {
            showGpsPrompt('Lokasi toko belum diatur admin.', { error: true, showButton: false });
            refreshSubmit();
            return;
        }

        showGpsPrompt(
            'Lokasi wajib untuk absensi. Ketuk tombol di bawah lalu pilih Izinkan saat browser/Safari bertanya.',
            { error: false, showButton: true },
        );

        if (navigator.permissions?.query) {
            try {
                const result = await navigator.permissions.query({ name: 'geolocation' });
                if (result.state === 'granted') {
                    await requestGps({ userInitiated: false });
                } else if (result.state === 'denied') {
                    showGpsPrompt(geolocationErrorMessage({ code: 1 }), { error: true, showButton: true });
                    refreshSubmit();
                }

                result.addEventListener('change', () => {
                    if (result.state === 'granted') {
                        void requestGps({ userInitiated: false });
                    } else if (result.state === 'denied') {
                        gpsReady = false;
                        showGpsPrompt(geolocationErrorMessage({ code: 1 }), { error: true, showButton: true });
                        refreshSubmit();
                    }
                });
            } catch {
                // Safari lama — tetap andalkan tombol izin lokasi.
            }
        }
    };

    bootCamera();
    bootGps();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const option = employeeSelect.selectedOptions[0];
        const actions = optionActions(option);
        if (! actions.includes(selectedMode)) {
            showGpsPrompt(
                selectedMode === 'check_in'
                    ? 'Pegawai ini tidak bisa Absen Masuk sekarang.'
                    : 'Pegawai ini tidak bisa Absen Pulang sekarang.',
                { error: true, showButton: gpsReady },
            );
            return;
        }

        if (selectedMode === 'check_in' && option.dataset.missedCheckout === '1') {
            const ok = window.confirm(
                'Anda belum absen pulang shift sebelumnya.\n\n'
                + 'Jika lanjut Absen Masuk, ketidakhadiran absen pulang akan tercatat.\n\n'
                + 'Lanjutkan Absen Masuk?',
            );
            if (! ok) {
                refreshSubmit();
                return;
            }
        }

        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Mengirim…';
        }

        try {
            if (! latInput.value || ! lngInput.value) {
                await requestGps({ userInitiated: true });
            }

            if (! latInput.value || ! lngInput.value) {
                throw Object.assign(new Error('Lokasi GPS belum siap. Izinkan lokasi lalu coba lagi.'), { code: 1 });
            }

            photoInput.value = capturePhoto(video, canvas);
            modeInput.value = selectedMode;
            stopCamera(video);
            form.submit();
        } catch (error) {
            showGpsPrompt(geolocationErrorMessage(error), { error: true, showButton: true });
            refreshSubmit();
        }
    });

    window.addEventListener('beforeunload', () => stopCamera(video));
    window.addEventListener('pagehide', () => stopCamera(video));
    window.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCamera(video);
        }
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && ! video.srcObject) {
            startCamera(video).catch(() => {});
        }
    });
    window.addEventListener('orientationchange', () => {
        if (cameraReady) startCamera(video).catch(() => {});
    });

    const prefillOption = employeeSelect.selectedOptions[0];
    const prefillActions = optionActions(prefillOption);
    if (prefillActions.includes(selectedMode)) {
        setMode(selectedMode, { preserveEmployee: true });
    } else if (prefillActions.length) {
        setMode(prefillActions[0], { preserveEmployee: true });
    } else {
        setMode(selectedMode);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-attendance-scan]');
    if (root) {
        bindScan(root);
    }
});
