-- =============================================================================
-- COGS PERHITUNGAN — Kosongkan Semua Data
-- Database: cogs_perhitungan (MySQL)
--
-- CARA PAKAI (Navicat / phpMyAdmin / MySQL Workbench):
--   Jalankan file ini SELURUHNYA
--
-- CATATAN:
--   - Struktur tabel TIDAK dihapus, hanya isi data
--   - Tabel migrations, users, cache, jobs TIDAK dikosongkan (opsi B di bawah)
--   - Setelah kosong, isi ulang demo: php artisan db:seed
--     atau jalankan bagian INSERT di mysql_full.sql
-- =============================================================================

USE `cogs_perhitungan`;

SET FOREIGN_KEY_CHECKS = 0;

-- Urutan: anak dulu, induk terakhir
TRUNCATE TABLE `cogs_calculations`;
TRUNCATE TABLE `sales_transactions`;
TRUNCATE TABLE `production_order_labors`;
TRUNCATE TABLE `production_order_materials`;
TRUNCATE TABLE `production_orders`;
TRUNCATE TABLE `inventory_lots`;
TRUNCATE TABLE `bill_of_materials`;
TRUNCATE TABLE `overhead_rates`;
TRUNCATE TABLE `products`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verifikasi (semua harus 0)
SELECT 'products'              AS tabel, COUNT(*) AS jumlah FROM products
UNION ALL SELECT 'bill_of_materials',       COUNT(*) FROM bill_of_materials
UNION ALL SELECT 'inventory_lots',         COUNT(*) FROM inventory_lots
UNION ALL SELECT 'production_orders',      COUNT(*) FROM production_orders
UNION ALL SELECT 'production_order_materials', COUNT(*) FROM production_order_materials
UNION ALL SELECT 'production_order_labors',    COUNT(*) FROM production_order_labors
UNION ALL SELECT 'overhead_rates',         COUNT(*) FROM overhead_rates
UNION ALL SELECT 'sales_transactions',     COUNT(*) FROM sales_transactions
UNION ALL SELECT 'cogs_calculations',      COUNT(*) FROM cogs_calculations;

-- =============================================================================
-- OPSI B — Kosongkan SEMUA tabel termasuk users/sessions/cache (HATI-HATI!)
-- Uncomment blok di bawah jika benar-benar ingin reset total:
-- =============================================================================
/*
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `cogs_calculations`;
TRUNCATE TABLE `sales_transactions`;
TRUNCATE TABLE `production_order_labors`;
TRUNCATE TABLE `production_order_materials`;
TRUNCATE TABLE `production_orders`;
TRUNCATE TABLE `inventory_lots`;
TRUNCATE TABLE `bill_of_materials`;
TRUNCATE TABLE `overhead_rates`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `sessions`;
TRUNCATE TABLE `password_reset_tokens`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `failed_jobs`;
TRUNCATE TABLE `job_batches`;
TRUNCATE TABLE `jobs`;
TRUNCATE TABLE `cache_locks`;
TRUNCATE TABLE `cache`;

SET FOREIGN_KEY_CHECKS = 1;
*/
