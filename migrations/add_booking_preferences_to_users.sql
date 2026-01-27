-- Add booking preference columns to users table
-- These columns are used by lawyers to set their booking window preferences

ALTER TABLE `users`
ADD COLUMN `default_booking_weeks` INT(11) DEFAULT 52 COMMENT 'Default weeks ahead clients can book' AFTER `is_active`,
ADD COLUMN `max_booking_weeks` INT(11) DEFAULT 104 COMMENT 'Maximum weeks ahead clients can book' AFTER `default_booking_weeks`,
ADD COLUMN `booking_window_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable booking window restrictions' AFTER `max_booking_weeks`;

-- Update existing users with default values
UPDATE `users` 
SET 
    `default_booking_weeks` = 52,
    `max_booking_weeks` = 104,
    `booking_window_enabled` = 1
WHERE `default_booking_weeks` IS NULL;
