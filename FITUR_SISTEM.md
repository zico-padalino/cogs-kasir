# COGS Kasir — Katalog Fitur Sistem

Dokumen ini merangkum **seluruh fitur** sistem untuk keperluan presentasi penjualan (PPT).  
Dirancang agar mudah dipecah per slide: ringkasan → keunggulan → modul → detail fitur → alur bisnis.

---

## 1. Ringkasan Produk (Slide Pembuka)

| | |
|---|---|
| **Nama produk** | **COGS Kasir** |
| **Tagline** | Dari bahan sampai harga jual — lalu jual di kasir |
| **Untuk siapa** | Pemilik kedai / kafe / bakery / restoran skala UMKM |
| **Masalah yang diselesaikan** | Modal menu tidak jelas, stok tidak terkontrol, kasir terpisah dari HPP, absensi & gaji masih manual |
| **Solusi** | Satu sistem: hitung modal menu → jual di POS → pantau laba, stok, karyawan, dan kas |

**Satu kalimat elevator pitch:**  
*Sistem all-in-one untuk usaha F&B: menghitung HPP/modal setiap menu dari resep, menjual lewat kasir (web & mobile), menerima pesanan meja via QR, mengelola dapur, absensi GPS, gaji, dan pembukuan — semuanya terhubung.*

---

## 2. Tiga Modul Utama

| Modul | Nama di sistem | Pengguna | Fokus |
|---|---|---|---|
| **COGS** | Hitung Modal Menu | Pemilik / kitchen / cost control | Bahan → resep → modal → harga jual |
| **Kasir** | Point of Sale | Kasir / dapur / bar | Penjualan, open bill, dapur, pembukuan |
| **Admin** | Modul Admin | Owner / HRD toko | Karyawan, absensi, gaji, akun, pengaturan |

Satu akun bisa punya **lebih dari satu modul**. Ada hub **Ganti Modul** untuk berpindah peran tanpa logout.

---

## 3. Keunggulan Penjualan (USP)

1. **COGS terhubung langsung ke Kasir** — setiap penjualan otomatis konsumsi stok & catat HPP/laba, bukan POS berdiri sendiri.
2. **Bahasa warung, bukan jargon akuntansi** — wizard “Hitung Modal Menu” mudah diikuti pemilik UMKM.
3. **Pesan online dari meja (QR)** — pelanggan scan → pesan dari HP → masuk antrean kasir & dapur.
4. **QRIS dinamis** — nominal pembayaran = total order (dari payload merchant).
5. **Dapur & Bar terpisah** — tiket dapur otomatis split makanan vs minuman.
6. **PIN stasiun kasir** — multi-operator aman di satu perangkat.
7. **Absensi GPS + selfie** — check-in/out dengan radius toko, potongan gaji otomatis.
8. **Cetak thermal + struk WhatsApp** — siap operasional harian.
9. **Web + aplikasi mobile** — kasir, dapur, dan COGS bisa diakses dari HP/tablet.
10. **Satu toko = satu instalasi** — fokus operasional kedai, sederhana untuk diadopsi.

---

## 4. Modul COGS — Hitung Modal Menu

**Branding modul:** *Hitung Modal Menu — Dari bahan sampai harga jual*

### 4.1 Wizard Setup (4 langkah)

Progress setup ditampilkan di sidebar (persentase selesai).

| Langkah | Fitur | Manfaat bisnis |
|---|---|---|
| 1 | **Biaya Lain (Overhead)** | Catat listrik, sewa, air — dialokasikan ke modal menu |
| 2 | **Bahan Baku** | Master bahan + stok + harga beli |
| 3 | **Menu & Resep** | Resep (BOM) per menu + add-on |
| 4 | **Harga Jual** | Tentukan harga dari modal / % untung; tampilkan di kasir |

### 4.2 Fitur Lengkap COGS

