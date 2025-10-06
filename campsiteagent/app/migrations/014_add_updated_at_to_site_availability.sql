-- Add updated_at column to track last refresh time of availability rows
-- Safe to run multiple times: checks for column existence

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'site_availability'
    AND COLUMN_NAME = 'updated_at'
);

SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE site_availability \n    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT "updated_at already exists on site_availability"'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


