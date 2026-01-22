-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 04, 2025 at 06:43 AM
-- Server version: 8.0.44-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `testLaw`
--

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `practice_area` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `case_description` text COLLATE utf8mb4_general_ci NOT NULL,
  `selected_lawyer` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Any',
  `lawyer_id` int DEFAULT NULL,
  `selected_date` date DEFAULT NULL,
  `consultation_date` date DEFAULT NULL,
  `consultation_time` time DEFAULT NULL COMMENT 'Specific time slot',
  `status` enum('pending','confirmed','cancelled','completed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `cancellation_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `full_name`, `email`, `phone`, `practice_area`, `case_description`, `selected_lawyer`, `lawyer_id`, `selected_date`, `consultation_date`, `consultation_time`, `status`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(3, 'fghfgh fghfgh ghfghfg', 'cvtaghap1019@gmail.com', '09306603160', 'Corporate Law', 'sfsdfsdfsdfdsfsr325wsv  dfgsdrgd\nsrdtgdhdf\nfgh\nhfgh\nfgh', 'Atty. Cris Fe De la Cruz', 6, '2025-12-04', '2025-12-04', '09:00:00', 'confirmed', NULL, '2025-12-04 06:38:39', '2025-12-04 06:39:09');

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consultation_lawyer` FOREIGN KEY (`lawyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
