-- TVRTKE v0.6: independently versioned registry sources (crafts, associations, foundations).
-- Run AFTER 001_initial.sql, 002_enrichment.sql and 003_publication_state.sql.
-- Do not insert fictitious records. Each source becomes visible only after an import commits.
CREATE TABLE IF NOT EXISTS registry_sources (
  source_type VARCHAR(16) NOT NULL PRIMARY KEY,
  active_import_id CHAR(32) NULL,
  source_url VARCHAR(512) NOT NULL,
  terms_url VARCHAR(512) NULL,
  coverage VARCHAR(16) NOT NULL DEFAULT 'partial',
  record_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  published_at_utc DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registry_records (
  source_type VARCHAR(16) NOT NULL,
  import_id CHAR(32) NOT NULL,
  record_hash CHAR(64) NOT NULL,
  source_identifier VARCHAR(128) NOT NULL,
  name VARCHAR(512) NOT NULL,
  oib CHAR(11) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'unknown',
  county VARCHAR(128) NULL,
  settlement VARCHAR(128) NULL,
  address VARCHAR(255) NULL,
  activity VARCHAR(512) NULL,
  PRIMARY KEY (source_type, import_id, record_hash),
  KEY idx_registry_name (source_type, import_id, name(120)),
  KEY idx_registry_oib (source_type, import_id, oib),
  KEY idx_registry_county (source_type, import_id, county(64)),
  KEY idx_registry_status (source_type, import_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registry_import_jobs (
  source_type VARCHAR(16) NOT NULL PRIMARY KEY,
  import_id CHAR(32) NOT NULL,
  input_size BIGINT UNSIGNED NOT NULL,
  input_mtime BIGINT UNSIGNED NOT NULL,
  next_byte_offset BIGINT UNSIGNED NOT NULL,
  imported_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expected_rows BIGINT UNSIGNED NULL,
  source_url VARCHAR(512) NOT NULL,
  terms_url VARCHAR(512) NULL,
  coverage VARCHAR(16) NOT NULL DEFAULT 'partial',
  csv_delimiter CHAR(1) NOT NULL,
  header_json TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
