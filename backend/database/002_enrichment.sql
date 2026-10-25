-- TVRTKE v0.2.0: optional public Sudreg enrichment (API v3.0.4).
-- Run AFTER 001_initial.sql. Import remains pinned to one snapshot.
CREATE TABLE IF NOT EXISTS subject_legal_forms (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  mbs CHAR(9) NOT NULL,
  form_code VARCHAR(12) NULL,
  form_name VARCHAR(128) NULL,
  form_abbreviation VARCHAR(20) NULL,
  PRIMARY KEY (snapshot_id, mbs),
  KEY idx_form_code (snapshot_id, form_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subject_activities (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  mbs CHAR(9) NOT NULL,
  activity_rbr SMALLINT UNSIGNED NOT NULL,
  activity_text TEXT NULL,
  nkd_code VARCHAR(12) NULL,
  nkd_name VARCHAR(512) NULL,
  nkd_version VARCHAR(64) NULL,
  PRIMARY KEY (snapshot_id, mbs, activity_rbr),
  KEY idx_activity_mbs (snapshot_id, mbs),
  KEY idx_activity_nkd (snapshot_id, nkd_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_addresses_settlement ON addresses (snapshot_id, settlement(80));
