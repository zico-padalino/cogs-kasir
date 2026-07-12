-- Add-on tambahan menu (contoh: telur, keju) — opsional saat pesan di kasir.
-- Jalankan di database aplikasi jika belum pakai migrate.

CREATE TABLE IF NOT EXISTS `product_addons` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `selling_price` DECIMAL(18, 4) NOT NULL DEFAULT 0,
    `material_product_id` BIGINT UNSIGNED NULL,
    `material_quantity` DECIMAL(18, 6) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `product_addons_product_id_is_active_index` (`product_id`, `is_active`),
    CONSTRAINT `product_addons_product_id_foreign`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `product_addons_material_product_id_foreign`
        FOREIGN KEY (`material_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
