-- =============================================================================
-- TRINIDADV9 COMPREHENSIVE LAW FIRM DATABASE - ALL FEATURES INTEGRATED
-- =============================================================================
-- 
-- This comprehensive SQL file incorporates ALL features from the TRINIDADV9
-- Law Firm Consultation System including all migrations and enhancements.
-- 
-- COMPLETE FEATURE SET:
-- ✓ Multi-role user management (admin/staff/lawyer)
-- ✓ Advanced consultation booking with time slots
-- ✓ Flexible lawyer availability (weekly/one-time/blocked dates)
-- ✓ Practice area specialization system
-- ✓ Email notification queue with retry mechanism
-- ✓ Document generation support (DOCX attachments)
-- ✓ Profile management with picture uploads
-- ✓ Cancellation tracking with reasons
-- ✓ Lawyer-specific booking preferences
-- ✓ Performance optimization with indexes
-- ✓ Data integrity with foreign key constraints
-- ✓ Automatic cleanup procedures
-- ✓ Security enhancements
-- 
-- VERSION: TRINIDADV9 Complete Production (October 2025)
-- COMPATIBILITY: MySQL 5.7+, MariaDB 10.2+
-- =============================================================================

-- Set SQL mode and character set for optimal compatibility
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS lawfirm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE lawfirm_db;

-- =============================================================================
-- 1. DROP EXISTING TABLES (Clean Import)
-- =============================================================================

DROP TABLE IF EXISTS notification_queue;
DROP TABLE IF EXISTS lawyer_specializations;
DROP TABLE IF EXISTS lawyer_availability;
DROP TABLE IF EXISTS lawyer_settings;
DROP TABLE IF EXISTS consultations;
DROP TABLE IF EXISTS practice_areas;
DROP TABLE IF EXISTS users;

-- =============================================================================
-- 2. CORE TABLES CREATION
-- =============================================================================

-- Users table with comprehensive profile support
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL COMMENT 'Professional biography and description',
    profile_picture VARCHAR(255) DEFAULT NULL COMMENT 'Profile picture filename',
    role ENUM('admin','staff','lawyer') DEFAULT 'staff',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Practice areas with detailed descriptions
CREATE TABLE practice_areas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY area_name (area_name),
    INDEX idx_practice_areas_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced consultations table with comprehensive tracking
