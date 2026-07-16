/**
 * Public QR attendance: live clock, employee select, selfie camera, GPS.
 */

function setText(el, text, isError = false) {
    if (! el) return;
    el.textContent = text;
    el.classList.toggle('is-error', isError);
}

function readGps() {
    return new Promise((resolve, reject) => {
        if (! navigator.geolocation) {
            reject(new Error('GPS tidak didukung di perangkat ini.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
            }),
            () => reject(new Error('Izinkan akses lokasi untuk absensi.')),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 5000 },
        );
    });
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
    // mirror selfie to match preview
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
        done: 'Sudah absen hari ini',
        closed: 'Di luar jam absen',
    }[action] || 'Pilih pegawai';
}

function bindScan(root) {
    const form = root.querySelector('[data-scan-form]');
    const video = root.querySelector('[data-scan-video]');
    const canvas = root.querySelector('[data-scan-canvas]');
    const employeeSelect = root.querySelector('[data-scan-employee]');
    const modeInput = root.querySelector('[data-scan-mode]');
    const modeLabel = root.querySelector('[data-scan-mode-label]');
    const latInput = root.querySelector('[data-scan-lat]');
    const lngInput = root.querySelector('[data-scan-lng]');
    const photoInput = root.querySelector('[data-scan-photo]');
    const submit = root.querySelector('[data-scan-submit]');
    const gpsStatus = root.querySelector('[data-scan-gps]');
    const clockEl = root.querySelector('[data-scan-clock]');
    const hasLocation = root.getAttribute('data-has-location') === '1';

    if (! form || ! video || ! canvas || ! employeeSelect) {
        return;
    }

    let cameraReady = false;
    let gpsReady = false;

    bindClock(clockEl);

    const refreshSubmit = () => {
        const option = employeeSelect.selectedOptions[0];
        const action = option?.dataset?.action || '';
        const canAct = action === 'check_in' || action === 'check_out';

        if (modeInput) modeInput.value = canAct ? action : '';
        if (modeLabel) {
            modeLabel.textContent = option?.value ? actionLabel(action) : 'Pilih pegawai dulu';
            modeLabel.classList.toggle('is-in', action === 'check_in');
            modeLabel.classList.toggle('is-out', action === 'check_out');
            modeLabel.classList.toggle('is-blocked', option?.value && ! canAct);
        }

        if (submit) {
            submit.disabled = ! (canAct && cameraReady && gpsReady && hasLocation);
            submit.textContent = canAct ? actionLabel(action) : 'Absen';
        }
    };

    employeeSelect.addEventListener('change', refreshSubmit);

    const bootCamera = async () => {
        try {
            await startCamera(video);
            cameraReady = true;
            refreshSubmit();
        } catch (_) {
            cameraReady = false;
            setText(gpsStatus, 'Kamera tidak bisa dibuka. Izinkan akses kamera.', true);
            refreshSubmit();
        }
    };

    const bootGps = async () => {
        if (! hasLocation) {
            setText(gpsStatus, 'Lokasi toko belum diatur admin.', true);
            refreshSubmit();
            return;
        }

        try {
            setText(gpsStatus, 'Membaca lokasi GPS…');
            const gps = await readGps();
            latInput.value = String(gps.lat);
            lngInput.value = String(gps.lng);
            gpsReady = true;
            setText(gpsStatus, `Lokasi siap (${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)})`);
            refreshSubmit();
        } catch (error) {
            gpsReady = false;
            setText(gpsStatus, error.message || 'Gagal membaca GPS.', true);
            refreshSubmit();
        }
    };

    bootCamera();
    bootGps();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const option = employeeSelect.selectedOptions[0];
        const action = option?.dataset?.action || '';
        if (action !== 'check_in' && action !== 'check_out') {
            setText(gpsStatus, 'Pegawai ini tidak bisa absen sekarang.', true);
            return;
        }

        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Mengirim…';
        }

        try {
            if (! latInput.value || ! lngInput.value) {
                const gps = await readGps();
                latInput.value = String(gps.lat);
                lngInput.value = String(gps.lng);
            }

            photoInput.value = capturePhoto(video, canvas);
            modeInput.value = action;
            stopCamera(video);
            form.submit();
        } catch (error) {
            setText(gpsStatus, error.message || 'Gagal mengirim absensi.', true);
            refreshSubmit();
        }
    });

    window.addEventListener('beforeunload', () => stopCamera(video));
    window.addEventListener('orientationchange', () => {
        if (cameraReady) startCamera(video).catch(() => {});
    });

    refreshSubmit();
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-attendance-scan]');
    if (root) {
        bindScan(root);
    }
});
