-- =============================================================================
-- FIX: /admin/salaries → 500 Server Error (MySQL + MariaDB)
--
-- Kompatibel MySQL (tanpa ADD COLUMN IF NOT EXISTS).
-- Aman diulang: kolom/tabel yang sudah ada akan dilewati.
--
-- Cara pakai di phpMyAdmin:
--   1) Pilih database aplikasi
--   2) Tab SQL → paste seluruh isi file ini → Go
--   3) Refresh https://kedaitjoan.online/admin/salaries
-- =============================================================================

SET NAMES utf8mb4;

-- Helper: tambah kolom hanya jika belum ada
DROP PROCEDURE IF EXISTS `cogs_add_column_if_missing`;
DELIMITER $$
CREATE PROCEDURE `cogs_add_column_if_missing`(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- -----------------------------------------------------------------------------
-- 1) users
-- -----------------------------------------------------------------------------
CALL cogs_add_column_if_missing('users', 'modules', 'JSON NULL AFTER `role`');
CALL cogs_add_column_if_missing('users', 'is_root', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `modules`');
CALL cogs_add_column_if_missing('users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`');

UPDATE `users`
SET `modules` = JSON_ARRAY(`role`)
WHERE `modules` IS NULL OR JSON_LENGTH(`modules`) = 0;

-- -----------------------------------------------------------------------------
-- 2) employees
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code` VARCHAR(32) NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `phone`         VARCHAR(32) NULL DEFAULT NULL,
    `email`         VARCHAR(255) NULL DEFAULT NULL,
    `position`      VARCHAR(255) NULL DEFAULT NULL,
    `department`    VARCHAR(255) NULL DEFAULT NULL,
    `hire_date`     DATE NULL DEFAULT NULL,
    `base_salary`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `daily_salary`  DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `status`        VARCHAR(20) NOT NULL DEFAULT 'active',
    `user_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `pin_hash`      VARCHAR(255) NULL DEFAULT NULL,
    `pin_set_at`    TIMESTAMP NULL DEFAULT NULL,
    `notes`         TEXT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employees_employee_code_unique` (`employee_code`),
    KEY `employees_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL cogs_add_column_if_missing('employees', 'daily_salary', 'DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER `base_salary`');
CALL cogs_add_column_if_missing('employees', 'pin_hash', 'VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`');
CALL cogs_add_column_if_missing('employees', 'pin_set_at', 'TIMESTAMP NULL DEFAULT NULL AFTER `pin_hash`');

-- -----------------------------------------------------------------------------
-- 3) employee_attendances
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_attendances` (
    `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`             BIGINT UNSIGNED NOT NULL,
    `work_date`               DATE NOT NULL,
    `check_in`                TIME NULL DEFAULT NULL,
    `check_in_lat`            DECIMAL(10,7) NULL DEFAULT NULL,
    `check_in_lng`            DECIMAL(10,7) NULL DEFAULT NULL,
    `check_in_photo_path`     VARCHAR(255) NULL DEFAULT NULL,
    `check_in_face_distance`  DECIMAL(18,6) NULL DEFAULT NULL,
    `check_out`               TIME NULL DEFAULT NULL,
    `check_out_lat`           DECIMAL(10,7) NULL DEFAULT NULL,
    `check_out_lng`           DECIMAL(10,7) NULL DEFAULT NULL,
    `check_out_photo_path`    VARCHAR(255) NULL DEFAULT NULL,
    `check_out_face_distance` DECIMAL(18,6) NULL DEFAULT NULL,
    `status`                  VARCHAR(20) NOT NULL DEFAULT 'hadir',
    `is_late`                 TINYINT(1) NOT NULL DEFAULT 0,
    `notes`                   VARCHAR(255) NULL DEFAULT NULL,
    `created_at`              TIMESTAMP NULL DEFAULT NULL,
    `updated_at`              TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_attendances_employee_id_work_date_unique` (`employee_id`, `work_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL cogs_add_column_if_missing('employee_attendances', 'check_in_lat', 'DECIMAL(10,7) NULL DEFAULT NULL AFTER `check_in`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_in_lng', 'DECIMAL(10,7) NULL DEFAULT NULL AFTER `check_in_lat`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_in_photo_path', 'VARCHAR(255) NULL DEFAULT NULL AFTER `check_in_lng`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_in_face_distance', 'DECIMAL(18,6) NULL DEFAULT NULL AFTER `check_in_photo_path`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_out_lat', 'DECIMAL(10,7) NULL DEFAULT NULL AFTER `check_out`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_out_lng', 'DECIMAL(10,7) NULL DEFAULT NULL AFTER `check_out_lat`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_out_photo_path', 'VARCHAR(255) NULL DEFAULT NULL AFTER `check_out_lng`');
CALL cogs_add_column_if_missing('employee_attendances', 'check_out_face_distance', 'DECIMAL(18,6) NULL DEFAULT NULL AFTER `check_out_photo_path`');
CALL cogs_add_column_if_missing('employee_attendances', 'is_late', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');

-- -----------------------------------------------------------------------------
-- 4) employee_salaries
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_salaries` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`   BIGINT UNSIGNED NOT NULL,
    `period_month`  DATE NOT NULL,
    `base_salary`   DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `daily_salary`  DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `work_days`     INT UNSIGNED NOT NULL DEFAULT 0,
    `allowance`     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `deduction`     DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total`         DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `status`        VARCHAR(20) NOT NULL DEFAULT 'draft',
    `paid_at`       DATETIME NULL DEFAULT NULL,
    `notes`         VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_salaries_employee_id_period_month_unique` (`employee_id`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL cogs_add_column_if_missing('employee_salaries', 'daily_salary', 'DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER `base_salary`');
CALL cogs_add_column_if_missing('employee_salaries', 'work_days', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `daily_salary`');

-- -----------------------------------------------------------------------------
-- 5) app_settings potongan gaji
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `app_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
('salary_default_deduction', '0', NOW(), NOW()),
('salary_deduction_late', '0', NOW(), NOW()),
('salary_late_after_minutes', '0', NOW(), NOW()),
('salary_deduction_alpha', '0', NOW(), NOW()),
('salary_deduction_izin', '0', NOW(), NOW()),
('salary_deduction_sakit', '0', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`);

DROP PROCEDURE IF EXISTS `cogs_add_column_if_missing`;

-- -----------------------------------------------------------------------------
-- 6) Verifikasi
-- -----------------------------------------------------------------------------
SELECT 'users.is_root' AS cek,
       IF(COUNT(*) > 0, 'OK', 'MISSING') AS status
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_root'
UNION ALL
SELECT 'employees', IF(COUNT(*) > 0, 'OK', 'MISSING')
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees'
UNION ALL
SELECT 'employee_salaries', IF(COUNT(*) > 0, 'OK', 'MISSING')
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_salaries'
UNION ALL
SELECT 'employee_salaries.daily_salary', IF(COUNT(*) > 0, 'OK', 'MISSING')
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_salaries' AND COLUMN_NAME = 'daily_salary'
UNION ALL
SELECT 'employees.daily_salary', IF(COUNT(*) > 0, 'OK', 'MISSING')
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'daily_salary';
