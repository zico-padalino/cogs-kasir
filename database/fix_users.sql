-- =============================================================================
-- COGS PERHITUNGAN — Perbaiki Akun Login
-- Database: cogs_perhitungan (MySQL)
--
-- MASALAH: hash password lama di SQL tidak valid untuk kata sandi "password"
-- SOLUSI : jalankan file ini di Navicat / phpMyAdmin / MySQL Workbench
--
-- Akun setelah diperbaiki:
--   COGS  → cogs@local.test  / password
--   Kasir → kasir@local.test / password
-- =============================================================================

USE `cogs_perhitungan`;

-- Hash bcrypt valid untuk password: "password"
INSERT INTO `users` (`name`, `email`, `role`, `password`, `created_at`, `updated_at`) VALUES
('Admin COGS', 'cogs@local.test', 'cogs',
 '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW()),
('Kasir Demo', 'kasir@local.test', 'kasir',
 '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `role` = VALUES(`role`),
    `password` = VALUES(`password`),
    `updated_at` = NOW();

-- Verifikasi
SELECT id, name, email, role, updated_at
FROM `users`
WHERE email IN ('cogs@local.test', 'kasir@local.test')
ORDER BY role;
