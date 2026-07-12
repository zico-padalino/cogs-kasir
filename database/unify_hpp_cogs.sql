-- =============================================================================
-- COGS PERHITUNGAN — Unifikasi HPP = COGS + integrasi menu kasir
-- Setara dengan: database/migrations/2026_07_09_210000_unify_hpp_cogs_schema.php
--
-- KONSEP:
--   • HPP (Harga Pokok Penjualan) = satu-satunya hasil perhitungan biaya.
--   • COGS = nilai yang di-link dari HPP (kolom total_cogs/unit_cogs = total_hpp/unit_hpp).
--   • Produk jadi stok COGS → ditandai is_menu_item → muncul di kasir dengan selling_price.
--
-- CARA PAKAI:
--   1. USE database yang dipakai project (default: cogs_perhitungan)
--   2. Jalankan file ini sekali
--   3. Atau: php artisan migrate --force
-- =============================================================================

USE `cogs_perhitungan`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. products — HPP per unit + flag menu kasir
-- -----------------------------------------------------------------------------
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `unit_hpp` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 AFTER `standard_cost`,
    ADD COLUMN IF NOT EXISTS `is_menu_item` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
    ADD COLUMN IF NOT EXISTS `hpp_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_menu_item`;

-- Backfill HPP dari biaya standar jika belum terisi
UPDATE `products`
SET `unit_hpp` = `standard_cost`
WHERE `unit_hpp` = 0 AND `standard_cost` > 0;

-- Produk jadi & setengah jadi otomatis jadi item menu kasir
UPDATE `products`
SET `is_menu_item` = 1
WHERE `type` IN ('finished_good', 'semi_finished');

-- -----------------------------------------------------------------------------
-- 2. cogs_calculations — kolom HPP (sumber) + COGS (link ke HPP)
-- -----------------------------------------------------------------------------
ALTER TABLE `cogs_calculations`
    ADD COLUMN IF NOT EXISTS `total_hpp` DECIMAL(18,4) NULL AFTER `manufacturing_overhead`,
    ADD COLUMN IF NOT EXISTS `unit_hpp` DECIMAL(18,4) NULL AFTER `total_hpp`;

-- Sinkronkan data lama: HPP = nilai COGS yang sudah ada
UPDATE `cogs_calculations`
SET
    `total_hpp` = `total_cogs`,
    `unit_hpp` = `unit_cogs`
WHERE `total_hpp` IS NULL OR `unit_hpp` IS NULL;

-- Pastikan COGS tetap sama dengan HPP (untuk baris yang hanya punya HPP)
UPDATE `cogs_calculations`
SET
    `total_cogs` = `total_hpp`,
    `unit_cogs` = `unit_hpp`
WHERE `total_hpp` IS NOT NULL AND (`total_cogs` <> `total_hpp` OR `unit_cogs` <> `unit_hpp`);

-- -----------------------------------------------------------------------------
-- 3. Sync unit_hpp produk dari perhitungan produksi terakhir
-- -----------------------------------------------------------------------------
UPDATE `products` p
INNER JOIN (
    SELECT cc.product_id, cc.unit_hpp
    FROM `cogs_calculations` cc
    INNER JOIN (
        SELECT product_id, MAX(id) AS latest_id
        FROM `cogs_calculations`
        WHERE reference_type LIKE '%ProductionOrder%'
        GROUP BY product_id
    ) latest ON latest.latest_id = cc.id
) calc ON calc.product_id = p.id
SET
    p.unit_hpp = calc.unit_hpp,
    p.hpp_updated_at = NOW()
WHERE calc.unit_hpp > 0;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- ROLLBACK (manual, hati-hati — backup dulu)
-- =============================================================================
-- ALTER TABLE `products` DROP COLUMN `unit_hpp`, DROP COLUMN `is_menu_item`, DROP COLUMN `hpp_updated_at`;
-- ALTER TABLE `cogs_calculations` DROP COLUMN `total_hpp`, DROP COLUMN `unit_hpp`;
