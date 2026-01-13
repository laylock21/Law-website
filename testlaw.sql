-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 06, 2026 at 01:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `full_name`, `email`, `phone`, `practice_area`, `case_description`, `selected_lawyer`, `lawyer_id`, `selected_date`, `consultation_date`, `consultation_time`, `status`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(3, 'fghfgh fghfgh ghfghfg', 'cvtaghap1019@gmail.com', '09306603160', 'Corporate Law', 'sfsdfsdfsdfdsfsr325wsv  dfgsdrgd\nsrdtgdhdf\nfgh\nhfgh\nfgh', 'Atty. Cris Fe De la Cruz', 6, '2025-12-04', '2025-12-04', '09:00:00', 'confirmed', NULL, '2025-12-04 06:38:39', '2025-12-04 06:39:09'),
(4, 'asd dss asd', 'cvtaghap1019@gmail.com', '09306603160', 'General Practice', 'dasdasd afdsfsdf', 'Atty. Cris Fe De la Cruz', 6, '2025-12-04', '2025-12-04', '10:00:00', 'confirmed', NULL, '2025-12-04 06:53:26', '2025-12-04 06:53:49'),
(5, 'xzczx zxcxz zxcxzc', 'cvtaghap1019@gmail.com', '09306603160', 'General Practice', 'sfsdfsdf sdfsdfsdfsd', 'Atty. Cris Fe De la Cruz', 6, '2025-12-04', '2025-12-04', '11:00:00', 'confirmed', NULL, '2025-12-04 07:10:50', '2025-12-04 07:11:00');

-- --------------------------------------------------------

--
-- Table structure for table `lawyer_availability`
--

CREATE TABLE `lawyer_availability` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_type` enum('weekly','one_time','blocked') NOT NULL DEFAULT 'weekly' COMMENT 'Schedule type',
  `specific_date` date DEFAULT NULL COMMENT 'For one_time and blocked types',
  `weekdays` varchar(20) NOT NULL DEFAULT '' COMMENT 'Comma-separated weekday numbers',
  `start_time` time DEFAULT '09:00:00',
  `end_time` time DEFAULT '17:00:00',
  `max_appointments` int(11) DEFAULT 5,
  `time_slot_duration` int(11) DEFAULT 60 COMMENT 'Duration in minutes',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lawyer_availability`
--