| Fitur | Deskripsi singkat | Poin jual |
|---|---|---|
| **Beranda / Dashboard** | Omzet hari & bulan, diskon, modal terjual, laba kotor, margin, tren 7 hari, top menu, stok, dana usaha | Pemilik lihat kesehatan usaha sekali buka |
| **Biaya Lain / Overhead** | Tarif aktif; alokasi % bahan, % upah, per jam, per unit | Modal lebih akurat, bukan cuma bahan |
| **Bahan Baku** | CRUD, terima stok (lot), sesuaikan sisa, riwayat, export PDF | Stok & harga beli selalu terkini |
| **Metode costing** | FIFO / rata-rata tertimbang / standard | Sesuai kebiasaan pembukuan toko |
| **Bahan Jadi** | Setengah jadi + resep + stok | Cocok untuk prep dapur (saus, adonan, dll.) |
| **Menu & Resep (BOM)** | Qty bahan, scrap, add-on (harga + konsumsi bahan), hitung modal | Tahu modal nyata tiap porsi |
| **Hitung Modal (roll-up)** | Kalkulasi multi-level dari pohon resep | Resep kompleks tetap akurat |
| **Harga Jual** | Harga absolut atau % margin; toggle tampil di kasir | Jual dengan margin yang disadari |
| **Production Order** | Jadwal → mulai → selesai produksi; konsumsi bahan + labor + overhead | HPP produksi tersimpan rapi |
| **Riwayat COGS** | Detail bahan + tenaga kerja + overhead | Audit modal kapan saja |
| **HPP saat penjualan** | Saat bayar di kasir, stok berkurang & COGS terikat transaksi | Laba per transaksi terlihat |
| **Stok Rusak / Waste** | Catat waste bahan/menu + nilai hilang | Kerugian operasional terukur |
| **Inventaris Operasional** | Aset non-COGS (piring, peralatan): terima, rusak, log | Aset dapur ikut terpantau |
| **Dana Usaha** | Saldo usaha: omzet vs pengeluaran (gaji & lain), forecast | Kas usaha tidak “hilang” di kepala |
| **Reset Data COGS** | Reset data costing (demo / mulai ulang) | Cocok pelatihan & onboarding |

---

## 5. Modul Kasir — Point of Sale

### 5.1 Menu Kasir

- Point of Sale  
- Dapur  
- Riwayat  
- Meja QR  
- Menu  
- Kategori  
- Pembukuan  
- Kas Tunai  

### 5.2 Point of Sale (POS)

| Fitur | Deskripsi | Poin jual |
|---|---|---|
| **Layar penjualan** | Grid menu, keranjang, qty, catatan item, add-on | Cepat di jam ramai |
| **Tipe order** | Dine In / Take Away + catatan pelanggan | Sesuai alur kedai |
| **Diskon** | Nominal atau persen | Fleksibel untuk promo |
| **Pembayaran** | Tunai / QRIS / Transfer + kembalian | Multi metode |
| **QRIS dinamis** | QR berisi nominal = total order | Kurangi salah transfer |
| **Open Bill** | Tagihan terbuka; merge meja/nama sama; reservasi stok | Cocok meja duduk lama |
| **Edit transaksi lunas** | Buka ulang order yang sudah paid | Koreksi tanpa drama |
| **Waste dari kasir** | Catat barang rusak/habis saat operasi | Operasional real-time |
| **Notifikasi** | Web Push / FCM / Expo + suara bell | Order online langsung ketahuan |
| **PIN stasiun** | Unlock sesi kasir (TTL), lock, keep-alive | Aman multi-shift |
| **Status jaringan** | Indikator koneksi di toolbar | Kasir sadar jika offline |

### 5.3 Alur Pesanan (Status)

`Draft` → `Menunggu Bayar` → `Menunggu Kasir` → `Siap Bayar` → `Tagihan terbuka` → `Sudah Bayar` → `Selesai` / `Batal`

- Sumber pesanan: **Kasir** atau **Online (Meja)**  
- Kasir bisa konfirmasi, tandai disajikan, atau batalkan pending  

### 5.4 Dapur & Cetak

| Fitur | Deskripsi | Poin jual |
|---|---|---|
| **Layar Dapur** | Tiket item, toggle delivered, auto-poll | Dapur tidak perlu teriak ke kasir |
| **Split dapur vs bar** | Makanan/snack → dapur; minuman → bar | Alur kitchen lebih rapi |
| **Struk PDF** | Pelanggan / dapur / bar; link signed untuk share | Mudah kirim ke pelanggan |
| **Thermal ESC/POS** | Cetak 58/80mm (Thermer Android) | Siap printer kasir umum |
| **WhatsApp struk** | Buka chat dengan link PDF | Tanpa printer tetap bisa kirim bukti |

