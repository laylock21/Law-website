-- ============================================
-- MIGRATION: Add 'blocked' Schedule Type
-- Version: 005
-- Date: 2025-10-07
-- Description: Adds 'blocked' option to schedule_type ENUM for admin blocking functionality
-- ============================================

-- Add 'blocked' to the schedule_type ENUM
ALTER TABLE lawyer_availability 
MODIFY COLUMN schedule_type ENUM('weekly', 'one_time', 'blocked') NOT NULL DEFAULT 'weekly' 
COMMENT 'Type of schedule: weekly recurring, one-time specific date, or blocked date';

-- ============================================
-- VERIFICATION
-- ============================================

-- Check if the column was updated
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'lawyer_availability' 
AND COLUMN_NAME = 'schedule_type';

-- Show current structure
DESCRIBE lawyer_availability;
