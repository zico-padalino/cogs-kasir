-- =============================================================================
-- JADWAL KERJA PEGAWAI (per hari Senin–Minggu)
--
-- Pilih database aplikasi di phpMyAdmin, lalu jalankan query ini satu kali.
-- Aman dijalankan ulang (CREATE TABLE IF NOT EXISTS).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employee_work_schedules` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`   BIGINT UNSIGNED NOT NULL,
    `day_of_week`   TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
    `clock_in`      VARCHAR(5) NOT NULL DEFAULT '08:00',
    `clock_out`     VARCHAR(5) NOT NULL DEFAULT '17:00',
    `is_off`        TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_work_schedules_employee_day_unique` (`employee_id`, `day_of_week`),
    KEY `employee_work_schedules_employee_id_foreign` (`employee_id`),
    CONSTRAINT `employee_work_schedules_employee_id_foreign`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifikasi:
SHOW COLUMNS FROM `employee_work_schedules`;