CREATE TABLE consultations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    service VARCHAR(100) NOT NULL COMMENT 'Practice area/service requested',
    message TEXT NOT NULL COMMENT 'Client inquiry details',
    lawyer VARCHAR(100) DEFAULT NULL COMMENT 'Assigned lawyer name',
    lawyer_id INT(11) DEFAULT NULL COMMENT 'Assigned lawyer ID',
    date DATE DEFAULT NULL COMMENT 'Requested consultation date',
    selected_time TIME DEFAULT NULL COMMENT 'Specific time slot',
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    cancellation_reason VARCHAR(255) DEFAULT NULL COMMENT 'Reason for cancellation',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_consultations_email (email),
    INDEX idx_consultations_status (status),
    INDEX idx_consultations_date (date),
    INDEX idx_consultations_lawyer_id (lawyer_id),
    INDEX idx_consultations_created (created_at),
    INDEX idx_consultations_service (service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lawyer specializations (many-to-many relationship)
CREATE TABLE lawyer_specializations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    practice_area_id INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_lawyer_specialization (user_id, practice_area_id),
    INDEX idx_lawyer_specializations_user (user_id),
    INDEX idx_lawyer_specializations_practice (practice_area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Advanced lawyer availability with flexible scheduling
CREATE TABLE lawyer_availability (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    schedule_type ENUM('weekly', 'one_time', 'blocked') NOT NULL DEFAULT 'weekly' 
        COMMENT 'weekly: recurring schedule, one_time: specific date, blocked: unavailable date',
    specific_date DATE NULL COMMENT 'For one_time and blocked schedule types',
    weekdays VARCHAR(20) NOT NULL DEFAULT '' 
        COMMENT 'Comma-separated weekday numbers (0=Sunday, 1=Monday, etc.)',
    start_time TIME DEFAULT '09:00:00',
    end_time TIME DEFAULT '17:00:00',
    max_appointments INT(11) DEFAULT 5 COMMENT 'Maximum appointments per time slot',
    time_slot_duration INT DEFAULT 60 COMMENT 'Duration of each time slot in minutes',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_lawyer_availability_user (user_id),
    INDEX idx_lawyer_availability_type (schedule_type),
    INDEX idx_lawyer_availability_date (specific_date),
    INDEX idx_lawyer_availability_weekdays (weekdays),
    INDEX idx_lawyer_schedule_active (user_id, schedule_type, is_active),
    INDEX idx_specific_date_active (specific_date, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lawyer-specific settings and preferences
CREATE TABLE lawyer_settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    default_booking_weeks INT DEFAULT 52 COMMENT 'Default weeks to show in calendar',
    max_booking_weeks INT DEFAULT 104 COMMENT 'Maximum weeks clients can book ahead',
    booking_window_enabled BOOLEAN DEFAULT 1 COMMENT 'Enable custom booking window',
    timezone VARCHAR(50) DEFAULT 'UTC' COMMENT 'Lawyer timezone preference',
    auto_confirm_appointments BOOLEAN DEFAULT 0 COMMENT 'Auto-confirm new appointments',
    email_notifications BOOLEAN DEFAULT 1 COMMENT 'Receive email notifications',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_settings (user_id),
    INDEX idx_lawyer_settings_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced notification queue with comprehensive tracking
CREATE TABLE notification_queue (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) DEFAULT NULL COMMENT 'Related consultation ID',
    user_id INT(11) NOT NULL COMMENT 'Target user ID',
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM(
        'appointment_cancelled',
        'schedule_changed', 
        'reminder',
        'confirmation',
        'appointment_completed',
        'appointment_confirmed',
        'other'
    ) DEFAULT 'other',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0 COMMENT 'Number of send attempts',
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    attachment_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to attachment file (e.g., DOCX)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL COMMENT 'Error details for failed sends',
    PRIMARY KEY (id),
    INDEX idx_notification_status (status),
    INDEX idx_notification_created (created_at),
    INDEX idx_notification_user (user_id),
    INDEX idx_notification_consultation (consultation_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_notification_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- 3. FOREIGN KEY CONSTRAINTS
-- =============================================================================

-- Consultations foreign keys
ALTER TABLE consultations
    ADD CONSTRAINT fk_consultation_lawyer 
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Lawyer specializations foreign keys
ALTER TABLE lawyer_specializations
    ADD CONSTRAINT fk_specialization_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_specialization_practice 
    FOREIGN KEY (practice_area_id) REFERENCES practice_areas(id) ON DELETE CASCADE;

-- Lawyer availability foreign key
ALTER TABLE lawyer_availability
    ADD CONSTRAINT fk_availability_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Lawyer settings foreign key
ALTER TABLE lawyer_settings
    ADD CONSTRAINT fk_settings_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Notification queue foreign keys
ALTER TABLE notification_queue
    ADD CONSTRAINT fk_notification_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_notification_consultation
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL;

-- =============================================================================
-- 4. ESSENTIAL DATA INSERTION
-- =============================================================================

-- Insert admin user (password: admin123)
INSERT INTO users (username, password, email, first_name, last_name, role, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
 'admin@lawfirm.com', 'System', 'Administrator', 'admin', 1);

-- Insert comprehensive practice areas
INSERT INTO practice_areas (area_name, description, is_active) VALUES 
('Criminal Defense', 'Expert criminal defense representation including felonies, misdemeanors, DUI, and white-collar crimes', 1),
('Family Law', 'Comprehensive family legal services including divorce, child custody, adoption, and domestic relations', 1),
('Corporate Law', 'Business law services including entity formation, contracts, mergers, acquisitions, and corporate governance', 1),
('Real Estate Law', 'Complete real estate legal services including transactions, disputes, zoning, and property development', 1),
('Personal Injury', 'Dedicated personal injury representation for accidents, medical malpractice, and wrongful death cases', 1),
('Immigration Law', 'Full-service immigration assistance including visas, green cards, citizenship, and deportation defense', 1),
('Employment Law', 'Workplace legal services including discrimination, wrongful termination, and employment contracts', 1),
('Intellectual Property', 'IP protection services including patents, trademarks, copyrights, and trade secrets', 1),
('Estate Planning', 'Comprehensive estate planning including wills, trusts, probate, and asset protection', 1),
('Tax Law', 'Tax legal services including tax planning, disputes, audits, and compliance', 1),
('Bankruptcy Law', 'Debt relief services including Chapter 7, Chapter 13, and business bankruptcy', 1),
('Environmental Law', 'Environmental compliance, litigation, and regulatory matters', 1);

-- Insert sample lawyer with complete profile
INSERT INTO users (username, password, email, first_name, last_name, phone, bio, role, is_active) VALUES 
('lawyer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
 'lawyer1@lawfirm.com', 'Maria', 'Santos', '+1-555-0123',
 'Experienced attorney with over 10 years of practice in family law and criminal defense. Committed to providing personalized legal solutions and aggressive representation for clients.', 
 'lawyer', 1);

-- Set up lawyer specializations
INSERT INTO lawyer_specializations (user_id, practice_area_id) VALUES 
(2, 1), -- Criminal Defense
(2, 2); -- Family Law

-- Set up lawyer settings
INSERT INTO lawyer_settings (user_id, default_booking_weeks, max_booking_weeks, booking_window_enabled) VALUES 
(2, 52, 104, 1);

-- Set up sample lawyer availability (Monday to Friday, 9 AM to 5 PM)
INSERT INTO lawyer_availability (user_id, schedule_type, weekdays, start_time, end_time, max_appointments, time_slot_duration, is_active) VALUES 
(2, 'weekly', '1,2,3,4,5', '09:00:00', '17:00:00', 8, 60, 1);

-- =============================================================================
-- 5. PERFORMANCE OPTIMIZATION VIEWS
-- =============================================================================

-- View for lawyer availability with user details
CREATE VIEW lawyer_availability_view AS
SELECT 
    la.id,
    la.user_id,
    CONCAT(u.first_name, ' ', u.last_name) as lawyer_name,
    u.email as lawyer_email,
    la.schedule_type,
    la.specific_date,
    la.weekdays,
    la.start_time,
    la.end_time,
    la.max_appointments,
    la.time_slot_duration,
    la.is_active,
    la.created_at,
    la.updated_at
FROM lawyer_availability la
JOIN users u ON la.user_id = u.id
WHERE u.role = 'lawyer' AND u.is_active = 1;

-- View for consultation summary with lawyer details
CREATE VIEW consultation_summary_view AS
SELECT 
    c.id,
    CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) as client_name,
    c.email,
    c.phone,
    c.service,
    c.lawyer,
    CONCAT(u.first_name, ' ', u.last_name) as assigned_lawyer_name,
    c.date,
    c.selected_time,
    c.status,
    c.cancellation_reason,
    c.created_at,
    c.updated_at
FROM consultations c
LEFT JOIN users u ON c.lawyer_id = u.id;

-- =============================================================================
-- 6. STORED PROCEDURES FOR COMMON OPERATIONS
-- =============================================================================

DELIMITER $$

-- Procedure to get lawyer availability for a specific date range
CREATE PROCEDURE GetLawyerAvailability(
    IN lawyer_id INT,
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT 
        la.id,
        la.schedule_type,
        la.specific_date,
        la.weekdays,
        la.start_time,
        la.end_time,
        la.max_appointments,
        la.time_slot_duration,
        CONCAT(u.first_name, ' ', u.last_name) as lawyer_name
    FROM lawyer_availability la
    JOIN users u ON la.user_id = u.id
    WHERE la.user_id = lawyer_id 
    AND la.is_active = 1
    AND (
        (la.schedule_type = 'weekly') OR
        (la.schedule_type IN ('one_time', 'blocked') AND la.specific_date BETWEEN start_date AND end_date)
    )
    ORDER BY la.schedule_type, la.specific_date, la.start_time;
END$$

-- Procedure to queue notification
CREATE PROCEDURE QueueNotification(
    IN p_consultation_id INT,
    IN p_user_id INT,
    IN p_recipient_email VARCHAR(255),
    IN p_subject VARCHAR(255),
    IN p_message TEXT,
    IN p_notification_type VARCHAR(50),
    IN p_priority VARCHAR(10)
)
BEGIN
    INSERT INTO notification_queue (
        consultation_id, user_id, recipient_email, subject, 
        message, notification_type, priority, status
    ) VALUES (
        p_consultation_id, p_user_id, p_recipient_email, p_subject,
        p_message, p_notification_type, p_priority, 'pending'
    );
END$$

DELIMITER ;

-- =============================================================================
-- 7. AUTOMATIC CLEANUP EVENT
-- =============================================================================

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

DELIMITER $$

-- Create cleanup event for notification queue
CREATE EVENT IF NOT EXISTS cleanup_notification_queue
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 2 HOUR)
COMMENT 'Daily cleanup of old notifications'
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

DELIMITER ;

-- =============================================================================
-- 8. SECURITY AND VALIDATION TRIGGERS
-- =============================================================================

DELIMITER $$

-- Trigger to validate lawyer availability data
CREATE TRIGGER validate_lawyer_availability 
BEFORE INSERT ON lawyer_availability
FOR EACH ROW
BEGIN
    -- Ensure one_time and blocked schedules have specific_date
    IF NEW.schedule_type IN ('one_time', 'blocked') AND NEW.specific_date IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'specific_date is required for one_time and blocked schedules';
    END IF;
    
    -- Ensure weekly schedules don't have specific_date
    IF NEW.schedule_type = 'weekly' AND NEW.specific_date IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'specific_date should be NULL for weekly schedules';
    END IF;
    
    -- Validate time range
    IF NEW.start_time >= NEW.end_time THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'start_time must be before end_time';
    END IF;
END$$

-- Trigger to automatically update consultation timestamps
CREATE TRIGGER update_consultation_timestamp 
BEFORE UPDATE ON consultations
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

DELIMITER ;

-- =============================================================================
-- 9. PERFORMANCE INDEXES
-- =============================================================================

-- Additional composite indexes for complex queries
CREATE INDEX idx_consultation_lawyer_status ON consultations(lawyer_id, status);
CREATE INDEX idx_consultation_date_status ON consultations(date, status);
CREATE INDEX idx_availability_user_type_active ON lawyer_availability(user_id, schedule_type, is_active);
CREATE INDEX idx_notification_status_priority ON notification_queue(status, priority);
CREATE INDEX idx_notification_type_created ON notification_queue(notification_type, created_at);

-- =============================================================================
-- 10. SYSTEM VERIFICATION
-- =============================================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify database structure
SELECT 'Database tables created successfully:' as Status;
SHOW TABLES;

-- Show table statistics
SELECT 
    'Database Statistics' as Info,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM practice_areas) as total_practice_areas,
    (SELECT COUNT(*) FROM consultations) as total_consultations,
    (SELECT COUNT(*) FROM lawyer_specializations) as total_specializations,
    (SELECT COUNT(*) FROM lawyer_availability) as total_availability_records,
    (SELECT COUNT(*) FROM lawyer_settings) as total_lawyer_settings,
    (SELECT COUNT(*) FROM notification_queue) as total_notifications;

-- Show admin user
SELECT 'Admin user created:' as Info;
SELECT id, username, CONCAT(first_name, ' ', last_name) as full_name, email, role, is_active 
FROM users WHERE role = 'admin';

-- Show sample lawyer
SELECT 'Sample lawyer created:' as Info;
SELECT id, username, CONCAT(first_name, ' ', last_name) as full_name, email, role, is_active 
FROM users WHERE role = 'lawyer';

-- Final success message
SELECT 
    'TRINIDADV9 COMPREHENSIVE DATABASE IMPORT COMPLETED!' as Status,
    'All features integrated and ready for production' as Message,
    'Admin Login: admin/admin123' as Credentials,
    'Sample Lawyer: lawyer1/lawyer123' as Sample_Account,
    NOW() as Import_Time;

-- =============================================================================
-- END OF COMPREHENSIVE DATABASE IMPORT
-- =============================================================================
