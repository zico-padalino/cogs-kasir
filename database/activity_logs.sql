-- Log aktivitas (login, transaksi, pesan meja, PIN kasir, absensi, akses akun)
-- Jalankan di phpMyAdmin / Navicat pada database aplikasi.
-- Aman dijalankan ulang (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(32) NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `actor_name` VARCHAR(255) NULL,
  `actor_email` VARCHAR(255) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `method` VARCHAR(16) NULL,
  `url` VARCHAR(2048) NULL,
  `route_name` VARCHAR(255) NULL,
  `channel` VARCHAR(32) NULL,
  `subject_type` VARCHAR(255) NULL,
  `subject_id` BIGINT UNSIGNED NULL,
  `session_id` VARCHAR(64) NULL,
  `properties` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_created_at_index` (`created_at`),
  KEY `activity_logs_category_created_at_index` (`category`, `created_at`),
  KEY `activity_logs_action_created_at_index` (`action`, `created_at`),
  KEY `activity_logs_user_id_created_at_index` (`user_id`, `created_at`),
  KEY `activity_logs_ip_address_index` (`ip_address`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`, `subject_id`),
  CONSTRAINT `activity_logs_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agar `php artisan migrate` tidak mencoba membuat tabel yang sama lagi
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_08_16_120000_create_activity_logs_table', IFNULL(MAX(`batch`), 0) + 1
FROM `migrations`
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `migration` = '2026_08_16_120000_create_activity_logs_table'
);

-- -----------------------------------------------------------------------------
-- Cek tabel
-- -----------------------------------------------------------------------------
-- SHOW COLUMNS FROM `activity_logs`;
-- SELECT * FROM `activity_logs` ORDER BY `id` DESC LIMIT 50;
