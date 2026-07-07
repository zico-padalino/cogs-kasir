# COGS Sederhana — Aplikasi Mobile (Expo)

Aplikasi Android/iOS yang berjalan **penuh di perangkat (offline, tanpa server Laravel)**. Tiga modul semuanya lokal:

- **COGS** — hitung Harga Pokok Produksi lewat wizard 6 langkah.
- **Kasir POS** — point of sale di perangkat + proses pesanan online yang masuk.
- **Pesan Online** — pelanggan pilih menu dan kirim pesanan langsung ke kasir.

UI/UX mengikuti gaya web Laravel (kartu statistik, warna brand indigo) tapi diadaptasi untuk layar HP. **Tidak ada dependensi ke Laravel** — tidak perlu server, `.env` URL, atau koneksi jaringan.

## Prasyarat

- Node.js 20+
- Akun [Expo](https://expo.dev) (gratis) untuk build APK di cloud

## Aplikasi COGS Lokal (utama, offline)

Tanpa mengubah Laravel, aplikasi menghitung COGS langsung di perangkat lewat 6 langkah:

1. **Biaya Overhead** — tarif biaya tidak langsung (mis. 15% dari bahan, atau Rp/jam).
2. **Daftar Produk** — bahan baku + produk jadi/setengah jadi.
3. **Resep (BOM)** — susunan bahan per 1 unit produk (dengan scrap %).
4. **Stok Bahan** — pembelian bahan per lot (jumlah & harga) → dasar FIFO / rata-rata.
5. **Produksi** — buat order, mulai, lalu selesaikan → COGS dihitung otomatis.
6. **Hasil COGS** — rincian Bahan + Tenaga Kerja + Overhead, per total & per unit.

Detail teknis:

- Database SQLite: `cogs_local.db` di perangkat
- Data demo bakery (tepung, gula, mentega, adonan, roti tawar) otomatis terisi
- Mesin perhitungan (FIFO, rata-rata tertimbang, roll-up BOM, overhead absorption) diport dari service Laravel
- Reset ke data demo kapan saja dari dalam aplikasi

Dari beranda aplikasi → **Buka Aplikasi COGS**.

## Kasir POS & Pesan Online (lokal, offline)

Kedua modul ini kini berjalan sepenuhnya di perangkat memakai SQLite `pos_local.db` (terpisah dari COGS):

- **Pesan Online** — pelanggan memilih menu, isi nama, lalu **Kirim Pesanan**. Pesanan tersimpan berstatus `submitted`.
- **Kasir POS** — tiga tab:
  - **Menu** → tap produk untuk masuk keranjang.
  - **Kasir** → atur qty, pilih metode bayar (Tunai/QRIS/Transfer), bayar → transaksi tersimpan.
  - **Online** → daftar pesanan online masuk; kasir memproses pembayaran → status jadi `paid`.
- **Riwayat Transaksi** — daftar pesanan yang sudah dibayar beserta detail item.

Data menu demo (kopi, pastry, makanan) otomatis terisi dan bisa langsung dipakai.

## Setup cepat

```bash
cd mobile
npm install
npm start
```

Scan QR dengan **Expo Go** di Android, atau tekan `a` untuk emulator. Tidak perlu `.env` atau server — semua data tersimpan di perangkat.

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
| **Beranda** (`/`) | Pintasan ke COGS, Kasir POS, Pesan Online, Riwayat |
| **Aplikasi COGS** (`/cogs`) | Wizard 6 langkah + hasil COGS (lokal) |
| **Kasir POS** (`/local-kasir`) | Menu, keranjang, pembayaran, pesanan online masuk (lokal) |
| **Pesan Online** (`/pesan-online`) | Pelanggan pilih menu & kirim pesanan (lokal) |
| **Riwayat** (`/local-orders`) | Daftar transaksi yang sudah dibayar (lokal) |

Semua data tersimpan di perangkat via SQLite — tidak ada koneksi ke Laravel.

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