INSERT INTO `lawyer_availability` (`id`, `user_id`, `schedule_type`, `specific_date`, `weekdays`, `start_time`, `end_time`, `max_appointments`, `time_slot_duration`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 6, 'weekly', NULL, '1,2,3,4,5', '09:00:00', '17:00:00', 3, 60, 1, '2025-12-04 06:37:39', '2025-12-04 06:37:39');

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
  `notification_type` enum('appointment_cancelled','schedule_changed','reminder','confirmation','appointment_completed','new_consultation','other') DEFAULT 'other',
  `consultation_id` int(11) DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_queue`
--

INSERT INTO `notification_queue` (`id`, `user_id`, `email`, `subject`, `message`, `notification_type`, `consultation_id`, `status`, `attempts`, `created_at`, `sent_at`, `error_message`) VALUES
(8, 6, 'cvtaghap1019@gmail.com', 'New Consultation Request - Corporate Law', '\n<!DOCTYPE html>\n<html>\n<head>\n<style>\n    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n    .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n    .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n    .content { padding: 30px; background: #f9f9f9; }\n    .details-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid #c5a253; }\n    .btn { display: inline-block; padding: 12px 25px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n    .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }\n    .highlight { color: #c5a253; font-weight: bold; }\n    .section-title { color: #1a2332; font-weight: bold; margin-top: 20px; margin-bottom: 10px; }\n</style>\n</head>\n<body>\n<div class=\'container\'>\n    <div class=\'header\'>\n        <h2>🔔 New Consultation Request</h2>\n        <p>MD Law Firm - Lawyer Portal</p>\n    </div>\n    \n    <div class=\'content\'>\n        <p>Dear <strong>Atty. Cris Fe De la Cruz</strong>,</p>\n        \n        <p>You have received a new consultation request through the MD Law Firm website. Please review the details below and take appropriate action.</p>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>👤 CLIENT INFORMATION</div>\n            <p><strong>Name:</strong> fghfgh fghfgh ghfghfg</p>\n            <p><strong>Email:</strong> cvtaghap1019@gmail.com</p>\n            <p><strong>Phone:</strong> 09306603160</p>\n            <p><strong>Practice Area:</strong> <span class=\'highlight\'>Corporate Law</span></p>\n            <p><strong>Preferred Date:</strong> December 4, 2025</p>\n            <p><strong>Preferred Time:</strong> 9:00 AM</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📝 CASE DESCRIPTION</div>\n            <p>sfsdfsdfsdfdsfsr325wsv  dfgsdrgd\nsrdtgdhdf\nfgh\nhfgh\nfgh</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📋 NEXT STEPS</div>\n            <ol>\n                <li>Log in to your lawyer dashboard to review the full consultation details</li>\n                <li>Contact the client to confirm the appointment</li>\n                <li>Update the consultation status once confirmed</li>\n            </ol>\n            \n            <div style=\'text-align: center; margin-top: 20px;\'>\n                <a href=\'http://localhost/Law/lawyer/dashboard.php\' class=\'btn\'>Access Your Dashboard</a>\n            </div>\n        </div>\n        \n        <p>If you have any questions or need assistance, please contact the admin team.</p>\n        \n        <p>Best regards,<br>\n        <strong>MD Law Firm</strong><br>\n        Administrative Team</p>\n    </div>\n    \n    <div class=\'footer\'>\n        <p>This is an automated notification. Please do not reply to this email.</p>\n        <p>For support, contact: admin@mdlawfirm.com</p>\n        <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n    </div>\n</div>\n</body>\n</html>\n        ', 'new_consultation', 3, 'sent', 0, '2025-12-04 06:38:39', '2025-12-04 06:38:44', NULL),
(9, 6, 'cvtaghap1019@gmail.com', 'Appointment Confirmed - Corporate Law', '\n<!DOCTYPE html>\n<html>\n<head>\n    <style>\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }\n        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }\n        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }\n        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }\n        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n        .highlight { color: #c5a253; font-weight: bold; }\n    </style>\n</head>\n<body>\n    <div class=\'container\'>\n        <div class=\'header\'>\n            <h2>✅ Appointment Confirmed</h2>\n        </div>\n        \n        <div class=\'content\'>\n            <p>Dear fghfgh fghfgh ghfghfg,</p>\n            \n            <div class=\'success\'>\n                <strong>✅ Great News!</strong> Your consultation appointment has been confirmed.\n            </div>\n            \n            <div class=\'details\'>\n                <h3>Appointment Details:</h3>\n                <p><strong>Lawyer:</strong> Atty. Cris Fe De la Cruz</p>\n                <p><strong>Practice Area:</strong> Corporate Law</p>\n                <p><strong>Date:</strong> <span class=\'highlight\'>Thursday, December 4, 2025</span></p>\n                <p><strong>Time:</strong> <span class=\'highlight\'>9:00 AM</span></p>\n            </div>\n            \n            <p>We\'re looking forward to meeting with you. Please arrive 10 minutes early to complete any necessary paperwork.</p>\n            \n            <p><strong>What to Bring:</strong></p>\n            <ul>\n                <li>Valid government-issued ID</li>\n                <li>Any relevant documents related to your case</li>\n                <li>List of questions or concerns you\'d like to discuss</li>\n            </ul>\n            \n            <p><strong>Important Reminders:</strong></p>\n            <ul>\n                <li>If you need to reschedule, please contact us at least 24 hours in advance</li>\n                <li>Our office is located at [Office Law Firm]</li>\n                <li>Free parking is available for clients</li>\n            </ul>\n            \n            <p>If you have any questions before your appointment, please don\'t hesitate to contact us.</p>\n            \n            <p>Best regards,<br>\n            <strong>MD Law Firm</strong><br>\n            Your Trusted Legal Partner</p>\n        </div>\n        \n        <div class=\'footer\'>\n            <p>This is an automated confirmation email. Please do not reply to this message.</p>\n            <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>\n        ', 'confirmation', 3, 'sent', 0, '2025-12-04 06:39:09', '2025-12-04 06:39:13', NULL),
(10, 6, 'cvtaghap1019@gmail.com', 'New Consultation Request - General Practice', '\n<!DOCTYPE html>\n<html>\n<head>\n<style>\n    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n    .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n    .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n    .content { padding: 30px; background: #f9f9f9; }\n    .details-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid #c5a253; }\n    .btn { display: inline-block; padding: 12px 25px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n    .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }\n    .highlight { color: #c5a253; font-weight: bold; }\n    .section-title { color: #1a2332; font-weight: bold; margin-top: 20px; margin-bottom: 10px; }\n</style>\n</head>\n<body>\n<div class=\'container\'>\n    <div class=\'header\'>\n        <h2>🔔 New Consultation Request</h2>\n        <p>MD Law Firm - Lawyer Portal</p>\n    </div>\n    \n    <div class=\'content\'>\n        <p>Dear <strong>Atty. Cris Fe De la Cruz</strong>,</p>\n        \n        <p>You have received a new consultation request through the MD Law Firm website. Please review the details below and take appropriate action.</p>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>👤 CLIENT INFORMATION</div>\n            <p><strong>Name:</strong> asd dss asd</p>\n            <p><strong>Email:</strong> cvtaghap1019@gmail.com</p>\n            <p><strong>Phone:</strong> 09306603160</p>\n            <p><strong>Practice Area:</strong> <span class=\'highlight\'>General Practice</span></p>\n            <p><strong>Preferred Date:</strong> December 4, 2025</p>\n            <p><strong>Preferred Time:</strong> 10:00 AM</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📝 CASE DESCRIPTION</div>\n            <p>dasdasd afdsfsdf</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📋 NEXT STEPS</div>\n            <ol>\n                <li>Log in to your lawyer dashboard to review the full consultation details</li>\n                <li>Contact the client to confirm the appointment</li>\n                <li>Update the consultation status once confirmed</li>\n            </ol>\n            \n            <div style=\'text-align: center; margin-top: 20px;\'>\n                <a href=\'http://localhost/Law/lawyer/dashboard.php\' class=\'btn\'>Access Your Dashboard</a>\n            </div>\n        </div>\n        \n        <p>If you have any questions or need assistance, please contact the admin team.</p>\n        \n        <p>Best regards,<br>\n        <strong>MD Law Firm</strong><br>\n        Administrative Team</p>\n    </div>\n    \n    <div class=\'footer\'>\n        <p>This is an automated notification. Please do not reply to this email.</p>\n        <p>For support, contact: admin@mdlawfirm.com</p>\n        <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n    </div>\n</div>\n</body>\n</html>\n        ', 'new_consultation', 4, 'sent', 0, '2025-12-04 06:53:27', '2025-12-04 06:53:32', NULL),
(11, 6, 'cvtaghap1019@gmail.com', 'Appointment Confirmed - General Practice', '\n<!DOCTYPE html>\n<html>\n<head>\n    <style>\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }\n        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }\n        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }\n        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }\n        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n        .highlight { color: #c5a253; font-weight: bold; }\n    </style>\n</head>\n<body>\n    <div class=\'container\'>\n        <div class=\'header\'>\n            <h2>✅ Appointment Confirmed</h2>\n        </div>\n        \n        <div class=\'content\'>\n            <p>Dear asd dss asd,</p>\n            \n            <div class=\'success\'>\n                <strong>✅ Great News!</strong> Your consultation appointment has been confirmed.\n            </div>\n            \n            <div class=\'details\'>\n                <h3>Appointment Details:</h3>\n                <p><strong>Lawyer:</strong> Atty. Cris Fe De la Cruz</p>\n                <p><strong>Practice Area:</strong> General Practice</p>\n                <p><strong>Date:</strong> <span class=\'highlight\'>Thursday, December 4, 2025</span></p>\n                <p><strong>Time:</strong> <span class=\'highlight\'>10:00 AM</span></p>\n            </div>\n            \n            <p>We\'re looking forward to meeting with you. Please arrive 10 minutes early to complete any necessary paperwork.</p>\n            \n            <p><strong>What to Bring:</strong></p>\n            <ul>\n                <li>Valid government-issued ID</li>\n                <li>Any relevant documents related to your case</li>\n                <li>List of questions or concerns you\'d like to discuss</li>\n            </ul>\n            \n            <p><strong>Important Reminders:</strong></p>\n            <ul>\n                <li>If you need to reschedule, please contact us at least 24 hours in advance</li>\n                <li>Our office is located at [Office Law Firm]</li>\n                <li>Free parking is available for clients</li>\n            </ul>\n            \n            <p>If you have any questions before your appointment, please don\'t hesitate to contact us.</p>\n            \n            <p>Best regards,<br>\n            <strong>MD Law Firm</strong><br>\n            Your Trusted Legal Partner</p>\n        </div>\n        \n        <div class=\'footer\'>\n            <p>This is an automated confirmation email. Please do not reply to this message.</p>\n            <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>\n        ', 'confirmation', 4, 'sent', 0, '2025-12-04 06:53:49', '2025-12-04 06:53:53', NULL),
(12, 6, 'cvtaghap1019@gmail.com', 'New Consultation Request - General Practice', '\n<!DOCTYPE html>\n<html>\n<head>\n<style>\n    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n    .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n    .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n    .content { padding: 30px; background: #f9f9f9; }\n    .details-box { background: white; padding: 20px; margin: 15px 0; border-left: 4px solid #c5a253; }\n    .btn { display: inline-block; padding: 12px 25px; background: #c5a253; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n    .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }\n    .highlight { color: #c5a253; font-weight: bold; }\n    .section-title { color: #1a2332; font-weight: bold; margin-top: 20px; margin-bottom: 10px; }\n</style>\n</head>\n<body>\n<div class=\'container\'>\n    <div class=\'header\'>\n        <h2>🔔 New Consultation Request</h2>\n        <p>MD Law Firm - Lawyer Portal</p>\n    </div>\n    \n    <div class=\'content\'>\n        <p>Dear <strong>Atty. Cris Fe De la Cruz</strong>,</p>\n        \n        <p>You have received a new consultation request through the MD Law Firm website. Please review the details below and take appropriate action.</p>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>👤 CLIENT INFORMATION</div>\n            <p><strong>Name:</strong> xzczx zxcxz zxcxzc</p>\n            <p><strong>Email:</strong> cvtaghap1019@gmail.com</p>\n            <p><strong>Phone:</strong> 09306603160</p>\n            <p><strong>Practice Area:</strong> <span class=\'highlight\'>General Practice</span></p>\n            <p><strong>Preferred Date:</strong> December 4, 2025</p>\n            <p><strong>Preferred Time:</strong> 11:00 AM</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📝 CASE DESCRIPTION</div>\n            <p>sfsdfsdf sdfsdfsdfsd</p>\n        </div>\n        \n        <div class=\'details-box\'>\n            <div class=\'section-title\'>📋 NEXT STEPS</div>\n            <ol>\n                <li>Log in to your lawyer dashboard to review the full consultation details</li>\n                <li>Contact the client to confirm the appointment</li>\n                <li>Update the consultation status once confirmed</li>\n            </ol>\n            \n            <div style=\'text-align: center; margin-top: 20px;\'>\n                <a href=\'http://localhost/Law/lawyer/dashboard.php\' class=\'btn\'>Access Your Dashboard</a>\n            </div>\n        </div>\n        \n        <p>If you have any questions or need assistance, please contact the admin team.</p>\n        \n        <p>Best regards,<br>\n        <strong>MD Law Firm</strong><br>\n        Administrative Team</p>\n    </div>\n    \n    <div class=\'footer\'>\n        <p>This is an automated notification. Please do not reply to this email.</p>\n        <p>For support, contact: admin@mdlawfirm.com</p>\n        <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n    </div>\n</div>\n</body>\n</html>\n        ', 'new_consultation', 5, 'sent', 0, '2025-12-04 07:10:50', '2025-12-04 07:10:55', NULL),
(13, 6, 'cvtaghap1019@gmail.com', 'Appointment Confirmed - General Practice', '\n<!DOCTYPE html>\n<html>\n<head>\n    <style>\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .header { background: #1a2332; color: white; padding: 20px; text-align: center; }\n        .content { background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 20px 0; }\n        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }\n        .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }\n        .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px; }\n        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }\n        .highlight { color: #c5a253; font-weight: bold; }\n    </style>\n</head>\n<body>\n    <div class=\'container\'>\n        <div class=\'header\'>\n            <h2>✅ Appointment Confirmed</h2>\n        </div>\n        \n        <div class=\'content\'>\n            <p>Dear xzczx zxcxz zxcxzc,</p>\n            \n            <div class=\'success\'>\n                <strong>✅ Great News!</strong> Your consultation appointment has been confirmed.\n            </div>\n            \n            <div class=\'details\'>\n                <h3>Appointment Details:</h3>\n                <p><strong>Lawyer:</strong> Atty. Cris Fe De la Cruz</p>\n                <p><strong>Practice Area:</strong> General Practice</p>\n                <p><strong>Date:</strong> <span class=\'highlight\'>Thursday, December 4, 2025</span></p>\n                <p><strong>Time:</strong> <span class=\'highlight\'>11:00 AM</span></p>\n            </div>\n            \n            <p>We\'re looking forward to meeting with you. Please arrive 10 minutes early to complete any necessary paperwork.</p>\n            \n            <p><strong>What to Bring:</strong></p>\n            <ul>\n                <li>Valid government-issued ID</li>\n                <li>Any relevant documents related to your case</li>\n                <li>List of questions or concerns you\'d like to discuss</li>\n            </ul>\n            \n            <p><strong>Important Reminders:</strong></p>\n            <ul>\n                <li>If you need to reschedule, please contact us at least 24 hours in advance</li>\n                <li>Our office is located at [Office Law Firm]</li>\n                <li>Free parking is available for clients</li>\n            </ul>\n            \n            <p>If you have any questions before your appointment, please don\'t hesitate to contact us.</p>\n            \n            <p>Best regards,<br>\n            <strong>MD Law Firm</strong><br>\n            Your Trusted Legal Partner</p>\n        </div>\n        \n        <div class=\'footer\'>\n            <p>This is an automated confirmation email. Please do not reply to this message.</p>\n            <p>&copy; 2025 MD Law Firm. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>\n        ', 'confirmation', 5, 'sent', 0, '2025-12-04 07:11:01', '2025-12-04 07:11:06', NULL);

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
(8, 'Intellectual Property', 'Patent, trademark, and copyright protection services', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'User profile description',
  `profile_picture` varchar(255) DEFAULT NULL COMMENT 'Profile picture filename',
  `role` enum('admin','staff','lawyer') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `default_booking_weeks` int(11) DEFAULT 52 COMMENT 'Default weeks to show in calendar (1 year default)',
  `max_booking_weeks` int(11) DEFAULT 104 COMMENT 'Maximum weeks clients can book ahead (2 years default)',
  `booking_window_enabled` tinyint(1) DEFAULT 1 COMMENT 'Enable custom booking window for this lawyer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `phone`, `description`, `profile_picture`, `role`, `is_active`, `created_at`, `default_booking_weeks`, `max_booking_weeks`, `booking_window_enabled`) VALUES
(1, 'admin', '4175', 'admin@lawfirm.com', 'System', 'Administrator', NULL, NULL, NULL, 'admin', 1, '2025-10-13 02:12:04', 52, 104, 1),
(6, 'itdep', '123', 'cvtaghap1019@gmail.com', 'Cris Fe', 'De la Cruz', '+639306603160', '.mgbifu', NULL, 'lawyer', 1, '2025-12-04 05:36:59', 4, 26, 1);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `fk_notification_consultation` (`consultation_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lawyer_availability`
--
ALTER TABLE `lawyer_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lawyer_specializations`
--
ALTER TABLE `lawyer_specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `practice_areas`
--
ALTER TABLE `practice_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `fk_notification_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
