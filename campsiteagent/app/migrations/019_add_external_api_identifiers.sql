-- Migration 019: Add external API identifiers to properly map ReserveCalifornia data
-- This addresses the mismatch between API site numbers and database site numbers

-- Add external API identifiers to sites table
ALTER TABLE sites ADD COLUMN external_site_id VARCHAR(50) NULL AFTER site_number;
ALTER TABLE sites ADD COLUMN external_unit_type_id VARCHAR(50) NULL AFTER unit_type_id;
ALTER TABLE sites ADD COLUMN external_facility_id VARCHAR(50) NULL AFTER facility_id;

-- Add index for faster lookups by external identifiers
CREATE INDEX idx_sites_external_site_id ON sites(external_site_id);
CREATE INDEX idx_sites_external_facility_id ON sites(external_facility_id);

-- Add external API identifiers to facilities table  
ALTER TABLE facilities ADD COLUMN external_facility_id VARCHAR(50) NULL AFTER facility_id;

-- Add index for faster lookups by external facility ID
CREATE INDEX idx_facilities_external_facility_id ON facilities(external_facility_id);

-- Update existing data to use current facility_id as external_facility_id
UPDATE facilities SET external_facility_id = facility_id WHERE external_facility_id IS NULL;

-- Update existing data to use current site_number as external_site_id
UPDATE sites SET external_site_id = site_number WHERE external_site_id IS NULL;
