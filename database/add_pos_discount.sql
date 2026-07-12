-- Diskon pesanan kasir (jalankan jika belum php artisan migrate)
ALTER TABLE `pos_orders`
    ADD COLUMN `discount_type` VARCHAR(20) NULL AFTER `subtotal`,
    ADD COLUMN `discount_value` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `discount_type`,
    ADD COLUMN `discount_amount` DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER `discount_value`;
