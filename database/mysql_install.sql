-- =============================================================================
-- COGS PERHITUNGAN — MySQL Install (Kosong + User Login)
--
-- CARA 1 — Laravel (DISARANKAN):
--   1. Pastikan MySQL/XAMPP berjalan
--   2. Buat database: CREATE DATABASE cogs_perhitungan;
--   3. Atur .env → DB_CONNECTION=mysql, DB_DATABASE=cogs_perhitungan
--   4. php artisan migrate --force
--   5. php artisan db:seed --class=UserSeeder
--
-- CARA 2 — Import SQL lengkap dengan data demo:
--   Jalankan file: database/mysql_full.sql
--
-- CARA 3 — PowerShell:
--   powershell -ExecutionPolicy Bypass -File database\setup.ps1
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cogs_perhitungan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Setelah database dibuat, jalankan migrate Laravel (lihat CARA 1 di atas).
-- File ini hanya membuat database; skema tabel dibuat otomatis oleh artisan migrate.
