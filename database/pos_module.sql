-- =============================================================================
-- COGS PERHITUNGAN — Modul POS (MySQL)
-- Setara dengan: database/migrations/2026_06_27_100000_create_pos_module_tables.php
--
-- CARA PAKAI (Navicat / phpMyAdmin / MySQL Workbench):
--   1. Pastikan database sudah ada dan berisi tabel users, products, sales_transactions
--   2. Jalankan file ini SELURUHNYA (sekali saja)
--   3. Atau lewat Laravel: php artisan migrate --force
--
-- Database: cogs_perhitungan
-- =============================================================================

USE `cogs_perhitungan`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. products — harga jual untuk POS
-- -----------------------------------------------------------------------------
ALTER TABLE `products`
    ADD COLUMN `selling_price` DECIMAL(18,4) NOT NULL DEFAULT 0.0000
        AFTER `standard_cost`;

-- -----------------------------------------------------------------------------
-- 2. pos_tables — meja / QR order
-- -----------------------------------------------------------------------------
CREATE TABLE `pos_tables` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `table_number`   VARCHAR(20) NOT NULL,
    `label`          VARCHAR(255) NOT NULL,
    `barcode_token`  VARCHAR(64) NOT NULL,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pos_tables_table_number_unique` (`table_number`),
    UNIQUE KEY `pos_tables_barcode_token_unique` (`barcode_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. pos_orders — order kasir / meja
-- -----------------------------------------------------------------------------
CREATE TABLE `pos_orders` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number`    VARCHAR(255) NOT NULL,
    `pos_table_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `source`          VARCHAR(20) NOT NULL DEFAULT 'kasir',
    `status`          VARCHAR(20) NOT NULL DEFAULT 'open',
    `customer_note`   TEXT NULL,
    `subtotal`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total`           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `payment_method`  VARCHAR(20) NULL DEFAULT NULL,
    `paid_at`         TIMESTAMP NULL DEFAULT NULL,
    `user_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pos_orders_order_number_unique` (`order_number`),
    KEY `pos_orders_status_created_at_index` (`status`, `created_at`),
    CONSTRAINT `pos_orders_pos_table_id_foreign`
        FOREIGN KEY (`pos_table_id`) REFERENCES `pos_tables` (`id`) ON DELETE SET NULL,
    CONSTRAINT `pos_orders_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. pos_order_items — detail item per order
-- -----------------------------------------------------------------------------
CREATE TABLE `pos_order_items` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pos_order_id`  BIGINT UNSIGNED NOT NULL,
    `product_id`    BIGINT UNSIGNED NOT NULL,
    `quantity`      DECIMAL(18,6) NOT NULL,
    `unit_price`    DECIMAL(18,4) NOT NULL,
    `line_total`    DECIMAL(18,4) NOT NULL,
    `notes`         VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `pos_order_items_pos_order_id_foreign`
        FOREIGN KEY (`pos_order_id`) REFERENCES `pos_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_order_items_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. sales_transactions — link ke order POS
-- -----------------------------------------------------------------------------
ALTER TABLE `sales_transactions`
    ADD COLUMN `pos_order_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD CONSTRAINT `sales_transactions_pos_order_id_foreign`
        FOREIGN KEY (`pos_order_id`) REFERENCES `pos_orders` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 6. Catatan migrasi Laravel (agar artisan migrate tidak duplikat)
-- -----------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_06_27_100000_create_pos_module_tables', 2);

-- -----------------------------------------------------------------------------
-- 7. Contoh data meja (opsional — hapus komentar jika perlu)
-- -----------------------------------------------------------------------------
-- INSERT INTO `pos_tables` (`table_number`, `label`, `barcode_token`, `is_active`, `created_at`, `updated_at`) VALUES
-- ('T01', 'Meja 1', 'tbl-token-meja-01-demo', 1, NOW(), NOW()),
-- ('T02', 'Meja 2', 'tbl-token-meja-02-demo', 1, NOW(), NOW()),
-- ('T03', 'Meja 3', 'tbl-token-meja-03-demo', 1, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 8. Verifikasi
-- -----------------------------------------------------------------------------
SELECT 'products.selling_price' AS cek,
       COUNT(*) AS ada
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'products'
  AND COLUMN_NAME = 'selling_price';

SELECT 'pos_tables' AS tabel, COUNT(*) AS jumlah_baris FROM `pos_tables`
UNION ALL
SELECT 'pos_orders', COUNT(*) FROM `pos_orders`
UNION ALL
SELECT 'pos_order_items', COUNT(*) FROM `pos_order_items`;

-- =============================================================================
-- ROLLBACK (jalankan manual jika perlu batalkan modul POS)
-- =============================================================================
-- USE `cogs_perhitungan`;
-- SET FOREIGN_KEY_CHECKS = 0;
--
-- ALTER TABLE `sales_transactions`
--     DROP FOREIGN KEY `sales_transactions_pos_order_id_foreign`,
--     DROP COLUMN `pos_order_id`;
--
-- DROP TABLE IF EXISTS `pos_order_items`;
-- DROP TABLE IF EXISTS `pos_orders`;
-- DROP TABLE IF EXISTS `pos_tables`;
--
-- ALTER TABLE `products` DROP COLUMN `selling_price`;
--
-- DELETE FROM `migrations`
-- WHERE `migration` = '2026_06_27_100000_create_pos_module_tables';
--
-- SET FOREIGN_KEY_CHECKS = 1;
