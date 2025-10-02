-- Add more metadata columns to sites table
-- Using individual statements to handle columns that may already exist

-- Sites table enhancements
ALTER TABLE sites ADD COLUMN site_name VARCHAR(255) DEFAULT NULL AFTER site_number;
ALTER TABLE sites ADD COLUMN unit_type_id INT DEFAULT NULL;
ALTER TABLE sites ADD COLUMN is_ada BOOLEAN DEFAULT FALSE;
ALTER TABLE sites ADD COLUMN vehicle_length INT DEFAULT 0;
ALTER TABLE sites ADD COLUMN allow_web_booking BOOLEAN DEFAULT TRUE;
ALTER TABLE sites ADD COLUMN is_web_viewable BOOLEAN DEFAULT TRUE;
ALTER TABLE sites ADD INDEX idx_is_ada (is_ada);
ALTER TABLE sites ADD INDEX idx_vehicle_length (vehicle_length);

-- Parks table enhancements
ALTER TABLE parks ADD COLUMN latitude DECIMAL(10, 7) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN longitude DECIMAL(10, 7) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN address1 VARCHAR(255) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN city VARCHAR(100) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN state VARCHAR(2) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN zip VARCHAR(10) DEFAULT NULL;
ALTER TABLE parks ADD COLUMN phone VARCHAR(20) DEFAULT NULL;

-- Facilities table enhancements
ALTER TABLE facilities ADD COLUMN description TEXT DEFAULT NULL;
ALTER TABLE facilities ADD COLUMN allow_web_booking BOOLEAN DEFAULT TRUE;
ALTER TABLE facilities ADD COLUMN facility_type INT DEFAULT 1;



