-- Availability runs
CREATE TABLE IF NOT EXISTS availability_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  park_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status ENUM('pending','success','error') NOT NULL DEFAULT 'pending',
  error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_park_started (park_id, started_at),
  CONSTRAINT fk_runs_park FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
