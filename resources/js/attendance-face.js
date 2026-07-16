/**
 * Face capture + GPS for attendance check-in/out and guided face enroll.
 * Uses @vladmandic/face-api from CDN.
 *
 * Guided enroll scans with a lightweight detector+landmarks loop,
 * and only computes the heavy face descriptor when a pose is locked.
 */

const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';
const FACE_API_SRC = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.min.js';

const ENROLL_POSES = [
    {
        id: 'center',
        short: 'Depan',
        label: 'Hadap lurus ke kamera',
        hint: 'Wajah di tengah bingkai',
        yawMin: -0.22,
        yawMax: 0.22,
        pitchMin: -0.2,
        pitchMax: 0.2,
    },
    {
        id: 'left',
        short: 'Kiri',
        label: 'Putar ke kiri',
        hint: 'Pelan saja, jangan terlalu jauh',
        yawMin: 0.12,
        yawMax: 0.95,
        pitchMin: -0.32,
        pitchMax: 0.32,
    },
    {
        id: 'right',
        short: 'Kanan',
        label: 'Putar ke kanan',
        hint: 'Pelan saja, jangan terlalu jauh',
        yawMin: -0.95,
        yawMax: -0.12,
        pitchMin: -0.32,
        pitchMax: 0.32,
    },
    {
        id: 'up',
        short: 'Atas',
        label: 'Angkat sedikit ke atas',
        hint: 'Dagu naik pelan',
        yawMin: -0.28,
        yawMax: 0.28,
        pitchMin: 0.08,
        pitchMax: 0.85,
    },
    {
        id: 'down',
        short: 'Bawah',
        label: 'Tundukkan sedikit',
        hint: 'Dagu turun pelan',
        yawMin: -0.28,
        yawMax: 0.28,
        pitchMin: -0.85,
        pitchMax: -0.08,
    },
];

const SCAN_OPTIONS = { inputSize: 224, scoreThreshold: 0.4 };
const LOCK_OPTIONS = { inputSize: 320, scoreThreshold: 0.4 };

let modelsReady = null;
let faceapi = null;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Gagal memuat library wajah.'));
        document.head.appendChild(script);
    });
}

async function ensureModels() {
    if (modelsReady) {
        return modelsReady;
    }

    modelsReady = (async () => {
        await loadScript(FACE_API_SRC);
        faceapi = window.faceapi;
        if (! faceapi) {
            throw new Error('Library wajah tidak tersedia.');
        }

        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
    })();

    return modelsReady;
}

async function startCamera(video, { lowRes = false } = {}) {
    const previous = video.srcObject;
    if (previous) {
        previous.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: { ideal: 'user' },
            width: { ideal: lowRes ? 640 : 960 },
            height: { ideal: lowRes ? 480 : 720 },
            frameRate: { ideal: 24, max: 30 },
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

function setStatus(el, text, isError = false) {
    if (! el) {
        return;
    }
    el.textContent = text;
    el.classList.toggle('is-error', isError);
}

function setFrameState(frame, state) {
    if (! frame) {
        return;
    }
    frame.classList.remove('is-searching', 'is-found', 'is-locking', 'is-locked');
    if (state) {
        frame.classList.add(state);
    }
}

function setHoldProgress(bar, ratio) {
    if (! bar) {
        return;
    }
    const pct = Math.max(0, Math.min(1, ratio)) * 100;
    bar.style.width = `${pct}%`;
    bar.parentElement?.classList.toggle('is-active', pct > 0 && pct < 100);
    bar.parentElement?.classList.toggle('is-complete', pct >= 100);
}

function estimatePose(landmarks) {
    const nose = landmarks.getNose()[3];
    const leftEye = landmarks.getLeftEye()[0];
    const rightEye = landmarks.getRightEye()[3];
    const jaw = landmarks.getJawOutline();
    const chin = jaw[16] || jaw[jaw.length - 1];
    const brow = landmarks.getLeftEyeBrow()[2];

    const eyeMidX = (leftEye.x + rightEye.x) / 2;
    const eyeDist = Math.max(Math.abs(rightEye.x - leftEye.x), 1);
    const yaw = (nose.x - eyeMidX) / eyeDist;

    const eyeMidY = (leftEye.y + rightEye.y) / 2;
    const faceH = Math.max(Math.abs(chin.y - brow.y), 1);
    const pitch = (eyeMidY - nose.y) / faceH;

    return { yaw, pitch };
}

function detectorOptions(opts) {
    return new faceapi.TinyFaceDetectorOptions(opts);
}

/** Fast path: face box + landmarks only (for live pose guidance). */
async function detectFaceLite(video) {
    const detection = await faceapi
        .detectSingleFace(video, detectorOptions(SCAN_OPTIONS))
        .withFaceLandmarks();

    if (! detection) {
        return null;
    }

    return detection;
}

/** Full path: include descriptor (only when locking a pose / check-in). */
async function detectFaceFull(video) {
    const detection = await faceapi
        .detectSingleFace(video, detectorOptions(LOCK_OPTIONS))
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (! detection) {
        throw new Error('Wajah tidak terdeteksi. Pastikan wajah di dalam bingkai.');
    }

    return detection;
}

async function detectFace(video) {
    return detectFaceFull(video);
}

function capturePhoto(video, canvas) {
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, width, height);
    return canvas.toDataURL('image/jpeg', 0.82);
}

function averageDescriptors(list) {
    if (list.length === 0) {
        return [];
    }
    const len = list[0].length;
    const avg = new Array(len).fill(0);
    list.forEach((desc) => {
        for (let i = 0; i < len; i += 1) {
            avg[i] += desc[i];
        }
    });
    return avg.map((v) => v / list.length);
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
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    });
}

