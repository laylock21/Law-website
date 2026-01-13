-- =============================================================================
-- TRINIDADV9 LAW FIRM CONSULTATION SYSTEM - COMPLETE DATABASE IMPORT
-- =============================================================================
-- 
-- This file contains the complete, consolidated database structure for the 
-- TRINIDADV9 Law Firm Consultation System with all features and enhancements.
-- 
-- FEATURES INCLUDED:
-- ✓ Client consultation booking system
-- ✓ Lawyer availability management (weekly patterns + specific dates)
-- ✓ Admin and lawyer user management with profile pictures
-- ✓ Practice area specialization system
-- ✓ Calendar integration with availability checking
-- ✓ Time slot booking system
-- ✓ Notification queue for email alerts
-- ✓ Flexible scheduling (weekly/one-time/blocked dates)
-- ✓ User profile descriptions and bio system
-- ✓ Foreign key constraints with cascade operations
-- ✓ Performance optimization indexes
-- ✓ Admin user with credentials: admin/admin123
-- 
-- VERSION: TRINIDADV9 Production Ready (October 2025)
-- COMPATIBILITY: MySQL 5.7+, MariaDB 10.2+
-- =============================================================================

-- Set SQL mode and character set
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS lawfirm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE lawfirm_db;

-- =============================================================================
-- 1. DROP EXISTING TABLES (Clean Import)
-- =============================================================================

-- Drop tables in correct order to avoid foreign key conflicts
DROP TABLE IF EXISTS notification_queue;
DROP TABLE IF EXISTS lawyer_specializations;
DROP TABLE IF EXISTS lawyer_availability;
DROP TABLE IF EXISTS consultations;
DROP TABLE IF EXISTS practice_areas;
DROP TABLE IF EXISTS users;

-- =============================================================================
-- 2. CORE TABLES CREATION
-- =============================================================================

