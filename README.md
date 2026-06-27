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
