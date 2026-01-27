-- ============================================
-- COMPLETE DATABASE FIX - Run this in phpMyAdmin
-- ============================================
-- This fixes BOTH the users and consultations tables
-- Copy and paste this entire file into phpMyAdmin SQL tab

-- ============================================
-- FIX 1: Add booking preferences to users table
-- ============================================
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `default_booking_weeks` INT(11) DEFAULT 52 COMMENT 'Default weeks ahead clients can book' AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `max_booking_weeks` INT(11) DEFAULT 104 COMMENT 'Maximum weeks ahead clients can book' AFTER `default_booking_weeks`,
ADD COLUMN IF NOT EXISTS `booking_window_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable booking window restrictions' AFTER `max_booking_weeks`;

-- Update existing users with default values
UPDATE `users` 
SET 
    `default_booking_weeks` = COALESCE(`default_booking_weeks`, 52),
    `max_booking_weeks` = COALESCE(`max_booking_weeks`, 104),
    `booking_window_enabled` = COALESCE(`booking_window_enabled`, 1);

-- ============================================
-- FIX 2: Add missing columns to consultations table
-- ============================================
ALTER TABLE `consultations`
ADD COLUMN IF NOT EXISTS `c_practice_area` VARCHAR(100) NULL AFTER `c_phone`,
ADD COLUMN IF NOT EXISTS `c_case_description` TEXT NULL AFTER `case_description`,
ADD COLUMN IF NOT EXISTS `c_selected_lawyer` VARCHAR(100) NULL AFTER `c_case_description`,
ADD COLUMN IF NOT EXISTS `c_selected_date` DATE NULL AFTER `c_selected_lawyer`;

-- ============================================
-- FIX 3: Rename columns to use c_ prefix
-- ============================================
-- Note: These will fail if columns already renamed - that's OK!
ALTER TABLE `consultations`
CHANGE COLUMN `consultation_date` `c_consultation_date` DATE NOT NULL;

ALTER TABLE `consultations`
CHANGE COLUMN `consultation_time` `c_consultation_time` TIME NOT NULL;

ALTER TABLE `consultations`
CHANGE COLUMN `cancellation_reason` `c_cancellation_reason` VARCHAR(255) NULL;

-- ============================================
-- FIX 4: Copy data from old columns to new
-- ============================================
UPDATE `consultations` 
SET `c_case_description` = COALESCE(`c_case_description`, `case_description`)
WHERE `c_case_description` IS NULL AND `case_description` IS NOT NULL;

-- ============================================
-- VERIFICATION QUERIES (Run these after)
-- ============================================
-- Uncomment these to verify the fixes worked:

-- SELECT 'Users table columns:' as info;
-- DESCRIBE users;

-- SELECT 'Consultations table columns:' as info;
-- DESCRIBE consultations;

-- ============================================
-- SUCCESS!
-- ============================================
-- If no errors appeared above, your database is now fixed!
-- Refresh your browser and the application should work.