function flashCapture(root) {
    const flash = root.querySelector('[data-face-flash]');
    if (! flash) {
        return;
    }
    flash.classList.remove('is-on');
    // force reflow so animation can replay
    void flash.offsetWidth;
    flash.classList.add('is-on');
    window.setTimeout(() => flash.classList.remove('is-on'), 420);
}

function bindSimpleCapture(root, { needGps }) {
    const video = root.querySelector('[data-attendance-video]');
    const canvas = root.querySelector('[data-attendance-canvas]');
    const form = root.querySelector('[data-attendance-form]');
    const status = root.querySelector('[data-attendance-status]');
    const submit = root.querySelector('[data-attendance-submit]');
    const photoInput = root.querySelector('[data-attendance-photo]');
    const descriptorInput = root.querySelector('[data-attendance-descriptor]');
    const latInput = root.querySelector('[data-attendance-lat]');
    const lngInput = root.querySelector('[data-attendance-lng]');
    const frame = root.querySelector('[data-face-frame]');

    if (! video || ! canvas || ! form || ! photoInput || ! descriptorInput) {
        return;
    }

    let ready = false;

    const boot = async () => {
        try {
            setStatus(status, 'Menyiapkan kamera…');
            setFrameState(frame, 'is-searching');
            await Promise.all([
                ensureModels(),
                startCamera(video, { lowRes: false }),
            ]);
            if (needGps) {
                setStatus(status, 'Membaca lokasi GPS…');
                const gps = await readGps();
                if (latInput) latInput.value = String(gps.lat);
                if (lngInput) lngInput.value = String(gps.lng);
            }
            ready = true;
            if (submit) submit.disabled = false;
            setFrameState(frame, 'is-found');
            setStatus(status, 'Siap — hadapkan wajah lalu tekan tombol.');
        } catch (error) {
            setStatus(status, error.message || 'Gagal menyiapkan absensi.', true);
            if (submit) submit.disabled = true;
            setFrameState(frame, 'is-searching');
        }
    };

    boot();

    const onOrientation = () => {
        if (! ready) return;
        startCamera(video, { lowRes: false }).catch(() => {});
    };
    window.addEventListener('orientationchange', onOrientation);
    window.addEventListener('resize', onOrientation);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (! ready) return;

        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Memproses…';
        }

        try {
            if (needGps && latInput && lngInput && (! latInput.value || ! lngInput.value)) {
                const gps = await readGps();
                latInput.value = String(gps.lat);
                lngInput.value = String(gps.lng);
            }

            setStatus(status, 'Mendeteksi wajah…');
            setFrameState(frame, 'is-locking');
            const detection = await detectFaceFull(video);
            descriptorInput.value = JSON.stringify(Array.from(detection.descriptor));
            photoInput.value = capturePhoto(video, canvas);
            flashCapture(root);
            setFrameState(frame, 'is-locked');
            setStatus(status, 'Mengirim data…');
            stopCamera(video);
            form.submit();
        } catch (error) {
            setStatus(status, error.message || 'Gagal mengambil wajah.', true);
            setFrameState(frame, 'is-found');
            if (submit) {
                submit.disabled = false;
                submit.textContent = needGps ? 'Ambil & Absen' : 'Simpan & lanjut';
            }
        }
    });

    window.addEventListener('beforeunload', () => stopCamera(video));
}

