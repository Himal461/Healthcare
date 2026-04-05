-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 02:28 PM
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
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointmentId`, `patientId`, `doctorId`, `dateTime`, `duration`, `status`, `reason`, `notes`, `cancellationReason`, `createdAt`, `updatedAt`) VALUES
(1, 1, 2, '2026-03-25 10:30:00', 30, 'completed', '', '\nConsultation completed on 2026-03-24 03:29:38 | Diagnosis: Ok report', NULL, '2026-03-24 02:19:05', '2026-03-24 03:29:38'),
(2, 1, 4, '2026-03-24 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 02:22:57', '2026-03-24 02:25:53'),
(3, 1, 4, '2026-03-24 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 02:26:11', '2026-03-24 02:28:34'),
(4, 1, 4, '2026-03-25 11:00:00', 30, 'completed', '', '\nConsultation completed on 2026-03-24 03:07:58 | Diagnosis: Clear', NULL, '2026-03-24 02:28:48', '2026-03-24 03:07:58'),
(5, 1, 5, '2026-03-25 11:00:00', 30, 'completed', '', '\nConsultation completed on 2026-03-24 03:31:40 | Diagnosis: Crystal', NULL, '2026-03-24 03:30:23', '2026-03-24 03:31:40'),
(6, 1, 7, '2026-03-24 16:00:00', 30, 'completed', '', NULL, NULL, '2026-03-24 15:41:48', '2026-03-24 17:09:25'),
(7, 1, 7, '2026-03-25 11:00:00', 30, 'cancelled', '', NULL, 'Cancelled by patient', '2026-03-24 22:38:29', '2026-03-24 22:48:17');

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
(67, 23, '2026-03-24 01:20:19', 'EMAIL_VERIFY', 'User verified their email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
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
(158, 1, '2026-03-26 00:19:33', 'LOGIN', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');

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
(1, 1, 6, 5, 190.00, 50.00, 7.20, 31.20, 278.40, 'unpaid', '2026-03-24 17:04:27', NULL),
(2, 1, 6, 6, 190.00, 50.00, 7.20, 31.20, 278.40, 'unpaid', '2026-03-24 17:07:08', NULL),
(3, 1, 6, 7, 190.00, 50.00, 7.20, 31.20, 278.40, 'unpaid', '2026-03-24 17:09:25', NULL),
(4, 1, 6, 8, 190.00, 50.00, 7.20, 31.20, 278.40, 'unpaid', '2026-03-24 17:25:06', NULL),
(5, 1, 6, 9, 190.00, 0.00, 5.70, 24.70, 220.40, 'unpaid', '2026-03-24 17:34:44', NULL),
(6, 1, 6, 10, 190.00, 60.00, 7.50, 32.50, 290.00, 'paid', '2026-03-24 17:38:38', '2026-03-24 22:47:46'),
(7, 1, 6, 11, 190.00, 40.00, 6.90, 29.90, 266.80, 'paid', '2026-03-24 17:47:31', '2026-03-24 22:47:35'),
(8, 1, 7, 12, 190.00, 80.00, 8.10, 35.10, 313.20, 'paid', '2026-03-24 22:41:38', '2026-03-24 23:45:46');

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
(8, 8, 'BP Check', 30.00);

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
(1, 'Cardiology', 'Heart and cardiovascular system care', NULL, 'Building A, Floor 3', NULL, NULL, 1),
(2, 'Neurology', 'Brain and nervous system care', NULL, 'Building A, Floor 4', NULL, NULL, 1),
(3, 'Pediatrics', 'Child healthcare services', NULL, 'Building B, Floor 1', NULL, NULL, 1),
(4, 'Orthopedics', 'Bone and joint care', NULL, 'Building B, Floor 2', NULL, NULL, 1),
(5, 'Emergency Medicine', 'Emergency care services', NULL, 'Building C, Floor 1', NULL, NULL, 1),
(6, 'Radiology', 'Medical imaging services', NULL, 'Building C, Floor 2', NULL, NULL, 1),
(7, 'Dermatology', 'Skin care services', NULL, 'Building D, Floor 1', NULL, NULL, 1),
(8, 'Ophthalmology', 'Eye care services', NULL, 'Building D, Floor 2', NULL, NULL, 1);

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
(9, 15, 'Radiology', 210.00, 9, 'MD, DABR', 'Dr. James Taylor specializes in diagnostic imaging including MRI, CT scans, X-rays, and ultrasound interpretation.', 1);

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
  `dayOfWeek` int(11) NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `isAvailable` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_availability`
