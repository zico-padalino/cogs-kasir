-- =============================================================================
-- DATA DUMMY — Warung Makan Sederhana (5 Menu)
-- Database: cogs_perhitungan
--
-- CERITA SINGKAT:
--   Anda punya warung kecil dengan 5 menu jualan:
--   1. Nasi Goreng    2. Mie Goreng    3. Es Teh Manis
--   4. Kopi Susu      5. Roti Bakar Keju
--
--   Ada 8 bahan baku, 1 biaya lain (listrik & gas 10% dari harga bahan),
--   resep per menu, stok bahan, catatan produksi, modal terhitung,
--   dan harga jual siap Kasir.
--
-- CARA PAKAI:
--   1. Pastikan migrasi sudah jalan: php artisan migrate
--   2. (Disarankan) Kosongkan data COGS dulu: jalankan database/truncate_all.sql
--   3. Jalankan file ini di phpMyAdmin / MySQL CLI
--
-- CATATAN:
--   File ini mengisi ulang tabel COGS dari awal (TRUNCATE).
--   Tabel users / kasir TIDAK dihapus.
-- =============================================================================

USE `cogs_perhitungan`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `cogs_calculations`;
TRUNCATE TABLE `sales_transactions`;
TRUNCATE TABLE `production_order_labors`;
TRUNCATE TABLE `production_order_materials`;
TRUNCATE TABLE `production_orders`;
TRUNCATE TABLE `inventory_lots`;
TRUNCATE TABLE `bill_of_materials`;
TRUNCATE TABLE `overhead_rates`;
TRUNCATE TABLE `products`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- LANGKAH 1 — Biaya lain (listrik & gas = 10% dari harga bahan)
-- =============================================================================
INSERT INTO `overhead_rates` (`name`, `allocation_base`, `rate`, `is_active`, `description`, `created_at`, `updated_at`) VALUES
('Listrik & gas dapur', 'direct_material', 0.100000, 1, '10% dari total harga bahan per produksi', NOW(), NOW());

-- =============================================================================
-- LANGKAH 2 — Bahan baku (8 bahan)
-- Harga beli per satuan sengaja bulat agar mudah dihitung manual
-- =============================================================================
INSERT INTO `products`
    (`id`, `sku`, `name`, `type`, `unit`, `standard_cost`, `unit_hpp`, `selling_price`, `costing_method`, `description`, `menu_category`, `image_path`, `is_active`, `is_menu_item`, `hpp_updated_at`, `created_at`, `updated_at`)
