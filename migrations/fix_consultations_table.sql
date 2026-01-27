-- Fix consultations table - Add missing columns
-- Run this migration to add columns that are missing from test.sql

ALTER TABLE `consultations`
ADD COLUMN `c_practice_area` VARCHAR(100) NULL AFTER `c_phone`,
ADD COLUMN `c_case_description` TEXT NULL AFTER `case_description`,
ADD COLUMN `c_selected_lawyer` VARCHAR(100) NULL AFTER `c_case_description`,
ADD COLUMN `c_selected_date` DATE NULL AFTER `c_selected_lawyer`,
ADD COLUMN `c_consultation_date` DATE NULL AFTER `consultation_date`,
ADD COLUMN `c_consultation_time` TIME NULL AFTER `consultation_time`,
ADD COLUMN `c_cancellation_reason` VARCHAR(255) NULL AFTER `cancellation_reason`;

-- Copy data from old columns to new prefixed columns (if old columns exist)
UPDATE `consultations` SET 
    `c_consultation_date` = `consultation_date`,
    `c_consultation_time` = `consultation_time`,
    `c_case_description` = `case_description`,
    `c_cancellation_reason` = `cancellation_reason`
WHERE `c_consultation_date` IS NULL OR `c_consultation_time` IS NULL;

-- Note: After verifying the migration works, you can drop the old columns:
-- ALTER TABLE `consultations` DROP COLUMN `case_description`;
-- ALTER TABLE `consultations` DROP COLUMN `consultation_date`;
-- ALTER TABLE `consultations` DROP COLUMN `consultation_time`;
-- ALTER TABLE `consultations` DROP COLUMN `cancellation_reason`;
