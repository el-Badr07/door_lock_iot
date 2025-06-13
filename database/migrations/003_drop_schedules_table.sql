-- Drop schedules table and related indexes
-- Access will no longer be time-based

-- Drop index first
DROP INDEX IF EXISTS idx_schedules_user_id;

-- Drop schedules table
DROP TABLE IF EXISTS schedules; 