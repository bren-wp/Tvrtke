-- TVRTKE v0.7: retain independently verified API totals for each pinned Sudreg dataset.
-- Run after migrations 001–004. Keeps existing data unchanged.
-- An old in-progress active-only import must not be represented as all-records complete.
ALTER TABLE sync_progress
  ADD COLUMN expected_rows BIGINT UNSIGNED NULL AFTER imported_rows,
  ADD COLUMN include_inactive TINYINT(1) NOT NULL DEFAULT 0 AFTER expected_rows;

CREATE INDEX idx_progress_snapshot ON sync_progress (snapshot_id, finished);

-- Optional lossless copy of each returned row (enabled only if storage/privacy checks permit).
-- Historical duplicate rows for a subject can coexist: row_index is its position
-- in the API snapshot dataset, independent of MBS. No credentials stored.
CREATE TABLE IF NOT EXISTS sudreg_raw_records (
  snapshot_id BIGINT UNSIGNED NOT NULL,
  dataset VARCHAR(32) NOT NULL,
  row_index BIGINT UNSIGNED NOT NULL,
  payload_json MEDIUMTEXT NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  PRIMARY KEY (snapshot_id,dataset,row_index),
  KEY idx_raw_dataset (dataset,snapshot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
