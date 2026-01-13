-- ============================================
-- MIGRATION: Add Notification System
-- Version: 002
-- Date: 2025-10-02
-- Description: Adds notification queue for email alerts
-- ============================================

-- Create notification queue table
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('appointment_cancelled', 'schedule_changed', 'reminder', 'other') DEFAULT 'other',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- AUTO-CLEANUP EVENT
-- ============================================

-- Enable event scheduler (required for automatic cleanup)
SET GLOBAL event_scheduler = ON;

-- Change delimiter for event creation
DELIMITER $$

-- Create daily cleanup event (runs at 2 AM)
CREATE EVENT IF NOT EXISTS cleanup_notification_queue
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 2 HOUR)
COMMENT 'Automatically cleanup old notifications to prevent database bloat'
DO
BEGIN
  -- Delete sent notifications older than 90 days
  DELETE FROM notification_queue 
  WHERE status = 'sent' 
  AND sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
  
  -- Delete failed notifications older than 30 days
  DELETE FROM notification_queue 
  WHERE status = 'failed' 
  AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
  
  -- Delete stuck pending notifications older than 7 days
  DELETE FROM notification_queue 
  WHERE status = 'pending' 
  AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$

-- Reset delimiter
DELIMITER ;

-- ============================================
-- VERIFICATION
-- ============================================

-- Check table was created
SHOW TABLES LIKE 'notification_queue';

-- Check structure
DESCRIBE notification_queue;

-- Check event was created
SHOW EVENTS WHERE Name = 'cleanup_notification_queue';

-- Check event scheduler is enabled
SHOW VARIABLES LIKE 'event_scheduler';
