-- Notifications table for email send logs
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_preview VARCHAR(500) NULL,
  status ENUM('sent','failed') NOT NULL,
  error TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metadata_json JSON NULL,
  PRIMARY KEY (id),
  KEY idx_user_email (user_email),
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
