-- TVRTKE v0.4.0 -- does not rewrite active snapshot or existing entries.
-- Existing deployments without this value are conservatively shown as partial.
INSERT IGNORE INTO app_state (state_key, state_value)
SELECT 'published_partial', '1' FROM app_state WHERE state_key = 'active_snapshot';
