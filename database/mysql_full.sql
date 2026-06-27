-- =============================================================================
-- COGS PERHITUNGAN — MySQL Full Install
-- Koneksi: localhost:3306 | user: root
--
-- CARA PAKAI (Navicat / phpMyAdmin / MySQL Workbench):
--   1. Jalankan file ini SELURUHNYA
-- 2. Update .env Laravel:
--        DB_CONNECTION=mysql
--        DB_HOST=127.0.0.1
--        DB_PORT=3306
--        DB_DATABASE=cogs_perhitungan
--        DB_USERNAME=root
--        DB_PASSWORD=<password mysql anda>
--   3. php artisan config:clear
--   4. php artisan migrate --force   (jika belum import SQL ini)
--   5. php artisan serve --port=8900
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cogs_perhitungan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cogs_perhitungan`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Hapus tabel lama (jika ada)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cogs_calculations`;
DROP TABLE IF EXISTS `sales_transactions`;
DROP TABLE IF EXISTS `production_order_labors`;
DROP TABLE IF EXISTS `production_order_materials`;
DROP TABLE IF EXISTS `production_orders`;
DROP TABLE IF EXISTS `inventory_lots`;
DROP TABLE IF EXISTS `bill_of_materials`;
DROP TABLE IF EXISTS `overhead_rates`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `failed_jobs`;
DROP TABLE IF EXISTS `job_batches`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `cache_locks`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `migrations`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Skema Tabel
-- -----------------------------------------------------------------------------
CREATE TABLE `migrations` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch`     INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(255) NOT NULL,
    `email`             VARCHAR(255) NOT NULL,
    `role`              VARCHAR(20) NOT NULL DEFAULT 'cogs',
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password`          VARCHAR(255) NOT NULL,
    `remember_token`    VARCHAR(100) NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
    `email`      VARCHAR(255) NOT NULL,
    `token`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
    `id`            VARCHAR(255) NOT NULL,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`    VARCHAR(45) NULL DEFAULT NULL,
    `user_agent`    TEXT NULL,
    `payload`       LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
    `key`        VARCHAR(255) NOT NULL,
    `value`      MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
    `key`        VARCHAR(255) NOT NULL,
    `owner`      VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue`        VARCHAR(255) NOT NULL,
    `payload`      LONGTEXT NOT NULL,
    `attempts`     TINYINT UNSIGNED NOT NULL,
    `reserved_at`  INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at`   INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
    `id`             VARCHAR(255) NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `total_jobs`     INT NOT NULL,
    `pending_jobs`   INT NOT NULL,
    `failed_jobs`    INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options`        MEDIUMTEXT NULL,
    `cancelled_at`   INT NULL DEFAULT NULL,
    `created_at`     INT NOT NULL,
    `finished_at`    INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`       VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue`      TEXT NOT NULL,
    `payload`    LONGTEXT NOT NULL,
    `exception`  LONGTEXT NOT NULL,
    `failed_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`            VARCHAR(255) NOT NULL,
    `name`           VARCHAR(255) NOT NULL,
    `type`           VARCHAR(255) NOT NULL,
    `unit`           VARCHAR(255) NOT NULL DEFAULT 'pcs',
    `standard_cost`  DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `costing_method` VARCHAR(255) NOT NULL DEFAULT 'weighted_average',
    `description`    TEXT NULL,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `products_sku_unique` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bill_of_materials` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_product_id` BIGINT UNSIGNED NOT NULL,
    `child_product_id`  BIGINT UNSIGNED NOT NULL,
    `quantity`          DECIMAL(18,6) NOT NULL,
    `scrap_percentage`  DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `sequence`          INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bill_of_materials_parent_product_id_child_product_id_unique` (`parent_product_id`, `child_product_id`),
    CONSTRAINT `bom_parent_fk` FOREIGN KEY (`parent_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `bom_child_fk`  FOREIGN KEY (`child_product_id`)  REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_lots` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`         BIGINT UNSIGNED NOT NULL,
    `lot_number`         VARCHAR(255) NULL DEFAULT NULL,
    `quantity_received`  DECIMAL(18,6) NOT NULL,
    `quantity_remaining` DECIMAL(18,6) NOT NULL,
    `unit_cost`          DECIMAL(18,4) NOT NULL,
    `received_at`        TIMESTAMP NOT NULL,
    `source_type`        VARCHAR(255) NULL DEFAULT NULL,
    `source_id`          BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `inventory_lots_product_id_received_at_index` (`product_id`, `received_at`),
    CONSTRAINT `inventory_lots_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_orders` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number`       VARCHAR(255) NOT NULL,
    `product_id`         BIGINT UNSIGNED NOT NULL,
    `quantity_planned`   DECIMAL(18,6) NOT NULL,
    `quantity_completed` DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    `status`             VARCHAR(255) NOT NULL DEFAULT 'draft',
    `machine_hours`      DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `started_at`         TIMESTAMP NULL DEFAULT NULL,
    `completed_at`       TIMESTAMP NULL DEFAULT NULL,
    `notes`              TEXT NULL,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `production_orders_order_number_unique` (`order_number`),
    CONSTRAINT `production_orders_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_order_materials` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_order_id` BIGINT UNSIGNED NOT NULL,
    `product_id`          BIGINT UNSIGNED NOT NULL,
    `quantity_planned`    DECIMAL(18,6) NOT NULL,
    `quantity_used`       DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    `unit_cost`           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_cost`          DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `pom_order_fk`   FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pom_product_fk` FOREIGN KEY (`product_id`)          REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_order_labors` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_order_id` BIGINT UNSIGNED NOT NULL,
    `description`         VARCHAR(255) NOT NULL,
    `labor_hours`         DECIMAL(18,4) NOT NULL,
    `hourly_rate`         DECIMAL(18,4) NOT NULL,
    `total_cost`          DECIMAL(18,4) NOT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `pol_order_fk` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `overhead_rates` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(255) NOT NULL,
    `allocation_base` VARCHAR(255) NOT NULL,
    `rate`            DECIMAL(18,6) NOT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `description`     TEXT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sales_transactions` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(255) NOT NULL,
    `product_id`     BIGINT UNSIGNED NOT NULL,
    `quantity`       DECIMAL(18,6) NOT NULL,
    `selling_price`  DECIMAL(18,4) NOT NULL,
    `total_revenue`  DECIMAL(18,4) NOT NULL,
    `sold_at`        TIMESTAMP NOT NULL,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sales_transactions_invoice_number_unique` (`invoice_number`),
    CONSTRAINT `sales_transactions_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cogs_calculations` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_type`         VARCHAR(255) NOT NULL,
    `reference_id`           BIGINT UNSIGNED NOT NULL,
    `product_id`             BIGINT UNSIGNED NOT NULL,
    `quantity`               DECIMAL(18,6) NOT NULL,
    `direct_material`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `direct_labor`           DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `manufacturing_overhead` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_cogs`             DECIMAL(18,4) NOT NULL,
    `unit_cogs`              DECIMAL(18,4) NOT NULL,
    `calculation_method`     VARCHAR(255) NOT NULL,
    `breakdown`              JSON NULL,
    `calculated_at`          TIMESTAMP NOT NULL,
    `created_at`             TIMESTAMP NULL DEFAULT NULL,
    `updated_at`             TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `cogs_calculations_reference_index` (`reference_type`, `reference_id`),
    CONSTRAINT `cogs_calculations_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- DATA: User Login (password = "password")
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`name`, `email`, `role`, `password`, `created_at`, `updated_at`) VALUES
('Admin COGS', 'cogs@local.test', 'cogs', '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW()),
('Kasir Demo', 'kasir@local.test', 'kasir', '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Overhead Rates
-- -----------------------------------------------------------------------------
INSERT INTO `overhead_rates` (`id`, `name`, `allocation_base`, `rate`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Overhead Pabrik - Bahan Langsung', 'direct_material', 0.150000, 1, '15% dari biaya bahan langsung', NOW(), NOW()),
(2, 'Overhead Tenaga Kerja',            'labor_hours',     25000.000000, 1, 'Rp 25.000 per jam kerja', NOW(), NOW()),
(3, 'Overhead Mesin',                   'machine_hours',   50000.000000, 1, 'Rp 50.000 per jam mesin', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Produk
-- -----------------------------------------------------------------------------
INSERT INTO `products` (`id`, `sku`, `name`, `type`, `unit`, `standard_cost`, `costing_method`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RM-FLOUR-001',  'Tepung Terigu',       'raw_material',   'kg',   12000.0000, 'fifo',               NULL, 1, NOW(), NOW()),
(2, 'RM-SUGAR-001',  'Gula Pasir',          'raw_material',   'kg',   15000.0000, 'fifo',               NULL, 1, NOW(), NOW()),
(3, 'RM-BUTTER-001', 'Mentega',             'raw_material',   'kg',   85000.0000, 'weighted_average', NULL, 1, NOW(), NOW()),
(4, 'SF-DOUGH-001',  'Adonan Roti',         'semi_finished',  'kg',       0.0000, 'weighted_average', NULL, 1, NOW(), NOW()),
(5, 'FG-BREAD-001',  'Roti Tawar Premium',  'finished_good',  'loaf',     0.0000, 'weighted_average', NULL, 1, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Bill of Materials
-- -----------------------------------------------------------------------------
INSERT INTO `bill_of_materials` (`id`, `parent_product_id`, `child_product_id`, `quantity`, `scrap_percentage`, `sequence`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 0.600000, 2.0000, 1, NOW(), NOW()),
(2, 4, 2, 0.100000, 1.0000, 2, NOW(), NOW()),
(3, 4, 3, 0.050000, 0.0000, 3, NOW(), NOW()),
(4, 5, 4, 0.500000, 3.0000, 1, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Persediaan (stok awal bahan baku)
-- -----------------------------------------------------------------------------
INSERT INTO `inventory_lots` (`id`, `product_id`, `lot_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `received_at`, `source_type`, `source_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'LOT-FLOUR-001',  500.000000, 468.482000, 11500.0000, NOW(), NULL, NULL, NOW(), NOW()),
(2, 1, 'LOT-FLOUR-002',  300.000000, 300.000000, 12500.0000, NOW(), NULL, NULL, NOW(), NOW()),
(3, 2, 'LOT-SUGAR-001',  200.000000, 194.798500, 14800.0000, NOW(), NULL, NULL, NOW(), NOW()),
(4, 3, 'LOT-BUTTER-001',  50.000000,  47.425000, 84000.0000, NOW(), NULL, NULL, NOW(), NOW()),
(5, 3, 'LOT-BUTTER-002',  30.000000,  30.000000, 86000.0000, NOW(), NULL, NULL, NOW(), NOW()),
(6, 5, 'PO-DEMO-001',    100.000000, 100.000000, 15163.2102, NOW(), 'App\\Models\\ProductionOrder', 1, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Production Order (selesai)
-- -----------------------------------------------------------------------------
INSERT INTO `production_orders` (`id`, `order_number`, `product_id`, `quantity_planned`, `quantity_completed`, `status`, `machine_hours`, `started_at`, `completed_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PO-DEMO-001', 5, 100.000000, 100.000000, 'completed', 6.0000, NOW(), NOW(), 'Produksi demo roti tawar', NOW(), NOW());

