/**
 * Geolocation helpers — tuned for Safari/iOS where permission prompts
 * often require a user gesture and high-accuracy reads can time out.
 */

export function geolocationErrorMessage(error) {
    if (! error) {
        return 'Gagal membaca lokasi GPS.';
    }

    if (typeof error === 'string') {
        return error;
    }

    const code = error.code;

    if (code === 1) {
        return 'Akses lokasi ditolak. Di iPhone: Pengaturan → Privasi & Keamanan → Layanan Lokasi → Safari → Izinkan. Lalu ketuk "Izinkan lokasi" di bawah.';
    }

    if (code === 2) {
        return 'Lokasi tidak tersedia. Pastikan GPS/Layanan Lokasi HP aktif, lalu coba lagi.';
    }

    if (code === 3) {
        return 'Waktu habis menunggu sinyal GPS. Pindah ke area terbuka atau ketuk "Coba lagi".';
    }

    return error.message || 'Gagal membaca lokasi GPS.';
}

export async function queryGeolocationPermission() {
    if (! navigator.permissions?.query) {
        return 'unknown';
    }

    try {
        const result = await navigator.permissions.query({ name: 'geolocation' });

        return result.state;
    } catch {
        return 'unknown';
    }
}

function getPosition(options) {
    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
}

function watchFirstPosition(options, timeoutMs) {
    return new Promise((resolve, reject) => {
        if (! navigator.geolocation?.watchPosition) {
            reject(Object.assign(new Error('GPS tidak didukung.'), { code: 0 }));
            return;
        }

        let watchId = null;
        const timer = window.setTimeout(() => {
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
            }
            reject(Object.assign(new Error('Waktu habis menunggu sinyal GPS.'), { code: 3 }));
        }, timeoutMs);

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                window.clearTimeout(timer);
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                }
                resolve(pos);
            },
            (err) => {
                window.clearTimeout(timer);
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                }
                reject(err);
            },
            options,
        );
    });
}

function normalizePosition(pos) {
    return {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
    };
}

/**
 * Read GPS with high-accuracy first, then low-accuracy, then watchPosition.
 * Pass { requireFresh: true } to avoid cached coordinates for attendance.
 */
export async function readGps({ requireFresh = true, timeout = 25000 } = {}) {
    if (! window.isSecureContext) {
        throw Object.assign(
            new Error('Lokasi hanya bisa diakses lewat HTTPS. Buka halaman absensi dengan https://.'),
            { code: 0 },
        );
    }

    if (! navigator.geolocation) {
        throw Object.assign(new Error('GPS tidak didukung di perangkat ini.'), { code: 0 });
    }

    const maximumAge = requireFresh ? 0 : 10000;
    const attempts = [
        { enableHighAccuracy: true, timeout, maximumAge },
        { enableHighAccuracy: false, timeout: Math.min(timeout, 15000), maximumAge: 15000 },
    ];

    let lastError = null;

    for (const options of attempts) {
        try {
            const pos = await getPosition(options);
            return normalizePosition(pos);
        } catch (error) {
            lastError = error;
            if (error?.code === 1) {
                throw error;
            }
        }
    }

    try {
        const pos = await watchFirstPosition(
            { enableHighAccuracy: true, maximumAge: 0, timeout },
            timeout,
        );
        return normalizePosition(pos);
    } catch (error) {
        throw lastError || error;
    }
}
