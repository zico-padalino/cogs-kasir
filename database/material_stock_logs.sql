-- Riwayat update/create stok bahan (langkah 2 COGS)
-- Jalankan di database aplikasi jika tidak memakai php artisan migrate

CREATE TABLE IF NOT EXISTS `material_stock_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_unit` VARCHAR(20) NULL,
  `inventory_lot_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(20) NOT NULL,
  `quantity_before` DECIMAL(18, 6) NULL,
  `quantity_after` DECIMAL(18, 6) NULL,
  `quantity_delta` DECIMAL(18, 6) NULL,
  `unit_cost` DECIMAL(18, 4) NULL,
  `lot_number` VARCHAR(255) NULL,
  `note` VARCHAR(255) NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `material_stock_logs_created_at_index` (`created_at`),
  INDEX `material_stock_logs_product_id_created_at_index` (`product_id`, `created_at`),
  INDEX `material_stock_logs_action_index` (`action`),
  CONSTRAINT `material_stock_logs_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_stock_logs_inventory_lot_id_foreign`
    FOREIGN KEY (`inventory_lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_stock_logs_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
