-- Add website_url column to parks table for official park websites
ALTER TABLE parks ADD COLUMN website_url VARCHAR(500) DEFAULT NULL AFTER phone;
