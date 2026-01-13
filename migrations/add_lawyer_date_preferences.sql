-- Add lawyer date range preferences to users table
-- This allows each lawyer to have individual booking window settings

ALTER TABLE users 
ADD COLUMN default_booking_weeks INT DEFAULT NULL COMMENT 'Default weeks to show in calendar (1 year default) - only for lawyers',
ADD COLUMN max_booking_weeks INT DEFAULT NULL COMMENT 'Maximum weeks clients can book ahead (2 years default) - only for lawyers',
ADD COLUMN booking_window_enabled BOOLEAN DEFAULT NULL COMMENT 'Enable custom booking window for this lawyer - only for lawyers';

-- Update existing lawyers with default values
UPDATE users 
SET default_booking_weeks = 52, 
    max_booking_weeks = 104, 
    booking_window_enabled = 1 
WHERE role = 'lawyer';

-- Add index for performance
ALTER TABLE users ADD INDEX idx_lawyer_booking_settings (role, booking_window_enabled);
