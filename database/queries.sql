-- =============================================================================
-- COGS PERHITUNGAN — Query SQL (MySQL)
-- Database: cogs_perhitungan
-- Buka dengan: Navicat, phpMyAdmin, MySQL Workbench, DBeaver
--
-- Pastikan sudah USE database:
--   USE cogs_perhitungan;
-- =============================================================================

USE `cogs_perhitungan`;

-- -----------------------------------------------------------------------------
-- 1. DASHBOARD — Ringkasan Total COGS
-- -----------------------------------------------------------------------------
SELECT
    COUNT(*)                                            AS total_perhitungan,
    ROUND(SUM(total_cogs), 0)                           AS total_cogs,
    ROUND(SUM(direct_material), 0)                      AS total_bahan_langsung,
    ROUND(SUM(direct_labor), 0)                         AS total_tenaga_kerja,
    ROUND(SUM(manufacturing_overhead), 0)               AS total_overhead
FROM cogs_calculations;


-- -----------------------------------------------------------------------------
-- 2. DASHBOARD — COGS per Produk
-- -----------------------------------------------------------------------------
SELECT
    p.sku,
    p.name                                              AS produk,
    ROUND(SUM(c.quantity), 0)                           AS qty,
    ROUND(SUM(c.total_cogs), 0)                         AS total_cogs,
    ROUND(
        IF(SUM(c.quantity) > 0, SUM(c.total_cogs) / SUM(c.quantity), 0),
        2
    )                                                   AS cogs_per_unit
FROM cogs_calculations c
JOIN products p ON p.id = c.product_id
GROUP BY p.id, p.sku, p.name
ORDER BY total_cogs DESC;


-- -----------------------------------------------------------------------------
-- 3. PRODUK & BOM — Daftar Semua Produk
-- -----------------------------------------------------------------------------
SELECT
    p.id,
    p.sku,
    p.name,
    p.type,
    p.unit,
    p.costing_method,
    p.standard_cost,
    COUNT(bom.id)                                       AS jumlah_komponen_bom
FROM products p
LEFT JOIN bill_of_materials bom ON bom.parent_product_id = p.id
GROUP BY p.id, p.sku, p.name, p.type, p.unit, p.costing_method, p.standard_cost
ORDER BY p.name;


-- -----------------------------------------------------------------------------
-- 4. PRODUK & BOM — Struktur BOM per Produk
-- -----------------------------------------------------------------------------
SELECT
    parent.sku          AS produk_induk_sku,
    parent.name         AS produk_induk,
    child.sku           AS komponen_sku,
    child.name          AS komponen,
    bom.quantity,
    child.unit,
    bom.scrap_percentage,
    bom.sequence
FROM bill_of_materials bom
JOIN products parent ON parent.id = bom.parent_product_id
JOIN products child  ON child.id  = bom.child_product_id
ORDER BY parent.name, bom.sequence;


-- -----------------------------------------------------------------------------
-- 5. PERSEDIAAN — Stok Tersedia per Produk
-- -----------------------------------------------------------------------------
SELECT
    p.sku,
    p.name,
    p.unit,
    ROUND(COALESCE(SUM(il.quantity_remaining), 0), 2)   AS stok_tersedia,
    ROUND(
        IF(COALESCE(SUM(il.quantity_remaining), 0) > 0,
           SUM(il.quantity_remaining * il.unit_cost) / SUM(il.quantity_remaining),
           0),
        0
    )                                                   AS rata_rata_biaya,
    COUNT(CASE WHEN il.quantity_remaining > 0 THEN 1 END) AS lot_aktif
FROM products p
LEFT JOIN inventory_lots il ON il.product_id = p.id
GROUP BY p.id, p.sku, p.name, p.unit
HAVING stok_tersedia > 0 OR lot_aktif > 0
ORDER BY p.name;


-- -----------------------------------------------------------------------------
-- 6. PERSEDIAAN — Detail Lot (FIFO)
-- -----------------------------------------------------------------------------
SELECT
    p.sku,
    p.name,
    il.lot_number,
    il.quantity_remaining,
    il.unit_cost,
    il.received_at
FROM inventory_lots il
JOIN products p ON p.id = il.product_id
WHERE il.quantity_remaining > 0
ORDER BY p.name, il.received_at ASC;


-- -----------------------------------------------------------------------------
-- 7. PRODUKSI — Daftar Production Order
-- -----------------------------------------------------------------------------
SELECT
    po.order_number,
    p.name                                              AS produk,
    po.quantity_planned,
    po.quantity_completed,
    po.status,
    po.machine_hours,
    po.started_at,
    po.completed_at
FROM production_orders po
JOIN products p ON p.id = po.product_id
ORDER BY po.created_at DESC;


-- -----------------------------------------------------------------------------
-- 8. PRODUKSI — Bahan & Biaya per Order
-- -----------------------------------------------------------------------------
SELECT
    po.order_number,
    p.name                                              AS bahan,
    pom.quantity_planned,
    pom.quantity_used,
    pom.unit_cost,
    pom.total_cost
FROM production_order_materials pom
JOIN production_orders po ON po.id = pom.production_order_id
JOIN products p ON p.id = pom.product_id
ORDER BY po.order_number, p.name;


-- -----------------------------------------------------------------------------
-- 9. PRODUKSI — Tenaga Kerja per Order
-- -----------------------------------------------------------------------------
SELECT
    po.order_number,
    pol.description,
    pol.labor_hours,
    pol.hourly_rate,
    pol.total_cost
FROM production_order_labors pol
JOIN production_orders po ON po.id = pol.production_order_id
ORDER BY po.order_number;


-- -----------------------------------------------------------------------------
-- 10. OVERHEAD — Tarif Aktif
-- -----------------------------------------------------------------------------
SELECT
    name,
    allocation_base,
    rate,
    description
