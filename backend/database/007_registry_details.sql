-- v0.7.0: preserve full public legal-entity activity description and exact
-- registry-entry/status dates separately from incorporation/deletion dates.
-- Run after 006. Source exports retain provenances in registry_sources.
ALTER TABLE registry_records
  MODIFY COLUMN activity MEDIUMTEXT NULL,
  ADD COLUMN datum_upisa DATE NULL AFTER datum_brisanja,
  ADD COLUMN datum_statusa DATE NULL AFTER datum_upisa;
