-- Create facilities table to store park facilities/campgrounds
CREATE TABLE IF NOT EXISTS facilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    park_id BIGINT UNSIGNED NOT NULL,
    facility_id VARCHAR(50) NULL COMMENT 'External facility ID from ReserveCalifornia',
    name VARCHAR(255) NOT NULL COMMENT 'Facility name e.g. "Bluff Camp (sites 44-66)"',
    description TEXT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE,
    INDEX idx_park_id (park_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add facility_id to sites table to link sites to facilities
ALTER TABLE sites ADD COLUMN facility_id BIGINT UNSIGNED NULL AFTER park_id;
ALTER TABLE sites ADD FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE SET NULL;
ALTER TABLE sites ADD INDEX idx_facility_id (facility_id);

