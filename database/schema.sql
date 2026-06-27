-- =============================================================================
-- COGS PERHITUNGAN — Skema Database MySQL
-- Database: cogs_perhitungan
--
-- Disarankan: php artisan migrate --force
-- Atau import: database/mysql_full.sql (skema + data demo)
-- Legacy SQLite: file ini TIDAK untuk SQLite — gunakan artisan migrate
-- =============================================================================

USE `cogs_perhitungan`;

-- Lihat definisi lengkap tabel di:
--   database/mysql_full.sql  (baris CREATE TABLE)
--
-- Tabel utama:
--   users, products, bill_of_materials, inventory_lots,
--   production_orders, production_order_materials, production_order_labors,
--   overhead_rates, sales_transactions, cogs_calculations
--
-- Kolom penting users:
--   role ENUM-like VARCHAR(20): 'cogs' | 'kasir'
