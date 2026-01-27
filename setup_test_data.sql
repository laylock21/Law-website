-- Setup Test Data for Law Website
-- Run this after importing test.sql

USE test;

-- 1. Create Practice Areas
INSERT INTO practice_areas (area_name, pa_description, is_active) VALUES
('Corporate Law', 'Business formation, contracts, and corporate governance', 1),
('Family Law', 'Divorce, custody, adoption, and family matters', 1),
('Criminal Law', 'Defense in criminal cases and legal representation', 1),
('Real Estate Law', 'Property transactions, leases, and real estate disputes', 1),
('Labor Law', 'Employment contracts, workplace disputes, and labor rights', 1);

-- 2. Create Test Lawyer Account
-- Password: admin123 (hashed with bcrypt)
INSERT INTO users (username, password, email, phone, role, is_active) VALUES
('lawyer1', '$2y$10$WJ9anXzaQ25hJaVFjf0BU.IUJH.vn1hooXxYEjH7e15O9rIpHNRpy', 'lawyer1@lawfirm.com', '+63 917 123 4567', 'lawyer', 1);

-- Get the lawyer's user_id
SET @lawyer_id = LAST_INSERT_ID();

-- 3. Create Lawyer Profile
INSERT INTO lawyer_profile (lawyer_id, lawyer_prefix, lp_fullname, lp_description) VALUES
(@lawyer_id, 'Atty.', 'Juan Dela Cruz', 'Experienced attorney with 10+ years in corporate and family law. Committed to providing excellent legal services to clients.');

-- 4. Assign Specializations to Lawyer
INSERT INTO lawyer_specializations (lawyer_id, pa_id) 
SELECT @lawyer_id, pa_id FROM practice_areas WHERE area_name IN ('Corporate Law', 'Family Law');

-- 5. Create Sample Weekly Availability
INSERT INTO lawyer_availability (lawyer_id, schedule_type, weekday, start_time, end_time, max_appointments, time_slot_duration, la_is_active) VALUES
(@lawyer_id, 'weekly', 'Monday', '09:00:00', '17:00:00', 5, 60, 1),
(@lawyer_id, 'weekly', 'Tuesday', '09:00:00', '17:00:00', 5, 60, 1),
(@lawyer_id, 'weekly', 'Wednesday', '09:00:00', '17:00:00', 5, 60, 1),
(@lawyer_id, 'weekly', 'Thursday', '09:00:00', '17:00:00', 5, 60, 1),
(@lawyer_id, 'weekly', 'Friday', '09:00:00', '15:00:00', 3, 60, 1);

-- 6. Create Sample Consultation
INSERT INTO consultations (c_full_name, c_email, c_phone, case_description, lawyer_id, consultation_date, consultation_time, c_status) VALUES
('Maria Santos', 'maria.santos@email.com', '+63 917 987 6543', 'Need legal advice regarding property dispute with neighbor', @lawyer_id, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', 'pending'),
('Pedro Reyes', 'pedro.reyes@email.com', '+63 918 111 2222', 'Consultation about business partnership agreement', @lawyer_id, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', 'confirmed');

-- 7. Display Created Data
SELECT 'Test data created successfully!' as Status;
SELECT CONCAT('Lawyer Username: lawyer1, Password: admin123, User ID: ', @lawyer_id) as LoginInfo;

-- Show created lawyer
SELECT u.user_id, u.username, u.email, u.role, lp.lp_fullname, lp.lawyer_prefix
FROM users u
LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
WHERE u.user_id = @lawyer_id;

-- Show lawyer specializations
SELECT u.username, pa.area_name
FROM users u
JOIN lawyer_specializations ls ON u.user_id = ls.lawyer_id
JOIN practice_areas pa ON ls.pa_id = pa.pa_id
WHERE u.user_id = @lawyer_id;

-- Show lawyer availability
SELECT weekday, start_time, end_time, max_appointments, schedule_type
FROM lawyer_availability
WHERE lawyer_id = @lawyer_id
ORDER BY FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

-- Show consultations
SELECT c_id, c_full_name, c_email, consultation_date, consultation_time, c_status
FROM consultations
WHERE lawyer_id = @lawyer_id;
