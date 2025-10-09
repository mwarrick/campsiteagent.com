-- Migration 021: Fix park alerts title column length
-- The title column is too short for some park alert titles

ALTER TABLE park_alerts MODIFY COLUMN title VARCHAR(500) NOT NULL;
