-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 09:42 AM
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
-- Database: `test`
--

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `c_id` int(11) NOT NULL,
  `client_user_id` int(11) DEFAULT NULL,
  `c_full_name` varchar(100) NOT NULL,
  `c_email` varchar(100) NOT NULL,
  `c_phone` varchar(20) NOT NULL,
  `case_description` text NOT NULL,
  `lawyer_id` int(11) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL,
  `c_status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_availability`
--

CREATE TABLE `lawyer_availability` (
  `la_id` int(11) NOT NULL,
  `lawyer_id` int(11) NOT NULL,
  `schedule_type` enum('weekly','one_time','blocked') NOT NULL,
  `blocked_reason` varchar(20) DEFAULT NULL,
  `weekday` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `specific_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_appointments` int(11) DEFAULT 1,
  `time_slot_duration` int(11) NOT NULL COMMENT 'minutes',
  `la_is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_profile`
--

CREATE TABLE `lawyer_profile` (
  `lawyer_id` int(11) NOT NULL,
  `lawyer_prefix` enum('Atty.','Atty. Jr.','Esq.') DEFAULT 'Atty.',
  `lp_fullname` varchar(150) NOT NULL,
  `lp_description` text DEFAULT NULL,
  `profile` longblob DEFAULT NULL,
  `booking_hours_per_week` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_specializations`
--

CREATE TABLE `lawyer_specializations` (
  `lawyer_id` int(11) NOT NULL,
  `pa_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `nq_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `notification_type` enum('appointment_cancelled','schedule_changed','other') DEFAULT 'other',
  `nq_status` enum('pending','sent','failed') DEFAULT 'pending',
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
  `pa_id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `pa_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `temporary_password` enum('temporary') DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','lawyer','client') NOT NULL DEFAULT 'lawyer',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`c_id`),
  ADD KEY `idx_consult_lawyer` (`lawyer_id`),
  ADD KEY `idx_consult_status` (`c_status`),
  ADD KEY `idx_consult_date` (`consultation_date`),
  ADD KEY `client_user_id` (`client_user_id`);

--
-- Indexes for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  ADD PRIMARY KEY (`la_id`),
  ADD KEY `idx_la_lawyer` (`lawyer_id`),
  ADD KEY `idx_la_type` (`schedule_type`),
  ADD KEY `idx_la_weekday` (`weekday`),
  ADD KEY `idx_la_date` (`specific_date`);

--
-- Indexes for table `lawyer_profile`
--
ALTER TABLE `lawyer_profile`
  ADD PRIMARY KEY (`lawyer_id`);

--
-- Indexes for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  ADD PRIMARY KEY (`lawyer_id`,`pa_id`),
  ADD KEY `pa_id` (`pa_id`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`nq_id`),
  ADD KEY `idx_notification_status` (`nq_status`,`created_at`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `consultation_id` (`consultation_id`);

--
-- Indexes for table `practice_areas`
--
ALTER TABLE `practice_areas`
  ADD PRIMARY KEY (`pa_id`),
  ADD UNIQUE KEY `uk_practice_area_name` (`area_name`),
  ADD KEY `idx_practice_area_active` (`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_username` (`username`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `c_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  MODIFY `la_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `nq_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `practice_areas`
--
ALTER TABLE `practice_areas`
  MODIFY `pa_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  ADD CONSTRAINT `lawyer_availability_ibfk_1` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `lawyer_profile`
--
ALTER TABLE `lawyer_profile`
  ADD CONSTRAINT `fk_lawyer_profile_user` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  ADD CONSTRAINT `lawyer_specializations_ibfk_1` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lawyer_specializations_ibfk_2` FOREIGN KEY (`pa_id`) REFERENCES `practice_areas` (`pa_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_queue_ibfk_2` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`c_id`) ON DELETE SET NULL;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `delete_expired_schedules` ON SCHEDULE EVERY 1 DAY STARTS '2026-01-19 11:41:23' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM lawyer_availability
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
