-- =============================================================================
-- COGS PERHITUNGAN — Skema Database MySQL Lengkap
-- Project: Laravel (COGS + Kasir + Admin HR + Kas Tunai)
--
-- ISI FILE INI:
--   • Semua tabel aplikasi (hasil gabungan migrations + kolom yang dipakai model)
--   • Seed minimal kategori menu
--   • Query verifikasi
--
-- CARA PAKAI (pilih salah satu):
--   A) Disarankan: php artisan migrate --force
--   B) Manual MySQL: jalankan file ini di phpMyAdmin / Navicat / CLI
--
-- Database default: cogs_perhitungan (sesuaikan di .env)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cogs_perhitungan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `cogs_perhitungan`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- OPSIONAL: Hapus semua tabel (fresh install). Hati-hati — data hilang!
-- =============================================================================
-- DROP TABLE IF EXISTS `cash_ledger_entries`;
-- DROP TABLE IF EXISTS `employee_salaries`;
-- DROP TABLE IF EXISTS `employee_attendances`;
-- DROP TABLE IF EXISTS `employees`;
-- DROP TABLE IF EXISTS `cogs_calculations`;
-- DROP TABLE IF EXISTS `sales_transactions`;
-- DROP TABLE IF EXISTS `pos_order_items`;
-- DROP TABLE IF EXISTS `pos_orders`;
-- DROP TABLE IF EXISTS `pos_tables`;
-- DROP TABLE IF EXISTS `production_order_labors`;
-- DROP TABLE IF EXISTS `production_order_materials`;
-- DROP TABLE IF EXISTS `production_orders`;
-- DROP TABLE IF EXISTS `inventory_lots`;
-- DROP TABLE IF EXISTS `bill_of_materials`;
-- DROP TABLE IF EXISTS `menu_categories`;
-- DROP TABLE IF EXISTS `products`;
-- DROP TABLE IF EXISTS `overhead_rates`;
-- DROP TABLE IF EXISTS `failed_jobs`;
-- DROP TABLE IF EXISTS `job_batches`;
-- DROP TABLE IF EXISTS `jobs`;
-- DROP TABLE IF EXISTS `cache_locks`;
-- DROP TABLE IF EXISTS `cache`;
-- DROP TABLE IF EXISTS `sessions`;
-- DROP TABLE IF EXISTS `password_reset_tokens`;
-- DROP TABLE IF EXISTS `users`;
-- DROP TABLE IF EXISTS `migrations`;

-- =============================================================================
-- LARAVEL — Auth & sistem
-- =============================================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(255) NOT NULL,
    `email`             VARCHAR(255) NOT NULL,
    `role`              VARCHAR(20) NOT NULL DEFAULT 'cogs',
    `modules`           JSON NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password`          VARCHAR(255) NOT NULL,
    `remember_token`    VARCHAR(100) NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email`       VARCHAR(255) NOT NULL,
    `token`       VARCHAR(255) NOT NULL,
    `created_at`  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
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