### 5.5 Meja & Pesan Online (QR)

| Fitur | Deskripsi | Poin jual |
|---|---|---|
| **Kelola meja** | CRUD label meja | Layout toko fleksibel |
| **Barcode / QR toko** | Scan → halaman pesan publik | Pelanggan pesan dari HP sendiri |
| **Pesan Online** | Pilih menu, nama, kirim, bayar tunai-ke-kasir / QRIS, status polling | Kurangi antrian di meja kasir |
| **PWA order** | Bisa dipasang seperti app di HP | Tanpa install Play Store |
| **Legal** | Syarat & Ketentuan + Kebijakan Privasi | Siap operasional resmi |

### 5.6 Master Menu di Kasir

| Fitur | Deskripsi |
|---|---|
| **Kelola menu** | Edit nama, harga, foto; tandai **Habis (sold out)** |
| **Kategori menu** | CRUD kategori tampilan POS & pesan online |
| **Sinkron stok menu** | Target qty stok barang jadi |

### 5.7 Pembukuan & Kas Tunai

| Fitur | Deskripsi | Poin jual |
|---|---|---|
| **Pembukuan** | Omzet kotor/bersih, diskon, waste, breakdown metode bayar, daftar order, filter periode, **PDF** | Laporan harian siap owner |
| **Kas Tunai** | Saldo harian: setoran awal, penjualan tunai, kembalian, pengeluaran kas | Rekonsiliasi laci kas mudah |

---

## 6. Modul Admin — Back-office

### 6.1 Menu Admin

- Dashboard  
- Data Karyawan  
- Absensi  
- QR Absensi  
- Gaji  
- Akses Akun  
- Log Aktivitas  
- Pengaturan  

### 6.2 Fitur Admin

| Fitur | Deskripsi | Poin jual |
|---|---|---|
| **Dashboard Admin** | Ringkasan penjualan (hari/minggu/bulan/all) dikurangi pengeluaran & gaji | Laba bersih lebih realistis |
| **Data Karyawan** | Kode, nama, email, tanggal masuk, gaji pokok & harian, status, jadwal kerja, PIN kasir | Satu database SDM toko |
| **Absensi** | Filter, input manual, paksa checkout, lihat selfie, cetak | Kontrol kehadiran rapi |
| **QR Absensi (publik)** | Scan QR → masuk/pulang + selfie + GPS (radius toko) | Tanpa mesin fingerprint mahal |
| **Status absensi** | Hadir / Izin / Sakit / Alpha / Cuti | Sesuai praktik HR UMKM |
| **Gaji Karyawan** | Generate dari absensi + tarif; input manual; tandai dibayar; potongan telat/alpha/izin/sakit | Gaji otomatis dari kehadiran |
| **Akses Akun** | CRUD user, assign modul/role, reset password | Keamanan & delegasi jelas |
| **Log Aktivitas** | Filter kategori, aksi, tanggal, keyword | Audit trail lengkap |
| **Pengaturan toko** | Nama, tagline, logo/favicon, QRIS, GPS absensi, jam & radius, potongan gaji default | Branding & aturan toko terpusat |
| **Convert gambar menu** | Konversi/kompres ke WebP | Menu lebih ringan di HP pelanggan |

---

## 7. Keamanan & Akses (Cross-cutting)

| Fitur | Manfaat |
|---|---|
| Login email/password (web & API mobile) | Akses terkontrol |
| Role/modul: COGS, Kasir, Admin (+ root) | Pemisahan tugas |
| Multi-modul per user + hub ganti modul | Fleksibel untuk owner |
| Paksa ganti password | Onboarding akun aman |
| PIN akun & PIN karyawan kasir | Stasiun kasir multi-operator |
| Gate absensi (wajib check-in sebelum akses modul) | Disiplin kerja |
| Log aktivitas (login, PIN, transaksi, absensi, CRUD) | Transparansi operasional |

---

## 8. Platform & Saluran Akses

