-- Manual "menu habis" flag for kasir Kelola Menu checklist.
-- Safe to run multiple times (checks column first).

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'is_sold_out'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE products ADD COLUMN is_sold_out TINYINT(1) NOT NULL DEFAULT 0 AFTER is_menu_item',
    'SELECT ''is_sold_out already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
