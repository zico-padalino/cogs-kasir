# COGS Kasir

Aplikasi web Laravel untuk perhitungan **Cost of Goods Sold (COGS)** dan modul **kasir penjualan**.

## Fitur

- Manajemen produk & Bill of Materials (BOM)
- Penerimaan stok & inventory lot
- Production order (mulai & selesaikan produksi)
- Perhitungan COGS (material, tenaga kerja, overhead)
- Riwayat perhitungan COGS
- Modul kasir penjualan
- Login multi-role: **COGS Admin** dan **Kasir**

## Persyaratan

- PHP 8.3+
- Composer
- Node.js 18+ (untuk build asset frontend)
- Ekstensi PHP: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite (default)
touch database/database.sqlite
php artisan migrate --seed

npm install
npm run build
```

## Menjalankan

```bash
php artisan serve --port=8900
```

Buka `http://localhost:8900`

## Akses publik (Cloudflare Tunnel)

Bagikan aplikasi ke internet tanpa buka port router / tanpa IP publik.

### Persyaratan

1. Install [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/):

```powershell
winget install --id Cloudflare.cloudflared
```

2. Pastikan Laravel sudah jalan normal (`php artisan serve --port=8900`).

### Mode 1 — Quick tunnel (paling cepat, untuk demo)

URL sementara `*.trycloudflare.com` (berubah setiap dijalankan):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-cloudflare-tunnel.ps1
```

Atau double-click `Jalankan-Tunnel.bat`.

Salin URL `https://....trycloudflare.com` dari output terminal, lalu bagikan ke teman.

### Mode 2 — Named tunnel (subdomain permanen)

Butuh domain yang sudah di Cloudflare (gratis):

```powershell
# Setup sekali (login, buat tunnel, DNS)
powershell -ExecutionPolicy Bypass -File scripts/setup-cloudflare-tunnel.ps1

# Update .env
# APP_URL=https://cogs.domainanda.com

# Jalankan
powershell -ExecutionPolicy Bypass -File scripts/start-cloudflare-tunnel.ps1 -Mode Named
```

File konfigurasi: `scripts/cloudflare/config.yml` (credentials tidak ikut commit).

## Akun demo

| Role | Email | Password |
|------|-------|----------|
| COGS Admin | `cogs@local.test` | `password` |
| Kasir | `kasir@local.test` | `password` |

## Database

- **SQLite** — default untuk development lokal (`database/database.sqlite`)
- **MySQL** — skema tersedia di `database/mysql_full.sql` dan `database/mysql_install.sql`

## Lisensi

MIT
