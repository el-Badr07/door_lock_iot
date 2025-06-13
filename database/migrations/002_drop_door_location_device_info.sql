-- Drop door_location and device_info columns from access_logs table
-- These are no longer needed since the API has been simplified
-- Check if columns exist before trying to drop them

DO $$ 
BEGIN
    -- Drop door_location column if it exists
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'access_logs' AND column_name = 'door_location') THEN
        ALTER TABLE access_logs DROP COLUMN door_location;
    END IF;
    
    -- Drop device_info column if it exists
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'access_logs' AND column_name = 'device_info') THEN
        ALTER TABLE access_logs DROP COLUMN device_info;
    END IF;
END $$; 