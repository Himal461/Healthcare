-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 04:17 PM
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
-- Database: `healthmanagement`
--

-- --------------------------------------------------------

--
-- Table structure for table `accountants`
--

CREATE TABLE `accountants` (
  `accountantId` int(11) NOT NULL,
  `staffId` int(11) NOT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `certification` varchar(255) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT 'General Accounting',
  `yearsOfExperience` int(11) DEFAULT 0,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accountants`
--

INSERT INTO `accountants` (`accountantId`, `staffId`, `qualification`, `certification`, `specialization`, `yearsOfExperience`, `createdAt`, `updatedAt`) VALUES
(1, 27, '', '', 'General Accounting', 0, '2026-04-11 15:26:58', '2026-04-11 15:26:58');

--
-- Triggers `accountants`
--
DELIMITER $$
CREATE TRIGGER `after_accountant_insert` AFTER INSERT ON `accountants` FOR EACH ROW BEGIN
    UPDATE users u
    JOIN staff s ON u.userId = s.userId
    SET u.role = 'accountant'
    WHERE s.staffId = NEW.staffId;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_appointments`
-- (See below for the actual view)
--
CREATE TABLE `active_appointments` (
`appointmentId` int(11)
,`dateTime` datetime
,`status` enum('scheduled','confirmed','in-progress','completed','cancelled','no-show')
,`reason` text
,`patientName` varchar(101)
,`doctorName` varchar(101)
,`specialization` varchar(100)
,`createdAt` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `adminId` int(11) NOT NULL,
  `staffId` int(11) NOT NULL,
  `adminLevel` enum('super','regular') DEFAULT 'regular',
  `permissions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`adminId`, `staffId`, `adminLevel`, `permissions`) VALUES
(3, 7, 'super', '{\"all\": true, \"users\": true, \"doctors\": true, \"billing\": true, \"settings\": true}');

--
-- Triggers `administrators`
--
DELIMITER $$
CREATE TRIGGER `after_admin_insert` AFTER INSERT ON `administrators` FOR EACH ROW BEGIN
    UPDATE users u
    JOIN staff s ON u.userId = s.userId
    SET u.role = 'admin'
    WHERE s.staffId = NEW.staffId;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointmentId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `doctorId` int(11) NOT NULL,
  `dateTime` datetime NOT NULL,
  `duration` int(11) DEFAULT 30 COMMENT 'Duration in minutes',
  `status` enum('scheduled','confirmed','in-progress','completed','cancelled','no-show') DEFAULT 'scheduled',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancellationReason` text DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `appointmentDate` date GENERATED ALWAYS AS (cast(`dateTime` as date)) STORED,
  `appointmentTime` time GENERATED ALWAYS AS (cast(`dateTime` as time)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointmentId`, `patientId`, `doctorId`, `dateTime`, `duration`, `status`, `reason`, `notes`, `cancellationReason`, `createdAt`, `updatedAt`) VALUES
(1, 1, 2, '2026-03-25 10:30:00', 30, 'completed', '', '\nConsultation completed on 2026-03-24 03:29:38 | Diagnosis: Ok report', NULL, '2026-03-24 02:19:05', '2026-03-24 03:29:38'),
(2, 1, 4, '2026-03-24 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 02:22:57', '2026-03-24 02:25:53'),
(3, 1, 4, '2026-03-24 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 02:26:11', '2026-03-24 02:28:34'),
(4, 1, 4, '2026-03-25 11:00:00', 30, 'completed', '', '', NULL, '2026-03-24 02:28:48', '2026-04-11 22:14:09'),
(5, 1, 5, '2026-03-25 11:00:00', 30, 'completed', '', '\nConsultation completed on 2026-03-24 03:31:40 | Diagnosis: Crystal', NULL, '2026-03-24 03:30:23', '2026-03-24 03:31:40'),
(6, 1, 7, '2026-03-24 16:00:00', 30, 'completed', '', NULL, NULL, '2026-03-24 15:41:48', '2026-03-24 17:09:25'),
(7, 1, 7, '2026-03-25 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 22:38:29', '2026-03-24 22:48:17'),
(9, 1, 4, '2026-04-14 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-04-11 23:52:09', '2026-04-12 00:09:02'),
(10, 1, 11, '2026-04-17 09:30:00', 30, 'cancelled', '\n[RESCHEDULED: 2026-04-12 00:13:37] New: Rescheduled by patient\n[RESCHEDULED by staff: 2026-04-12 00:30:10] Rescheduled by staff\n[RESCHEDULED by admin: 2026-04-12 00:31:53] Rescheduled by admin', NULL, 'Doctor unavailable (Day Off)', '2026-04-11 23:59:39', '2026-04-12 00:44:26'),
(11, 3, 11, '2026-04-14 14:30:00', 30, 'completed', '', NULL, NULL, '2026-04-12 00:27:23', '2026-04-12 00:51:54'),
(12, 13, 6, '2026-04-16 14:00:00', 30, 'completed', '\n[RESCHEDULED by admin: 2026-04-12 23:11:38] Rescheduled by admin\n[RESCHEDULED by staff: 2026-04-12 23:12:10] Rescheduled by staff', NULL, NULL, '2026-04-12 23:10:52', '2026-04-12 23:12:47'),
(13, 13, 6, '2026-04-16 10:00:00', 30, 'scheduled', '', NULL, NULL, '2026-04-12 23:18:48', '2026-04-12 23:18:48'),
(14, 14, 11, '2026-04-13 09:00:00', 30, 'completed', '', NULL, NULL, '2026-04-12 23:27:17', '2026-04-12 23:32:49'),
(15, 14, 11, '2026-04-13 10:00:00', 30, 'completed', '', NULL, NULL, '2026-04-12 23:33:47', '2026-04-12 23:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `logId` int(11) NOT NULL,
  `userId` int(11) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ipAddress` varchar(45) DEFAULT NULL,
  `userAgent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`logId`, `userId`, `timestamp`, `action`, `details`, `ipAddress`, `userAgent`) VALUES
(1, 1, '2026-03-23 19:08:50', 'EMAIL_VERIFY', 'User verified their email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(2, 1, '2026-03-23 19:09:17', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(3, 1, '2026-03-23 19:31:30', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(4, 1, '2026-03-23 19:31:39', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(5, 1, '2026-03-23 19:45:44', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(6, NULL, '2026-03-23 19:51:51', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(7, NULL, '2026-03-23 19:55:50', 'PASSWORD_CHANGE', 'User changed their password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(8, NULL, '2026-03-23 19:55:55', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(9, NULL, '2026-03-23 19:56:06', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(10, NULL, '2026-03-23 20:01:30', 'EXPORT_REPORT', 'Exported appointments report from 2026-02-21 to 2026-03-23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(11, NULL, '2026-03-23 20:01:53', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(12, NULL, '2026-03-23 20:02:11', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(13, NULL, '2026-03-23 20:02:49', 'USER_PROMOTE', 'Changed user 6 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(14, NULL, '2026-03-23 20:02:56', 'USER_PROMOTE', 'Changed user 6 role to nurse', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(15, NULL, '2026-03-23 20:03:02', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(16, NULL, '2026-03-23 20:03:25', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(17, NULL, '2026-03-23 20:04:06', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(18, 1, '2026-03-23 20:04:33', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(19, 1, '2026-03-23 20:31:44', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(20, NULL, '2026-03-23 20:31:53', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(21, NULL, '2026-03-23 20:38:13', 'USER_DELETE', 'Deleted user ID: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(22, NULL, '2026-03-23 20:38:57', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(23, NULL, '2026-03-23 20:39:03', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(24, NULL, '2026-03-23 20:39:22', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(25, NULL, '2026-03-23 20:39:35', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(26, NULL, '2026-03-23 20:40:02', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(27, NULL, '2026-03-23 20:40:09', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(28, NULL, '2026-03-23 21:05:47', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(29, 1, '2026-03-23 21:05:56', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(30, 1, '2026-03-23 21:06:18', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(31, NULL, '2026-03-23 21:06:25', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(32, NULL, '2026-03-23 21:06:31', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(33, NULL, '2026-03-23 21:06:42', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(34, NULL, '2026-03-23 21:17:10', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(35, NULL, '2026-03-23 21:17:45', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(36, NULL, '2026-03-23 21:30:47', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(37, NULL, '2026-03-23 21:30:58', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(38, NULL, '2026-03-23 21:31:20', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(39, NULL, '2026-03-23 21:31:43', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(40, NULL, '2026-03-23 22:25:55', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(41, 1, '2026-03-23 22:26:05', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(42, 1, '2026-03-23 22:26:23', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(43, NULL, '2026-03-23 22:26:32', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(44, NULL, '2026-03-23 22:47:47', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(45, NULL, '2026-03-23 22:48:13', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(46, NULL, '2026-03-23 22:48:29', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(47, NULL, '2026-03-23 22:48:41', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(48, NULL, '2026-03-23 22:48:53', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(49, NULL, '2026-03-23 22:49:00', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(50, NULL, '2026-03-23 22:49:25', 'REGISTER_PATIENT', 'Registered new patient: himall', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(51, NULL, '2026-03-23 22:57:45', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(52, NULL, '2026-03-23 23:01:39', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(53, NULL, '2026-03-23 23:01:46', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(54, NULL, '2026-03-23 23:06:58', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(55, NULL, '2026-03-23 23:07:13', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(56, NULL, '2026-03-23 23:07:27', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(57, 1, '2026-03-23 23:07:33', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(58, 1, '2026-03-24 00:06:01', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(59, NULL, '2026-03-24 00:07:20', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(60, NULL, '2026-03-24 00:22:52', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(61, NULL, '2026-03-24 00:23:00', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(62, NULL, '2026-03-24 00:26:01', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(63, NULL, '2026-03-24 00:56:29', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(64, NULL, '2026-03-24 00:59:06', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(66, NULL, '2026-03-24 01:12:55', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(67, NULL, '2026-03-24 01:20:19', 'EMAIL_VERIFY', 'User verified their email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(68, 1, '2026-03-24 01:37:25', 'PASSWORD_RESET_REQUEST', 'Password reset requested for email: himalkumarkari@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(69, 1, '2026-03-24 01:37:51', 'PASSWORD_RESET', 'User reset their password via email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(70, 1, '2026-03-24 01:38:07', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(71, 1, '2026-03-24 01:38:22', 'PASSWORD_CHANGE', 'User changed their password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(72, 1, '2026-03-24 01:38:29', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(73, 10, '2026-03-24 01:38:37', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(74, 10, '2026-03-24 01:38:55', 'PASSWORD_CHANGE', 'User changed their password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(75, 10, '2026-03-24 01:46:13', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(76, 15, '2026-03-24 01:46:43', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(77, 15, '2026-03-24 01:46:53', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(78, 15, '2026-03-24 01:47:04', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(79, 18, '2026-03-24 01:47:23', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(80, 18, '2026-03-24 01:47:32', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(81, 18, '2026-03-24 01:47:39', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(82, 17, '2026-03-24 01:47:52', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(83, 17, '2026-03-24 01:47:59', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(84, 17, '2026-03-24 01:48:04', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(85, 16, '2026-03-24 01:48:16', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(86, 16, '2026-03-24 01:48:22', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(87, 16, '2026-03-24 01:48:27', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(88, 13, '2026-03-24 01:48:42', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(89, 13, '2026-03-24 01:48:56', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(90, 13, '2026-03-24 01:49:00', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(91, 14, '2026-03-24 01:49:20', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(92, 14, '2026-03-24 01:49:30', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(93, 14, '2026-03-24 01:49:34', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(94, 12, '2026-03-24 01:50:02', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(95, 12, '2026-03-24 01:50:15', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(96, 12, '2026-03-24 01:50:21', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(97, 11, '2026-03-24 01:50:31', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(98, 11, '2026-03-24 01:50:42', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(99, 1, '2026-03-24 01:50:52', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(100, 1, '2026-03-24 01:51:19', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(101, 19, '2026-03-24 01:51:26', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(102, 19, '2026-03-24 01:51:36', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(103, 20, '2026-03-24 01:51:46', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(104, 20, '2026-03-24 01:57:09', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(105, 1, '2026-03-24 01:57:21', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(106, 1, '2026-03-24 02:19:05', 'BOOK_APPOINTMENT', 'Booked appt #1 with doctor 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(107, 1, '2026-03-24 02:19:19', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(108, 1, '2026-03-24 02:19:31', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(109, 1, '2026-03-24 02:22:57', 'BOOK_APPOINTMENT', 'Booked appt #2 with doctor 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(110, 1, '2026-03-24 02:25:53', 'CANCEL_APPOINTMENT', 'Cancelled appointment ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(111, 1, '2026-03-24 02:26:11', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(112, 1, '2026-03-24 02:28:34', 'CANCEL_APPOINTMENT', 'Cancelled appointment ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(113, 1, '2026-03-24 02:28:48', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(114, 1, '2026-03-24 02:28:56', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(115, 11, '2026-03-24 02:30:01', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(116, 11, '2026-03-24 02:30:52', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(117, 11, '2026-03-24 02:40:25', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(118, 1, '2026-03-24 02:40:32', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(119, 1, '2026-03-24 02:40:49', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(120, 12, '2026-03-24 02:41:21', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(121, 12, '2026-03-24 02:41:27', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(122, 11, '2026-03-24 02:41:35', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(123, 11, '2026-03-24 03:07:58', 'SAVE_MEDICAL_RECORD', 'Saved record #2 for patient #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(124, 11, '2026-03-24 03:12:32', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(125, 13, '2026-03-24 03:12:48', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(126, 1, '2026-03-24 03:26:49', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(127, 1, '2026-03-24 03:27:28', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(128, 13, '2026-03-24 03:27:45', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(129, 13, '2026-03-24 03:28:05', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(130, 11, '2026-03-24 03:28:13', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(131, 11, '2026-03-24 03:29:38', 'SAVE_MEDICAL_RECORD', 'Saved record #3 for patient #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(132, 11, '2026-03-24 03:29:57', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(133, 1, '2026-03-24 03:30:06', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(134, 1, '2026-03-24 03:30:23', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(135, 1, '2026-03-24 03:30:26', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(136, 11, '2026-03-24 03:30:47', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(137, 11, '2026-03-24 03:31:10', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(138, 14, '2026-03-24 03:31:16', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(139, 14, '2026-03-24 03:31:40', 'SAVE_MEDICAL_RECORD', 'Saved record #4 for patient #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(140, 14, '2026-03-24 03:31:47', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(141, 1, '2026-03-24 03:31:53', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(142, 1, '2026-03-24 03:56:38', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(143, 1, '2026-03-24 04:00:50', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(144, 1, '2026-03-24 15:40:17', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(145, 1, '2026-03-24 15:41:48', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(146, 16, '2026-03-24 15:42:08', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(147, 1, '2026-03-24 17:27:41', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(148, 16, '2026-03-24 17:28:32', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(149, 1, '2026-03-24 22:37:57', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(150, 1, '2026-03-24 22:38:29', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(151, 16, '2026-03-24 22:47:35', 'Marked bill #7 as paid', 'Bill amount: 266.80', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(152, 16, '2026-03-24 22:47:46', 'Marked bill #6 as paid', 'Bill amount: 290.00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(153, 1, '2026-03-24 22:48:12', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(154, 1, '2026-03-24 22:48:17', 'CANCEL_APPOINTMENT', 'Cancelled appointment ID: 7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(155, 16, '2026-03-24 23:59:09', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(156, 19, '2026-03-24 23:59:42', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(157, 20, '2026-03-25 00:04:52', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(158, 1, '2026-03-26 00:19:33', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(159, 16, '2026-03-26 02:23:26', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(160, 1, '2026-03-26 02:24:25', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(161, 1, '2026-03-26 02:25:08', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(162, 19, '2026-03-26 02:25:30', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(163, 19, '2026-03-26 02:25:53', 'RECORD_VITALS', 'Recorded vitals for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(164, 20, '2026-03-26 02:30:15', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(165, 15, '2026-03-26 13:22:26', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(166, 10, '2026-04-09 20:16:44', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(167, 10, '2026-04-09 20:18:13', 'DELETE_APPOINTMENT', 'Deleted appointment ID: 8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(168, 10, '2026-04-09 20:18:49', 'EXPORT_REPORT', 'Exported appointments report from 2026-03-10 to 2026-04-09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(169, 10, '2026-04-09 21:15:42', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(170, 10, '2026-04-09 21:15:54', 'UPDATE_USER_ROLE', 'Changed user 23 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(171, 10, '2026-04-09 21:16:00', 'UPDATE_USER_ROLE', 'Changed user 23 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(172, 10, '2026-04-09 23:22:09', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(173, 19, '2026-04-09 23:22:58', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(174, 20, '2026-04-09 23:23:17', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(175, 20, '2026-04-09 23:25:23', 'PROFILE_UPDATE', 'User updated their profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(176, 20, '2026-04-09 23:25:31', 'PROFILE_UPDATE', 'User updated their profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(177, 1, '2026-04-09 23:25:46', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(178, 1, '2026-04-09 23:30:56', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(179, 16, '2026-04-09 23:31:32', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(180, 1, '2026-04-09 23:31:52', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(181, 11, '2026-04-09 23:32:30', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(182, 15, '2026-04-09 23:33:13', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(183, 13, '2026-04-09 23:33:54', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(184, 13, '2026-04-09 23:34:23', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(185, 13, '2026-04-09 23:34:33', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(186, 13, '2026-04-09 23:34:47', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(187, 10, '2026-04-09 23:38:14', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(188, 1, '2026-04-09 23:40:00', 'PASSWORD_RESET_REQUEST', 'Password reset requested for email: himalkumarkari@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(189, 1, '2026-04-09 23:41:05', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(190, 1, '2026-04-10 19:36:34', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(191, 10, '2026-04-10 19:36:39', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(192, 10, '2026-04-10 20:09:49', 'UPDATE_USER_ROLE', 'Changed user 23 role to doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(193, 10, '2026-04-10 20:09:57', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(194, NULL, '2026-04-10 20:10:06', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(195, NULL, '2026-04-10 20:10:26', 'UPDATE_AVAILABILITY', 'Updated availability', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(196, NULL, '2026-04-10 20:10:45', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(197, 10, '2026-04-10 20:10:57', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(198, 10, '2026-04-10 20:11:03', 'UPDATE_USER_ROLE', 'Changed user 23 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(199, 10, '2026-04-10 20:11:08', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(200, 10, '2026-04-10 20:11:26', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(201, NULL, '2026-04-10 20:11:34', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(202, NULL, '2026-04-10 20:11:44', 'PROCESS_PAYMENT', 'Processed payment for bill 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(203, NULL, '2026-04-10 20:12:52', 'REGISTER_PATIENT', 'Registered: alok', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(204, 10, '2026-04-10 20:13:18', 'DELETE_USER', 'Deleted user 24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(205, 10, '2026-04-10 20:32:44', 'UPDATE_USER_ROLE', 'Changed user 23 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(206, 10, '2026-04-10 20:32:48', 'UPDATE_USER_ROLE', 'Changed user 23 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(207, 10, '2026-04-10 20:44:08', 'MARK_BILL_PAID', 'Marked bill #4 as paid via Cash', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(208, 10, '2026-04-10 20:44:19', 'MARK_BILL_PAID', 'Marked bill #3 as paid via Cash', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(209, 10, '2026-04-10 20:44:28', 'MARK_BILL_PAID', 'Marked bill #2 as paid via Cash', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(210, 10, '2026-04-10 20:44:35', 'MARK_BILL_PAID', 'Marked bill #1 as paid via Cash', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(211, 10, '2026-04-10 21:00:30', 'UPDATE_APPOINTMENT_STATUS', 'Updated appointment 4 from completed to in-progress', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(212, 10, '2026-04-10 21:08:54', 'UPDATE_STAFF', 'Updated staff ID: 18, User ID: 23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(213, 10, '2026-04-10 21:09:35', 'CREATE_STAFF', 'Created doctor: alok (User ID: 25)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(214, NULL, '2026-04-10 21:16:37', 'CREATE_BILL', 'Created bill for patient 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(215, NULL, '2026-04-10 21:17:15', 'PROCESS_PAYMENT', 'Processed payment for bill 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(216, NULL, '2026-04-10 21:19:00', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(217, 19, '2026-04-10 21:19:14', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(218, NULL, '2026-04-10 21:21:07', 'PASSWORD_RESET_REQUEST', 'Password reset requested for email: abinashhkarkii@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(219, 1, '2026-04-10 21:29:49', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(220, 19, '2026-04-10 21:30:56', 'RECORD_VITALS', 'Recorded vitals for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(221, 19, '2026-04-10 21:34:29', 'PROFILE_UPDATE', 'Updated profile', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(222, 1, '2026-04-10 21:40:45', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(223, 1, '2026-04-10 21:41:03', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(224, 19, '2026-04-10 21:47:23', 'CREATE_LAB_TEST', 'Created lab test ID: 1 for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(225, 19, '2026-04-10 21:47:30', 'COLLECT_SAMPLE', 'Collected sample for test ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(226, 19, '2026-04-10 21:47:50', 'ENTER_LAB_RESULTS', 'Entered results for test ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(227, 19, '2026-04-10 21:48:29', 'CREATE_LAB_TEST', 'Created lab test ID: 2 for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(228, 25, '2026-04-10 21:49:16', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(229, 25, '2026-04-10 21:49:42', 'UPDATE_AVAILABILITY', 'Updated availability', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(230, 25, '2026-04-10 22:00:22', 'UPDATE_LAB_TEST', 'Doctor updated lab test ID: 2 to status: completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(231, 25, '2026-04-10 22:12:38', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(232, 13, '2026-04-10 22:12:46', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(233, 13, '2026-04-10 22:13:07', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(234, 25, '2026-04-10 22:13:13', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(235, 19, '2026-04-10 22:30:11', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(236, 25, '2026-04-10 22:30:24', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(237, 10, '2026-04-10 22:52:44', 'DELETE_USER', 'Deleted user 23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(238, 10, '2026-04-11 14:09:44', 'UPDATE_USER_ROLE', 'Changed user 22 role to nurse', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(239, 10, '2026-04-11 14:09:48', 'UPDATE_USER_ROLE', 'Changed user 22 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `audit_log` (`logId`, `userId`, `timestamp`, `action`, `details`, `ipAddress`, `userAgent`) VALUES
(240, 10, '2026-04-11 14:10:07', 'UPDATE_STAFF', 'Updated staff ID: 17, User ID: 20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(241, 25, '2026-04-11 14:10:57', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(242, 10, '2026-04-11 14:21:42', 'PROCESS_SALARY', 'Processed salary for staff ID: 20, Amount: 2500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(243, 10, '2026-04-11 14:22:03', 'PROCESS_SALARY', 'Processed salary for doctor ID: 25, Amount: 5000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(244, 10, '2026-04-11 14:33:20', 'UPDATE_USER_ROLE', 'Changed user 22 role to accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(245, 1, '2026-04-11 14:33:41', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(246, 10, '2026-04-11 14:34:13', 'UPDATE_USER_ROLE', 'Changed user 22 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(247, 10, '2026-04-11 14:39:41', 'CREATE_STAFF', 'Created accountant: abishek (User ID: 26)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(248, NULL, '2026-04-11 14:40:14', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(249, NULL, '2026-04-11 14:42:27', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(250, NULL, '2026-04-11 14:42:36', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(251, NULL, '2026-04-11 14:45:46', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(252, NULL, '2026-04-11 14:45:56', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(253, 10, '2026-04-11 14:54:13', 'DELETE_STAFF', 'Deleted staff ID: 21, User: Abishek Karki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(254, 10, '2026-04-11 14:54:35', 'UPDATE_USER_ROLE', 'Changed user 25 role to accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(255, 10, '2026-04-11 14:55:16', 'UPDATE_USER_ROLE', 'Changed user 25 role to doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(256, 10, '2026-04-11 14:55:45', 'CREATE_STAFF', 'Created accountant: abishek (User ID: 27)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(259, 25, '2026-04-11 14:56:25', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(260, NULL, '2026-04-11 14:56:37', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(261, 10, '2026-04-11 14:58:56', 'DELETE_STAFF', 'Deleted staff ID: 22, User: Abishek Karki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(262, 10, '2026-04-11 15:04:51', 'DELETE_STAFF', 'Deleted staff ID: 20, User: Jane Smith', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(263, 10, '2026-04-11 15:05:20', 'CREATE_STAFF', 'Created accountant: abishek (User ID: 28)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(266, NULL, '2026-04-11 15:06:12', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(267, 10, '2026-04-11 15:07:57', 'DELETE_STAFF', 'Deleted staff ID: 23, User: Abishek Karki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(268, 10, '2026-04-11 15:08:30', 'CREATE_STAFF', 'Created doctor: abishek (User ID: 29)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(269, NULL, '2026-04-11 15:08:56', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(270, NULL, '2026-04-11 15:09:00', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(271, 10, '2026-04-11 15:09:19', 'DELETE_STAFF', 'Deleted staff ID: 24, User: Abishek Karki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(272, 10, '2026-04-11 15:09:35', 'CREATE_STAFF', 'Created nurse: abishek (User ID: 30)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(273, NULL, '2026-04-11 15:09:43', 'LOGIN', 'User logged in successfully as nurse', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(274, NULL, '2026-04-11 15:10:01', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(275, 10, '2026-04-11 15:15:55', 'DELETE_STAFF', 'Deleted staff ID: 25, User: Abishek Karki', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(276, 10, '2026-04-11 15:16:14', 'CREATE_STAFF', 'Created accountant: abishek (User ID: 31)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(277, NULL, '2026-04-11 15:16:27', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(278, 10, '2026-04-11 15:26:32', 'UPDATE_USER_ROLE', 'Changed user 31 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(279, 10, '2026-04-11 15:26:35', 'DELETE_USER', 'Deleted user 31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(280, 10, '2026-04-11 15:26:58', 'CREATE_STAFF', 'Created accountant: abishek (User ID: 32)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(281, 32, '2026-04-11 15:27:20', 'LOGIN', 'User logged in successfully as accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(282, 32, '2026-04-11 15:27:51', 'PROCESS_SALARY', 'Processed salary for nurse ID: 19, Amount: 3500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(283, 32, '2026-04-11 15:28:28', 'PROCESS_SALARY', 'Processed salary for accountant ID: 32, Amount: 4000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(284, 10, '2026-04-11 15:31:28', 'UPDATE_USER_ROLE', 'Changed user 32 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(285, 10, '2026-04-11 15:31:31', 'UPDATE_USER_ROLE', 'Changed user 32 role to accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(287, 25, '2026-04-11 15:38:46', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(288, 25, '2026-04-11 15:39:56', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(289, 32, '2026-04-11 15:43:23', 'LOGIN', 'User logged in successfully as accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(290, 10, '2026-04-11 15:49:07', 'UPDATE_USER_ROLE', 'Changed user 32 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(291, 10, '2026-04-11 15:49:07', 'UPDATE_USER_ROLE', 'Changed user 32 role to staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(292, 32, '2026-04-11 15:49:18', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(293, 32, '2026-04-11 15:49:29', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(294, 32, '2026-04-11 15:49:36', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(295, 10, '2026-04-11 15:49:50', 'UPDATE_USER_ROLE', 'Changed user 32 role to accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(296, 10, '2026-04-11 15:49:50', 'UPDATE_USER_ROLE', 'Changed user 32 role to accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(297, 32, '2026-04-11 15:49:58', 'LOGIN', 'User logged in successfully as accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(298, 10, '2026-04-11 15:52:12', 'CREATE_STAFF', 'Created doctor: testt (User ID: 33)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(299, 32, '2026-04-11 15:52:47', 'PROCESS_SALARY', 'Processed salary for doctor ID: 33, Amount: 5000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(300, 32, '2026-04-11 19:35:07', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(301, 20, '2026-04-11 19:35:52', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(302, 20, '2026-04-11 19:59:05', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(303, 19, '2026-04-11 19:59:14', 'LOGIN', 'User logged in successfully as nurse', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(304, 19, '2026-04-11 20:07:14', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(305, 20, '2026-04-11 20:07:22', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(306, 32, '2026-04-11 20:28:40', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(307, 19, '2026-04-11 20:28:50', 'LOGIN', 'User logged in successfully as nurse', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(308, 19, '2026-04-11 20:52:28', 'RECORD_VITALS', 'Recorded vitals for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(309, 19, '2026-04-11 20:53:44', 'RECORD_VITALS', 'Recorded vitals for patient ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(310, 19, '2026-04-11 20:55:14', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(311, 1, '2026-04-11 20:55:21', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(312, 20, '2026-04-11 21:07:36', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(313, 20, '2026-04-11 21:29:46', 'CREATE_BILL', 'Created bill #11 for patient 1, Amount: 174', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(314, 10, '2026-04-11 21:30:14', 'LOGIN', 'User logged in successfully as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(315, 25, '2026-04-11 21:37:17', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(316, 25, '2026-04-11 21:43:52', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(317, 25, '2026-04-11 21:52:09', 'UPDATE_LAB_TEST', 'Doctor updated lab test ID: 2 to status: completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(318, 20, '2026-04-11 22:02:51', 'PROCESS_PAYMENT', 'Processed payment for bill #12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(319, 10, '2026-04-11 22:06:50', 'UPDATE_USER_ROLE', 'Changed user 33 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(320, 10, '2026-04-11 22:06:50', 'UPDATE_USER_ROLE', 'Changed user 33 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(321, 10, '2026-04-11 22:06:54', 'DELETE_USER', 'Deleted user 33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(322, 10, '2026-04-11 22:09:41', 'UPDATE_APPOINTMENT', 'Updated appointment 4 to completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(323, 10, '2026-04-11 22:10:36', 'UPDATE_APPOINTMENT', 'Updated appointment 4 to scheduled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(324, 10, '2026-04-11 22:14:09', 'UPDATE_APPOINTMENT', 'Updated appointment 4 to completed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(325, 10, '2026-04-11 22:31:09', 'ADD_DEPARTMENT', 'Added Test', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(326, 1, '2026-04-11 22:40:15', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(327, 25, '2026-04-11 23:20:43', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(328, 13, '2026-04-11 23:20:58', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(329, 13, '2026-04-11 23:21:08', 'CLEAR_AVAILABILITY', 'Cleared availability for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(330, 13, '2026-04-11 23:21:14', 'SET_DEFAULT_AVAILABILITY', 'Set default schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(331, 13, '2026-04-11 23:21:24', 'UPDATE_AVAILABILITY', 'Updated availability schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(332, 13, '2026-04-11 23:40:03', 'UPDATE_AVAILABILITY', 'Updated monthly schedule for doctor ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(333, 1, '2026-04-11 23:51:48', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(334, 1, '2026-04-11 23:52:16', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 4 on 2026-04-14 at 11:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(335, 13, '2026-04-11 23:53:02', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(336, 13, '2026-04-11 23:53:15', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(337, 13, '2026-04-11 23:53:40', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(338, 25, '2026-04-11 23:53:46', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(339, 25, '2026-04-11 23:54:12', 'UPDATE_AVAILABILITY', 'Updated monthly schedule for doctor ID: 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(340, 20, '2026-04-11 23:59:45', 'APPOINTMENT_BOOK', 'Staff booked appointment for patient ID: 1 with doctor ID: 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(341, 10, '2026-04-12 00:08:10', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(342, 1, '2026-04-12 00:13:43', 'RESCHEDULE_APPOINTMENT', 'Patient rescheduled appointment 10 from 2026-04-18 11:30:00 to 2026-04-30 13:30:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(343, 10, '2026-04-12 00:17:09', 'LOGIN', 'User logged in successfully as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(344, 10, '2026-04-12 00:27:30', 'BOOK_APPOINTMENT_ADMIN', 'Admin booked appointment for patient 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(345, 20, '2026-04-12 00:30:17', 'RESCHEDULE_APPOINTMENT_STAFF', 'Staff rescheduled appointment 10 to 2026-05-08 09:30:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(346, 10, '2026-04-12 00:31:59', 'RESCHEDULE_APPOINTMENT_ADMIN', 'Admin rescheduled appointment 10 to 2026-04-17 09:30:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(347, 25, '2026-04-12 00:45:14', 'UPDATE_AVAILABILITY', 'Updated monthly schedule for doctor ID: 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(348, 25, '2026-04-12 00:54:47', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(349, 1, '2026-04-12 00:54:53', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(350, 1, '2026-04-12 00:55:09', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(351, 9, '2026-04-12 00:55:57', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(352, 9, '2026-04-12 00:56:24', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(353, 20, '2026-04-12 00:56:28', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(354, 20, '2026-04-12 00:56:45', 'PROCESS_PAYMENT', 'Processed payment for bill #14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(355, 20, '2026-04-12 00:57:11', 'PROCESS_PAYMENT', 'Processed payment for bill #13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(356, 10, '2026-04-12 01:04:33', 'UPDATE_DEPARTMENT', 'Updated department ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(357, 10, '2026-04-12 01:21:13', 'LOGIN', 'User logged in successfully as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(358, 10, '2026-04-12 01:21:26', 'PROCESS_SALARY', 'Processed salary for doctor ID: 16, Amount: 5000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(359, 10, '2026-04-12 01:22:10', 'UPDATE_USER_ROLE', 'Changed user 11 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(360, 10, '2026-04-12 01:22:10', 'UPDATE_USER_ROLE', 'Changed user 11 role to patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(361, 10, '2026-04-12 01:22:18', 'UPDATE_USER_ROLE', 'Changed user 11 role to doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(362, 10, '2026-04-12 01:22:18', 'UPDATE_USER_ROLE', 'Changed user 11 role to doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(363, 10, '2026-04-12 01:31:48', 'LOGIN', 'User logged in successfully as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(364, 1, '2026-04-12 01:36:20', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(365, 10, '2026-04-12 22:02:15', 'LOGIN', 'User logged in successfully as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(366, 20, '2026-04-12 22:26:33', 'LOGIN', 'User logged in successfully as staff', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(367, 10, '2026-04-12 22:29:28', 'CREATE_USER', 'Created patient: test', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(368, 34, '2026-04-12 22:30:43', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(369, 34, '2026-04-12 22:30:54', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(370, 34, '2026-04-12 22:31:16', 'PASSWORD_RESET_REQUEST', 'Password reset requested for email: cihe240209@student.cihe.edu.au', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(371, 34, '2026-04-12 22:32:40', 'PASSWORD_RESET', 'User reset their password via email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(372, 34, '2026-04-12 23:08:34', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(373, 15, '2026-04-12 23:10:00', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(374, 15, '2026-04-12 23:10:29', 'UPDATE_AVAILABILITY', 'Updated monthly schedule for doctor ID: 6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(375, 10, '2026-04-12 23:10:58', 'BOOK_APPOINTMENT_ADMIN', 'Admin booked appointment for patient 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(376, 10, '2026-04-12 23:11:43', 'RESCHEDULE_APPOINTMENT_ADMIN', 'Admin rescheduled appointment 12 to 2026-04-17 14:30:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(377, 20, '2026-04-12 23:12:16', 'RESCHEDULE_APPOINTMENT_STAFF', 'Staff rescheduled appointment 12 to 2026-04-16 14:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(378, 10, '2026-04-12 23:13:30', 'MARK_BILL_PAID', 'Marked bill #16 as paid via Cash', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(379, 34, '2026-04-12 23:18:54', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 6 on 2026-04-16 at 10:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(380, 34, '2026-04-12 23:20:14', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(381, 35, '2026-04-12 23:25:47', 'EMAIL_VERIFY', 'User verified their email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(382, 35, '2026-04-12 23:26:14', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(383, 35, '2026-04-12 23:27:22', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 11 on 2026-04-13 at 09:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(384, 15, '2026-04-12 23:28:17', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(385, 25, '2026-04-12 23:28:27', 'LOGIN', 'User logged in successfully as doctor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(386, 35, '2026-04-12 23:33:53', 'BOOK_APPOINTMENT', 'Booked appointment with doctor ID: 11 on 2026-04-13 at 10:00:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(387, 25, '2026-04-12 23:39:16', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(388, 32, '2026-04-12 23:39:27', 'LOGIN', 'User logged in successfully as accountant', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(389, 10, '2026-04-13 00:12:30', 'LOGOUT', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(390, 1, '2026-04-13 00:12:57', 'LOGIN', 'User logged in successfully as patient', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `billId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `appointmentId` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `totalAmount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','cancelled','refunded') DEFAULT 'pending',
  `paymentMethod` varchar(50) DEFAULT NULL,
  `paymentDate` datetime DEFAULT NULL,
  `dueDate` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `billId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `appointmentId` int(11) DEFAULT NULL,
  `recordId` int(11) DEFAULT NULL,
  `consultationFee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `additionalCharges` decimal(10,2) NOT NULL DEFAULT 0.00,
  `serviceCharge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gst` decimal(10,2) NOT NULL DEFAULT 0.00,
  `totalAmount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','cancelled','overdue') DEFAULT 'unpaid',
  `generatedAt` datetime DEFAULT current_timestamp(),
  `paidAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`billId`, `patientId`, `appointmentId`, `recordId`, `consultationFee`, `additionalCharges`, `serviceCharge`, `gst`, `totalAmount`, `status`, `generatedAt`, `paidAt`) VALUES
(1, 1, 6, 5, 190.00, 50.00, 7.20, 31.20, 278.40, 'paid', '2026-03-24 17:04:27', '2026-04-10 20:44:35'),
(2, 1, 6, 6, 190.00, 50.00, 7.20, 31.20, 278.40, 'paid', '2026-03-24 17:07:08', '2026-04-10 20:44:28'),
(3, 1, 6, 7, 190.00, 50.00, 7.20, 31.20, 278.40, 'paid', '2026-03-24 17:09:25', '2026-04-10 20:44:19'),
(4, 1, 6, 8, 190.00, 50.00, 7.20, 31.20, 278.40, 'paid', '2026-03-24 17:25:06', '2026-04-10 20:44:08'),
(5, 1, 6, 9, 190.00, 0.00, 5.70, 24.70, 220.40, 'paid', '2026-03-24 17:34:44', '2026-04-10 20:11:44'),
(6, 1, 6, 10, 190.00, 60.00, 7.50, 32.50, 290.00, 'paid', '2026-03-24 17:38:38', '2026-03-24 22:47:46'),
(7, 1, 6, 11, 190.00, 40.00, 6.90, 29.90, 266.80, 'paid', '2026-03-24 17:47:31', '2026-03-24 22:47:35'),
(8, 1, 7, 12, 190.00, 80.00, 8.10, 35.10, 313.20, 'paid', '2026-03-24 22:41:38', '2026-03-24 23:45:46'),
(9, 1, NULL, NULL, 159.00, 0.00, 4.77, 20.67, 184.44, 'paid', '2026-04-10 21:16:37', '2026-04-10 21:17:15'),
(10, 1, NULL, 14, 150.00, 5.00, 4.65, 20.15, 179.80, 'paid', '2026-04-10 22:44:52', '2026-04-10 22:46:41'),
(11, 1, NULL, NULL, 150.00, 0.00, 4.50, 19.50, 174.00, 'paid', '2026-04-11 21:29:39', '2026-04-11 22:01:19'),
(12, 1, NULL, 15, 150.00, 0.00, 4.50, 19.50, 174.00, 'paid', '2026-04-11 22:02:06', '2026-04-11 22:02:45'),
(13, 3, 11, 16, 150.00, 200.00, 10.50, 45.50, 406.00, 'paid', '2026-04-12 00:36:08', '2026-04-12 00:57:05'),
(14, 3, 11, 17, 150.00, 0.00, 4.50, 19.50, 174.00, 'paid', '2026-04-12 00:36:50', '2026-04-12 00:56:38'),
(15, 3, 11, 18, 150.00, 200.00, 10.50, 45.50, 406.00, 'paid', '2026-04-12 00:51:54', '2026-04-12 00:56:10'),
(16, 13, 12, 19, 180.00, 150.00, 9.90, 42.90, 382.80, 'paid', '2026-04-12 23:12:47', '2026-04-12 23:13:30'),
(17, 14, NULL, 20, 150.00, 12.00, 4.86, 21.06, 187.92, 'unpaid', '2026-04-12 23:30:58', NULL),
(18, 14, NULL, 21, 150.00, 12.00, 4.86, 21.06, 187.92, 'unpaid', '2026-04-12 23:31:04', NULL),
(19, 14, 14, 22, 150.00, 13.00, 4.89, 21.19, 189.08, 'unpaid', '2026-04-12 23:32:49', NULL),
(20, 14, 15, 23, 150.00, 123.00, 8.19, 35.49, 316.68, 'paid', '2026-04-12 23:35:35', '2026-04-12 23:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `bill_charges`
--

CREATE TABLE `bill_charges` (
  `id` int(11) NOT NULL,
  `billId` int(11) NOT NULL,
  `chargeName` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bill_charges`
--

INSERT INTO `bill_charges` (`id`, `billId`, `chargeName`, `amount`) VALUES
(1, 1, 'Urine Test', 50.00),
(2, 2, 'Urine Test', 50.00),
(3, 3, 'Urine Test', 50.00),
(4, 4, 'Urine Test', 50.00),
(5, 6, 'Urine Test', 60.00),
(6, 7, 'Medicine Charges', 40.00),
(7, 8, 'Medicine Charges', 50.00),
(8, 8, 'BP Check', 30.00),
(9, 10, 'E', 5.00),
(10, 13, 'Medicine Charges', 200.00),
(11, 15, 'Medicine Charges', 200.00),
(12, 16, 'Adding bills', 150.00),
(13, 17, '123', 12.00),
(14, 18, '123', 12.00),
(15, 19, 'ggc', 13.00),
(16, 20, 'n', 123.00);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `departmentId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `headDoctorId` int(11) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `phoneNumber` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `isActive` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`departmentId`, `name`, `description`, `headDoctorId`, `location`, `phoneNumber`, `email`, `isActive`) VALUES
(1, 'Cardiology', 'Heart and cardiovascular system care', 11, 'Building A, Floor 3', '', '', 1),
(2, 'Neurology', 'Brain and nervous system care', NULL, 'Building A, Floor 4', NULL, NULL, 1),
(3, 'Pediatrics', 'Child healthcare services', NULL, 'Building B, Floor 1', NULL, NULL, 1),
(4, 'Orthopedics', 'Bone and joint care', NULL, 'Building B, Floor 2', NULL, NULL, 1),
(5, 'Emergency Medicine', 'Emergency care services', NULL, 'Building C, Floor 1', NULL, NULL, 1),
(6, 'Radiology', 'Medical imaging services', NULL, 'Building C, Floor 2', NULL, NULL, 1),
(7, 'Dermatology', 'Skin care services', NULL, 'Building D, Floor 1', NULL, NULL, 1),
(8, 'Ophthalmology', 'Eye care services', NULL, 'Building D, Floor 2', NULL, NULL, 1),
(9, 'Test', '', NULL, '9 Block', '', '', 0),
(10, 'General Medicine', 'Primary care and general health', NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctorId` int(11) NOT NULL,
  `staffId` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `consultationFee` decimal(10,2) DEFAULT NULL,
  `yearsOfExperience` int(11) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `isAvailable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctorId`, `staffId`, `specialization`, `consultationFee`, `yearsOfExperience`, `education`, `biography`, `isAvailable`) VALUES
(2, 8, 'Cardiology', 250.00, 15, 'MD, FACC', 'Dr. John Smith is a board-certified cardiologist with over 15 years of experience in treating heart conditions, including coronary artery disease, heart failure, and arrhythmias.', 1),
(3, 9, 'Neurology', 280.00, 12, 'MD, PhD', 'Dr. Sarah Johnson specializes in treating neurological disorders including epilepsy, multiple sclerosis, Parkinson\'s disease, and stroke rehabilitation.', 1),
(4, 10, 'Pediatrics', 200.00, 10, 'MD, FAAP', 'Dr. Michael Williams provides compassionate care for children from birth through adolescence, specializing in developmental pediatrics and childhood immunizations.', 1),
(5, 11, 'Orthopedics', 220.00, 18, 'MD, FACS', 'Dr. Robert Brown is an experienced orthopedic surgeon specializing in joint replacement, sports medicine, and minimally invasive procedures for bone and joint conditions.', 1),
(6, 12, 'Dermatology', 180.00, 8, 'MD, FAAD', 'Dr. Emily Davis provides comprehensive skin care services including treatment for acne, eczema, psoriasis, and skin cancer screening and treatment.', 1),
(7, 13, 'Ophthalmology', 190.00, 14, 'MD, FACS', 'Dr. David Wilson specializes in comprehensive eye care including cataract surgery, glaucoma treatment, and laser vision correction.', 1),
(8, 14, 'Obstetrics & Gynecology', 240.00, 11, 'MD, FACOG', 'Dr. Lisa Martinez provides comprehensive women\'s health services including prenatal care, family planning, and menopause management.', 1),
(9, 15, 'Radiology', 210.00, 9, 'MD, DABR', 'Dr. James Taylor specializes in diagnostic imaging including MRI, CT scans, X-rays, and ultrasound interpretation.', 1),
(11, 19, '', 150.00, 0, '', '', 1);

--
-- Triggers `doctors`
--
DELIMITER $$
CREATE TRIGGER `after_doctor_insert` AFTER INSERT ON `doctors` FOR EACH ROW BEGIN
    UPDATE users u
    JOIN staff s ON u.userId = s.userId
    SET u.role = 'doctor'
    WHERE s.staffId = NEW.staffId;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_availability`
--

CREATE TABLE `doctor_availability` (
  `availabilityId` int(11) NOT NULL,
  `doctorId` int(11) NOT NULL,
  `availabilityDate` date NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `isAvailable` tinyint(1) DEFAULT 1,
  `isDayOff` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_availability`
--

INSERT INTO `doctor_availability` (`availabilityId`, `doctorId`, `availabilityDate`, `startTime`, `endTime`, `isAvailable`, `isDayOff`, `createdAt`, `updatedAt`) VALUES
(1, 4, '2026-04-13', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(2, 4, '2026-04-14', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(3, 4, '2026-04-15', '09:00:00', '17:00:00', 0, 1, '2026-04-11 13:40:03', '2026-04-11 13:46:07'),
(4, 4, '2026-04-16', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(5, 4, '2026-04-17', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(6, 4, '2026-04-20', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(7, 4, '2026-04-21', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(8, 4, '2026-04-22', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(9, 4, '2026-04-23', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(10, 4, '2026-04-24', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(11, 4, '2026-04-27', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(12, 4, '2026-04-28', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(13, 4, '2026-04-29', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(14, 4, '2026-04-30', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(15, 4, '2026-05-01', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(16, 4, '2026-05-04', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(17, 4, '2026-05-05', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(18, 4, '2026-05-06', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(19, 4, '2026-05-07', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(20, 4, '2026-05-08', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(21, 4, '2026-05-11', '09:00:00', '17:00:00', 1, 0, '2026-04-11 13:40:03', '2026-04-11 13:40:03'),
(99, 11, '2026-04-11', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(100, 11, '2026-04-12', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(101, 11, '2026-04-13', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(102, 11, '2026-04-14', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(103, 11, '2026-04-15', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(104, 11, '2026-04-16', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(105, 11, '2026-04-17', '09:00:00', '17:00:00', 0, 1, '2026-04-11 14:45:14', '2026-04-11 14:45:22'),
(106, 11, '2026-04-18', '09:00:00', '17:00:00', 0, 1, '2026-04-11 14:45:14', '2026-04-11 14:45:28'),
(107, 11, '2026-04-19', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(108, 11, '2026-04-20', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(109, 11, '2026-04-21', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(110, 11, '2026-04-22', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(111, 11, '2026-04-23', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(112, 11, '2026-04-24', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(113, 11, '2026-04-25', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(114, 11, '2026-04-26', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(115, 11, '2026-04-27', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(116, 11, '2026-04-28', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(117, 11, '2026-04-29', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(118, 11, '2026-04-30', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(119, 11, '2026-05-01', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(120, 11, '2026-05-02', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(121, 11, '2026-05-03', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(122, 11, '2026-05-04', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(123, 11, '2026-05-05', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(124, 11, '2026-05-06', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(125, 11, '2026-05-07', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(126, 11, '2026-05-08', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(127, 11, '2026-05-09', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(128, 11, '2026-05-10', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(129, 11, '2026-05-11', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(130, 11, '2026-05-12', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(131, 11, '2026-05-13', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(132, 11, '2026-05-14', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(133, 11, '2026-05-15', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(134, 11, '2026-05-16', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(135, 11, '2026-05-17', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(136, 11, '2026-05-18', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(137, 11, '2026-05-19', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(138, 11, '2026-05-20', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(139, 11, '2026-05-21', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(140, 11, '2026-05-22', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(141, 11, '2026-05-23', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(142, 11, '2026-05-24', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(143, 11, '2026-05-25', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(144, 11, '2026-05-26', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(145, 11, '2026-05-27', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(146, 11, '2026-05-28', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(147, 11, '2026-05-29', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(148, 11, '2026-05-30', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(149, 11, '2026-05-31', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(150, 11, '2026-06-01', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(151, 11, '2026-06-02', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(152, 11, '2026-06-03', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(153, 11, '2026-06-04', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(154, 11, '2026-06-05', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(155, 11, '2026-06-06', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(156, 11, '2026-06-07', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(157, 11, '2026-06-08', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(158, 11, '2026-06-09', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(159, 11, '2026-06-10', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(160, 11, '2026-06-11', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(161, 11, '2026-06-12', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(162, 11, '2026-06-13', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(163, 11, '2026-06-14', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(164, 11, '2026-06-15', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(165, 11, '2026-06-16', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(166, 11, '2026-06-17', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(167, 11, '2026-06-18', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(168, 11, '2026-06-19', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(169, 11, '2026-06-20', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(170, 11, '2026-06-21', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(171, 11, '2026-06-22', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(172, 11, '2026-06-23', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(173, 11, '2026-06-24', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(174, 11, '2026-06-25', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(175, 11, '2026-06-26', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(176, 11, '2026-06-27', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(177, 11, '2026-06-28', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(178, 11, '2026-06-29', '09:00:00', '17:00:00', 1, 0, '2026-04-11 14:45:14', '2026-04-11 14:45:14'),
(181, 6, '2026-04-13', '09:00:00', '17:00:00', 1, 0, '2026-04-12 13:10:29', '2026-04-12 13:10:29'),
(182, 6, '2026-04-14', '09:00:00', '17:00:00', 1, 0, '2026-04-12 13:10:29', '2026-04-12 13:10:29'),
(183, 6, '2026-04-15', '09:00:00', '17:00:00', 1, 0, '2026-04-12 13:10:29', '2026-04-12 13:10:29'),
(184, 6, '2026-04-16', '09:00:00', '17:00:00', 1, 0, '2026-04-12 13:10:29', '2026-04-12 13:10:29'),
(185, 6, '2026-04-17', '09:00:00', '17:00:00', 0, 1, '2026-04-12 13:10:29', '2026-04-12 13:18:16');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_department`
--

CREATE TABLE `doctor_department` (
  `doctorId` int(11) NOT NULL,
  `departmentId` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `doctor_schedule`
-- (See below for the actual view)
--
CREATE TABLE `doctor_schedule` (
`doctorId` int(11)
,`doctorName` varchar(101)
,`specialization` varchar(100)
,`appointmentId` int(11)
,`dateTime` datetime
,`duration` int(11)
,`status` enum('scheduled','confirmed','in-progress','completed','cancelled','no-show')
,`patientName` varchar(101)
);

-- --------------------------------------------------------

--
-- Table structure for table `hospital_finance`
--

CREATE TABLE `hospital_finance` (
  `financeId` int(11) NOT NULL,
  `transactionType` enum('revenue','expense') NOT NULL,
  `category` varchar(50) NOT NULL COMMENT 'bill_payment, salary, other_income, other_expense',
  `amount` decimal(10,2) NOT NULL,
  `referenceId` int(11) DEFAULT NULL COMMENT 'billId or salaryId',
  `description` text DEFAULT NULL,
  `transactionDate` datetime NOT NULL,
  `createdBy` int(11) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hospital_finance`
--

INSERT INTO `hospital_finance` (`financeId`, `transactionType`, `category`, `amount`, `referenceId`, `description`, `transactionDate`, `createdBy`, `createdAt`) VALUES
(1, 'expense', 'salary', 2500.00, 1, 'Salary payment - staff - 2026-04', '2026-04-11 14:21:42', 10, '2026-04-11 14:21:42'),
(2, 'expense', 'salary', 5000.00, 2, 'Salary payment - doctor - 2026-04', '2026-04-11 14:22:03', 10, '2026-04-11 14:22:03'),
(3, 'expense', 'salary', 3500.00, 3, 'Salary payment - nurse - 2026-04', '2026-04-11 15:27:51', 32, '2026-04-11 15:27:51'),
(4, 'expense', 'salary', 4000.00, 4, 'Salary payment - accountant - 2026-04', '2026-04-11 15:28:28', 32, '2026-04-11 15:28:28'),
(5, 'expense', 'salary', 5000.00, 5, 'Salary payment - doctor - 2026-04', '2026-04-11 15:52:47', 32, '2026-04-11 15:52:47'),
(6, 'revenue', 'bill_generated', 174.00, 11, 'Bill generated for patient ID: 1', '2026-04-11 21:29:46', 20, '2026-04-11 21:29:46'),
(7, 'revenue', 'bill_payment', 174.00, 11, 'Bill payment received via cash', '2026-04-11 22:01:19', 1, '2026-04-11 22:01:19'),
(8, 'revenue', 'bill_payment', 174.00, 12, 'Payment processed by staff via Cash', '2026-04-11 22:02:45', 20, '2026-04-11 22:02:45'),
(9, 'revenue', 'bill_payment', 406.00, 15, 'Bill payment received via credit_card', '2026-04-12 00:56:10', 9, '2026-04-12 00:56:10'),
(10, 'revenue', 'bill_payment', 174.00, 14, 'Payment processed by staff via Cash', '2026-04-12 00:56:38', 20, '2026-04-12 00:56:38'),
(11, 'revenue', 'bill_payment', 406.00, 13, 'Payment processed by staff via Cash', '2026-04-12 00:57:05', 20, '2026-04-12 00:57:05'),
(12, 'expense', 'salary', 5000.00, 6, 'Salary payment - doctor - 2026-04', '2026-04-12 01:21:26', 10, '2026-04-12 01:21:26'),
(13, 'revenue', 'bill_payment', 316.68, 20, 'Bill payment received via credit_card', '2026-04-12 23:36:58', 35, '2026-04-12 23:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `testId` int(11) NOT NULL,
  `recordId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `testName` varchar(100) NOT NULL,
  `testType` varchar(50) DEFAULT NULL,
  `orderedBy` int(11) NOT NULL,
  `orderedDate` datetime DEFAULT current_timestamp(),
  `performedDate` datetime DEFAULT NULL,
  `results` text DEFAULT NULL,
  `referenceRange` varchar(100) DEFAULT NULL,
  `status` enum('ordered','in-progress','completed','cancelled') DEFAULT 'ordered',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`testId`, `recordId`, `patientId`, `testName`, `testType`, `orderedBy`, `orderedDate`, `performedDate`, `results`, `referenceRange`, `status`, `notes`) VALUES
(1, 12, 1, 'Complete Blood Count (CBC)', 'Urine Test', 7, '2026-04-10 21:47:23', '2026-04-10 21:47:50', 'Done', NULL, 'completed', ''),
(2, 12, 1, 'Lipid Profile', 'X-Ray', 11, '2026-04-10 21:48:29', '2026-04-11 21:52:09', '', NULL, 'completed', '');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `recordId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `doctorId` int(11) NOT NULL,
  `appointmentId` int(11) DEFAULT NULL,
  `creationDate` datetime DEFAULT current_timestamp(),
  `diagnosis` text DEFAULT NULL,
  `treatmentNotes` text DEFAULT NULL,
  `prescriptions` text DEFAULT NULL,
  `followUpDate` date DEFAULT NULL,
  `isConfidential` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`recordId`, `patientId`, `doctorId`, `appointmentId`, `creationDate`, `diagnosis`, `treatmentNotes`, `prescriptions`, `followUpDate`, `isConfidential`) VALUES
(1, 3, 2, 1, '2026-03-24 02:56:44', 'Test Diagnosis - 2026-03-23 16:56:44', 'Test treatment notes', 'Test medication: 500mg twice daily', NULL, 0),
(2, 1, 2, 4, '2026-03-24 03:07:58', 'Clear', '', '', '2026-03-23', 0),
(3, 1, 2, 1, '2026-03-24 03:29:38', 'Ok report', '', '', '2026-03-23', 0),
(4, 1, 5, 5, '2026-03-24 03:31:40', 'Crystal', '', '', '2026-03-23', 0),
(5, 1, 7, 6, '2026-03-24 17:04:27', 'ok', '', NULL, NULL, 0),
(6, 1, 7, 6, '2026-03-24 17:07:08', 'ok', '', NULL, '2026-03-24', 0),
(7, 1, 7, 6, '2026-03-24 17:09:25', 'ok', '', NULL, NULL, 0),
(8, 1, 7, 6, '2026-03-24 17:25:06', 'ok', '', NULL, NULL, 0),
(9, 1, 7, 6, '2026-03-24 17:34:44', 'Fina;', '', NULL, '2026-03-24', 0),
(10, 1, 7, 6, '2026-03-24 17:38:38', 'try', '', NULL, '2026-03-24', 0),
(11, 1, 7, 6, '2026-03-24 17:47:31', 'Final done', '', NULL, '2026-03-24', 0),
(12, 1, 7, 7, '2026-03-24 22:41:38', 'Checking bills', '', NULL, NULL, 0),
(14, 1, 11, NULL, '2026-04-10 22:44:52', 'Test', '', NULL, NULL, 0),
(15, 1, 11, NULL, '2026-04-11 22:02:06', 'Testing bills', '', NULL, NULL, 0),
(16, 3, 11, 11, '2026-04-12 00:36:08', 'Done clear', '', NULL, NULL, 0),
(17, 3, 11, 11, '2026-04-12 00:36:50', 'Done', '', NULL, NULL, 0),
(18, 3, 11, 11, '2026-04-12 00:51:54', 'Clear- testing', '', NULL, NULL, 0),
(19, 13, 6, 12, '2026-04-12 23:12:47', 'Test done', '', NULL, NULL, 0),
(20, 14, 11, NULL, '2026-04-12 23:30:58', 'sdfcav', 'zdv', NULL, '2026-04-26', 0),
(21, 14, 11, NULL, '2026-04-12 23:31:04', 'sdfcav', 'zdv', NULL, '2026-04-26', 0),
(22, 14, 11, 14, '2026-04-12 23:32:49', 'ugu', '', NULL, NULL, 0),
(23, 14, 11, 15, '2026-04-12 23:35:35', 'hjbj', '', NULL, '2026-04-13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `messageId` int(11) NOT NULL,
  `senderId` int(11) NOT NULL,
  `receiverId` int(11) NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `isRead` tinyint(1) DEFAULT 0,
  `isArchived` tinyint(1) DEFAULT 0,
  `createdAt` datetime DEFAULT current_timestamp(),
  `readAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notificationId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `type` enum('appointment','reminder','prescription','lab_result','system','message') NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `isRead` tinyint(1) DEFAULT 0,
  `sentDate` datetime DEFAULT current_timestamp(),
  `readDate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notificationId`, `userId`, `type`, `title`, `message`, `link`, `isRead`, `sentDate`, `readDate`) VALUES
(1, 11, 'appointment', 'New Appointment', 'Patient booked appointment for Mar 25, 2026 10:30 AM', NULL, 0, '2026-03-24 02:19:05', NULL),
(2, 1, 'appointment', 'Appointment Confirmed', 'Your appointment with Dr. John Smith is booked for Mar 25, 2026 10:30 AM', NULL, 1, '2026-03-24 02:19:05', '2026-04-10 21:30:04'),
(3, 13, 'appointment', 'New Appointment', 'Patient booked appointment for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:22:57', NULL),
(4, 1, 'appointment', 'Appointment Confirmed', 'Your appointment with Dr. Michael Williams is booked for Mar 24, 2026 11:00 AM', NULL, 1, '2026-03-24 02:22:57', '2026-04-10 21:30:04'),
(5, 13, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:26:11', NULL),
(6, 1, 'appointment', 'Appointment Booked', 'Your appointment with Dr. Michael Williams has been booked for Mar 24, 2026 11:00 AM', NULL, 1, '2026-03-24 02:26:11', '2026-04-10 21:30:04'),
(7, 13, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 02:28:48', NULL),
(8, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 1, '2026-03-24 02:28:48', '2026-04-10 21:30:04'),
(9, 1, '', 'Medical Record Added', 'Dr. John Smith added a new medical record on Mar 23, 2026.', NULL, 1, '2026-03-24 03:07:58', '2026-04-10 21:30:04'),
(10, 1, '', 'Medical Record Added', 'Dr. John Smith added a new medical record on Mar 23, 2026.', NULL, 1, '2026-03-24 03:29:38', '2026-04-10 21:30:04'),
(11, 14, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 03:30:23', NULL),
(12, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 1, '2026-03-24 03:30:23', '2026-04-10 21:30:04'),
(13, 1, '', 'Medical Record Added', 'Dr. Robert Brown added a new medical record on Mar 23, 2026.', NULL, 1, '2026-03-24 03:31:40', '2026-04-10 21:30:04'),
(14, 16, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 24, 2026 4:00 PM', NULL, 0, '2026-03-24 15:41:48', NULL),
(15, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 24, 2026 4:00 PM', NULL, 1, '2026-03-24 15:41:48', '2026-04-10 21:30:04'),
(16, 1, '', '', 'New bill generated for your consultation on 2026-03-24', 'view-bill.php?id=1', 1, '2026-03-24 17:04:27', '2026-04-10 21:30:04'),
(17, 1, '', '', 'New bill generated for your consultation on 2026-03-24', 'view-bill.php?id=3', 1, '2026-03-24 17:09:25', '2026-04-10 21:30:04'),
(18, 16, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 22:38:29', NULL),
(19, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 1, '2026-03-24 22:38:29', '2026-04-10 21:30:04'),
(20, 15, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 27, 2026 10:30 AM', NULL, 0, '2026-03-26 02:25:08', NULL),
(21, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 27, 2026 10:30 AM', NULL, 1, '2026-03-26 02:25:08', '2026-04-10 21:30:04'),
(22, 1, '', 'Payment Received', 'Payment of $220.40 received.', NULL, 1, '2026-04-10 20:11:44', '2026-04-10 21:30:04'),
(23, 1, '', 'Payment Confirmed', 'Your payment of $278.40 for Bill #000004 has been confirmed. Thank you!', '../patient/view-bill.php?bill_id=4', 1, '2026-04-10 20:44:08', '2026-04-10 21:30:04'),
(24, 1, '', 'Payment Confirmed', 'Your payment of $278.40 for Bill #000003 has been confirmed. Thank you!', '../patient/view-bill.php?bill_id=3', 1, '2026-04-10 20:44:19', '2026-04-10 21:30:04'),
(25, 1, '', 'Payment Confirmed', 'Your payment of $278.40 for Bill #000002 has been confirmed. Thank you!', '../patient/view-bill.php?bill_id=2', 1, '2026-04-10 20:44:28', '2026-04-10 21:30:04'),
(26, 1, '', 'Payment Confirmed', 'Your payment of $278.40 for Bill #000001 has been confirmed. Thank you!', '../patient/view-bill.php?bill_id=1', 1, '2026-04-10 20:44:35', '2026-04-10 21:30:04'),
(27, 1, 'appointment', 'Appointment Status Updated', 'Your appointment with Dr. Michael Williams on Mar 25, 2026 11:00 AM has been updated to: In-progress', NULL, 1, '2026-04-10 21:00:30', '2026-04-10 21:30:04'),
(28, 13, 'appointment', 'Appointment Status Updated', 'Appointment with patient Himal Karki on Mar 25, 2026 11:00 AM has been updated to: In-progress', NULL, 0, '2026-04-10 21:00:30', NULL),
(29, 1, '', 'Payment Received', 'Payment of $184.44 received.', NULL, 1, '2026-04-10 21:17:15', '2026-04-10 21:30:01'),
(30, 1, 'lab_result', 'Lab Test Ordered', 'A new lab test \'Complete Blood Count (CBC)\' has been ordered for you.', NULL, 0, '2026-04-10 21:47:23', NULL),
(31, 16, 'lab_result', 'Lab Test Ordered', 'A new lab test \'Complete Blood Count (CBC)\' has been ordered for patient Himal Karki.', NULL, 0, '2026-04-10 21:47:23', NULL),
(32, 1, 'lab_result', 'Lab Test Ordered', 'A new lab test \'Lipid Profile\' has been ordered for you.', NULL, 0, '2026-04-10 21:48:29', NULL),
(33, 25, 'lab_result', 'Lab Test Ordered', 'A new lab test \'Lipid Profile\' has been ordered for patient Himal Karki.', NULL, 1, '2026-04-10 21:48:29', '2026-04-10 22:02:36'),
(34, 1, 'lab_result', 'Lab Test Completed', 'Your lab test \'Lipid Profile\' has been completed with results available.', NULL, 0, '2026-04-10 22:00:22', NULL),
(35, 1, '', 'Payment Successful', 'Payment for Bill #000010 of $179.80 successful.', NULL, 0, '2026-04-10 22:46:41', NULL),
(37, 1, '', 'New Bill Generated', 'A new bill of $174.00 has been generated for your consultation.', '../patient/view-bill.php?bill_id=11', 0, '2026-04-11 21:29:46', NULL),
(38, 1, 'lab_result', 'Lab Test Completed', 'Your lab test \'Lipid Profile\' has been completed with results available.', NULL, 0, '2026-04-11 21:52:09', NULL),
(39, 1, '', 'Payment Successful', 'Your payment of $174.00 for Bill #000011 has been received.', NULL, 0, '2026-04-11 22:01:25', NULL),
(40, 1, '', 'New Bill Generated', 'A new bill of $174.00 has been generated for your consultation.', '../patient/view-bill.php?bill_id=12', 0, '2026-04-11 22:02:13', NULL),
(41, 1, '', 'Payment Received', 'Your payment of $174.00 has been received. Thank you!', NULL, 0, '2026-04-11 22:02:51', NULL),
(42, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 14, 2026 11:00 AM', NULL, 0, '2026-04-11 23:52:16', NULL),
(43, 13, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 14, 2026 11:00 AM', NULL, 0, '2026-04-11 23:52:16', NULL),
(44, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 18, 2026 11:30 AM by reception.', NULL, 0, '2026-04-11 23:59:45', NULL),
(45, 25, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 18, 2026 11:30 AM with patient: Himal Karki', NULL, 0, '2026-04-11 23:59:45', NULL),
(46, 25, 'appointment', 'Appointment Rescheduled', 'Appointment has been rescheduled from Apr 18, 2026 11:30 AM to Apr 30, 2026 1:30 PM', NULL, 0, '2026-04-12 00:13:37', NULL),
(47, 1, 'appointment', 'Appointment Rescheduled', 'Your appointment has been rescheduled to Apr 30, 2026 1:30 PM', NULL, 0, '2026-04-12 00:13:37', NULL),
(48, 9, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 14, 2026 2:30 PM by admin.', NULL, 0, '2026-04-12 00:27:30', NULL),
(49, 25, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 14, 2026 2:30 PM with patient: Himal Karki', NULL, 0, '2026-04-12 00:27:30', NULL),
(50, 1, 'appointment', 'Appointment Rescheduled', 'Your appointment has been rescheduled to May 8, 2026 9:30 AM by reception.', NULL, 0, '2026-04-12 00:30:10', NULL),
(51, 25, 'appointment', 'Appointment Rescheduled', 'Appointment with patient has been rescheduled to May 8, 2026 9:30 AM', NULL, 0, '2026-04-12 00:30:10', NULL),
(52, 1, 'appointment', 'Appointment Rescheduled', 'Your appointment has been rescheduled to Apr 17, 2026 9:30 AM by administration.', NULL, 0, '2026-04-12 00:31:53', NULL),
(53, 25, 'appointment', 'Appointment Rescheduled', 'Appointment with patient has been rescheduled to Apr 17, 2026 9:30 AM', NULL, 0, '2026-04-12 00:31:53', NULL),
(54, 9, '', 'New Bill Generated', 'A new bill of $406.00 has been generated for your consultation.', '../patient/view-bill.php?bill_id=13', 0, '2026-04-12 00:36:15', NULL),
(55, 9, '', 'New Bill Generated', 'A new bill of $174.00 has been generated for your consultation.', '../patient/view-bill.php?bill_id=14', 0, '2026-04-12 00:36:56', NULL),
(56, 9, '', 'New Bill Generated', 'A new bill of $406.00 has been generated for your consultation.', '../patient/view-bill.php?bill_id=15', 0, '2026-04-12 00:52:00', NULL),
(57, 9, 'appointment', 'Consultation Completed', 'Your consultation with Dr. Alok Karki has been completed. View your medical record and bill.', NULL, 0, '2026-04-12 00:52:00', NULL),
(58, 9, '', 'Payment Successful', 'Your payment of $406.00 for Bill #000015 has been received.', NULL, 0, '2026-04-12 00:56:16', NULL),
(59, 9, '', 'Payment Received', 'Your payment of $174.00 has been received. Thank you!', NULL, 0, '2026-04-12 00:56:45', NULL),
(60, 9, '', 'Payment Received', 'Your payment of $406.00 has been received. Thank you!', NULL, 0, '2026-04-12 00:57:11', NULL),
(61, 16, '', 'Salary Payment Received', 'Your salary of $5,000.00 for April 2026 has been processed.', NULL, 0, '2026-04-12 01:21:33', NULL),
(62, 34, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 13, 2026 11:00 AM by admin.', NULL, 0, '2026-04-12 23:10:58', NULL),
(63, 15, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 13, 2026 11:00 AM with patient: Test Patient', NULL, 0, '2026-04-12 23:10:58', NULL),
(64, 34, 'appointment', 'Appointment Rescheduled', 'Your appointment has been rescheduled to Apr 17, 2026 2:30 PM by administration.', NULL, 0, '2026-04-12 23:11:38', NULL),
(65, 15, 'appointment', 'Appointment Rescheduled', 'Appointment with patient has been rescheduled to Apr 17, 2026 2:30 PM', NULL, 0, '2026-04-12 23:11:38', NULL),
(66, 34, 'appointment', 'Appointment Rescheduled', 'Your appointment has been rescheduled to Apr 16, 2026 2:00 PM by reception.', NULL, 0, '2026-04-12 23:12:10', NULL),
(67, 15, 'appointment', 'Appointment Rescheduled', 'Appointment with patient has been rescheduled to Apr 16, 2026 2:00 PM', NULL, 0, '2026-04-12 23:12:10', NULL),
(68, 34, '', 'New Bill Generated', 'A new bill of $382.80 has been generated for your consultation.', '../patient/view-bill.php?bill_id=16', 0, '2026-04-12 23:12:54', NULL),
(69, 34, 'appointment', 'Consultation Completed', 'Your consultation with Dr. Emily Davis has been completed. View your medical record and bill.', NULL, 0, '2026-04-12 23:12:54', NULL),
(70, 34, '', 'Payment Confirmed', 'Your payment of $382.80 for Bill #000016 has been confirmed. Thank you!', '../patient/view-bill.php?bill_id=16', 0, '2026-04-12 23:13:30', NULL),
(71, 34, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 16, 2026 10:00 AM', NULL, 0, '2026-04-12 23:18:54', NULL),
(72, 15, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 16, 2026 10:00 AM', NULL, 0, '2026-04-12 23:18:54', NULL),
(73, 35, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 13, 2026 9:00 AM', NULL, 0, '2026-04-12 23:27:22', NULL),
(74, 25, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 13, 2026 9:00 AM', NULL, 1, '2026-04-12 23:27:22', '2026-04-12 23:28:44'),
(75, 35, '', 'New Bill Generated', 'A new bill of $187.92 has been generated for your consultation.', '../patient/view-bill.php?bill_id=17', 0, '2026-04-12 23:31:04', NULL),
(76, 35, '', 'New Bill Generated', 'A new bill of $187.92 has been generated for your consultation.', '../patient/view-bill.php?bill_id=18', 0, '2026-04-12 23:31:10', NULL),
(77, 35, '', 'New Bill Generated', 'A new bill of $189.08 has been generated for your consultation.', '../patient/view-bill.php?bill_id=19', 0, '2026-04-12 23:32:56', NULL),
(78, 35, 'appointment', 'Consultation Completed', 'Your consultation with Dr. Alok Karki has been completed. View your medical record and bill.', NULL, 0, '2026-04-12 23:32:56', NULL),
(79, 35, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Apr 13, 2026 10:00 AM', NULL, 0, '2026-04-12 23:33:53', NULL),
(80, 25, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Apr 13, 2026 10:00 AM', NULL, 0, '2026-04-12 23:33:53', NULL),
(81, 35, '', 'New Bill Generated', 'A new bill of $316.68 has been generated for your consultation.', '../patient/view-bill.php?bill_id=20', 0, '2026-04-12 23:35:41', NULL),
(82, 35, 'appointment', 'Consultation Completed', 'Your consultation with Dr. Alok Karki has been completed. View your medical record and bill.', NULL, 0, '2026-04-12 23:35:41', NULL),
(83, 35, '', 'Payment Successful', 'Your payment of $316.68 for Bill #000020 has been received.', NULL, 0, '2026-04-12 23:37:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `nurses`
--

CREATE TABLE `nurses` (
  `nurseId` int(11) NOT NULL,
  `staffId` int(11) NOT NULL,
  `nursingSpecialty` varchar(100) DEFAULT NULL,
  `certification` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nurses`
--

INSERT INTO `nurses` (`nurseId`, `staffId`, `nursingSpecialty`, `certification`) VALUES
(2, 16, 'Cardiac Care', 'CCRN, ACLS, BLS');

--
-- Triggers `nurses`
--
DELIMITER $$
CREATE TRIGGER `after_nurse_insert` AFTER INSERT ON `nurses` FOR EACH ROW BEGIN
    UPDATE users u
    JOIN staff s ON u.userId = s.userId
    SET u.role = 'nurse'
    WHERE s.staffId = NEW.staffId;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patientId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `dateOfBirth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergencyContactName` varchar(100) DEFAULT NULL,
  `emergencyContactPhone` varchar(20) DEFAULT NULL,
  `bloodType` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `knownAllergies` text DEFAULT NULL,
  `insuranceProvider` varchar(100) DEFAULT NULL,
  `insuranceNumber` varchar(50) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patientId`, `userId`, `dateOfBirth`, `address`, `emergencyContactName`, `emergencyContactPhone`, `bloodType`, `knownAllergies`, `insuranceProvider`, `insuranceNumber`, `createdAt`, `updatedAt`) VALUES
(1, 1, '2004-12-13', '1 Grazier Lane, Belconnen ACT 2617', 'None', 'None', 'A+', 'None', 'None', 'None', '2026-03-23 19:08:19', '2026-03-23 19:08:19'),
(3, 9, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-23 22:49:25', '2026-03-23 22:49:25'),
(5, 21, '1985-05-15', '123 Main St, Gungahlin', 'Jane Doe', '+61 4383473495', 'O+', 'Penicillin', NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(8, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 00:05:06', '2026-03-25 00:05:06'),
(9, 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-26 02:24:05', '2026-03-26 02:24:05'),
(11, 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-10 21:25:37', '2026-04-10 21:25:37'),
(12, 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-10 22:32:46', '2026-04-10 22:32:46'),
(13, 34, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-12 22:29:28', '2026-04-12 22:29:28'),
(14, 35, '2026-04-01', '', '', '', 'A+', 'vs', '', '', '2026-04-12 23:23:57', '2026-04-12 23:23:57');

-- --------------------------------------------------------

--
-- Table structure for table `patient_emergency_contacts`
--

CREATE TABLE `patient_emergency_contacts` (
  `contactId` int(11) NOT NULL,
  `patientId` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `isPrimary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `patient_summary`
-- (See below for the actual view)
--
CREATE TABLE `patient_summary` (
`userId` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`firstName` varchar(50)
,`lastName` varchar(50)
,`phoneNumber` varchar(20)
,`dateOfBirth` date
,`address` text
,`bloodType` enum('A+','A-','B+','B-','AB+','AB-','O+','O-')
,`knownAllergies` text
,`insuranceProvider` varchar(100)
,`totalAppointments` bigint(21)
,`totalRecords` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescriptionId` int(11) NOT NULL,
  `recordId` int(11) NOT NULL,
  `medicationName` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date DEFAULT NULL,
  `refills` int(11) DEFAULT 0,
  `instructions` text DEFAULT NULL,
  `status` enum('active','completed','cancelled','expired') DEFAULT 'active',
  `prescribedBy` int(11) NOT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`prescriptionId`, `recordId`, `medicationName`, `dosage`, `frequency`, `startDate`, `endDate`, `refills`, `instructions`, `status`, `prescribedBy`, `createdAt`, `updatedAt`) VALUES
(1, 6, 'Paracetamol', '', '', '2026-03-24', '0000-00-00', 0, '', 'active', 7, '2026-03-24 17:07:08', '2026-03-24 17:07:08'),
(2, 7, 'Paracetamol', '', '', '2026-03-24', NULL, 0, '', 'active', 7, '2026-03-24 17:09:25', '2026-03-24 17:09:25'),
(3, 8, 'Paracetamol', '', '', '0000-00-00', NULL, 0, '', 'active', 7, '2026-03-24 17:25:06', '2026-03-24 17:25:06'),
(4, 8, 'a', '', '', '0000-00-00', NULL, 0, '', 'active', 7, '2026-03-24 17:25:06', '2026-03-24 17:25:06'),
(5, 9, 'Paracetamol', '', '', '2026-03-24', NULL, 0, '', 'active', 7, '2026-03-24 17:34:44', '2026-03-24 17:34:44'),
(6, 10, 'Paracetamol', '', '', '2026-03-24', NULL, 0, '', 'active', 7, '2026-03-24 17:38:38', '2026-03-24 17:38:38'),
(7, 11, 'Paracetamol', '2 tablets', 'twice a day', '0000-00-00', NULL, 0, '', 'active', 7, '2026-03-24 17:47:31', '2026-03-24 17:47:31'),
(8, 11, 'Neurofen', '1 tablet', 'Once a day', '0000-00-00', NULL, 0, '', 'active', 7, '2026-03-24 17:47:31', '2026-03-24 17:47:31'),
(9, 12, 'Neurofen', '3 tablets', 'Thrice a day', '2026-04-24', NULL, 0, 'Take for a month regularly without missing', 'active', 7, '2026-03-24 22:41:38', '2026-03-24 22:41:38'),
(10, 14, 'D', '', '', '2026-04-10', NULL, 0, '', 'active', 11, '2026-04-10 22:44:52', '2026-04-10 22:44:52'),
(11, 16, 'Price', '', '', '2026-04-11', NULL, 0, '', 'active', 11, '2026-04-12 00:36:08', '2026-04-12 00:36:08'),
(12, 20, 'dfz', 'sfs', 'fdf', '2026-04-12', NULL, 0, 'fda', 'active', 11, '2026-04-12 23:30:58', '2026-04-12 23:30:58'),
(13, 20, 'mmm', '12', '5', '2026-04-12', NULL, 0, 'ntg', 'active', 11, '2026-04-12 23:30:58', '2026-04-12 23:30:58'),
(14, 21, 'dfz', 'sfs', 'fdf', '2026-04-12', NULL, 0, 'fda', 'active', 11, '2026-04-12 23:31:04', '2026-04-12 23:31:04'),
(15, 21, 'mmm', '12', '5', '2026-04-12', NULL, 0, 'ntg', 'active', 11, '2026-04-12 23:31:04', '2026-04-12 23:31:04'),
(16, 22, 'hbh', '', '', '2026-04-12', NULL, 0, '', 'active', 11, '2026-04-12 23:32:49', '2026-04-12 23:32:49'),
(17, 23, 'm', 'k', 'm', '2026-04-12', NULL, 0, '', 'active', 11, '2026-04-12 23:35:35', '2026-04-12 23:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `salary_payments`
--

CREATE TABLE `salary_payments` (
  `salaryId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `staffId` int(11) DEFAULT NULL,
  `role` enum('doctor','nurse','staff','accountant','admin') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paymentDate` datetime NOT NULL,
  `salaryMonth` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `paidBy` int(11) NOT NULL COMMENT 'User ID who processed payment',
  `notes` text DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'paid',
  `createdAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_payments`
--

INSERT INTO `salary_payments` (`salaryId`, `userId`, `staffId`, `role`, `amount`, `paymentDate`, `salaryMonth`, `paidBy`, `notes`, `status`, `createdAt`) VALUES
(1, 20, 17, 'staff', 2500.00, '2026-04-11 14:21:42', '2026-04', 10, '', 'paid', '2026-04-11 14:21:42'),
(2, 25, 19, 'doctor', 5000.00, '2026-04-11 14:22:03', '2026-04', 10, '', 'paid', '2026-04-11 14:22:03'),
(3, 19, 16, 'nurse', 3500.00, '2026-04-11 15:27:51', '2026-04', 32, '', 'paid', '2026-04-11 15:27:51'),
(4, 32, 27, 'accountant', 4000.00, '2026-04-11 15:28:28', '2026-04', 32, '', 'paid', '2026-04-11 15:28:28'),
(5, 33, 28, 'doctor', 5000.00, '2026-04-11 15:52:47', '2026-04', 32, '', 'paid', '2026-04-11 15:52:47'),
(6, 16, 13, 'doctor', 5000.00, '2026-04-12 01:21:26', '2026-04', 10, '', 'paid', '2026-04-12 01:21:26');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staffId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `licenseNumber` varchar(50) DEFAULT NULL,
  `hireDate` date DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staffId`, `userId`, `licenseNumber`, `hireDate`, `department`, `position`, `createdAt`, `updatedAt`) VALUES
(7, 10, 'ADMIN001', '2026-03-24', 'Administration', 'System Administrator', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(8, 11, 'MED12345', '2026-03-24', 'Cardiology', 'Senior Cardiologist', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(9, 12, 'MED12346', '2026-03-24', 'Neurology', 'Senior Neurologist', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(10, 13, 'MED12347', '2026-03-24', 'Pediatrics', 'Pediatrician', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(11, 14, 'MED12348', '2026-03-24', 'Orthopedics', 'Orthopedic Surgeon', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(12, 15, 'MED12349', '2026-03-24', 'Dermatology', 'Dermatologist', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(13, 16, 'MED12350', '2026-03-24', 'Ophthalmology', 'Ophthalmologist', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(14, 17, 'MED12351', '2026-03-24', 'Obstetrics & Gynecology', 'OB/GYN', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(15, 18, 'MED12352', '2026-03-24', 'Radiology', 'Radiologist', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(16, 19, 'NURSE1234', '2026-03-24', 'Cardiology', 'Registered Nurse', '2026-03-24 01:09:55', '2026-04-10 21:34:29'),
(17, 20, 'RECEPTION01', '2026-03-24', 'Front Desk', 'Receptionist', '2026-03-24 01:09:55', '2026-04-11 14:10:07'),
(19, 25, 'LR1212', '2026-04-10', '', 'Staff', '2026-04-10 21:09:35', '2026-04-10 21:09:35'),
(27, 32, '', '2026-04-11', 'Finance', 'Nurse', '2026-04-11 15:26:58', '2026-04-11 15:26:58');

--
-- Triggers `staff`
--
DELIMITER $$
CREATE TRIGGER `after_staff_insert` AFTER INSERT ON `staff` FOR EACH ROW BEGIN
    UPDATE users SET role = 'staff' WHERE userId = NEW.userId;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_salary_config`
--

CREATE TABLE `staff_salary_config` (
  `configId` int(11) NOT NULL,
  `staffId` int(11) NOT NULL,
  `baseSalary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `effectiveFrom` date NOT NULL,
  `effectiveTo` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `settingId` int(11) NOT NULL,
  `settingKey` varchar(100) NOT NULL,
  `settingValue` text DEFAULT NULL,
  `settingType` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`settingId`, `settingKey`, `settingValue`, `settingType`, `description`, `updatedAt`) VALUES
(1, 'site_name', 'HealthManagement', 'string', 'Name of the website', '2026-03-23 09:06:37'),
(2, 'site_url', 'http://localhost', 'string', 'Base URL of the website', '2026-03-23 09:06:37'),
(3, 'admin_email', 'admin@healthmanagement.com', 'string', 'Administrator email address', '2026-03-23 09:06:37'),
(4, 'appointment_duration', '30', 'integer', 'Default appointment duration in minutes', '2026-03-23 09:06:37'),
(5, 'max_appointments_per_day', '10', 'integer', 'Maximum appointments per doctor per day', '2026-03-23 09:06:37'),
(6, 'verification_required', 'true', 'boolean', 'Require email verification for new users', '2026-03-23 09:06:37'),
(7, 'maintenance_mode', 'false', 'boolean', 'Put site in maintenance mode', '2026-03-23 09:06:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `firstName` varchar(50) NOT NULL,
  `lastName` varchar(50) NOT NULL,
  `phoneNumber` varchar(20) DEFAULT NULL,
  `role` enum('patient','staff','nurse','doctor','admin','accountant') DEFAULT 'patient',
  `isVerified` tinyint(1) DEFAULT 0,
  `verificationCode` varchar(64) DEFAULT NULL,
  `resetToken` varchar(64) DEFAULT NULL,
  `resetExpiry` datetime DEFAULT NULL,
  `dateCreated` datetime DEFAULT current_timestamp(),
  `lastLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userId`, `username`, `passwordHash`, `email`, `firstName`, `lastName`, `phoneNumber`, `role`, `isVerified`, `verificationCode`, `resetToken`, `resetExpiry`, `dateCreated`, `lastLogin`) VALUES
(1, 'himal', '$2y$10$PpZS0kKYVCk3jjIMKtbyF.GjsuPEZhhrU1RewaMdeAvIv8kEe8Wte', 'himalkumarkari@gmail.com', 'Himal', 'Karki', '0450595809', 'patient', 1, NULL, 'a98f684b03ebf34b91b57d5f70a86d84bd2ffe3fe8d5002d9812c695dbb52d60', '2026-04-09 16:39:54', '2026-03-23 19:08:19', '2026-04-13 00:12:57'),
(9, 'himall', '$2y$10$WXkqBI4H4UENxDZuG4yJAOmfXwB6GVbgDm3wni3NhSc/566Iclssi', 'abinashcarkee@gmail.com', 'Himal', 'Karki', '0450595809', 'patient', 1, NULL, NULL, NULL, '2026-03-23 22:49:25', '2026-04-12 00:55:56'),
(10, 'admin', '$2y$10$e4EcffhKENf3COqn5.Px9.aHimhV8p3o3Fg/f65qcMy3TcmVEPl26', 'admin@healthmanagement.com', 'Admin', 'User', '+61 4383473483', 'admin', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-12 22:02:15'),
(11, 'dr.smith', '$2y$10$raHKHkD8iZRhJic5Mf3m9OqRABxxV98Hgssecb/KP8qjxEYdtCg5a', 'dr.smith@healthmanagement.com', 'John', 'Smith', '+61 4383473484', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-09 23:32:30'),
(12, 'dr.johnson', '$2y$10$53iQ0yl6u/5qAuwo.ZArOO7teGCXyirG8Rya9pNtNfuotGRpp9EKu', 'dr.johnson@healthmanagement.com', 'Sarah', 'Johnson', '+61 4383473485', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 02:41:21'),
(13, 'dr.williams', '$2y$10$zSR8ovL/BMNiHZgo8zmqiuahijVTYefy.zkUZx7M.b/YVNH05faQi', 'dr.williams@healthmanagement.com', 'Michael', 'Williams', '+61 4383473486', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-11 23:53:15'),
(14, 'dr.brown', '$2y$10$sFL54NSVUWgln8IxT//3ke.UolE9qb7.Mru/TlWB3Kx4uXkLdmWnG', 'dr.brown@healthmanagement.com', 'Robert', 'Brown', '+61 4383473487', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 03:31:16'),
(15, 'dr.davis', '$2y$10$OtW09Zp.GsEfLLCPbQyxJuF.HsmQW2c/Zl3yfcA62yK6y0HuAoJpq', 'dr.davis@healthmanagement.com', 'Emily', 'Davis', '+61 4383473488', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-12 23:10:00'),
(16, 'dr.wilson', '$2y$10$ptse6rilvKUHCgTfBHjoMegyxOzLxdHNYGOoHNWZY/8CxZxCPWEna', 'dr.wilson@healthmanagement.com', 'David', 'Wilson', '+61 4383473489', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-09 23:31:32'),
(17, 'dr.martinez', '$2y$10$KF8DH1LeTyXmJranH4zeTuOqlguWBx8jrzSrajKXStLLRVVScGhWC', 'dr.martinez@healthmanagement.com', 'Lisa', 'Martinez', '+61 4383473490', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:47:52'),
(18, 'dr.taylor', '$2y$10$y5X4uvo96lDxQHTRNQ4A0ONMfWkHy0WhYMen85v6LM63rUPo3zAjK', 'dr.taylor@healthmanagement.com', 'James', 'Taylor', '+61 4383473491', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:47:23'),
(19, 'nurse.jane', '$2y$10$lsWjbWdkTToR4p6DfS9IxuR1e2Sq2Y3lruEkURfCO8PVULMk9rt32', 'nurse.jane@healthmanagement.com', 'Jane', 'Doe', '+61 4383473492', 'nurse', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-11 20:28:50'),
(20, 'reception', '$2y$10$D.kDWGvSanIqlLEgYb6/POTzzczoQ718Z0HkCDW.iulzJspixZ2Ui', 'reception@healthmanagement.com', 'Sarah', 'Wilson', '+61 4383473493', 'staff', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-04-12 22:26:33'),
(21, 'john.doe', '$2y$10$N.Q6LPYcXTaL3d6XuNMeGuZ3kugvc2BEDY7DAUTkQzuY5XfUf.lT6', 'john.doe@email.com', 'John', 'Doe', '+61 4383473494', 'patient', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', NULL),
(25, 'alok', '$2y$10$e72sWl/vZuvDQx1IMAfPJ.01fcIsZQ23VFOmLaATMgZvwqSaQOPnK', 'munalchaudhary@gmail.com', 'Alok', 'Karki', '0450595809', 'doctor', 1, NULL, NULL, NULL, '2026-04-10 21:09:35', '2026-04-12 23:28:27'),
(32, 'abishek', '$2y$10$WcNU4te81A7WYQkg4G8ATOyxvsFAsONKNPK1eJTXaBDCiC3EqPcvq', 'abhisheklamakarki@gmail.com', 'Abishek', 'Karki', '04223', 'accountant', 1, NULL, NULL, NULL, '2026-04-11 15:26:58', '2026-04-12 23:39:27'),
(34, 'test', '$2y$10$Wh1cnjcM7VIf7ja9mmLK1.YeV9hrc0l2lf6/dDwveNiAyyo.mhZfW', 'cihe240209@student.cihe.edu.au', 'Test', 'Patient', '3434', 'patient', 1, NULL, NULL, NULL, '2026-04-12 22:29:28', '2026-04-12 23:08:34'),
(35, 'Akatuwal', '$2y$10$sRXvsDYWgvfI6U5iS.86SeCorlJ0q0RTl1TbP4WiLabxuUKySjaYW', 'amulyakatuwal@gmail.com', 'amulya', 'katuwal', '0450378010', 'patient', 1, NULL, NULL, NULL, '2026-04-12 23:23:57', '2026-04-12 23:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `vitals`
--

CREATE TABLE `vitals` (
  `vitalsId` int(11) NOT NULL,
  `recordId` int(11) NOT NULL,
  `recordedDate` datetime DEFAULT current_timestamp(),
  `height` decimal(5,2) DEFAULT NULL COMMENT 'Height in cm',
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Weight in kg',
  `bodyTemperature` decimal(4,1) DEFAULT NULL COMMENT 'Body temperature in Celsius',
  `bloodPressureSystolic` int(11) DEFAULT NULL COMMENT 'Systolic blood pressure',
  `bloodPressureDiastolic` int(11) DEFAULT NULL COMMENT 'Diastolic blood pressure',
  `heartRate` int(11) DEFAULT NULL COMMENT 'Heart rate in bpm',
  `respiratoryRate` int(11) DEFAULT NULL COMMENT 'Respiratory rate in breaths/min',
  `oxygenSaturation` int(11) DEFAULT NULL COMMENT 'SpO2 percentage',
  `notes` text DEFAULT NULL,
  `recordedBy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vitals`
--

INSERT INTO `vitals` (`vitalsId`, `recordId`, `recordedDate`, `height`, `weight`, `bodyTemperature`, `bloodPressureSystolic`, `bloodPressureDiastolic`, `heartRate`, `respiratoryRate`, `oxygenSaturation`, `notes`, `recordedBy`) VALUES
(1, 12, '2026-03-26 02:25:53', 178.00, 63.00, 36.0, 120, NULL, 120, NULL, NULL, '', 16),
(2, 12, '2026-04-10 21:30:56', 175.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', 16),
(3, 14, '2026-04-11 20:52:28', 174.00, 67.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 16),
(4, 14, '2026-04-11 20:53:44', 174.00, 66.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 16);

-- --------------------------------------------------------

--
-- Structure for view `active_appointments`
--
DROP TABLE IF EXISTS `active_appointments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_appointments`  AS SELECT `a`.`appointmentId` AS `appointmentId`, `a`.`dateTime` AS `dateTime`, `a`.`status` AS `status`, `a`.`reason` AS `reason`, concat(`pu`.`firstName`,' ',`pu`.`lastName`) AS `patientName`, concat(`du`.`firstName`,' ',`du`.`lastName`) AS `doctorName`, `d`.`specialization` AS `specialization`, `a`.`createdAt` AS `createdAt` FROM (((((`appointments` `a` join `patients` `p` on(`a`.`patientId` = `p`.`patientId`)) join `users` `pu` on(`p`.`userId` = `pu`.`userId`)) join `doctors` `d` on(`a`.`doctorId` = `d`.`doctorId`)) join `staff` `s` on(`d`.`staffId` = `s`.`staffId`)) join `users` `du` on(`s`.`userId` = `du`.`userId`)) WHERE `a`.`status` in ('scheduled','confirmed','in-progress') ;

-- --------------------------------------------------------

--
-- Structure for view `doctor_schedule`
--
DROP TABLE IF EXISTS `doctor_schedule`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `doctor_schedule`  AS SELECT `d`.`doctorId` AS `doctorId`, concat(`u`.`firstName`,' ',`u`.`lastName`) AS `doctorName`, `d`.`specialization` AS `specialization`, `a`.`appointmentId` AS `appointmentId`, `a`.`dateTime` AS `dateTime`, `a`.`duration` AS `duration`, `a`.`status` AS `status`, concat(`pu`.`firstName`,' ',`pu`.`lastName`) AS `patientName` FROM (((((`doctors` `d` join `staff` `s` on(`d`.`staffId` = `s`.`staffId`)) join `users` `u` on(`s`.`userId` = `u`.`userId`)) left join `appointments` `a` on(`d`.`doctorId` = `a`.`doctorId`)) left join `patients` `p` on(`a`.`patientId` = `p`.`patientId`)) left join `users` `pu` on(`p`.`userId` = `pu`.`userId`)) ;

-- --------------------------------------------------------

--
-- Structure for view `patient_summary`
--
DROP TABLE IF EXISTS `patient_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `patient_summary`  AS SELECT `u`.`userId` AS `userId`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`firstName` AS `firstName`, `u`.`lastName` AS `lastName`, `u`.`phoneNumber` AS `phoneNumber`, `p`.`dateOfBirth` AS `dateOfBirth`, `p`.`address` AS `address`, `p`.`bloodType` AS `bloodType`, `p`.`knownAllergies` AS `knownAllergies`, `p`.`insuranceProvider` AS `insuranceProvider`, (select count(0) from `appointments` `a` where `a`.`patientId` = `p`.`patientId`) AS `totalAppointments`, (select count(0) from `medical_records` `mr` where `mr`.`patientId` = `p`.`patientId`) AS `totalRecords` FROM (`users` `u` join `patients` `p` on(`u`.`userId` = `p`.`userId`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accountants`
--
ALTER TABLE `accountants`
  ADD PRIMARY KEY (`accountantId`),
  ADD UNIQUE KEY `staffId` (`staffId`);

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`adminId`),
  ADD UNIQUE KEY `staffId` (`staffId`),
  ADD KEY `idx_staffId` (`staffId`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointmentId`),
  ADD KEY `idx_patientId` (`patientId`),
  ADD KEY `idx_doctorId` (`doctorId`),
  ADD KEY `idx_dateTime` (`dateTime`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_patient_status` (`patientId`,`status`),
  ADD KEY `idx_doctor_datetime` (`doctorId`,`dateTime`),
  ADD KEY `idx_appointments_status_datetime` (`status`,`dateTime`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`logId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_audit_log_user_date` (`userId`,`timestamp`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`billId`),
  ADD KEY `appointmentId` (`appointmentId`),
  ADD KEY `idx_patientId` (`patientId`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dueDate` (`dueDate`),
  ADD KEY `idx_billing_patient_status` (`patientId`,`status`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`billId`),
  ADD KEY `recordId` (`recordId`),
  ADD KEY `idx_patient_bill` (`patientId`),
  ADD KEY `idx_appointment` (`appointmentId`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `bill_charges`
--
ALTER TABLE `bill_charges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bill` (`billId`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`departmentId`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `headDoctorId` (`headDoctorId`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctorId`),
  ADD UNIQUE KEY `staffId` (`staffId`),
  ADD KEY `idx_staffId` (`staffId`),
  ADD KEY `idx_specialization` (`specialization`),
  ADD KEY `idx_availability` (`isAvailable`);

--
-- Indexes for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD PRIMARY KEY (`availabilityId`),
  ADD UNIQUE KEY `unique_doctor_date` (`doctorId`,`availabilityDate`),
  ADD KEY `idx_doctor_date` (`doctorId`,`availabilityDate`);

--
-- Indexes for table `doctor_department`
--
ALTER TABLE `doctor_department`
  ADD PRIMARY KEY (`doctorId`,`departmentId`),
  ADD KEY `departmentId` (`departmentId`);

--
-- Indexes for table `hospital_finance`
--
ALTER TABLE `hospital_finance`
  ADD PRIMARY KEY (`financeId`),
  ADD KEY `idx_transactionType` (`transactionType`),
  ADD KEY `idx_transactionDate` (`transactionDate`),
  ADD KEY `idx_referenceId` (`referenceId`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`testId`),
  ADD KEY `orderedBy` (`orderedBy`),
  ADD KEY `idx_recordId` (`recordId`),
  ADD KEY `idx_patientId` (`patientId`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`recordId`),
  ADD KEY `appointmentId` (`appointmentId`),
  ADD KEY `idx_patientId` (`patientId`),
  ADD KEY `idx_doctorId` (`doctorId`),
  ADD KEY `idx_creationDate` (`creationDate`),
  ADD KEY `idx_patient_date` (`patientId`,`creationDate`),
  ADD KEY `idx_medical_records_patient_date` (`patientId`,`creationDate`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`messageId`),
  ADD KEY `idx_senderId` (`senderId`),
  ADD KEY `idx_receiverId` (`receiverId`),
  ADD KEY `idx_isRead` (`isRead`),
  ADD KEY `idx_createdAt` (`createdAt`),
  ADD KEY `idx_messages_receiver_read` (`receiverId`,`isRead`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notificationId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_isRead` (`isRead`),
  ADD KEY `idx_sentDate` (`sentDate`),
  ADD KEY `idx_notifications_user_read` (`userId`,`isRead`);

--
-- Indexes for table `nurses`
--
ALTER TABLE `nurses`
  ADD PRIMARY KEY (`nurseId`),
  ADD UNIQUE KEY `staffId` (`staffId`),
  ADD KEY `idx_staffId` (`staffId`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patientId`),
  ADD UNIQUE KEY `userId` (`userId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_bloodType` (`bloodType`);

--
-- Indexes for table `patient_emergency_contacts`
--
ALTER TABLE `patient_emergency_contacts`
  ADD PRIMARY KEY (`contactId`),
  ADD KEY `idx_patientId` (`patientId`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescriptionId`),
  ADD KEY `prescribedBy` (`prescribedBy`),
  ADD KEY `idx_recordId` (`recordId`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_medication` (`medicationName`);

--
-- Indexes for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`salaryId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_salaryMonth` (`salaryMonth`),
  ADD KEY `idx_paidBy` (`paidBy`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staffId`),
  ADD UNIQUE KEY `userId` (`userId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_licenseNumber` (`licenseNumber`);

--
-- Indexes for table `staff_salary_config`
--
ALTER TABLE `staff_salary_config`
  ADD PRIMARY KEY (`configId`),
  ADD KEY `idx_staffId` (`staffId`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`settingId`),
  ADD UNIQUE KEY `settingKey` (`settingKey`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_verification` (`verificationCode`),
  ADD KEY `idx_reset_token` (`resetToken`);

--
-- Indexes for table `vitals`
--
ALTER TABLE `vitals`
  ADD PRIMARY KEY (`vitalsId`),
  ADD KEY `recordedBy` (`recordedBy`),
  ADD KEY `idx_recordId` (`recordId`),
  ADD KEY `idx_recordedDate` (`recordedDate`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accountants`
--
ALTER TABLE `accountants`
  MODIFY `accountantId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `adminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointmentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `logId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=391;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `billId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `bill_charges`
--
ALTER TABLE `bill_charges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `departmentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctorId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  MODIFY `availabilityId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT for table `hospital_finance`
--
ALTER TABLE `hospital_finance`
  MODIFY `financeId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `testId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `recordId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `messageId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notificationId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `nurses`
--
ALTER TABLE `nurses`
  MODIFY `nurseId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patientId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `patient_emergency_contacts`
--
ALTER TABLE `patient_emergency_contacts`
  MODIFY `contactId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescriptionId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `salaryId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staffId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `staff_salary_config`
--
ALTER TABLE `staff_salary_config`
  MODIFY `configId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `settingId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `vitals`
--
ALTER TABLE `vitals`
  MODIFY `vitalsId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accountants`
--
ALTER TABLE `accountants`
  ADD CONSTRAINT `accountants_ibfk_1` FOREIGN KEY (`staffId`) REFERENCES `staff` (`staffId`) ON DELETE CASCADE;

--
-- Constraints for table `administrators`
--
ALTER TABLE `administrators`
  ADD CONSTRAINT `administrators_ibfk_1` FOREIGN KEY (`staffId`) REFERENCES `staff` (`staffId`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctorId`) REFERENCES `doctors` (`doctorId`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE SET NULL;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`),
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`appointmentId`) REFERENCES `appointments` (`appointmentId`) ON DELETE SET NULL;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`),
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`recordId`) REFERENCES `medical_records` (`recordId`) ON DELETE SET NULL;

--
-- Constraints for table `bill_charges`
--
ALTER TABLE `bill_charges`
  ADD CONSTRAINT `bill_charges_ibfk_1` FOREIGN KEY (`billId`) REFERENCES `bills` (`billId`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`headDoctorId`) REFERENCES `doctors` (`doctorId`) ON DELETE SET NULL;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`staffId`) REFERENCES `staff` (`staffId`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  ADD CONSTRAINT `doctor_availability_ibfk_1` FOREIGN KEY (`doctorId`) REFERENCES `doctors` (`doctorId`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_department`
--
ALTER TABLE `doctor_department`
  ADD CONSTRAINT `doctor_department_ibfk_1` FOREIGN KEY (`doctorId`) REFERENCES `doctors` (`doctorId`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_department_ibfk_2` FOREIGN KEY (`departmentId`) REFERENCES `departments` (`departmentId`) ON DELETE CASCADE;

--
-- Constraints for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`recordId`) REFERENCES `medical_records` (`recordId`) ON DELETE CASCADE,
  ADD CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`),
  ADD CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`orderedBy`) REFERENCES `doctors` (`doctorId`);

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_mr_appointment` FOREIGN KEY (`appointmentId`) REFERENCES `appointments` (`appointmentId`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mr_doctor` FOREIGN KEY (`doctorId`) REFERENCES `doctors` (`doctorId`),
  ADD CONSTRAINT `fk_mr_patient` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`),
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctorId`) REFERENCES `doctors` (`doctorId`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`appointmentId`) REFERENCES `appointments` (`appointmentId`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`senderId`) REFERENCES `users` (`userId`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiverId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;

--
-- Constraints for table `nurses`
--
ALTER TABLE `nurses`
  ADD CONSTRAINT `nurses_ibfk_1` FOREIGN KEY (`staffId`) REFERENCES `staff` (`staffId`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;

--
-- Constraints for table `patient_emergency_contacts`
--
ALTER TABLE `patient_emergency_contacts`
  ADD CONSTRAINT `patient_emergency_contacts_ibfk_1` FOREIGN KEY (`patientId`) REFERENCES `patients` (`patientId`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`recordId`) REFERENCES `medical_records` (`recordId`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`prescribedBy`) REFERENCES `doctors` (`doctorId`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`userId`) ON DELETE CASCADE;

--
-- Constraints for table `vitals`
--
ALTER TABLE `vitals`
  ADD CONSTRAINT `vitals_ibfk_1` FOREIGN KEY (`recordId`) REFERENCES `medical_records` (`recordId`) ON DELETE CASCADE,
  ADD CONSTRAINT `vitals_ibfk_2` FOREIGN KEY (`recordedBy`) REFERENCES `staff` (`staffId`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