| Platform | Yang bisa dilakukan |
|---|---|
| **Web (desktop/tablet)** | COGS, Kasir, Admin lengkap |
| **PWA Kasir / Order** | Kasir & pesan meja seperti aplikasi |
| **Mobile Expo (Android)** | Login API; modul Kasir, COGS, Dapur, ubah password |
| **Halaman publik** | Pesan online (`/pesan`), absensi QR (`/absensi`) |

**Catatan positioning:** satu instalasi = **satu toko** (bukan SaaS multi-cabang). Cocok untuk kedai yang ingin sistem sendiri, terkontrol, dan siap dioperasikan harian.

---

## 9. Laporan & Analitik (untuk slide “Insight Owner”)

| Laporan | Lokasi |
|---|---|
| Omzet, modal, laba, top menu | Dashboard COGS |
| Omzet net − expense/gaji | Dashboard Admin |
| Pembukuan + PDF + metode bayar | Kasir → Pembukuan |
| Riwayat & ringkasan HPP | Modul COGS |
| PDF stok bahan | Bahan Baku |
| Cetak absensi | Admin Absensi |
| Forecast pengeluaran | Dana Usaha |

---

## 10. Alur Bisnis End-to-End (untuk 1–2 slide diagram)

```text
1. Setup COGS
   Biaya Lain → Bahan Baku → Resep Menu → Harga Jual
                 ↓
2. Operasional Harian
   Absensi GPS → Buka Kasir (PIN) → Terima order (kasir / QR meja)
                 ↓
3. Produksi & Penyajian
   Dapur / Bar → Cetak tiket / thermal → Sajikan
                 ↓
4. Pembayaran
   Tunai / QRIS / Transfer → Stok & HPP otomatis tercatat
                 ↓
5. Tutup Hari
   Kas Tunai → Pembukuan PDF → Pantau laba di Dashboard
                 ↓
6. Periodik
   Generate gaji dari absensi → Bayar → Cek Dana Usaha
```

---

## 11. Kapasitas Server DomaiNesia (untuk API & Trafik)

