-- Add park_number column to parks table
ALTER TABLE parks ADD COLUMN park_number VARCHAR(50) NULL AFTER external_id;

-- Update San Onofre State Beach with correct name and park number
UPDATE parks 
SET name = 'San Onofre SB', 
    park_number = '712'
WHERE external_id = 'san_onofre';



