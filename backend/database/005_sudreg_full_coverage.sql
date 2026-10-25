-- TVRTKE v0.7: retain independently verified API totals for each pinned Sudreg dataset.
-- Run after migrations 001–004. Keeps existing data unchanged.
-- An old in-progress active-only import must not be represented as all-records complete.
ALTER TABLE sync_progress
  ADD COLUMN expected_rows BIGINT UNSIGNED NULL AFTER imported_rows,
  ADD COLUMN include_inactive TINYINT(1) NOT NULL DEFAULT 0 AFTER expected_rows;

CREATE INDEX idx_progress_snapshot ON sync_progress (snapshot_id, finished);
