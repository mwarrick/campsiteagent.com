-- Align availability_runs schema to support daily aggregated runs (MySQL-compatible)
-- - Allow NULL park_id for aggregate runs
-- - Add optional configuration column for run metadata (only if missing)
-- - Include 'running' in status enum

DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_availability_runs $$
CREATE PROCEDURE migrate_availability_runs()
BEGIN
  DECLARE col_exists INT DEFAULT 0;

  -- Add configuration column if it does not exist
  SELECT COUNT(*) INTO col_exists
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'availability_runs'
    AND COLUMN_NAME = 'configuration';

  IF col_exists = 0 THEN
    ALTER TABLE availability_runs
      ADD COLUMN configuration TEXT NULL AFTER status;
  END IF;

  -- Allow NULL park_id for aggregate runs (safe to run repeatedly)
  ALTER TABLE availability_runs
    MODIFY park_id BIGINT UNSIGNED NULL;

  -- Ensure status enum includes 'running' (safe to run repeatedly if already matches)
  ALTER TABLE availability_runs
    MODIFY status ENUM('pending','running','success','error') NOT NULL DEFAULT 'pending';
END $$
CALL migrate_availability_runs() $$
DROP PROCEDURE migrate_availability_runs $$
DELIMITER ;


