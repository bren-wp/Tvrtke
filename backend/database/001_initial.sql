-- TVRTKE v0.1: staging by snapshot, atomic publication by app_state.active_snapshot.
-- Uses utf8mb4 on MySQL/MariaDB; no Oracle DDL is executed on the hosting.
CREATE TABLE IF NOT EXISTS app_state (
  state_key VARCHAR(64) NOT NULL PRIMARY KEY,
  state_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  mbs CHAR(9) NOT NULL,
  oib CHAR(11) NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 0,
  datum_osnivanja DATE NULL,
  datum_brisanja DATE NULL,
  PRIMARY KEY (snapshot_id, mbs),
  KEY idx_subject_oib (snapshot_id, oib),
  KEY idx_subject_status (snapshot_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_names (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  mbs CHAR(9) NOT NULL,
  name VARCHAR(512) NOT NULL,
  PRIMARY KEY(snapshot_id, mbs),
  KEY idx_name_prefix (snapshot_id, name(120))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addresses (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  mbs CHAR(9) NOT NULL,
  county VARCHAR(128) NULL,
  municipality VARCHAR(128) NULL,
  settlement VARCHAR(128) NULL,
  street VARCHAR(255) NULL,
  house_number VARCHAR(32) NULL,
  postal_code VARCHAR(12) NULL,
  PRIMARY KEY(snapshot_id, mbs),
  KEY idx_county (snapshot_id, county)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_progress (
  dataset VARCHAR(32) NOT NULL PRIMARY KEY,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  next_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
  imported_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
  finished TINYINT(1) NOT NULL DEFAULT 0,
  truncated TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  snapshot_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(16) NOT NULL,
  message VARCHAR(500) NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL,
  KEY idx_sync_snapshot (snapshot_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
