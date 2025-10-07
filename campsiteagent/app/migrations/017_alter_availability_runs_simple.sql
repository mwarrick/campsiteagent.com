-- Simple schema update for availability_runs without routines
-- Run these statements individually. If the ADD COLUMN fails with "Duplicate column",
-- you can safely ignore it and proceed to the next statement.

-- 1) Allow NULL park_id for aggregate runs
ALTER TABLE availability_runs
  MODIFY park_id BIGINT UNSIGNED NULL;

-- 2) Add configuration column (run only if not present; will error if it exists)
ALTER TABLE availability_runs
  ADD COLUMN configuration TEXT NULL AFTER status;

-- 3) Expand status enum to include 'running'
ALTER TABLE availability_runs
  MODIFY status ENUM('pending','running','success','error') NOT NULL DEFAULT 'pending';


