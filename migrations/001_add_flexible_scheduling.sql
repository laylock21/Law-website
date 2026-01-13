-- ============================================
-- MIGRATION: Add Flexible Scheduling Support
-- Version: 001
-- Date: 2025-10-02
-- Author: System Migration
-- Description: 
--   Adds support for both weekly recurring schedules 
--   and one-time specific date schedules with time slots
-- ============================================

-- ⚠️ IMPORTANT: BACKUP YOUR DATABASE BEFORE RUNNING THIS MIGRATION!
-- Run this command first:
-- mysqldump -u root -p lawfirm_db > backup_before_migration_001.sql

-- ============================================
-- STEP 1: Add New Columns
-- ============================================

-- Add schedule_type column (weekly or one_time)
ALTER TABLE lawyer_availability 
ADD COLUMN schedule_type ENUM('weekly', 'one_time') NOT NULL DEFAULT 'weekly' 
COMMENT 'Type of schedule: weekly recurring or one-time specific date'
AFTER user_id;

-- Add specific_date column (for one-time schedules)
ALTER TABLE lawyer_availability 
ADD COLUMN specific_date DATE NULL 
COMMENT 'Specific date for one-time schedules (NULL for weekly schedules)'
AFTER schedule_type;

-- Add is_active column (for soft delete)
ALTER TABLE lawyer_availability 
ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 1 
COMMENT 'Active status: 1=active, 0=inactive (soft delete)'
AFTER max_appointments;

-- ============================================
-- STEP 2: Update Existing Data
-- ============================================

-- Mark all existing schedules as 'weekly' type
UPDATE lawyer_availability 
SET schedule_type = 'weekly',
    is_active = 1
WHERE schedule_type IS NULL OR schedule_type = '';

-- ============================================
-- STEP 3: Remove Old Constraints
-- ============================================

-- Drop the UNIQUE constraint on user_id 
-- (allows multiple schedules per lawyer)
ALTER TABLE lawyer_availability 
DROP INDEX IF EXISTS unique_lawyer_availability;

-- ============================================
-- STEP 4: Add New Indexes for Performance
-- ============================================

-- Composite index for efficient queries
ALTER TABLE lawyer_availability 
ADD INDEX idx_lawyer_schedule (user_id, schedule_type, is_active);

-- Index for specific date lookups
ALTER TABLE lawyer_availability 
ADD INDEX idx_specific_date (specific_date, is_active);

-- Index for active schedules
ALTER TABLE lawyer_availability 
ADD INDEX idx_active_schedules (is_active, user_id);

-- ============================================
-- STEP 5: Add Validation Constraint
-- ============================================

-- Add check constraint: one-time schedules MUST have specific_date
-- (MySQL 8.0.16+, for older versions this will be skipped)
ALTER TABLE lawyer_availability 
ADD CONSTRAINT chk_one_time_has_date 
CHECK (
    (schedule_type = 'weekly' AND specific_date IS NULL) OR
    (schedule_type = 'one_time' AND specific_date IS NOT NULL)
);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- ⚠️ DO NOT RUN THESE WITH THE MIGRATION!
-- Run these queries SEPARATELY after the migration completes
-- Copy and paste each query ONE AT A TIME in phpMyAdmin

-- See file: 001_verify.sql for easy copy-paste verification

-- ============================================
-- MIGRATION COMPLETE ✅
-- ============================================

-- Next Steps:
-- 1. Verify all queries above return expected results
-- 2. Test the application to ensure existing schedules still work
-- 3. If everything works, you can proceed to Phase 2 (API updates)
-- 4. If there are issues, run the rollback script: 001_rollback.sql

-- Notes:
-- - All existing weekly schedules are preserved
-- - Lawyers can now have multiple schedules (weekly + one-time)
-- - One-time schedules will override weekly schedules on specific dates
-- - Soft delete allows deactivating schedules without losing data