INSERT INTO `production_order_materials` (`id`, `production_order_id`, `product_id`, `quantity_planned`, `quantity_used`, `unit_cost`, `total_cost`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 31.518000, 31.518000, 11500.0000, 362457.0000, NOW(), NOW()),
(2, 1, 2,  5.201500,  5.201500, 14800.0000,  76982.2000, NOW(), NOW()),
(3, 1, 3,  2.575000,  2.575000, 84750.0000, 218231.2500, NOW(), NOW());

INSERT INTO `production_order_labors` (`id`, `production_order_id`, `description`, `labor_hours`, `hourly_rate`, `total_cost`, `created_at`, `updated_at`) VALUES
(1, 1, 'Operator Produksi', 8.0000, 20000.0000, 160000.0000, NOW(), NOW()),
(2, 1, 'Quality Control',   2.0000, 25000.0000,  50000.0000, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- DATA: Hasil Perhitungan COGS
-- -----------------------------------------------------------------------------
INSERT INTO `cogs_calculations` (
    `id`, `reference_type`, `reference_id`, `product_id`, `quantity`,
    `direct_material`, `direct_labor`, `manufacturing_overhead`,
    `total_cogs`, `unit_cogs`, `calculation_method`, `breakdown`, `calculated_at`, `created_at`, `updated_at`
) VALUES (
    1,
    'App\\Models\\ProductionOrder',
    1,
    5,
    100.000000,
    657670.4500,
    210000.0000,
    648650.5675,
    1516321.0175,
    15163.2102,
    'absorption_costing_production',
    JSON_OBJECT(
        'production_order_id', 1,
        'order_number', 'PO-DEMO-001',
        'quantity_completed', 100
    ),
    NOW(), NOW(), NOW()
);

-- -----------------------------------------------------------------------------
-- Catatan migrasi Laravel (agar artisan migrate tidak error)
-- -----------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_06_26_110411_create_products_table', 1),
('2026_06_26_110412_create_bill_of_materials_table', 1),
('2026_06_26_110414_create_inventory_lots_table', 1),
('2026_06_26_110415_create_production_orders_table', 1),
('2026_06_26_110416_create_production_order_materials_table', 1),
('2026_06_26_110418_create_production_order_labors_table', 1),
('2026_06_26_110419_create_overhead_rates_table', 1),
('2026_06_26_110420_create_sales_transactions_table', 1),
('2026_06_26_110421_create_cogs_calculations_table', 1),
('2026_06_26_000001_add_role_to_users_table', 1);

-- =============================================================================
-- SELESAI — Verifikasi:
-- SELECT COUNT(*) FROM products;           -- 5
-- SELECT COUNT(*) FROM cogs_calculations;  -- 1
-- SELECT SUM(total_cogs) FROM cogs_calculations;  -- 1516321.0175
-- =============================================================================
