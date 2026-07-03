# Kasir POS — Aplikasi Mobile (Expo)

Aplikasi Android/iOS yang membungkus UI Laravel (kasir & pesan online) lewat WebView, dengan shell native yang mengikuti gaya mobile Laravel (warna brand, kartu, tombol besar).

## Prasyarat

- Node.js 20+
- Akun [Expo](https://expo.dev) (gratis) untuk build APK di cloud
- Server Laravel sudah jalan dan bisa diakses dari HP

## Mode Kasir Lokal (khusus Android)

Tanpa mengubah Laravel, Android bisa uji POS langsung dengan data tersimpan di HP:

- Database SQLite: `cogs_local.db` di perangkat
- Menu demo (kopi, pastry, snack) otomatis terisi
- Keranjang & riwayat transaksi tersimpan lokal
- Nomor order `001`, `002`, … reset per hari (seperti web)

Dari beranda aplikasi → **Buka Kasir Lokal**.

Mode server Laravel (WebView) tetap tersedia jika ingin terhubung ke backend asli.

## Setup cepat

```bash
cd mobile
npm install
cp .env.example .env
```

Edit `.env`:

```env
# Emulator Android → host PC
EXPO_PUBLIC_APP_URL=http://10.0.2.2:8000

# HP fisik di WiFi yang sama → ganti IP komputer/server
# EXPO_PUBLIC_APP_URL=http://192.168.1.10:8000

# Production HTTPS
# EXPO_PUBLIC_APP_URL=https://domain-anda.com
```

Jalankan untuk development:

```bash
npm start
```

Scan QR dengan **Expo Go** di Android, atau tekan `a` untuk emulator.

## Build APK (unduh & install di Android)

> **Error `Invalid UUID appId`?**  
> Artinya `projectId` EAS belum terdaftar. Jalankan **`npx eas init`** dulu (langkah 2), baru `npm run build:apk`.

### 1. Login Expo

```bash
cd mobile
npx eas login
```

### 2. Inisialisasi project EAS (wajib, sekali saja)

```bash
npx eas init
```

- Pilih **Create a new project** (atau link ke project Expo yang sudah ada)
- Perintah ini menulis UUID valid ke `app.json` → `expo.extra.eas.projectId`
- **Jangan** isi manual dengan teks seperti `replace-with-eas-project-id`

### 3. Build APK

```bash
npm run build:apk
```

Setelah selesai, Expo memberi link unduh APK. Transfer ke HP → install (izinkan "Unknown sources" jika perlu).

**Kenapa lama?** Build EAS jalan di server Expo (antrean + compile Android dari nol). Biasanya **10–25 menit**, kadang lebih saat ramai. Itu normal — bukan error.

| Cara | Kecepatan | Kapan dipakai |
|------|-----------|---------------|
| **Expo Go** (`npm start`) | ~10 detik | Uji coba harian, Kasir Lokal |
| **Build lokal** (`npm run build:apk:local`) | ~5–15 menit* | APK install tanpa antrean cloud |
| **EAS cloud** (`npm run build:apk`) | ~10–25 menit | Tanpa Android Studio di PC |

\*Build lokal butuh **Android Studio** + SDK terpasang. Build berikutnya lebih cepat (cache Gradle).

### Uji cepat tanpa build APK (paling disarankan)

Untuk coba **Kasir Lokal** di Android tanpa menunggu build:

```bash
npm start
```

Scan QR dengan aplikasi **Expo Go** — tidak perlu EAS / APK. Data tetap tersimpan di HP (mode lokal).

### Build APK di komputer sendiri (lebih cepat dari antrean EAS)

Prasyarat: install [Android Studio](https://developer.android.com/studio), buka sekali, pasang Android SDK.

```bash
npm run build:apk:local
```

APK hasil build:

```
mobile/android/app/build/outputs/apk/release/app-release.apk
```

Copy ke HP → install.

### Cek status build EAS (kalau tetap pakai cloud)

```bash
npx eas build:list
```

Buka juga dashboard: https://expo.dev → project **cogs-kasir** → Builds.

### Build AAB (Google Play)

```bash
npm run build:aab
```

## Struktur layar

| Layar | Isi |
|-------|-----|
| **Beranda** | Pilih Kasir POS atau Pesan Online |
| **Kasir** | WebView → `/kasir` |
| **Pesan** | WebView → `/pesan` |
| **Pengaturan** | Ubah URL server Laravel |

URL server juga bisa diubah dari aplikasi tanpa rebuild (disimpan di perangkat).

## Tips koneksi server

| Situasi | URL |
|---------|-----|
| Emulator Android | `http://10.0.2.2:8000` |
| HP fisik + `php artisan serve` | `http://IP-KOMPUTER:8000` |
| Production | `https://domain-anda.com` |

Pastikan Laravel dijalankan dengan host yang bisa diakses jaringan:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Untuk production, gunakan **HTTPS** dan set `APP_URL` di `.env` Laravel sesuai domain.

## Regenerasi ikon

Ikon diambil dari `public/icons/icon-512.png` Laravel:

```bash
# dari root project
copy public\icons\icon-512.png mobile\assets\icon.png
copy public\icons\icon-512.png mobile\assets\adaptive-icon.png
copy public\icons\icon-512.png mobile\assets\splash-icon.png
```

Lalu build ulang APK.

## Package Android

`com.cogsperhitungan.kasir` — ubah di `app.json` jika diperlukan.