function bindGuidedEnroll(root) {
    const video = root.querySelector('[data-attendance-video]');
    const canvas = root.querySelector('[data-attendance-canvas]');
    const form = root.querySelector('[data-attendance-form]');
    const status = root.querySelector('[data-attendance-status]');
    const guideText = root.querySelector('[data-face-guide-text]');
    const guideHint = root.querySelector('[data-face-guide-hint]');
    const poseCount = root.querySelector('[data-face-pose-count]');
    const poseList = root.querySelector('[data-face-pose-list]');
    const progressFill = root.querySelector('[data-face-progress-fill]');
    const holdBar = root.querySelector('[data-face-hold-bar]');
    const submit = root.querySelector('[data-attendance-submit]');
    const photoInput = root.querySelector('[data-attendance-photo]');
    const descriptorInput = root.querySelector('[data-attendance-descriptor]');
    const frame = root.querySelector('[data-face-frame]');

    if (! video || ! canvas || ! form || ! photoInput || ! descriptorInput) {
        return;
    }

    const HOLD_MS = 380;
    const COOLDOWN_MS = 280;
    const LOOP_GAP_MS = 40;

    let poseIndex = 0;
    const captured = [];
    let centerPhoto = null;
    let ready = false;
    let alive = true;
    let holdStartedAt = 0;
    let cooldownUntil = 0;
    let locking = false;
    let lastStatusKey = '';

    const speakStatus = (text, isError = false) => {
        const key = `${isError ? 'e' : 'o'}:${text}`;
        if (key === lastStatusKey) {
            return;
        }
        lastStatusKey = key;
        setStatus(status, text, isError);
    };

    const refreshPoseUi = () => {
        const pose = ENROLL_POSES[poseIndex];
        const doneCount = captured.length;
        const total = ENROLL_POSES.length;

        if (guideText) {
            guideText.textContent = pose
                ? pose.label
                : 'Selesai — tekan Simpan & lanjut';
        }
        if (guideHint) {
            guideHint.textContent = pose
                ? pose.hint
                : 'Wajah sudah terdaftar';
        }
        if (poseCount) {
            poseCount.textContent = `${doneCount} / ${total}`;
        }
        if (progressFill) {
            progressFill.style.width = `${(doneCount / total) * 100}%`;
        }
        poseList?.querySelectorAll('[data-pose]').forEach((item) => {
            const id = item.getAttribute('data-pose');
            const done = captured.some((row) => row.id === id);
            const current = pose && pose.id === id;
            item.classList.toggle('is-done', done);
            item.classList.toggle('is-current', Boolean(current));
        });
        if (submit) {
            submit.disabled = doneCount < total;
        }
    };

    const finishAll = (detection) => {
        descriptorInput.value = JSON.stringify(
            averageDescriptors(captured.map((c) => c.descriptor)),
        );
        photoInput.value = centerPhoto || capturePhoto(video, canvas);
        speakStatus('Semua pose selesai. Tekan Simpan & lanjut.');
        setFrameState(frame, 'is-locked');
        setHoldProgress(holdBar, 1);
        if (submit) submit.disabled = false;
        alive = false;
        return detection;
    };

    const lockPose = async (pose) => {
        locking = true;
        setFrameState(frame, 'is-locking');
        speakStatus('Mengunci pose…');

        try {
            const detection = await detectFaceFull(video);
            const { yaw, pitch } = estimatePose(detection.landmarks);
            const okYaw = yaw >= pose.yawMin && yaw <= pose.yawMax;
            const okPitch = pitch >= pose.pitchMin && pitch <= pose.pitchMax;

            if (! okYaw || ! okPitch) {
                holdStartedAt = 0;
                setHoldProgress(holdBar, 0);
                setFrameState(frame, 'is-found');
                speakStatus('Tahan pose lagi…');
                return;
            }

            captured.push({
                id: pose.id,
                descriptor: Array.from(detection.descriptor),
            });

            if (pose.id === 'center' || ! centerPhoto) {
                centerPhoto = capturePhoto(video, canvas);
            }

            flashCapture(root);
            poseIndex += 1;
            holdStartedAt = 0;
            cooldownUntil = Date.now() + COOLDOWN_MS;
            setHoldProgress(holdBar, 0);
            refreshPoseUi();
            speakStatus(`✓ ${pose.short} tersimpan`);

            if (captured.length >= ENROLL_POSES.length) {
                finishAll(detection);
                setFrameState(frame, 'is-locked');
                return;
            }

            setFrameState(frame, 'is-found');
        } catch (_) {
            holdStartedAt = 0;
            setHoldProgress(holdBar, 0);
            setFrameState(frame, 'is-searching');
            speakStatus('Wajah hilang — masukkan lagi ke bingkai');
        } finally {
            locking = false;
        }
    };

    const scanLoop = async () => {
        if (! alive || ! ready || poseIndex >= ENROLL_POSES.length) {
            return;
        }

        const pose = ENROLL_POSES[poseIndex];
        const now = Date.now();

        try {
            if (locking || now < cooldownUntil) {
                // pause singkat antar pose / saat mengunci
            } else {
                const detection = await detectFaceLite(video);

                if (! detection) {
                    holdStartedAt = 0;
                    setHoldProgress(holdBar, 0);
                    setFrameState(frame, 'is-searching');
                    speakStatus('Cari wajah di bingkai…');
                } else {
                    const { yaw, pitch } = estimatePose(detection.landmarks);
                    const okYaw = yaw >= pose.yawMin && yaw <= pose.yawMax;
                    const okPitch = pitch >= pose.pitchMin && pitch <= pose.pitchMax;

                    if (okYaw && okPitch) {
                        if (! holdStartedAt) {
                            holdStartedAt = now;
                        }
                        const held = now - holdStartedAt;
                        const ratio = held / HOLD_MS;
                        setHoldProgress(holdBar, ratio);
                        setFrameState(frame, 'is-locking');
                        speakStatus('Tahan…');

                        if (held >= HOLD_MS) {
                            await lockPose(pose);
                        }
                    } else {
                        holdStartedAt = 0;
                        setHoldProgress(holdBar, 0);
                        setFrameState(frame, 'is-found');
                        speakStatus(pose.label);
                    }
                }
            }
        } catch (_) {
            holdStartedAt = 0;
            setHoldProgress(holdBar, 0);
            setFrameState(frame, 'is-searching');
            speakStatus('Cari wajah di bingkai…');
        }

        if (alive && poseIndex < ENROLL_POSES.length) {
            window.setTimeout(scanLoop, LOOP_GAP_MS);
        }
    };

    const boot = async () => {
        try {
            speakStatus('Menyiapkan kamera…');
            setFrameState(frame, 'is-searching');
            refreshPoseUi();
            await Promise.all([
                ensureModels(),
                startCamera(video, { lowRes: true }),
            ]);
            ready = true;
            refreshPoseUi();
            speakStatus('Hadap depan — otomatis');
            scanLoop();
        } catch (error) {
            speakStatus(error.message || 'Gagal menyiapkan kamera.', true);
            alive = false;
        }
    };

    boot();

    const restartCam = () => {
        if (! ready || ! alive) return;
        startCamera(video, { lowRes: true }).catch(() => {});
    };
    window.addEventListener('orientationchange', restartCam);
    window.addEventListener('resize', restartCam);

    form.addEventListener('submit', (event) => {
        if (captured.length < ENROLL_POSES.length) {
            event.preventDefault();
            speakStatus('Tunggu sampai semua pose tersimpan.', true);
            return;
        }

        if (! descriptorInput.value || ! photoInput.value) {
            descriptorInput.value = JSON.stringify(
                averageDescriptors(captured.map((c) => c.descriptor)),
            );
            photoInput.value = centerPhoto || capturePhoto(video, canvas);
        }

        alive = false;
        stopCamera(video);
    });

    window.addEventListener('beforeunload', () => {
        alive = false;
        stopCamera(video);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const check = document.querySelector('[data-attendance-check]');
    if (check) {
        bindSimpleCapture(check, { needGps: true });
    }

    const enroll = document.querySelector('[data-attendance-enroll]');
    if (enroll) {
        if (enroll.getAttribute('data-face-guide') === '1') {
            bindGuidedEnroll(enroll);
        } else {
            bindSimpleCapture(enroll, { needGps: false });
        }
    }
});
