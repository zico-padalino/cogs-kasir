-- Ceklis item pesanan yang sudah diantar (per baris item).
-- Jalankan sekali. Jika kolom sudah ada, abaikan error duplicate column.
ALTER TABLE `pos_order_items`
    ADD COLUMN `is_delivered` TINYINT(1) NOT NULL DEFAULT 0 AFTER `addon_ids`,
    ADD COLUMN `delivered_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_delivered`;
