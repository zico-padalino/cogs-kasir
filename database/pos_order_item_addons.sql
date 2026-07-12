-- Simpan add-on yang dipilih per item pesanan (untuk potong stok bahan saat bayar).
-- Jalankan sekali. Jika kolom sudah ada, abaikan error duplicate column.
ALTER TABLE `pos_order_items`
    ADD COLUMN `addon_ids` JSON NULL AFTER `notes`;