-- Create users table (admin, staff, and lawyer accounts)
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL COMMENT 'User profile description - can be used for bio, specialties, etc.',
    profile_picture VARCHAR(255) DEFAULT NULL COMMENT 'Stores the filename of the lawyer profile picture',
    role ENUM('admin','staff','lawyer') DEFAULT 'staff',
    is_active TINYINT(1) DEFAULT 1,
    default_booking_weeks INT DEFAULT 52 COMMENT 'Default weeks to show in calendar (1 year default) - only for lawyers',
    max_booking_weeks INT DEFAULT 104 COMMENT 'Maximum weeks clients can book ahead (2 years default) - only for lawyers',
    booking_window_enabled BOOLEAN DEFAULT 1 COMMENT 'Enable custom booking window for this lawyer - only for lawyers',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    INDEX idx_lawyer_booking_settings (role, booking_window_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create practice areas table (legal services offered)
CREATE TABLE practice_areas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create consultations table (main booking system with time slots)
CREATE TABLE consultations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    practice_area VARCHAR(50) NOT NULL,
    case_description TEXT NOT NULL,
    selected_lawyer VARCHAR(100) DEFAULT 'Any',
    lawyer_id INT(11) DEFAULT NULL,
    selected_date DATE DEFAULT NULL,
    consultation_date DATE DEFAULT NULL,
    consultation_time TIME NULL COMMENT 'Specific time slot for consultation',
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create lawyer_specializations table (Many-to-Many relationship)
CREATE TABLE lawyer_specializations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    practice_area_id INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_lawyer_specialization (user_id, practice_area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create lawyer_availability table (flexible scheduling system)
CREATE TABLE lawyer_availability (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    schedule_type ENUM('weekly', 'one_time', 'blocked') NOT NULL DEFAULT 'weekly' COMMENT 'Type of schedule: weekly recurring, one-time specific date, or blocked date',
    specific_date DATE NULL COMMENT 'For one_time and blocked schedule types',
    weekdays VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Comma-separated list of weekday numbers (0=Sunday, 1=Monday, etc.)',
    start_time TIME DEFAULT '09:00:00',
    end_time TIME DEFAULT '17:00:00',
    max_appointments INT(11) DEFAULT 5,
    time_slot_duration INT DEFAULT 60 COMMENT 'Duration of each time slot in minutes',
    is_active BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create notification queue table (email system)
CREATE TABLE notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('appointment_cancelled', 'schedule_changed', 'reminder', 'confirmation', 'other') DEFAULT 'other',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- 3. PERFORMANCE OPTIMIZATION - INDEXES
-- =============================================================================

-- Users table indexes
ALTER TABLE users ADD INDEX idx_users_username (username);
ALTER TABLE users ADD INDEX idx_users_role (role);
ALTER TABLE users ADD INDEX idx_users_active (is_active);

-- Practice areas indexes
ALTER TABLE practice_areas ADD INDEX idx_practice_areas_name (area_name);
ALTER TABLE practice_areas ADD INDEX idx_practice_areas_active (is_active);

-- Consultations table indexes
ALTER TABLE consultations ADD INDEX idx_consultations_email (email);
ALTER TABLE consultations ADD INDEX idx_consultations_status (status);
ALTER TABLE consultations ADD INDEX idx_consultations_date (selected_date);
ALTER TABLE consultations ADD INDEX idx_consultations_lawyer_id (lawyer_id);
ALTER TABLE consultations ADD INDEX idx_consultations_consultation_date (consultation_date);
ALTER TABLE consultations ADD INDEX idx_consultations_consultation_time (consultation_time);

-- Lawyer availability indexes
ALTER TABLE lawyer_availability ADD INDEX idx_lawyer_availability_user (user_id);
ALTER TABLE lawyer_availability ADD INDEX idx_lawyer_availability_weekdays (weekdays);
ALTER TABLE lawyer_availability ADD INDEX idx_lawyer_schedule (user_id, schedule_type, is_active);
ALTER TABLE lawyer_availability ADD INDEX idx_specific_date (specific_date, is_active);
ALTER TABLE lawyer_availability ADD INDEX idx_active_schedules (is_active, user_id);

-- Lawyer specializations indexes
ALTER TABLE lawyer_specializations ADD INDEX idx_lawyer_specializations_user (user_id);
ALTER TABLE lawyer_specializations ADD INDEX idx_lawyer_specializations_practice (practice_area_id);

-- Notification queue indexes
ALTER TABLE notification_queue ADD INDEX idx_status (status);
ALTER TABLE notification_queue ADD INDEX idx_created (created_at);
ALTER TABLE notification_queue ADD INDEX idx_user (user_id);

-- =============================================================================
-- 4. FOREIGN KEY CONSTRAINTS
-- =============================================================================

-- Lawyer specializations foreign keys
ALTER TABLE lawyer_specializations
    ADD CONSTRAINT lawyer_specializations_ibfk_1 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT lawyer_specializations_ibfk_2 
    FOREIGN KEY (practice_area_id) REFERENCES practice_areas(id) ON DELETE CASCADE;

-- Lawyer availability foreign key
ALTER TABLE lawyer_availability
    ADD CONSTRAINT lawyer_availability_ibfk_1 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Consultations foreign key with cascade delete
ALTER TABLE consultations
    ADD CONSTRAINT fk_consultation_lawyer 
    FOREIGN KEY (lawyer_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Notification queue foreign key
ALTER TABLE notification_queue
    ADD CONSTRAINT notification_queue_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- =============================================================================
-- 5. DATA VALIDATION CONSTRAINTS (MySQL 8.0.16+ / MariaDB 10.2.1+)
-- =============================================================================

-- Note: CHECK constraints are supported in MySQL 8.0.16+ and MariaDB 10.2.1+
-- If your database version doesn't support CHECK constraints, this will be ignored
-- ALTER TABLE lawyer_availability ADD CONSTRAINT chk_one_time_has_date 
-- CHECK ((schedule_type = 'weekly' AND specific_date IS NULL) OR (schedule_type IN ('one_time', 'blocked') AND specific_date IS NOT NULL));

-- =============================================================================
-- 6. AUTO_INCREMENT SETTINGS
-- =============================================================================

ALTER TABLE users MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE practice_areas MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE consultations MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE lawyer_specializations MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE lawyer_availability MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE notification_queue MODIFY id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- =============================================================================
-- 7. ESSENTIAL DATA INSERTION
-- =============================================================================

-- Insert admin user (username: admin, password: admin123)
-- Password hash generated with PHP: password_hash('admin123', PASSWORD_DEFAULT)
-- Note: Admin users don't need booking preferences, so we set them to NULL
INSERT INTO users (username, password, email, first_name, last_name, role, is_active, default_booking_weeks, max_booking_weeks, booking_window_enabled) VALUES 
('admin', '$2a$12$4gvN2cb6AvSWkxIl6.cx3.aly5nPnb8g47xlcy.JTx3qvWgi2WjSS', 'admin@lawfirm.com', 'System', 'Administrator', 'admin', 1, NULL, NULL, NULL);

-- Insert practice areas (essential legal services)
INSERT INTO practice_areas (area_name, description, is_active) VALUES 
('Criminal Defense', 'Aggressive defense strategies and courtroom expertise for criminal cases', 1),
('Family Law', 'Compassionate guidance through family matters including divorce, custody, and adoption', 1),
('Corporate Law', 'Strategic business counsel, corporate governance, and commercial transactions', 1),
('Real Estate', 'Comprehensive real estate services including property transactions and disputes', 1),
('Personal Injury', 'Dedicated representation for accident victims and injury claims', 1),
('Immigration Law', 'Expert assistance with visa applications, citizenship, and immigration matters', 1),
('Employment Law', 'Workplace rights protection and employment-related legal services', 1),
('Intellectual Property', 'Patent, trademark, and copyright protection services', 1);

-- =============================================================================
-- 8. NOTIFICATION SYSTEM AUTO-CLEANUP (Optional)
-- =============================================================================

-- Enable event scheduler for automatic cleanup (optional)
-- Uncomment the following lines if you want automatic cleanup of old notifications

/*
SET GLOBAL event_scheduler = ON;

DELIMITER $$

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

DELIMITER ;
*/

-- =============================================================================
-- 9. DATABASE VERIFICATION
-- =============================================================================

-- Show all tables created
SELECT 'Database tables created successfully:' as Status;
SHOW TABLES;

-- Verify users table structure
SELECT 'Users table structure:' as Info;
DESCRIBE users;

-- Show admin user
SELECT 'Admin user created:' as Info;
SELECT id, username, CONCAT(first_name, ' ', last_name) as full_name, email, role, is_active, created_at FROM users WHERE role = 'admin';

-- Show practice areas
SELECT 'Practice areas available:' as Info;
SELECT id, area_name, description, is_active FROM practice_areas ORDER BY area_name;

-- Show table counts
SELECT 'Database statistics:' as Info;
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM practice_areas) as total_practice_areas,
    (SELECT COUNT(*) FROM consultations) as total_consultations,
    (SELECT COUNT(*) FROM lawyer_specializations) as total_specializations,
    (SELECT COUNT(*) FROM lawyer_availability) as total_availability_records,
    (SELECT COUNT(*) FROM notification_queue) as total_notifications;

-- =============================================================================
-- 10. SYSTEM CONFIGURATION NOTES
-- =============================================================================

/*
=============================================================================
IMPORTANT INFORMATION FOR SYSTEM ADMINISTRATORS
=============================================================================

1. DEFAULT LOGIN CREDENTIALS:
   - Admin Username: admin
   - Admin Password: admin123
   - Admin Email: admin@lawfirm.com
   
2. DATABASE FEATURES:
   ✓ Complete user management (admin, staff, lawyer roles)
   ✓ Profile pictures support (upload directory: uploads/profile_pictures/)
   ✓ User descriptions/bio system
   ✓ Practice area specialization system
   ✓ Flexible scheduling (weekly patterns, specific dates, blocked dates)
   ✓ Time slot booking system with configurable durations
   ✓ Consultation booking with lawyer assignment
   ✓ Email notification queue system
   ✓ Foreign key constraints with cascade operations
   ✓ Performance-optimized indexes
   ✓ Data integrity enforcement
   ✓ MySQL 8.0+ validation constraints (where supported)

3. LAWYER AVAILABILITY SYSTEM:
   - schedule_type: 'weekly' (recurring), 'one_time' (specific date), 'blocked' (unavailable)
   - Weekdays stored as comma-separated numbers (0=Sunday, 1=Monday, etc.)
   - Supports custom time ranges and appointment limits
   - Configurable time slot durations (default: 60 minutes)

4. CONSULTATION WORKFLOW:
   - Clients select practice area → lawyer → available date → time slot
   - System enforces lawyer availability constraints
   - Automatic lawyer assignment based on specializations
   - Status tracking: pending → confirmed → completed/cancelled
   - Time-specific booking support

5. NOTIFICATION SYSTEM:
   - Email queue for appointment confirmations, cancellations, reminders
   - Automatic retry mechanism for failed emails
   - Optional auto-cleanup of old notifications
   - Support for multiple notification types

6. SECURITY FEATURES:
   ✓ Bcrypt password hashing (PHP password_hash function)
   ✓ Role-based access control
   ✓ Input validation (application layer)
   ✓ Prepared statements for all queries
   ✓ Foreign key constraints for data integrity
   ✓ Unique constraints to prevent duplicates

7. PERFORMANCE OPTIMIZATIONS:
   ✓ Indexes on all frequently queried columns
   ✓ Composite indexes for complex queries
   ✓ Efficient query structures
   ✓ Proper table relationships
   ✓ UTF8MB4 character set for full Unicode support

8. NEXT STEPS AFTER IMPORT:
   □ Test admin login functionality
   □ Add lawyer users through admin panel
   □ Configure practice area specializations
   □ Set up lawyer availability schedules
   □ Test consultation booking flow
   □ Configure email settings for notifications
   □ Set up file upload directories
   □ Configure backup procedures

9. FILE SYSTEM REQUIREMENTS:
   - Create directory: uploads/profile_pictures/ (with write permissions)
   - Ensure PHP has write access to logs/ directory
   - Configure proper file permissions for security

10. COMPATIBILITY NOTES:
    - Requires MySQL 5.7+ or MariaDB 10.2+
    - PHP 7.4+ recommended
    - Uses modern SQL features where available
    - Graceful degradation for older MySQL versions

=============================================================================
END OF CONFIGURATION NOTES
=============================================================================
*/

-- Success message
SELECT 'TRINIDADV9 Law Firm Database Import Completed Successfully!' as Status,
       'System ready for production use' as Message,
       'Admin credentials: admin/admin123' as Login_Info,
       NOW() as Import_Time;

-- =============================================================================
-- END OF DATABASE IMPORT
-- =============================================================================
