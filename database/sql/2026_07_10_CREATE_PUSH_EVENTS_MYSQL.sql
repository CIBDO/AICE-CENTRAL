-- ============================================================================
-- Table: push_events
-- Base: MySQL / vue-laravel
-- But: Journaliser tous les push reçus côté central pour l'observabilité IT.
-- ============================================================================
CREATE TABLE IF NOT EXISTS push_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  region_code VARCHAR(50) NULL,

  endpoint VARCHAR(255) NOT NULL,
  method VARCHAR(10) NULL,

  status ENUM('OK', 'ERROR') NOT NULL,
  http_status INT NULL,
  duration_ms INT NULL,

  correlation_id CHAR(36) NULL,

  mandats_count INT NULL,
  recettes_count INT NULL,
  banques_count INT NULL,

  message VARCHAR(1000) NULL,
  payload_hash BINARY(32) NULL,
  remote_ip VARCHAR(64) NULL,
  user_agent VARCHAR(512) NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_push_events_received_at (received_at),
  KEY idx_push_events_region_date (region_code, received_at),
  KEY idx_push_events_status_http (status, http_status, received_at),
  KEY idx_push_events_endpoint (endpoint, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
