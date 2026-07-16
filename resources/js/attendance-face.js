/**
 * Face capture + GPS for attendance check-in/out and employee enroll.
 * Uses @vladmandic/face-api from CDN (TinyFaceDetector + FaceLandmark68 + FaceRecognition).
 */

const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model';
const FACE_API_SRC = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/dist/face-api.min.js';

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
    const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: 'user',
            width: { ideal: 640 },
            height: { ideal: 480 },
        },
    });
    video.srcObject = stream;
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

async function detectDescriptor(video) {
    const detection = await faceapi
        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (! detection) {
        throw new Error('Wajah tidak terdeteksi. Hadapkan wajah ke kamera.');
    }

    return Array.from(detection.descriptor);
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

function bindPanel(root, { needGps }) {
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

    (async () => {
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
    })();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (! ready) {
            return;
        }

        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Memproses…';
        }

        try {
            if (needGps && latInput && lngInput && (! latInput.value || ! lngInput.value)) {
                setStatus(status, 'Membaca lokasi GPS…');
                const gps = await readGps();
                latInput.value = String(gps.lat);
                lngInput.value = String(gps.lng);
            }

            setStatus(status, 'Mendeteksi wajah…');
            const descriptor = await detectDescriptor(video);
            descriptorInput.value = JSON.stringify(descriptor);
            photoInput.value = capturePhoto(video, canvas);
            setStatus(status, 'Mengirim data…');
            stopCamera(video);
            form.submit();
        } catch (error) {
            setStatus(status, error.message || 'Gagal mengambil wajah.', true);
            if (submit) {
                submit.disabled = false;
                submit.textContent = needGps ? 'Ambil & Absen' : 'Simpan wajah dari kamera';
            }
        }
    });

    window.addEventListener('beforeunload', () => stopCamera(video));
}

document.addEventListener('DOMContentLoaded', () => {
    const check = document.querySelector('[data-attendance-check]');
    if (check) {
        bindPanel(check, { needGps: true });
    }

    const enroll = document.querySelector('[data-attendance-enroll]');
    if (enroll) {
        bindPanel(enroll, { needGps: false });
    }
});
