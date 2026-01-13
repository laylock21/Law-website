-- ============================================
-- MIGRATION: Add Confirmation Notification Type
-- Version: 003
-- Date: 2025-10-09
-- Description: Adds 'appointment_confirmed' to notification_type enum
-- ============================================

-- Modify the notification_type enum to include 'appointment_confirmed'
ALTER TABLE notification_queue 
MODIFY COLUMN notification_type ENUM(
    'appointment_cancelled', 
    'appointment_confirmed', 
    'schedule_changed', 
    'reminder', 
    'other'
) DEFAULT 'other';

-- Verify the change
DESCRIBE notification_queue;

-- Check existing notification types
SELECT DISTINCT notification_type, COUNT(*) as count
FROM notification_queue
GROUP BY notification_type;
