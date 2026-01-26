-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 23, 2026 at 02:59 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `testlaw`
--

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `practice_area` varchar(50) NOT NULL,
  `case_description` text NOT NULL,
  `selected_lawyer` varchar(100) DEFAULT 'Any',
  `lawyer_id` int(11) DEFAULT NULL,
  `selected_date` date DEFAULT NULL,
  `consultation_date` date DEFAULT NULL,
  `consultation_time` time DEFAULT NULL COMMENT 'Specific time slot',
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_availability`
--

CREATE TABLE `lawyer_availability` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_type` enum('weekly','one_time','blocked') NOT NULL DEFAULT 'weekly' COMMENT 'Schedule type',
  `blocked_reason` enum('Unavailable','Sick Leave','Personal Leave','Holiday','Emergency','Out of Office') DEFAULT NULL,
  `specific_date` date DEFAULT NULL COMMENT 'For one_time and blocked types',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `weekdays` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL COMMENT 'Comma-separated weekday numbers',
  `start_time` time DEFAULT '09:00:00',
  `end_time` time DEFAULT '17:00:00',
  `max_appointments` int(11) DEFAULT 5,
  `time_slot_duration` int(11) DEFAULT 60 COMMENT 'Duration in minutes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_specializations`
--

CREATE TABLE `lawyer_specializations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `practice_area_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('appointment_cancelled','schedule_changed','other') DEFAULT 'other',
  `consultation_id` int(11) DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practice_areas`
--

CREATE TABLE `practice_areas` (
  `id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `practice_areas`
--

INSERT INTO `practice_areas` (`id`, `area_name`, `description`, `is_active`) VALUES
(1, 'Criminal Defense', 'Aggressive defense strategies and courtroom expertise for criminal cases', 1),
(2, 'Family Law', 'Compassionate guidance through family matters including divorce, custody, and adoption', 1),
(3, 'Corporate Law', 'Strategic business counsel, corporate governance, and commercial transactions', 1),
(4, 'Real Estate', 'Comprehensive real estate services including property transactions and disputes', 1),
(5, 'Personal Injury', 'Dedicated representation for accident victims and injury claims', 1),
(6, 'Immigration Law', 'Expert assistance with visa applications, citizenship, and immigration matters', 1),
(7, 'Employment Law', 'Workplace rights protection and employment-related legal services', 1),
(8, 'Intellectual Property', 'Patent, trademark, and copyright protection services', 1),
(9, 'Bankruptcy Law', 'Debt, insolvency, and financial restructuring.', 1),
(10, 'Banking and Finance Law', 'legal practice that oversees “the organization, ownership, and operation of banks and depository institutions', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `temporary_password` enum('temporary') DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `lawyer_prefix` varchar(10) DEFAULT NULL COMMENT 'would be changed to enum with a choice of prefix',
  `phone` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'User profile description',
  `profile_picture` varchar(255) DEFAULT NULL COMMENT 'Profile picture filename',
  `role` enum('admin','staff','lawyer') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `default_booking_weeks` int(11) DEFAULT 52 COMMENT 'Default weeks to show in calendar (1 year default)',
  `max_booking_weeks` int(11) DEFAULT 104 COMMENT 'Maximum weeks clients can book ahead (2 years default)',
  `booking_window_enabled` tinyint(1) DEFAULT 1 COMMENT 'Enable custom booking window for this lawyer',
  `Profile` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `temporary_password`, `email`, `first_name`, `last_name`, `lawyer_prefix`, `phone`, `description`, `profile_picture`, `role`, `is_active`, `created_at`, `default_booking_weeks`, `max_booking_weeks`, `booking_window_enabled`, `Profile`) VALUES
(1, 'admin', '$2y$10$iGNgERhrmU9xU3y73O2ivOeAKvjucQ06df0Agf24BO4Cvrty6ZLqa', NULL, 'admin@lawfirm.com', 'System', 'Administrator', NULL, NULL, NULL, NULL, 'admin', 1, '2025-10-13 02:12:04', 52, 104, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL COMMENT 'SHA-256 hash of session_id()',
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `status` enum('active','expired','logged_out','invalid') DEFAULT 'active',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_consultations_email` (`email`),
  ADD KEY `idx_consultations_status` (`status`),
  ADD KEY `idx_consultations_date` (`selected_date`),
  ADD KEY `idx_consultations_lawyer_id` (`lawyer_id`),
  ADD KEY `idx_consultations_consultation_date` (`consultation_date`),
  ADD KEY `idx_consultations_consultation_time` (`consultation_time`);

--
-- Indexes for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lawyer_availability_user` (`user_id`),
  ADD KEY `idx_lawyer_availability_weekdays` (`weekdays`),
  ADD KEY `idx_lawyer_schedule` (`user_id`,`schedule_type`,`is_active`),
  ADD KEY `idx_specific_date` (`specific_date`,`is_active`),
  ADD KEY `idx_active_schedules` (`is_active`,`user_id`);

--
-- Indexes for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lawyer_specialization` (`user_id`,`practice_area_id`),
  ADD KEY `idx_lawyer_specializations_user` (`user_id`),
  ADD KEY `idx_lawyer_specializations_practice` (`practice_area_id`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `practice_areas`
--
ALTER TABLE `practice_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_practice_areas_name` (`area_name`),
  ADD KEY `idx_practice_areas_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_active` (`is_active`),
  ADD KEY `idx_lawyer_booking_settings` (`role`,`booking_window_enabled`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `practice_areas`
--
ALTER TABLE `practice_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consultation_lawyer` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  ADD CONSTRAINT `lawyer_availability_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  ADD CONSTRAINT `lawyer_specializations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lawyer_specializations_ibfk_2` FOREIGN KEY (`practice_area_id`) REFERENCES `practice_areas` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `delete_expired_schedules` ON SCHEDULE EVERY 1 DAY STARTS '2026-01-19 11:41:23' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM lawyer_schedule
  WHERE
    (
      schedule_type = 'blocked'
      AND (
        (specific_date IS NOT NULL AND specific_date < CURDATE())
        OR
        (end_date IS NOT NULL AND end_date < CURDATE())
      )
    )
    OR
    (
      schedule_type = 'one_time'
      AND specific_date IS NOT NULL
      AND specific_date < CURDATE()
    )$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
