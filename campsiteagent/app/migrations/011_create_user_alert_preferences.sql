-- User alert preferences
-- Allows users to customize which parks they want alerts for, date ranges, and notification frequency
CREATE TABLE IF NOT EXISTS user_alert_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  park_id BIGINT UNSIGNED NULL COMMENT 'NULL means all parks',
  start_date DATE NULL COMMENT 'NULL means any date',
  end_date DATE NULL COMMENT 'NULL means any date',
  frequency ENUM('immediate','daily_digest','weekly_digest') NOT NULL DEFAULT 'immediate',
  weekend_only TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Only alert for Friday-Saturday availability',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_park_id (park_id),
  KEY idx_enabled (enabled),
  CONSTRAINT fk_user_alert_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_alert_preferences_park FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add default preference for existing users (alert for all parks, all dates, immediate)
INSERT INTO user_alert_preferences (user_id, park_id, start_date, end_date, frequency, weekend_only, enabled)
SELECT id, NULL, NULL, NULL, 'immediate', 1, 1
FROM users
WHERE NOT EXISTS (
  SELECT 1 FROM user_alert_preferences WHERE user_id = users.id
);

