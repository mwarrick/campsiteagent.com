-- Add user-selected parks for monitoring

-- Step 1: Add facility_filter column to support per-park facility filtering
-- Check if column exists first
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'parks' 
    AND COLUMN_NAME = 'facility_filter'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE parks ADD COLUMN facility_filter JSON NULL COMMENT ''Optional JSON array of facility IDs to include (null = all facilities)''',
    'SELECT ''Column facility_filter already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add San Clemente State Beach (between San Onofre and Doheny)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('San Clemente SB', 'san_clemente', '707', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number), active = VALUES(active);

-- Step 3: Add Chino Hills State Park (inland park with camping)
INSERT INTO parks (name, external_id, park_number, active) 
VALUES ('Chino Hills SP', 'chino_hills', '627', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), park_number = VALUES(park_number), active = VALUES(active);

-- Step 4: Crystal Cove State Park - only Moro Campground (facility 447)
-- Update if exists, or insert if not
INSERT INTO parks (name, external_id, park_number, active, facility_filter) 
VALUES ('Crystal Cove SP', 'crystal_cove', '635', 1, '["447"]')
ON DUPLICATE KEY UPDATE 
    park_number = VALUES(park_number), 
    active = VALUES(active),
    facility_filter = VALUES(facility_filter);

