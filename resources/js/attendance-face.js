/**
 * Face capture + GPS for attendance check-in/out and guided face enroll.
 * Uses @vladmandic/face-api from CDN.
 */

const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';
const FACE_API_SRC = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.min.js';

const ENROLL_POSES = [
    { id: 'center', label: 'Hadap depan (lurus ke kamera)', yawMin: -0.12, yawMax: 0.12, pitchMin: -0.12, pitchMax: 0.12 },
    { id: 'left', label: 'Putar wajah ke KIRI', yawMin: 0.18, yawMax: 0.85, pitchMin: -0.25, pitchMax: 0.25 },
    { id: 'right', label: 'Putar wajah ke KANAN', yawMin: -0.85, yawMax: -0.18, pitchMin: -0.25, pitchMax: 0.25 },
    { id: 'up', label: 'Angkat wajah ke ATAS', yawMin: -0.2, yawMax: 0.2, pitchMin: 0.12, pitchMax: 0.7 },
    { id: 'down', label: 'Tundukkan wajah ke BAWAH', yawMin: -0.2, yawMax: 0.2, pitchMin: -0.7, pitchMax: -0.12 },
];

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

function setStatus(el, text, isError = false) {
    if (! el) {
        return;
    }
    el.textContent = text;
    el.classList.toggle('is-error', isError);
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

async function detectFace(video) {
    const detection = await faceapi
        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.45 }))
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (! detection) {
        throw new Error('Wajah tidak terdeteksi. Pastikan wajah di dalam bingkai.');
    }

    return detection;
}

function capturePhoto(video, canvas) {
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, width, height);
    return canvas.toDataURL('image/jpeg', 0.85);
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

    if (! video || ! canvas || ! form || ! photoInput || ! descriptorInput) {
        return;
    }

    let ready = false;

    const boot = async () => {
        try {
            setStatus(status, 'Memuat model wajah…');
            await ensureModels();
            setStatus(status, 'Mengaktifkan kamera…');
            await startCamera(video);
            if (needGps) {
                setStatus(status, 'Membaca lokasi GPS…');
                const gps = await readGps();
                if (latInput) latInput.value = String(gps.lat);
                if (lngInput) lngInput.value = String(gps.lng);
            }
            ready = true;
            if (submit) submit.disabled = false;
            setStatus(status, 'Siap — hadapkan wajah lalu tekan tombol.');
        } catch (error) {
            setStatus(status, error.message || 'Gagal menyiapkan absensi.', true);
            if (submit) submit.disabled = true;
        }
    };

    boot();

    const onOrientation = () => {
        if (! ready) return;
        startCamera(video).catch(() => {});
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
            const detection = await detectFace(video);
            descriptorInput.value = JSON.stringify(Array.from(detection.descriptor));
            photoInput.value = capturePhoto(video, canvas);
            setStatus(status, 'Mengirim data…');
            stopCamera(video);
            form.submit();
        } catch (error) {
            setStatus(status, error.message || 'Gagal mengambil wajah.', true);
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
    const poseCount = root.querySelector('[data-face-pose-count]');
    const poseList = root.querySelector('[data-face-pose-list]');
    const captureBtn = root.querySelector('[data-face-capture-pose]');
    const submit = root.querySelector('[data-attendance-submit]');
    const photoInput = root.querySelector('[data-attendance-photo]');
    const descriptorInput = root.querySelector('[data-attendance-descriptor]');

    if (! video || ! canvas || ! form || ! photoInput || ! descriptorInput || ! captureBtn) {
        return;
    }

    let poseIndex = 0;
    const captured = [];
    let centerPhoto = null;
    let ready = false;

    const refreshPoseUi = () => {
        const pose = ENROLL_POSES[poseIndex];
        if (guideText) {
            guideText.textContent = pose
                ? pose.label
                : 'Semua pose selesai. Tekan Simpan & lanjut.';
        }
        if (poseCount) {
            poseCount.textContent = `${captured.length} / ${ENROLL_POSES.length}`;
        }
        poseList?.querySelectorAll('[data-pose]').forEach((item) => {
            const id = item.getAttribute('data-pose');
            const done = captured.some((row) => row.id === id);
            const current = pose && pose.id === id;
            item.classList.toggle('is-done', done);
            item.classList.toggle('is-current', Boolean(current));
        });
        captureBtn.disabled = ! ready || ! pose;
        captureBtn.textContent = pose ? 'Ambil pose ini' : 'Selesai';
        if (submit) {
            submit.disabled = captured.length < ENROLL_POSES.length;
        }
    };

    const boot = async () => {
        try {
            setStatus(status, 'Memuat model wajah…');
            await ensureModels();
            setStatus(status, 'Mengaktifkan kamera…');
            await startCamera(video);
            ready = true;
            setStatus(status, 'Ikuti instruksi di atas bingkai.');
            refreshPoseUi();
        } catch (error) {
            setStatus(status, error.message || 'Gagal menyiapkan kamera.', true);
            captureBtn.disabled = true;
        }
    };

    boot();

    const restartCam = () => {
        if (! ready) return;
        startCamera(video).catch(() => {});
    };
    window.addEventListener('orientationchange', restartCam);
    window.addEventListener('resize', restartCam);

    captureBtn.addEventListener('click', async () => {
        if (! ready || poseIndex >= ENROLL_POSES.length) {
            return;
        }

        const pose = ENROLL_POSES[poseIndex];
        captureBtn.disabled = true;
        setStatus(status, 'Memeriksa pose…');

        try {
            const detection = await detectFace(video);
            const { yaw, pitch } = estimatePose(detection.landmarks);
            const okYaw = yaw >= pose.yawMin && yaw <= pose.yawMax;
            const okPitch = pitch >= pose.pitchMin && pitch <= pose.pitchMax;

            if (! okYaw || ! okPitch) {
                throw new Error(`Pose belum pas. ${pose.label}, lalu tekan Ambil lagi.`);
            }

            captured.push({
                id: pose.id,
                descriptor: Array.from(detection.descriptor),
            });

            if (pose.id === 'center' || ! centerPhoto) {
                centerPhoto = capturePhoto(video, canvas);
            }

            poseIndex += 1;
            setStatus(status, `Pose “${pose.id}” berhasil.`);
            refreshPoseUi();

            if (captured.length >= ENROLL_POSES.length) {
                descriptorInput.value = JSON.stringify(averageDescriptors(captured.map((c) => c.descriptor)));
                photoInput.value = centerPhoto || capturePhoto(video, canvas);
                setStatus(status, 'Semua pose selesai. Tekan Simpan & lanjut.');
                if (submit) submit.disabled = false;
            }
        } catch (error) {
            setStatus(status, error.message || 'Gagal mengambil pose.', true);
            captureBtn.disabled = false;
        }
    });

    form.addEventListener('submit', (event) => {
        if (captured.length < ENROLL_POSES.length) {
            event.preventDefault();
            setStatus(status, 'Selesaikan semua pose wajah dulu.', true);
            return;
        }

        if (! descriptorInput.value || ! photoInput.value) {
            descriptorInput.value = JSON.stringify(averageDescriptors(captured.map((c) => c.descriptor)));
            photoInput.value = centerPhoto || capturePhoto(video, canvas);
        }

        stopCamera(video);
    });

    window.addEventListener('beforeunload', () => stopCamera(video));
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