Bagian ini untuk slide **infrastruktur / rekomendasi hosting** saat pitching ke pemilik usaha.  
Harga & promo mengacu katalog publik DomaiNesia (bisa berubah; cek ulang di [domainesia.com](https://www.domainesia.com) saat penawaran).

### 11.1 Apa yang dimaksud “100 pelanggan”?

| Interpretasi | Artinya untuk server | Kebutuhan |
|---|---|---|
| **100 pelanggan / hari** | Trafik rendah–sedang; puncak jam makan | VPS 2–4 GB sudah nyaman |
| **~100 pelanggan bersamaan** (HP pesan QR + kasir + dapur) | Lonjakan API polling status, notifikasi, cetak struk, query stok | **Minimal VPS 4 GB**; aman **8 GB** di jam sibuk |
| **100 transaksi / jam** | Beban tulis DB lebih tinggi (order, bayar, HPP, stok) | VPS **4–8 GB** + MySQL dioptimasi |

Untuk kedai tipikal: 100 orang datang dalam sehari **bukan** berarti 100 request bersamaan. Yang berat biasanya **puncak concurrent** (banyak HP buka `/pesan` + 1–3 kasir + layar dapur + push).

### 11.2 Beban teknis COGS Kasir di server

Aplikasi ini **bukan website statis**. Satu toko aktif biasanya menjalankan bersamaan:

- Web kasir / admin / COGS (Laravel)
- **API** untuk mobile & PWA (`/api/v1/...`)
- Halaman **pesan online** + polling status
- MySQL (order, stok, HPP, absensi, gaji)
- Generate **PDF struk** / laporan
- Upload foto menu & selfie absensi
- (Opsional) queue/worker notifikasi push

Karena itu **shared Web Hosting / Cloud Hosting biasa kurang ideal** untuk produksi dengan API + banyak perangkat. Butuh **Cloud VPS** (akses root, PHP 8.3+, Composer, cron, Nginx/Apache, MySQL sendiri).

### 11.3 Jenis layanan DomaiNesia — mana yang dipakai?

| Layanan DomaiNesia | Cocok untuk COGS Kasir? | Keterangan |
|---|---|---|
| **Web Hosting / Cloud Hosting** | ❌ Tidak disarankan produksi | Resource shared; susah kontrol PHP-FPM, worker, API concurrent |
| **Cloud VPS Lite** | ✅ Hemat, cukup untuk 1 toko | Intel Xeon Platinum, NVMe RAID10 (2x replikasi), harga lebih murah |
| **Cloud VPS Turbo** | ✅ Direkomendasikan produksi sibuk | AMD EPYC Genoa, NVMe 3x replikasi, respons API lebih snappy |
| **Managed VPS (Pluton)** | ✅ Jika tidak mau kelola server sendiri | Setup dibantu DomaiNesia; biaya lebih tinggi (~Rp1jt+/bulan) |

**Kesimpulan singkat:** pilih **Cloud VPS** (Lite atau Turbo). Jangan andalkan shared hosting jika ingin API stabil + puluhan HP pelanggan.

### 11.4 Perbandingan paket (estimasi kapasitas 1 toko)

Harga di bawah = **harga perpanjangan / bulan** (harga promo registrasi sering lebih murah).

#### Cloud VPS Lite ([katalog](https://www.domainesia.com/cloud-vps-lite/))

| Paket | Spek | Harga ~ | Estimasi kapasitas COGS Kasir | Verdict |
|---|---|---|---|---|
| **Lite 1GB** | 1 vCPU / 1 GB / 20 GB | Rp48rb | Demo / development saja | ❌ Produksi API |
| **Lite 2GB** | 2 vCPU / 2 GB / 30 GB | Rp100rb | 1 kasir + dapur, pesan QR sepi–sedang (~20–40 HP ringan), ≤50 pelanggan/hari nyaman | ⚠️ Mulai produksi kecil |
| **Lite 4GB** | 3 vCPU / 4 GB / 60 GB | Rp270rb | **~100 pelanggan/hari** atau puncak puluhan concurrent; API + MySQL 1 toko | ✅ **Rekomendasi hemat** |
| **Lite 8GB** | 4 vCPU / 8 GB / 100 GB | Rp645rb | Jam sibuk padat, banyak foto/PDF, buffer lonjakan weekend | ✅ Aman / skala naik |
| Lite 12GB+ | 4–16 vCPU / 12–64 GB | Rp880rb+ | Multi-toko di 1 VPS atau traffic sangat tinggi | Opsional |

#### Cloud VPS Turbo ([katalog](https://www.domainesia.com/cloud-vps/))

| Paket | Spek | Harga perpanjang ~ | Estimasi kapasitas | Verdict |
|---|---|---|---|---|
| **Turbo 1GB** | 1 vCPU / 1 GB / 20 GB | Rp160rb | Demo / staging | ❌ Produksi |
| **Turbo 2GB** | 2 vCPU / 2 GB / 40 GB | Rp320rb | Toko kecil, API ringan | ⚠️ Entry produksi |
| **Turbo 4GB** | 3 vCPU / 4 GB / 80 GB | Rp640rb | **Target ~100 pelanggan + API responsif** | ✅ **Rekomendasi produksi** |
| **Turbo 8GB** | 4 vCPU / 8 GB / 160 GB | Rp1.200rb | Puncak ramai, headroom notifikasi & laporan berat | ✅ Premium / aman |

> Promo sering memotong harga bulan pertama (contoh: Lite −10%, Turbo −50%). **Pakai angka perpanjangan** saat hitung biaya bulanan ke klien.

### 11.5 Rekomendasi siap pakai (untuk PPT)

| Skenario bisnis | Paket DomaiNesia | Alasan 1 kalimat |
|---|---|---|
| Demo / training / 1–2 user | Lite **1–2 GB** | Hemat; cukup uji fitur |
| Kedai sepi–sedang, ≤50 pelanggan/hari | Lite **2 GB** atau Turbo **2 GB** | 1–2 kasir + pesan QR ringan |
| **~100 pelanggan/hari atau puluhan HP bersamaan** | **Lite 4 GB** (hemat) atau **Turbo 4 GB** (lebih cepat) | Sweet spot API + MySQL + POS |
| Weekend ramai / event / banyak foto absensi & struk PDF | **Lite 8 GB** atau **Turbo 8 GB** | Headroom CPU saat generate PDF & concurrent |
| Owner tidak mau urus server | **Managed VPS Pluton 2–4 GB** | Tim DomaiNesia bantu setup awal |

**Rekomendasi default saat jual sistem:**  
**Cloud VPS Turbo 4GB** (produksi nyaman) **atau Cloud VPS Lite 4GB** (anggaran ketat) — keduanya cukup untuk API + ~100 pelanggan skala kedai.

### 11.6 Checklist setup agar “100 pelanggan” benar-benar lancar

Spesifikasi VPS saja tidak cukup. Pastikan konfigurasi berikut:

1. **OS:** Ubuntu 22.04/24.04 LTS  
2. **Stack:** Nginx + PHP 8.3/8.4-FPM + MySQL 8 + Composer  
3. **SSL:** HTTPS (Let’s Encrypt) — wajib untuk PWA, kamera absensi, push  
4. **Cron Laravel:** `schedule:run` tiap menit (gaji, job berkala)  
5. **Queue worker** (opsional tapi disarankan): notifikasi push tidak memblokir request kasir  
6. **Optimasi:** `php artisan config/route/view:cache`, OPcache aktif  
7. **Upload:** cukup space untuk foto menu & selfie (pilih paket storage ≥40–80 GB jika banyak gambar)  
8. **Backup:** snapshot VPS / dump MySQL harian  
9. **Monitor:** pantau RAM & CPU jam makan; upgrade ke 8 GB jika swap sering dipakai  

### 11.7 Catatan pitching ke klien

- Satu instalasi = **satu toko**. Estimasi di atas untuk **1 outlet**.  
- Biaya VPS **jauh lebih kecil** dari kerugian 1 jam kasir lambat di jam sibuk.  
- Mulai dari **4 GB**, upgrade mudah di DomaiNesia tanpa migrasi ulang data.  
- Kalau klien hanya punya shared hosting lama: **jelaskan harus pindah ke VPS** agar API mobile & pesan meja stabil.

---

## 12. Saran Struktur PPT Penjualan

Gunakan urutan slide berikut agar narasi jualan mengalir:

| No | Slide | Isi dari dokumen ini |
|---|---|---|
| 1 | Cover | Nama produk + tagline |
| 2 | Masalah UMKM F&B | Modal gelap, stok kacau, absensi manual |
| 3 | Solusi & elevator pitch | Bagian 1 |
| 4 | Tiga modul | Bagian 2 |
| 5 | USP / keunggulan | Bagian 3 |
| 6–8 | Demo COGS | Bagian 4 (wizard + hitung modal) |
| 9–11 | Demo Kasir | Bagian 5 (POS, QR meja, dapur) |
| 12–13 | Demo Admin | Bagian 6 (absensi, gaji) |
| 14 | Alur end-to-end | Bagian 10 |
| 15 | Laporan owner | Bagian 9 |
| 16 | Platform (web + mobile) | Bagian 8 |
| 17 | Keamanan | Bagian 7 |
| 18 | **Server & kapasitas (~100 pelanggan)** | **Bagian 11** |
| 19 | Closing / CTA | Demo live, onboarding, instalasi |

---

## 13. Cheat Sheet Fitur per Modul (versi singkat)

### COGS
Dashboard · Biaya Lain · Bahan Baku · Bahan Jadi · Menu & Resep · Harga Jual · Production Order · Hitung/Riwayat COGS · Stok Rusak · Inventaris Ops · Dana Usaha

### Kasir
POS · Open Bill · Diskon · Tunai/QRIS/Transfer · Dapur & Bar · Meja QR · Pesan Online · Menu & Kategori · Sold Out · Riwayat · Pembukuan PDF · Kas Tunai · Thermal & WA Struk · Notifikasi Push · PIN Stasiun

### Admin
Dashboard · Karyawan & Jadwal · Absensi + QR GPS Selfie · Gaji & Potongan · Akses Akun · Log Aktivitas · Pengaturan Toko & QRIS · Convert Gambar Menu

### Hosting (DomaiNesia)
Jangan shared hosting · Pakai Cloud VPS · Default jual: **Lite/Turbo 4GB** untuk ~100 pelanggan · Naik ke **8GB** jika jam sibuk padat

---

*Dokumen ini disusun dari fitur aktual di codebase COGS Kasir untuk keperluan presentasi penjualan. Estimasi kapasitas server bersifat panduan operasional (bukan benchmark resmi DomaiNesia); harga paket bisa berubah sesuai katalog & promo penyedia.*
