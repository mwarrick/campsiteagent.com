-- Migration 020: Create park alerts table
-- This table stores park alerts, closures, and restrictions scraped from park websites

CREATE TABLE IF NOT EXISTS park_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    park_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('closure', 'restriction', 'advisory', 'hours_change', 'maintenance') NOT NULL,
    severity ENUM('critical', 'warning', 'info') NOT NULL DEFAULT 'info',
    title VARCHAR(500) NOT NULL,
    description TEXT,
    effective_date DATE NULL,
    expiration_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    source_url VARCHAR(500) NULL,
    scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_park_alerts_park_id (park_id),
    INDEX idx_park_alerts_active (is_active),
    INDEX idx_park_alerts_type (alert_type),
    INDEX idx_park_alerts_severity (severity),
    INDEX idx_park_alerts_dates (effective_date, expiration_date),
    
    FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