CREATE TABLE IF NOT EXISTS `cache` (
    `key`         VARCHAR(255) NOT NULL,
    `value`       MEDIUMTEXT NOT NULL,
    `expiration`  BIGINT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key`         VARCHAR(255) NOT NULL,
    `owner`       VARCHAR(255) NOT NULL,
    `expiration`  BIGINT NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue`         VARCHAR(255) NOT NULL,
    `payload`       LONGTEXT NOT NULL,
    `attempts`      SMALLINT UNSIGNED NOT NULL,
    `reserved_at`   INT UNSIGNED NULL DEFAULT NULL,
    `available_at`  INT UNSIGNED NOT NULL,
    `created_at`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
    `id`              VARCHAR(255) NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `total_jobs`      INT NOT NULL,
    `pending_jobs`    INT NOT NULL,
    `failed_jobs`     INT NOT NULL,
    `failed_job_ids`  LONGTEXT NOT NULL,
    `options`         MEDIUMTEXT NULL,
    `cancelled_at`    INT NULL DEFAULT NULL,
    `created_at`      INT NOT NULL,
    `finished_at`     INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`        VARCHAR(255) NOT NULL,
    `connection`  TEXT NOT NULL,
    `queue`       TEXT NOT NULL,
    `payload`     LONGTEXT NOT NULL,
    `exception`   LONGTEXT NOT NULL,
    `failed_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
    KEY `failed_jobs_connection_queue_failed_at_index` (`connection`(191), `queue`(191), `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration`  VARCHAR(255) NOT NULL,
    `batch`      INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- COGS — Produk, stok, produksi, HPP/COGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `products` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`             VARCHAR(255) NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `type`            VARCHAR(255) NOT NULL COMMENT 'raw_material | semi_finished | finished_good',
    `unit`            VARCHAR(255) NOT NULL DEFAULT 'pcs',
    `standard_cost`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'Estimasi biaya awal',
    `unit_hpp`        DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'HPP per unit (COGS = HPP)',
    `selling_price`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT 'Harga jual menu — diatur di COGS',
    `costing_method`  VARCHAR(255) NOT NULL DEFAULT 'weighted_average',
    `description`     TEXT NULL,
    `menu_category`   VARCHAR(50) NULL DEFAULT NULL COMMENT 'Slug dari menu_categories',
    `image_path`      VARCHAR(255) NULL DEFAULT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `is_menu_item`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = tampil di kasir',
    `hpp_updated_at`  TIMESTAMP NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `products_sku_unique` (`sku`),
    KEY `products_type_is_menu_item_index` (`type`, `is_menu_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_categories` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50) NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `sort_order`  INT NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `menu_categories_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bill_of_materials` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_product_id`  BIGINT UNSIGNED NOT NULL,
    `child_product_id`   BIGINT UNSIGNED NOT NULL,
    `quantity`           DECIMAL(18,6) NOT NULL,
    `scrap_percentage`   DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `sequence`           INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `bill_of_materials_parent_product_id_child_product_id_unique` (`parent_product_id`, `child_product_id`),
    CONSTRAINT `bill_of_materials_parent_product_id_foreign`
        FOREIGN KEY (`parent_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `bill_of_materials_child_product_id_foreign`
        FOREIGN KEY (`child_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_lots` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`           BIGINT UNSIGNED NOT NULL,
    `lot_number`           VARCHAR(255) NULL DEFAULT NULL,
    `quantity_received`    DECIMAL(18,6) NOT NULL,
    `quantity_remaining`   DECIMAL(18,6) NOT NULL,
    `unit_cost`            DECIMAL(18,4) NOT NULL,
    `received_at`          TIMESTAMP NOT NULL,
    `source_type`          VARCHAR(255) NULL DEFAULT NULL,
    `source_id`            BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `inventory_lots_product_id_received_at_index` (`product_id`, `received_at`),
    CONSTRAINT `inventory_lots_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_orders` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number`        VARCHAR(255) NOT NULL,
    `product_id`          BIGINT UNSIGNED NOT NULL,
    `quantity_planned`    DECIMAL(18,6) NOT NULL,
    `quantity_completed`  DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    `status`              VARCHAR(255) NOT NULL DEFAULT 'draft',
    `machine_hours`       DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `started_at`          TIMESTAMP NULL DEFAULT NULL,
    `completed_at`        TIMESTAMP NULL DEFAULT NULL,
    `notes`               TEXT NULL,
    `created_at`          TIMESTAMP NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `production_orders_order_number_unique` (`order_number`),
    CONSTRAINT `production_orders_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_order_materials` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_order_id`   BIGINT UNSIGNED NOT NULL,
    `product_id`            BIGINT UNSIGNED NOT NULL,
    `quantity_planned`      DECIMAL(18,6) NOT NULL,
    `quantity_used`         DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
    `unit_cost`             DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_cost`            DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `created_at`            TIMESTAMP NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `production_order_materials_production_order_id_foreign`
        FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `production_order_materials_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `production_order_labors` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `production_order_id`   BIGINT UNSIGNED NOT NULL,
    `description`           VARCHAR(255) NOT NULL,
    `labor_hours`           DECIMAL(18,4) NOT NULL,
    `hourly_rate`           DECIMAL(18,4) NOT NULL,
    `total_cost`            DECIMAL(18,4) NOT NULL,
    `created_at`            TIMESTAMP NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `production_order_labors_production_order_id_foreign`
        FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `overhead_rates` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(255) NOT NULL,
    `allocation_base`  VARCHAR(255) NOT NULL,
    `rate`             DECIMAL(18,6) NOT NULL,
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `description`      TEXT NULL,
    `created_at`       TIMESTAMP NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cogs_calculations` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference_type`           VARCHAR(255) NOT NULL,
    `reference_id`             BIGINT UNSIGNED NOT NULL,
    `product_id`               BIGINT UNSIGNED NOT NULL,
    `quantity`                 DECIMAL(18,6) NOT NULL,
    `direct_material`          DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `direct_labor`             DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `manufacturing_overhead`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_hpp`                DECIMAL(18,4) NULL DEFAULT NULL COMMENT 'Sumber perhitungan',
    `unit_hpp`                 DECIMAL(18,4) NULL DEFAULT NULL,
    `total_cogs`               DECIMAL(18,4) NOT NULL COMMENT 'Sama dengan total_hpp',
    `unit_cogs`                DECIMAL(18,4) NOT NULL COMMENT 'Sama dengan unit_hpp',
    `calculation_method`       VARCHAR(255) NOT NULL,
    `breakdown`                JSON NULL,
    `calculated_at`            TIMESTAMP NOT NULL,
    `created_at`               TIMESTAMP NULL DEFAULT NULL,
    `updated_at`               TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `cogs_calculations_reference_type_reference_id_index` (`reference_type`, `reference_id`),
    CONSTRAINT `cogs_calculations_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- KASIR — POS, penjualan, meja
-- =============================================================================

CREATE TABLE IF NOT EXISTS `pos_tables` (
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

CREATE TABLE IF NOT EXISTS `pos_orders` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number`         VARCHAR(255) NOT NULL,
    `order_day`            DATE NULL DEFAULT NULL,
    `pos_table_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `source`               VARCHAR(20) NOT NULL DEFAULT 'kasir',
    `order_type`           VARCHAR(20) NOT NULL DEFAULT 'takeaway',
    `status`               VARCHAR(20) NOT NULL DEFAULT 'open',
    `customer_note`        TEXT NULL,
    `subtotal`             DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total`                DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `amount_received`      DECIMAL(18,4) NULL DEFAULT NULL,
    `change_amount`        DECIMAL(18,4) NULL DEFAULT NULL,
    `payment_method`       VARCHAR(20) NULL DEFAULT NULL,
    `payment_proof_path`   VARCHAR(255) NULL DEFAULT NULL,
    `paid_at`              TIMESTAMP NULL DEFAULT NULL,
    `confirmed_at`         TIMESTAMP NULL DEFAULT NULL,
    `confirmed_by`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `user_id`              BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pos_orders_order_number_unique` (`order_number`),
    KEY `pos_orders_status_created_at_index` (`status`, `created_at`),
    KEY `pos_orders_order_day_index` (`order_day`),
    CONSTRAINT `pos_orders_pos_table_id_foreign`
        FOREIGN KEY (`pos_table_id`) REFERENCES `pos_tables` (`id`) ON DELETE SET NULL,
    CONSTRAINT `pos_orders_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `pos_orders_confirmed_by_foreign`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_order_items` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pos_order_id`  BIGINT UNSIGNED NOT NULL,
    `product_id`    BIGINT UNSIGNED NOT NULL,
    `quantity`      DECIMAL(18,6) NOT NULL,
    `unit_price`    DECIMAL(18,4) NOT NULL,
    `line_total`    DECIMAL(18,4) NOT NULL,
    `notes`         VARCHAR(255) NULL DEFAULT NULL,
    `addon_ids`     JSON NULL,
    `is_delivered`  TINYINT(1) NOT NULL DEFAULT 0,
    `delivered_at`  TIMESTAMP NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `pos_order_items_pos_order_id_foreign`
        FOREIGN KEY (`pos_order_id`) REFERENCES `pos_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `pos_order_items_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_transactions` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pos_order_id`    BIGINT UNSIGNED NULL DEFAULT NULL,
    `invoice_number`  VARCHAR(255) NOT NULL,
    `product_id`      BIGINT UNSIGNED NOT NULL,
    `quantity`        DECIMAL(18,6) NOT NULL,
    `selling_price`   DECIMAL(18,4) NOT NULL,
    `total_revenue`   DECIMAL(18,4) NOT NULL,
    `sold_at`         TIMESTAMP NOT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sales_transactions_invoice_number_unique` (`invoice_number`),
    CONSTRAINT `sales_transactions_pos_order_id_foreign`
        FOREIGN KEY (`pos_order_id`) REFERENCES `pos_orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `sales_transactions_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- KAS TUNAI — Buku kas harian
-- =============================================================================

CREATE TABLE IF NOT EXISTS `cash_ledger_entries` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`          VARCHAR(20) NOT NULL COMMENT 'float_in | sale_in | change_out | expense',
    `direction`     VARCHAR(3) NOT NULL COMMENT 'in | out',
    `amount`        DECIMAL(18,4) NOT NULL,
    `note`          VARCHAR(255) NOT NULL,
    `pos_order_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `occurred_at`   TIMESTAMP NOT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `cash_ledger_entries_occurred_at_index` (`occurred_at`),
    KEY `cash_ledger_entries_pos_order_id_index` (`pos_order_id`),
    CONSTRAINT `cash_ledger_entries_pos_order_id_foreign`
        FOREIGN KEY (`pos_order_id`) REFERENCES `pos_orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `cash_ledger_entries_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ADMIN HR — Karyawan, absensi, gaji
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employees` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code`   VARCHAR(32) NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `phone`           VARCHAR(32) NULL DEFAULT NULL,
    `email`           VARCHAR(255) NULL DEFAULT NULL,
    `position`        VARCHAR(255) NULL DEFAULT NULL,
    `department`      VARCHAR(255) NULL DEFAULT NULL,
    `hire_date`       DATE NULL DEFAULT NULL,
    `base_salary`     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `status`          VARCHAR(20) NOT NULL DEFAULT 'active',
    `user_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
    `notes`           TEXT NULL,
    `created_at`      TIMESTAMP NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
    CONSTRAINT `employees_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_attendances` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`  BIGINT UNSIGNED NOT NULL,
    `work_date`    DATE NOT NULL,
    `check_in`     TIME NULL DEFAULT NULL,
    `check_out`    TIME NULL DEFAULT NULL,
    `status`       VARCHAR(20) NOT NULL DEFAULT 'hadir',
    `notes`        VARCHAR(255) NULL DEFAULT NULL,
    `created_at`   TIMESTAMP NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_attendances_employee_id_work_date_unique` (`employee_id`, `work_date`),
    CONSTRAINT `employee_attendances_employee_id_foreign`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_salaries` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`   BIGINT UNSIGNED NOT NULL,
    `period_month`  DATE NOT NULL,
    `base_salary`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `allowance`     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `deduction`     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total`         DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `status`        VARCHAR(20) NOT NULL DEFAULT 'draft',
    `paid_at`       DATETIME NULL DEFAULT NULL,
    `notes`         VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_salaries_employee_id_period_month_unique` (`employee_id`, `period_month`),
    CONSTRAINT `employee_salaries_employee_id_foreign`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- SEED MINIMAL — Kategori menu kasir
-- =============================================================================

INSERT IGNORE INTO `menu_categories` (`slug`, `name`, `sort_order`, `created_at`, `updated_at`) VALUES
('minuman', 'Minuman', 1, NOW(), NOW()),
('makanan', 'Makanan', 2, NOW(), NOW()),
('pastry',  'Pastry',  3, NOW(), NOW()),
('snack',   'Snack',   4, NOW(), NOW()),
('lainnya', 'Lainnya', 99, NOW(), NOW());

-- =============================================================================
-- VERIFIKASI — Daftar semua tabel aplikasi
-- =============================================================================

SELECT TABLE_NAME AS tabel, TABLE_ROWS AS perkiraan_baris
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

-- =============================================================================
-- RINGKASAN TABEL (24 tabel aplikasi + migrations)
-- =============================================================================
-- Laravel : users, password_reset_tokens, sessions, cache, cache_locks,
--           jobs, job_batches, failed_jobs, migrations
-- COGS    : products, menu_categories, bill_of_materials, inventory_lots,
--           production_orders, production_order_materials, production_order_labors,
--           overhead_rates, cogs_calculations
-- Kasir   : pos_tables, pos_orders, pos_order_items, sales_transactions
-- Kas     : cash_ledger_entries
-- Admin   : employees, employee_attendances, employee_salaries
--
-- File terkait:
--   database/mysql_schema_full.sql — skema lengkap
--   database/cogs_dummy_data.sql   — data dummy modul COGS
--   database/unify_hpp_cogs.sql    — patch HPP = COGS
--   database/add_admin_hr.sql    — patch admin/HR saja
--   database/pos_module.sql      — patch POS saja
--   database/queries.sql         — query laporan
--   database/ALUR_HPP_COGS.md    — dokumentasi alur HPP/COGS
-- =============================================================================