VALUES
-- id 1-8 = bahan
(1,  'BAHAN-BERAS',       'Beras',         'raw_material', 'kg',    0, 0, 0, 'weighted_average', 'Bahan nasi goreng',           NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(2,  'BAHAN-MIE',         'Mie Instan',    'raw_material', 'pcs',   0, 0, 0, 'weighted_average', '1 bungkus mie mentah',        NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(3,  'BAHAN-TEH',         'Teh Celup',     'raw_material', 'pcs',   0, 0, 0, 'weighted_average', '1 kantong teh celup',         NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(4,  'BAHAN-KOPI',        'Kopi Bubuk',    'raw_material', 'kg',    0, 0, 0, 'weighted_average', 'Kopi bubuk untuk seduh',      NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(5,  'BAHAN-ROTI',        'Roti Tawar',    'raw_material', 'pcs',   0, 0, 0, 'weighted_average', '1 lembar roti tawar',         NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(6,  'BAHAN-GULA',        'Gula Pasir',    'raw_material', 'kg',    0, 0, 0, 'weighted_average', 'Gula untuk minuman & masak',  NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(7,  'BAHAN-MINYAK',      'Minyak Goreng', 'raw_material', 'liter', 0, 0, 0, 'weighted_average', 'Minyak untuk goreng',         NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(8,  'BAHAN-SUSU',        'Susu Cair',     'raw_material', 'liter', 0, 0, 0, 'weighted_average', 'Susu UHT untuk kopi susu',    NULL, NULL, 1, 0, NULL, NOW(), NOW());

-- Stok awal bahan
INSERT INTO `inventory_lots`
    (`product_id`, `lot_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `received_at`, `source_type`, `source_id`, `created_at`, `updated_at`)
VALUES
(1, 'STOK-BERAS-01',  50.000000, 48.000000, 12000.0000, NOW(), NULL, NULL, NOW(), NOW()),  -- habis ~2 kg (10 porsi nasi)
(2, 'STOK-MIE-01',   100.000000, 90.000000,  3500.0000, NOW(), NULL, NULL, NOW(), NOW()),  -- habis 10 bungkus
(3, 'STOK-TEH-01',   200.000000, 180.000000,   500.0000, NOW(), NULL, NULL, NOW(), NOW()),  -- habis 20 teh
(4, 'STOK-KOPI-01',    5.000000,  4.775000, 80000.0000, NOW(), NULL, NULL, NOW(), NOW()),  -- habis 0.225 kg
(5, 'STOK-ROTI-01',   50.000000, 30.000000,  3000.0000, NOW(), NULL, NULL, NOW(), NOW()),  -- habis 20 lembar (10 roti bakar × 2)
(6, 'STOK-GULA-01',   20.000000, 19.475000, 14000.0000, NOW(), NULL, NULL, NOW(), NOW()),
(7, 'STOK-MINYAK-01', 10.000000,  9.650000, 18000.0000, NOW(), NULL, NULL, NOW(), NOW()),
(8, 'STOK-SUSU-01',   10.000000,  8.500000, 15000.0000, NOW(), NULL, NULL, NOW(), NOW());

-- =============================================================================
-- LANGKAH 3 — Menu & resep (5 menu jualan)
-- Modal (unit_hpp) sudah diisi = hasil simulasi produksi + biaya lain 10%
-- =============================================================================
INSERT INTO `products`
    (`id`, `sku`, `name`, `type`, `unit`, `standard_cost`, `unit_hpp`, `selling_price`, `costing_method`, `description`, `menu_category`, `image_path`, `is_active`, `is_menu_item`, `hpp_updated_at`, `created_at`, `updated_at`)
VALUES
(11, 'MENU-NASI-GORENG',  'Nasi Goreng',       'finished_good', 'porsi', 0, 3190.0000, 12000.0000, 'weighted_average', 'Nasi goreng warung spesial',  'makanan',  NULL, 1, 1, NOW(), NOW(), NOW()),
(12, 'MENU-MIE-GORENG',   'Mie Goreng',        'finished_good', 'porsi', 0, 4224.0000, 15000.0000, 'weighted_average', 'Mie goreng dengan telur',     'makanan',  NULL, 1, 1, NOW(), NOW(), NOW()),
(13, 'MENU-ES-TEH',       'Es Teh Manis',      'finished_good', 'gelas', 0,  858.0000,  5000.0000, 'weighted_average', 'Teh manis dingin',            'minuman',  NULL, 1, 1, NOW(), NOW(), NOW()),
(14, 'MENU-KOPI-SUSU',    'Kopi Susu',         'finished_good', 'gelas', 0, 3124.0000, 18000.0000, 'weighted_average', 'Kopi susu gula aren',         'minuman',  NULL, 1, 1, NOW(), NOW(), NOW()),
(15, 'MENU-ROTI-BAKAR',   'Roti Bakar Keju',   'finished_good', 'pcs',   0, 6677.0000, 12000.0000, 'weighted_average', 'Roti bakar + keju',           'makanan',  NULL, 1, 1, NOW(), NOW(), NOW());

-- Resep: berapa bahan dipakai untuk 1 porsi/gelas/pcs menu
INSERT INTO `bill_of_materials`
    (`parent_product_id`, `child_product_id`, `quantity`, `scrap_percentage`, `sequence`, `created_at`, `updated_at`)
VALUES
-- Nasi Goreng: 200 g beras + sedikit minyak & gula
(11, 1, 0.200000, 0, 1, NOW(), NOW()),
(11, 7, 0.020000, 0, 2, NOW(), NOW()),
(11, 6, 0.010000, 0, 3, NOW(), NOW()),

-- Mie Goreng: 1 bungkus mie + minyak & gula
(12, 2, 1.000000, 0, 1, NOW(), NOW()),
(12, 7, 0.015000, 0, 2, NOW(), NOW()),
(12, 6, 0.005000, 0, 3, NOW(), NOW()),

-- Es Teh: 1 teh celup + gula
(13, 3, 1.000000, 0, 1, NOW(), NOW()),
(13, 6, 0.020000, 0, 2, NOW(), NOW()),

-- Kopi Susu: kopi bubuk + susu + gula
(14, 4, 0.015000, 0, 1, NOW(), NOW()),
(14, 8, 0.100000, 0, 2, NOW(), NOW()),
(14, 6, 0.010000, 0, 3, NOW(), NOW()),

-- Roti Bakar: 2 lembar roti + sedikit gula (olesan)
(15, 5, 2.000000, 0, 1, NOW(), NOW()),
(15, 6, 0.005000, 0, 2, NOW(), NOW());

-- =============================================================================
-- LANGKAH 4 — Catatan produksi (sudah selesai, modal terhitung)
-- =============================================================================
INSERT INTO `production_orders`
    (`id`, `order_number`, `product_id`, `quantity_planned`, `quantity_completed`, `status`, `machine_hours`, `started_at`, `completed_at`, `notes`, `created_at`, `updated_at`)
VALUES
(1, 'DEMO-PO-001', 11, 10.000000, 10.000000, 'completed', 0, NOW(), NOW(), 'Produksi 10 porsi nasi goreng', NOW(), NOW()),
(2, 'DEMO-PO-002', 12, 10.000000, 10.000000, 'completed', 0, NOW(), NOW(), 'Produksi 10 porsi mie goreng',  NOW(), NOW()),
(3, 'DEMO-PO-003', 13, 20.000000, 20.000000, 'completed', 0, NOW(), NOW(), 'Seduh 20 gelas es teh',         NOW(), NOW()),
(4, 'DEMO-PO-004', 14, 15.000000, 15.000000, 'completed', 0, NOW(), NOW(), 'Seduh 15 gelas kopi susu',      NOW(), NOW()),
(5, 'DEMO-PO-005', 15, 10.000000, 10.000000, 'completed', 0, NOW(), NOW(), 'Bakar 10 roti bakar keju',      NOW(), NOW());

-- Ringkasan perhitungan modal (untuk riwayat / langkah 5)
INSERT INTO `cogs_calculations`
    (`reference_type`, `reference_id`, `product_id`, `quantity`, `direct_material`, `direct_labor`, `manufacturing_overhead`, `total_hpp`, `unit_hpp`, `total_cogs`, `unit_cogs`, `calculation_method`, `breakdown`, `calculated_at`, `created_at`, `updated_at`)
VALUES
('App\\Models\\ProductionOrder', 1, 11, 10.000000, 29000.0000, 0.0000, 2900.0000, 31900.0000, 3190.0000, 31900.0000, 3190.0000, 'absorption_costing_production', NULL, NOW(), NOW(), NOW()),
('App\\Models\\ProductionOrder', 2, 12, 10.000000, 38400.0000, 0.0000, 3840.0000, 42240.0000, 4224.0000, 42240.0000, 4224.0000, 'absorption_costing_production', NULL, NOW(), NOW(), NOW()),
('App\\Models\\ProductionOrder', 3, 13, 20.000000, 15600.0000, 0.0000, 1560.0000, 17160.0000,  858.0000, 17160.0000,  858.0000, 'absorption_costing_production', NULL, NOW(), NOW(), NOW()),
('App\\Models\\ProductionOrder', 4, 14, 15.000000, 42600.0000, 0.0000, 4260.0000, 46860.0000, 3124.0000, 46860.0000, 3124.0000, 'absorption_costing_production', NULL, NOW(), NOW(), NOW()),
('App\\Models\\ProductionOrder', 5, 15, 10.000000, 60700.0000, 0.0000, 6070.0000, 66770.0000, 6677.0000, 66770.0000, 6677.0000, 'absorption_costing_production', NULL, NOW(), NOW(), NOW());

-- Stok menu jadi (hasil produksi, siap dijual)
INSERT INTO `inventory_lots`
    (`product_id`, `lot_number`, `quantity_received`, `quantity_remaining`, `unit_cost`, `received_at`, `source_type`, `source_id`, `created_at`, `updated_at`)
VALUES
(11, 'HASIL-NASI-01',  10.000000, 10.000000,  3190.0000, NOW(), 'App\\Models\\ProductionOrder', 1, NOW(), NOW()),
(12, 'HASIL-MIE-01',   10.000000, 10.000000,  4224.0000, NOW(), 'App\\Models\\ProductionOrder', 2, NOW(), NOW()),
(13, 'HASIL-TEH-01',   20.000000, 20.000000,   858.0000, NOW(), 'App\\Models\\ProductionOrder', 3, NOW(), NOW()),
(14, 'HASIL-KOPI-01',  15.000000, 15.000000,  3124.0000, NOW(), 'App\\Models\\ProductionOrder', 4, NOW(), NOW()),
(15, 'HASIL-ROTI-01',  10.000000, 10.000000,  6677.0000, NOW(), 'App\\Models\\ProductionOrder', 5, NOW(), NOW());

-- =============================================================================
-- RANGKUMAN — cek cepat setelah import
-- =============================================================================
SELECT '=== 5 MENU & HARGA JUAL ===' AS info;

SELECT
    p.name           AS menu,
    p.unit           AS satuan,
    p.unit_hpp       AS modal_per_satuan,
    p.selling_price  AS harga_jual,
    ROUND(p.selling_price - p.unit_hpp, 0) AS untung_per_satuan,
    ROUND((p.selling_price - p.unit_hpp) / NULLIF(p.selling_price, 0) * 100, 1) AS persen_untung
FROM products p
WHERE p.type = 'finished_good'
ORDER BY p.id;

SELECT '=== RESEP (BAHAN PER 1 MENU) ===' AS info;

SELECT
    parent.name AS menu,
    child.name  AS bahan,
    bom.quantity,
    child.unit  AS satuan_bahan,
    ROUND(bom.quantity * il.unit_cost, 0) AS biaya_bahan
FROM bill_of_materials bom
JOIN products parent ON parent.id = bom.parent_product_id
JOIN products child  ON child.id  = bom.child_product_id
LEFT JOIN inventory_lots il ON il.product_id = child.id AND il.quantity_remaining > 0
WHERE parent.type = 'finished_good'
ORDER BY parent.id, bom.sequence;
