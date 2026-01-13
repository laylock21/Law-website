-- ============================================
-- MIGRATION: Add Time Slot Support
-- Version: 004
-- Date: 2025-10-03
-- Description: Adds time slot functionality to consultation booking
-- ============================================

-- Add consultation_time column to consultations table
ALTER TABLE consultations 
ADD COLUMN consultation_time TIME NULL AFTER consultation_date;

-- Add time_slot_duration to lawyer_availability (in minutes)
ALTER TABLE lawyer_availability 
ADD COLUMN time_slot_duration INT DEFAULT 60 COMMENT 'Duration of each time slot in minutes' AFTER max_appointments;

-- Update existing records to have default 60-minute slots
UPDATE lawyer_availability 
SET time_slot_duration = 60 
WHERE time_slot_duration IS NULL;

-- ============================================
-- VERIFICATION
-- ============================================

-- Check if columns were added
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'consultations' 
AND COLUMN_NAME = 'consultation_time';

SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'lawyer_availability' 
AND COLUMN_NAME = 'time_slot_duration';

-- Show sample data structure
DESCRIBE consultations;
DESCRIBE lawyer_availability;
