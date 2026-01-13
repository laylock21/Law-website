-- ============================================
-- MIGRATION: Fix Race Conditions and Data Integrity
-- Version: 004
-- Date: 2025-10-09
-- Description: Adds constraints to prevent race conditions and data corruption
-- ============================================

-- Use the correct database
USE lawfirm_db;

-- First, let's check if the constraint already exists and drop it if needed
-- (This prevents errors if running the migration multiple times)
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
    WHERE constraint_schema = 'lawfirm_db' 
    AND table_name = 'lawyer_availability' 
    AND constraint_name = 'unique_lawyer_date_schedule');

SET @sql = IF(@constraint_exists > 0, 
    'ALTER TABLE lawyer_availability DROP CONSTRAINT unique_lawyer_date_schedule', 
    'SELECT "Constraint does not exist" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique constraint to prevent duplicate date blocking
-- Note: This will fail if duplicate data already exists
ALTER TABLE lawyer_availability 
ADD CONSTRAINT unique_lawyer_date_schedule 
UNIQUE (user_id, specific_date, schedule_type);

-- Add indexes for better performance (use IF NOT EXISTS equivalent)
-- Drop index if exists, then create
DROP INDEX IF EXISTS idx_lawyer_availability_date ON lawyer_availability;
CREATE INDEX idx_lawyer_availability_date ON lawyer_availability (user_id, specific_date);

DROP INDEX IF EXISTS idx_consultations_lawyer_date ON consultations;
CREATE INDEX idx_consultations_lawyer_date ON consultations (lawyer_id, consultation_date);

DROP INDEX IF EXISTS idx_consultations_status ON consultations;
CREATE INDEX idx_consultations_status ON consultations (status);

-- Note: MySQL CHECK constraints are parsed but ignored in versions < 8.0.16
-- For older MySQL versions, these constraints won't be enforced but won't cause errors
ALTER TABLE lawyer_availability 
ADD CONSTRAINT check_valid_date_range 
CHECK (start_time <= end_time);

ALTER TABLE lawyer_availability 
ADD CONSTRAINT check_max_appointments_positive 
CHECK (max_appointments >= 0);

-- For consultations, we'll skip the future date constraint as it may be too restrictive
-- ALTER TABLE consultations 
-- ADD CONSTRAINT check_consultation_future_date 
-- CHECK (consultation_date >= CURDATE());

-- Verify the changes (simplified to avoid permission issues)
SHOW INDEX FROM lawyer_availability WHERE Key_name LIKE 'idx_%' OR Key_name LIKE 'unique_%';
SHOW INDEX FROM consultations WHERE Key_name LIKE 'idx_%';

-- Show success message
SELECT 'Migration 004 completed successfully!' as Status;