--

INSERT INTO `doctor_availability` (`availabilityId`, `doctorId`, `dayOfWeek`, `startTime`, `endTime`, `isAvailable`, `created_at`, `updated_at`) VALUES
(1, 1, 0, '00:00:00', '00:00:00', 0, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(2, 1, 1, '09:00:00', '17:00:00', 1, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(3, 1, 2, '09:00:00', '17:00:00', 1, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(4, 1, 3, '09:00:00', '17:00:00', 1, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(5, 1, 4, '09:00:00', '17:00:00', 1, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(6, 1, 5, '09:00:00', '17:00:00', 1, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(7, 1, 6, '00:00:00', '00:00:00', 0, '2026-03-23 22:47:47', '2026-03-23 22:47:47'),
(9, 1, 0, '00:00:00', '00:00:00', 0, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(10, 1, 1, '09:00:00', '17:00:00', 1, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(11, 1, 2, '09:00:00', '17:00:00', 1, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(12, 1, 3, '09:00:00', '17:00:00', 1, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(13, 1, 4, '00:00:00', '00:00:00', 0, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(14, 1, 5, '09:00:00', '17:00:00', 1, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(15, 1, 6, '00:00:00', '00:00:00', 0, '2026-03-23 22:48:13', '2026-03-23 22:48:13'),
(17, 1, 0, '09:00:00', '17:00:00', 1, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(18, 1, 1, '09:00:00', '17:00:00', 1, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(19, 1, 2, '09:00:00', '17:00:00', 1, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(20, 1, 3, '09:00:00', '17:00:00', 1, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(21, 1, 4, '00:00:00', '00:00:00', 0, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(22, 1, 5, '09:00:00', '17:00:00', 1, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(23, 1, 6, '00:00:00', '00:00:00', 0, '2026-03-24 00:26:01', '2026-03-24 00:26:01'),
(24, 6, 0, '00:00:00', '00:00:00', 0, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(25, 6, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(26, 6, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(27, 6, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(28, 6, 4, '09:00:00', '17:00:00', 1, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(29, 6, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(30, 6, 6, '00:00:00', '00:00:00', 0, '2026-03-24 01:46:53', '2026-03-24 01:46:53'),
(31, 9, 0, '00:00:00', '00:00:00', 0, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(32, 9, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(33, 9, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(34, 9, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(35, 9, 4, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(36, 9, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(37, 9, 6, '00:00:00', '00:00:00', 0, '2026-03-24 01:47:32', '2026-03-24 01:47:32'),
(38, 8, 0, '00:00:00', '00:00:00', 0, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(39, 8, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(40, 8, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(41, 8, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(42, 8, 4, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(43, 8, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(44, 8, 6, '00:00:00', '00:00:00', 0, '2026-03-24 01:47:59', '2026-03-24 01:47:59'),
(45, 7, 0, '00:00:00', '00:00:00', 0, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(46, 7, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(47, 7, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(48, 7, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(49, 7, 4, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(50, 7, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(51, 7, 6, '00:00:00', '00:00:00', 0, '2026-03-24 01:48:22', '2026-03-24 01:48:22'),
(52, 4, 0, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(53, 4, 1, '00:00:00', '00:00:00', 0, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(54, 4, 2, '00:00:00', '00:00:00', 0, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(55, 4, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(56, 4, 4, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(57, 4, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(58, 4, 6, '09:00:00', '17:00:00', 1, '2026-03-24 01:48:56', '2026-03-24 01:48:56'),
(59, 5, 0, '09:00:00', '17:00:00', 1, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(60, 5, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(61, 5, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(62, 5, 3, '00:00:00', '00:00:00', 0, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(63, 5, 4, '00:00:00', '00:00:00', 0, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(64, 5, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(65, 5, 6, '09:00:00', '17:00:00', 1, '2026-03-24 01:49:30', '2026-03-24 01:49:30'),
(66, 3, 0, '09:00:00', '17:00:00', 1, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(67, 3, 1, '09:00:00', '17:00:00', 1, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(68, 3, 2, '09:00:00', '17:00:00', 1, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(69, 3, 3, '09:00:00', '17:00:00', 1, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(70, 3, 4, '00:00:00', '00:00:00', 0, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(71, 3, 5, '09:00:00', '17:00:00', 1, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(72, 3, 6, '00:00:00', '00:00:00', 0, '2026-03-24 01:50:15', '2026-03-24 01:50:15'),
(73, 2, 0, '00:00:00', '00:00:00', 0, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(74, 2, 1, '09:00:00', '17:00:00', 1, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(75, 2, 2, '09:00:00', '17:00:00', 1, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(76, 2, 3, '09:00:00', '17:00:00', 1, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(77, 2, 4, '09:00:00', '17:00:00', 1, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(78, 2, 5, '09:00:00', '17:00:00', 1, '2026-03-24 02:30:52', '2026-03-24 02:30:52'),
(79, 2, 6, '00:00:00', '00:00:00', 0, '2026-03-24 02:30:52', '2026-03-24 02:30:52');

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
(12, 1, 7, 7, '2026-03-24 22:41:38', 'Checking bills', '', NULL, NULL, 0);

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
(2, 1, 'appointment', 'Appointment Confirmed', 'Your appointment with Dr. John Smith is booked for Mar 25, 2026 10:30 AM', NULL, 0, '2026-03-24 02:19:05', NULL),
(3, 13, 'appointment', 'New Appointment', 'Patient booked appointment for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:22:57', NULL),
(4, 1, 'appointment', 'Appointment Confirmed', 'Your appointment with Dr. Michael Williams is booked for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:22:57', NULL),
(5, 13, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:26:11', NULL),
(6, 1, 'appointment', 'Appointment Booked', 'Your appointment with Dr. Michael Williams has been booked for Mar 24, 2026 11:00 AM', NULL, 0, '2026-03-24 02:26:11', NULL),
(7, 13, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 02:28:48', NULL),
(8, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 02:28:48', NULL),
(9, 1, '', 'Medical Record Added', 'Dr. John Smith added a new medical record on Mar 23, 2026.', NULL, 0, '2026-03-24 03:07:58', NULL),
(10, 1, '', 'Medical Record Added', 'Dr. John Smith added a new medical record on Mar 23, 2026.', NULL, 0, '2026-03-24 03:29:38', NULL),
(11, 14, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 03:30:23', NULL),
(12, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 03:30:23', NULL),
(13, 1, '', 'Medical Record Added', 'Dr. Robert Brown added a new medical record on Mar 23, 2026.', NULL, 0, '2026-03-24 03:31:40', NULL),
(14, 16, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 24, 2026 4:00 PM', NULL, 0, '2026-03-24 15:41:48', NULL),
(15, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 24, 2026 4:00 PM', NULL, 0, '2026-03-24 15:41:48', NULL),
(16, 1, '', '', 'New bill generated for your consultation on 2026-03-24', 'view-bill.php?id=1', 0, '2026-03-24 17:04:27', NULL),
(17, 1, '', '', 'New bill generated for your consultation on 2026-03-24', 'view-bill.php?id=3', 0, '2026-03-24 17:09:25', NULL),
(18, 16, 'appointment', 'New Appointment Booked', 'A new appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 22:38:29', NULL),
(19, 1, 'appointment', 'Appointment Booked', 'Your appointment has been booked for Mar 25, 2026 11:00 AM', NULL, 0, '2026-03-24 22:38:29', NULL);

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
(6, 22, '1990-08-22', '456 Oak Ave, Gungahlin', 'John Smith', '+61 4383473497', 'A-', 'Peanuts', NULL, NULL, '2026-03-24 01:09:56', '2026-03-24 01:09:56'),
(7, 23, '2004-12-13', 'Belconnen', 'Himal', '9811783391', 'A+', 'None', 'None', 'None', '2026-03-24 01:19:20', '2026-03-24 01:19:20'),
(8, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 00:05:06', '2026-03-25 00:05:06');

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
(9, 12, 'Neurofen', '3 tablets', 'Thrice a day', '2026-04-24', NULL, 0, 'Take for a month regularly without missing', 'active', 7, '2026-03-24 22:41:38', '2026-03-24 22:41:38');

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
(16, 19, 'NURSE12345', '2026-03-24', 'Cardiology', 'Registered Nurse', '2026-03-24 01:09:55', '2026-03-24 01:09:55'),
(17, 20, 'RECEPTION001', '2026-03-24', 'Front Desk', 'Receptionist', '2026-03-24 01:09:55', '2026-03-24 01:09:55');

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
  `role` enum('patient','staff','nurse','doctor','admin') DEFAULT 'patient',
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
(1, 'himal', '$2y$10$PpZS0kKYVCk3jjIMKtbyF.GjsuPEZhhrU1RewaMdeAvIv8kEe8Wte', 'himalkumarkari@gmail.com', 'Himal', 'Karki', '0450595809', 'patient', 1, NULL, NULL, NULL, '2026-03-23 19:08:19', '2026-03-26 00:19:33'),
(9, 'himall', '$2y$10$WXkqBI4H4UENxDZuG4yJAOmfXwB6GVbgDm3wni3NhSc/566Iclssi', 'abinashcarkee@gmail.com', 'Himal', 'Karki', '0450595809', 'patient', 1, NULL, NULL, NULL, '2026-03-23 22:49:25', NULL),
(10, 'admin', '$2y$10$e4EcffhKENf3COqn5.Px9.aHimhV8p3o3Fg/f65qcMy3TcmVEPl26', 'admin@healthmanagement.com', 'Admin', 'User', '+61 4383473483', 'admin', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:38:37'),
(11, 'dr.smith', '$2y$10$raHKHkD8iZRhJic5Mf3m9OqRABxxV98Hgssecb/KP8qjxEYdtCg5a', 'dr.smith@healthmanagement.com', 'John', 'Smith', '+61 4383473484', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 03:30:47'),
(12, 'dr.johnson', '$2y$10$53iQ0yl6u/5qAuwo.ZArOO7teGCXyirG8Rya9pNtNfuotGRpp9EKu', 'dr.johnson@healthmanagement.com', 'Sarah', 'Johnson', '+61 4383473485', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 02:41:21'),
(13, 'dr.williams', '$2y$10$zSR8ovL/BMNiHZgo8zmqiuahijVTYefy.zkUZx7M.b/YVNH05faQi', 'dr.williams@healthmanagement.com', 'Michael', 'Williams', '+61 4383473486', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 03:27:45'),
(14, 'dr.brown', '$2y$10$sFL54NSVUWgln8IxT//3ke.UolE9qb7.Mru/TlWB3Kx4uXkLdmWnG', 'dr.brown@healthmanagement.com', 'Robert', 'Brown', '+61 4383473487', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 03:31:16'),
(15, 'dr.davis', '$2y$10$OtW09Zp.GsEfLLCPbQyxJuF.HsmQW2c/Zl3yfcA62yK6y0HuAoJpq', 'dr.davis@healthmanagement.com', 'Emily', 'Davis', '+61 4383473488', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:46:43'),
(16, 'dr.wilson', '$2y$10$ptse6rilvKUHCgTfBHjoMegyxOzLxdHNYGOoHNWZY/8CxZxCPWEna', 'dr.wilson@healthmanagement.com', 'David', 'Wilson', '+61 4383473489', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 23:59:09'),
(17, 'dr.martinez', '$2y$10$KF8DH1LeTyXmJranH4zeTuOqlguWBx8jrzSrajKXStLLRVVScGhWC', 'dr.martinez@healthmanagement.com', 'Lisa', 'Martinez', '+61 4383473490', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:47:52'),
(18, 'dr.taylor', '$2y$10$y5X4uvo96lDxQHTRNQ4A0ONMfWkHy0WhYMen85v6LM63rUPo3zAjK', 'dr.taylor@healthmanagement.com', 'James', 'Taylor', '+61 4383473491', 'doctor', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 01:47:23'),
(19, 'nurse.jane', '$2y$10$lsWjbWdkTToR4p6DfS9IxuR1e2Sq2Y3lruEkURfCO8PVULMk9rt32', 'nurse.jane@healthmanagement.com', 'Jane', 'Doe', '+61 4383473492', 'nurse', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-24 23:59:42'),
(20, 'reception', '$2y$10$D.kDWGvSanIqlLEgYb6/POTzzczoQ718Z0HkCDW.iulzJspixZ2Ui', 'reception@healthmanagement.com', 'Sarah', 'Wilson', '+61 4383473493', 'staff', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', '2026-03-25 00:04:52'),
(21, 'john.doe', '$2y$10$N.Q6LPYcXTaL3d6XuNMeGuZ3kugvc2BEDY7DAUTkQzuY5XfUf.lT6', 'john.doe@email.com', 'John', 'Doe', '+61 4383473494', 'patient', 1, NULL, NULL, NULL, '2026-03-24 01:09:55', NULL),
(22, 'jane.smith', '$2y$10$YbROFiNkg/sZeran3llkPusnWNfC1a2HegjR20ge7qg.if8ZSSbqe', 'jane.smith@email.com', 'Jane', 'Smith', '+61 4383473496', 'patient', 1, NULL, NULL, NULL, '2026-03-24 01:09:56', NULL),
(23, 'abinash', '$2y$10$ZBWMuqXlStbqT49LHw57HObAehxe3VCfSi8jnbcs/JW7HtuxREJ1O', 'abinashhkarkii@gmail.com', 'Abinash', 'Karki', '0450595809', 'patient', 1, NULL, NULL, NULL, '2026-03-24 01:19:20', NULL);

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
  ADD KEY `idx_doctor` (`doctorId`),
  ADD KEY `idx_day` (`dayOfWeek`);

--
-- Indexes for table `doctor_department`
--
ALTER TABLE `doctor_department`
  ADD PRIMARY KEY (`doctorId`,`departmentId`),
  ADD KEY `departmentId` (`departmentId`);

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
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staffId`),
  ADD UNIQUE KEY `userId` (`userId`),
  ADD KEY `idx_userId` (`userId`),
  ADD KEY `idx_licenseNumber` (`licenseNumber`);

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
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `adminId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointmentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `logId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `billId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bill_charges`
--
ALTER TABLE `bill_charges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `departmentId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctorId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `doctor_availability`
--
ALTER TABLE `doctor_availability`
  MODIFY `availabilityId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `testId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `recordId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `messageId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notificationId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `nurses`
--
ALTER TABLE `nurses`
  MODIFY `nurseId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patientId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patient_emergency_contacts`
--
ALTER TABLE `patient_emergency_contacts`
  MODIFY `contactId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescriptionId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staffId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `settingId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `vitals`
--
ALTER TABLE `vitals`
  MODIFY `vitalsId` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
