-- Make door_location and device_info columns nullable in access_logs table
-- This allows the API to work without requiring these parameters

ALTER TABLE access_logs 
ALTER COLUMN door_location DROP NOT NULL;

-- device_info was already nullable (TEXT type), but let's be explicit
ALTER TABLE access_logs 
ALTER COLUMN device_info DROP NOT NULL; 