-- Update test_drives table to add time fields
-- This script adds time-related columns to the test_drives table

-- Add test_time column to store the time (HH:MM format)
ALTER TABLE test_drives ADD COLUMN test_time VARCHAR(5) AFTER test_date;

-- Add test_datetime column to store the combined date and time
ALTER TABLE test_drives ADD COLUMN test_datetime DATETIME AFTER test_time;

-- Update existing records to have default time values
-- Set default time to 10:00 AM for existing records
UPDATE test_drives SET test_time = '10:00' WHERE test_time IS NULL;

-- Update existing records to combine date and time into datetime
UPDATE test_drives SET test_datetime = CONCAT(test_date, ' ', test_time, ':00') WHERE test_datetime IS NULL;

-- Add index on test_datetime for better performance
CREATE INDEX idx_test_datetime ON test_drives(test_datetime);

-- Add index on car_id and test_datetime for unique constraint checking
CREATE INDEX idx_car_datetime ON test_drives(car_id, test_datetime);