FROM overhead_rates
WHERE is_active = 1
ORDER BY name;


-- -----------------------------------------------------------------------------
-- 11. RIWAYAT COGS — Semua Perhitungan
-- -----------------------------------------------------------------------------
SELECT
    c.calculated_at,
    p.sku,
    p.name                                              AS produk,
    c.quantity,
    c.direct_material,
    c.direct_labor,
    c.manufacturing_overhead,
    c.total_cogs,
    c.unit_cogs,
    c.calculation_method,
    c.reference_type
FROM cogs_calculations c
JOIN products p ON p.id = c.product_id
ORDER BY c.calculated_at DESC;


-- -----------------------------------------------------------------------------
-- 12. PENJUALAN — Transaksi & Margin Kotor
-- -----------------------------------------------------------------------------
SELECT
    st.invoice_number,
    st.sold_at,
    p.name                                              AS produk,
    st.quantity,
    st.selling_price,
    st.total_revenue,
    c.total_cogs,
    ROUND(st.total_revenue - COALESCE(c.total_cogs, 0), 0) AS laba_kotor,
    ROUND(
        IF(st.total_revenue > 0,
           ((st.total_revenue - COALESCE(c.total_cogs, 0)) / st.total_revenue) * 100,
           0),
        1
    )                                                   AS margin_persen
FROM sales_transactions st
JOIN products p ON p.id = st.product_id
LEFT JOIN cogs_calculations c
    ON c.reference_type = 'App\\Models\\SalesTransaction'
   AND c.reference_id = st.id
ORDER BY st.sold_at DESC;


-- -----------------------------------------------------------------------------
-- 13. USER — Daftar Akun Login
-- -----------------------------------------------------------------------------
SELECT
    id,
    name,
    email,
    role,
    created_at
FROM users
ORDER BY role, name;


-- -----------------------------------------------------------------------------
-- 14. STATUS — Cek Kelengkapan Data (Panduan 6 Langkah)
-- -----------------------------------------------------------------------------
SELECT 'overhead_aktif' AS cek,
       COUNT(*) AS jumlah,
       IF(COUNT(*) > 0, 'OK', 'KOSONG') AS status
FROM overhead_rates WHERE is_active = 1
UNION ALL
SELECT 'bahan_baku',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM products WHERE type = 'raw_material'
UNION ALL
SELECT 'barang_jadi',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM products WHERE type IN ('semi_finished', 'finished_good')
UNION ALL
SELECT 'bom',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM bill_of_materials
UNION ALL
SELECT 'stok_aktif',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM inventory_lots WHERE quantity_remaining > 0
UNION ALL
SELECT 'produksi_selesai',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM production_orders WHERE status = 'completed'
UNION ALL
SELECT 'hasil_cogs',
       COUNT(*),
       IF(COUNT(*) > 0, 'OK', 'KOSONG')
FROM cogs_calculations;


-- -----------------------------------------------------------------------------
-- 15. INSERT — Contoh Data Master (opsional)
-- -----------------------------------------------------------------------------

-- Tarif overhead
-- INSERT INTO overhead_rates (name, allocation_base, rate, is_active, description, created_at, updated_at)
-- VALUES
-- ('Overhead Pabrik - Bahan', 'direct_material', 0.15, 1, '15% dari biaya bahan langsung', NOW(), NOW()),
-- ('Overhead Tenaga Kerja',   'labor_hours',     25000, 1, 'Rp 25.000 per jam kerja', NOW(), NOW());

-- Produk
-- INSERT INTO products (sku, name, type, unit, standard_cost, costing_method, is_active, created_at, updated_at)
-- VALUES
-- ('RM-FLOUR-001', 'Tepung Terigu', 'raw_material', 'kg', 12000, 'fifo', 1, NOW(), NOW()),
-- ('FG-BREAD-001', 'Roti Tawar',    'finished_good','loaf', 0, 'weighted_average', 1, NOW(), NOW());

-- Terima stok
-- INSERT INTO inventory_lots (product_id, lot_number, quantity_received, quantity_remaining, unit_cost, received_at, created_at, updated_at)
-- VALUES (1, 'LOT-001', 500, 500, 11500, NOW(), NOW(), NOW());


-- -----------------------------------------------------------------------------
-- 16. MAINTENANCE — Kosongkan Data COGS (struktur tabel tetap ada)
--     Atau jalankan: database/truncate_all.sql
-- -----------------------------------------------------------------------------
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE cogs_calculations;
-- TRUNCATE TABLE sales_transactions;
-- TRUNCATE TABLE production_order_labors;
-- TRUNCATE TABLE production_order_materials;
-- TRUNCATE TABLE production_orders;
-- TRUNCATE TABLE inventory_lots;
-- TRUNCATE TABLE bill_of_materials;
-- TRUNCATE TABLE overhead_rates;
-- TRUNCATE TABLE products;
-- SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 17. PERBAIKAN — Reset Akun Login (password = "password")
--     Atau jalankan file: database/fix_users.sql
-- -----------------------------------------------------------------------------
-- USE cogs_perhitungan;
-- INSERT INTO `users` (`name`, `email`, `role`, `password`, `created_at`, `updated_at`) VALUES
-- ('Admin COGS', 'cogs@local.test', 'cogs',
--  '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW()),
-- ('Kasir Demo', 'kasir@local.test', 'kasir',
--  '$2y$12$q6GEjgOI8aptqJCoLdHi4eD340tWa1pV5BG1HWA9Hv5zkIKNe7Zb.', NOW(), NOW())
-- ON DUPLICATE KEY UPDATE
--     `name` = VALUES(`name`),
--     `role` = VALUES(`role`),
--     `password` = VALUES(`password`),
--     `updated_at` = NOW();
