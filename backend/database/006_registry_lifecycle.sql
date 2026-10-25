-- TVRTKE v0.7.1: original dates from the authorized source; no inferred history.
-- Run once after 005.
ALTER TABLE registry_records
  ADD COLUMN datum_osnivanja DATE NULL AFTER activity,
  ADD COLUMN datum_brisanja DATE NULL AFTER datum_osnivanja;
