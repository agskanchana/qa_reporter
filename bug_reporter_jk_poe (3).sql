-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 04, 2025 at 03:13 PM
-- Server version: 8.3.0
-- PHP Version: 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bug_reporter_jk_poe`
--

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

DROP TABLE IF EXISTS `checklist_items`;
CREATE TABLE IF NOT EXISTS `checklist_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stage` enum('wp_conversion','page_creation','golive') NOT NULL,
  `title` varchar(255) NOT NULL,
  `how_to_check` text NOT NULL,
  `how_to_fix` text NOT NULL,
  `created_by` int DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_archived` (`is_archived`)
) ENGINE=MyISAM AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `stage`, `title`, `how_to_check`, `how_to_fix`, `created_by`, `is_archived`, `archived_at`, `archived_by`) VALUES
(34, 'page_creation', 'Page creation checklist 8', 'dfdf', 'dfdf', 1, 1, '2025-02-15 13:20:21', 1),
(33, 'page_creation', 'Page creation checklist 7', 'd', 'd', 1, 1, '2025-02-15 13:20:50', 1),
(30, 'page_creation', 'Page creation checklist 4', 'dfd', 'dfdf', 1, 1, '2025-02-15 13:21:01', 1),
(31, 'page_creation', 'Page creation checklist 5', 'dfdfd', 'dfdf', 1, 1, '2025-02-15 13:20:58', 1),
(32, 'page_creation', 'Page creation checklist 6', 'sddf', 'dfdf', 1, 1, '2025-02-15 13:20:54', 1),
(27, 'golive', 'Golive check list 2', '2', '2', 1, 1, '2025-02-19 17:41:19', 1),
(28, 'wp_conversion', 'New check list item', '2222', '222', 1, 1, '2025-02-19 17:40:48', 1),
(29, 'page_creation', 'Page creation checklist 3', 'dfd', 'dfd', 1, 1, '2025-02-15 13:21:05', 1),
(26, 'golive', 'Golive check list 1', '1', '1', 1, 1, '2025-02-19 17:41:16', 1),
(25, 'page_creation', 'Page creation checklist 2', '2', '2', 1, 1, '2025-02-15 13:21:12', 1),
(23, 'wp_conversion', 'WP Conversion check list 2', '2', '2', 1, 1, '2025-02-15 08:21:43', 1),
(24, 'page_creation', 'Page creation checklist 1', '1', '1', 1, 1, '2025-02-15 13:19:59', 1),
(22, 'wp_conversion', 'WP Conversion check list 1', '1', '1', 1, 1, '2025-02-19 17:40:49', 1),
(35, 'page_creation', 'Page creation checklist 9', 'dfdf', 'dfdf', 1, 1, '2025-02-15 13:20:15', 1),
(36, 'page_creation', 'Page creation checklist 10', 'd', 'd', 1, 1, '2025-02-15 13:20:25', 1),
(37, 'page_creation', 'Page creation checklist 11', 'd', 'd', 1, 1, '2025-02-15 13:20:28', 1),
(38, 'page_creation', 'Page creation checklist 12', 'dd', 'ddd', 1, 1, '2025-02-15 13:20:34', 1),
(39, 'page_creation', 'Page creation checklist 13', 'dfdfd', 'dfdfd', 1, 1, '2025-02-15 13:20:37', 1),
(40, 'page_creation', 'Page creation checklist 14', 'd', 'd', 1, 1, '2025-02-15 13:20:40', 1),
(41, 'page_creation', 'Page creation checklist 15', 'df', 'dfdfd', 1, 1, '2025-02-15 13:20:46', 1),
(42, 'page_creation', 'Page creation checklist 16', 'd', 'd', 1, 1, '2025-02-19 17:40:52', 1),
(43, 'page_creation', 'Page creation checklist 17', 'Page creation checklist 17', 'dd', 1, 1, '2025-02-19 17:41:07', 1),
(44, 'page_creation', 'Page creation checklist 18', 'd', 'd', 1, 1, '2025-02-19 17:41:09', 1),
(45, 'page_creation', 'Page creation checklist 19', 'd', 'd', 1, 1, '2025-02-19 17:41:13', 1),
(46, 'page_creation', 'Page creation checklist 20', 'd', 'd', 1, 1, '2025-02-15 13:21:08', 1),
(47, 'page_creation', 'nw check list item for page cration', 'df', 'dfdf', 1, 1, '2025-02-16 08:33:00', 1),
(48, 'wp_conversion', 'WP conversion checklist 1', 'wewew', 'wewew', 1, 0, NULL, NULL),
(49, 'wp_conversion', 'WP conversion checklist 2', 'sdsds', 'dsds', 1, 0, NULL, NULL),
(50, 'wp_conversion', 'WP conversion checklist 3', 'sd', 'df', 1, 0, NULL, NULL),
(51, 'wp_conversion', 'WP conversion checklist 4', 'd', 'd', 1, 0, NULL, NULL),
(52, 'page_creation', 'Page creation checklist 1', 'd', 'd', 1, 0, NULL, NULL),
(53, 'page_creation', 'Page creation checklist 2', 'd', 'd', 1, 0, NULL, NULL),
(54, 'page_creation', 'Page creation checklist 3', 'd', 'd', 1, 0, NULL, NULL),
(55, 'golive', 'Golive checklist 1', 'dfdf', 'dfdf', 1, 0, NULL, NULL),
(56, 'golive', 'Golive checklist 2', 'dfd', 'fdfd', 1, 0, NULL, NULL),
(57, 'golive', 'Golive checklist 3', 'dd', 'ddd', 1, 0, NULL, NULL),
(58, 'wp_conversion', 'WP conversion checklist 5', 'sss', 'sss', 1, 0, NULL, NULL),
(59, 'wp_conversion', 'New item', 'd', 'd', 1, 1, '2025-03-01 12:35:48', 1),
(60, 'wp_conversion', '123123123', '22', 'sdsd', 1, 1, '2025-03-01 12:18:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `checklist_item_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `checklist_item_id` (`checklist_item_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deadline_extension_requests`
--

DROP TABLE IF EXISTS `deadline_extension_requests`;
CREATE TABLE IF NOT EXISTS `deadline_extension_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `deadline_type` enum('wp_conversion','project') NOT NULL,
  `original_deadline` date NOT NULL,
  `requested_deadline` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `review_comment` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deadline_extension_requests`
--

INSERT INTO `deadline_extension_requests` (`id`, `project_id`, `requested_by`, `deadline_type`, `original_deadline`, `requested_deadline`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`, `review_comment`) VALUES
(1, 97, 11, 'wp_conversion', '2025-03-11', '2025-03-12', 'I need more time bcoz i hv a big head', 'denied', 1, '2025-03-02 12:01:39', '2025-03-02 06:19:01', 'bcoz i  can'),
(2, 95, 11, 'wp_conversion', '2025-03-10', '2025-03-20', 'i hv my wedding', 'denied', 1, '2025-03-02 12:07:49', '2025-03-02 06:37:24', 'fuck ur wedding'),
(3, 97, 11, 'wp_conversion', '2025-03-11', '2025-03-20', 'fgfgf', 'denied', 1, '2025-03-02 12:16:10', '2025-03-02 06:45:49', 'yrdydddddd'),
(4, 97, 11, 'wp_conversion', '2025-03-11', '2025-03-20', 's', 'approved', 1, '2025-03-02 12:53:54', '2025-03-02 07:23:38', ''),
(5, 96, 14, 'wp_conversion', '2025-03-11', '2025-03-13', 'ddd', 'approved', 1, '2025-03-02 19:35:52', '2025-03-02 14:05:31', ''),
(6, 102, 11, 'wp_conversion', '2025-03-01', '2025-03-05', 'i need more time', 'approved', 1, '2025-03-04 20:24:10', '2025-03-04 14:27:00', ''),
(7, 103, 11, 'wp_conversion', '2025-03-01', '2025-03-05', 'dfdfd', 'approved', 1, '2025-03-04 20:24:01', '2025-03-04 14:27:17', '');

-- --------------------------------------------------------

--
-- Table structure for table `missed_deadlines`
--

DROP TABLE IF EXISTS `missed_deadlines`;
CREATE TABLE IF NOT EXISTS `missed_deadlines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `deadline_type` enum('wp_conversion','project') NOT NULL,
  `original_deadline` date NOT NULL,
  `reason` text,
  `reason_provided_by` int DEFAULT NULL,
  `reason_provided_at` datetime DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `missed_deadlines`
--

INSERT INTO `missed_deadlines` (`id`, `project_id`, `deadline_type`, `original_deadline`, `reason`, `reason_provided_by`, `reason_provided_at`, `recorded_at`) VALUES
(1, 101, 'wp_conversion', '2025-03-01', 'I had my wedding', 11, '2025-03-04 19:42:18', '2025-03-04 19:37:02'),
(2, 102, 'wp_conversion', '2025-03-01', 'aaaaa', 11, '2025-03-04 19:56:41', '2025-03-04 19:46:08'),
(3, 103, 'wp_conversion', '2025-03-01', 'Deadline Project 3 test', 11, '2025-03-04 19:57:12', '2025-03-04 19:57:00');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `role` enum('admin','qa_manager','qa_reporter','webmaster') DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `role` (`role`),
  KEY `is_read` (`is_read`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `role`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, NULL, 'admin', 'Deadline extension requested for WP Conversion by janith for project: Dr. Sandy', 'warning', 1, '2025-03-02 06:19:01'),
(2, 11, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sandy\' has been rejected.', 'danger', 1, '2025-03-02 06:31:39'),
(3, NULL, 'admin', 'Deadline extension requested for WP Conversion by janith for project: Dr. Sam', 'warning', 1, '2025-03-02 06:37:24'),
(4, 11, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sam\' has been rejected.', 'danger', 1, '2025-03-02 06:37:49'),
(5, NULL, 'admin', 'Deadline extension requested for WP Conversion by janith for project: Dr. Sandy', 'warning', 1, '2025-03-02 06:45:49'),
(6, 11, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sandy\' has been rejected.', 'danger', 1, '2025-03-02 06:46:10'),
(7, NULL, 'admin', 'Deadline extension requested for WP Conversion by janith for project: Dr. Sandy', 'warning', 1, '2025-03-02 07:23:38'),
(8, 11, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sandy\' has been approved.', 'success', 1, '2025-03-02 07:23:54'),
(9, NULL, 'admin', 'WP Conversion deadline extension requested by menuka for project: Dr. Strange', 'warning', 1, '2025-03-02 14:05:31'),
(10, 14, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Strange\' has been approved.', 'success', 1, '2025-03-02 14:05:52'),
(11, 1, 'admin', 'WP conversion deadline missed for project \'Test Past Deadline Project\'. The deadline was 2025-03-01.', 'warning', 1, '2025-03-04 14:07:02'),
(12, 11, 'webmaster', 'You have missed the WP conversion deadline for project \'Test Past Deadline Project\'. The deadline was 2025-03-01. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-04 14:07:02'),
(13, 1, 'admin', 'WP conversion deadline missed for project \'Test Past Deadline Project 2\'. The deadline was 2025-03-01.', 'warning', 1, '2025-03-04 14:16:08'),
(14, 11, 'webmaster', 'You have missed the WP conversion deadline for project \'Test Past Deadline Project 2\'. The deadline was 2025-03-01. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-04 14:16:08'),
(15, 1, 'admin', 'New deadline extension request for project \'Test Past Deadline Project 2\'. Deadline type: Wp conversion', 'info', 1, '2025-03-04 14:27:00'),
(16, 1, 'admin', 'WP conversion deadline missed for project \'Test Past Deadline Project 3\'. The deadline was 2025-03-01.', 'warning', 1, '2025-03-04 14:27:00'),
(17, 11, 'webmaster', 'You have missed the WP conversion deadline for project \'Test Past Deadline Project 3\'. The deadline was 2025-03-01. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-04 14:27:00'),
(18, 1, 'admin', 'New deadline extension request for project \'Test Past Deadline Project 3\'. Deadline type: Wp conversion', 'info', 1, '2025-03-04 14:27:17'),
(19, 11, NULL, 'Your WP Conversion deadline extension request for project \'Test Past Deadline Project 3\' has been approved.', 'success', 1, '2025-03-04 14:54:01'),
(20, 11, NULL, 'Your WP Conversion deadline extension request for project \'Test Past Deadline Project 2\' has been approved.', 'success', 1, '2025-03-04 14:54:10'),
(21, NULL, NULL, 'Debug notification', 'info', 0, '2025-03-04 15:12:49'),
(22, NULL, NULL, 'Debug notification', 'info', 0, '2025-03-04 15:12:51');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `webmaster_id` int DEFAULT NULL,
  `current_status` varchar(255) DEFAULT 'wp_conversion',
  `project_deadline` date DEFAULT NULL,
  `wp_conversion_deadline` date DEFAULT NULL,
  `gp_link` varchar(255) DEFAULT NULL,
  `ticket_link` varchar(255) DEFAULT NULL,
  `test_site_link` varchar(255) DEFAULT NULL,
  `live_site_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_name` (`name`),
  KEY `webmaster_id` (`webmaster_id`)
) ENGINE=MyISAM AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `webmaster_id`, `current_status`, `project_deadline`, `wp_conversion_deadline`, `gp_link`, `ticket_link`, `test_site_link`, `live_site_link`, `created_at`, `updated_at`) VALUES
(95, 'Dr. Sam', 11, 'wp_conversion_qa', '2025-03-25', '2025-03-10', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', '', '2025-02-27 15:31:00', '2025-03-01 04:25:49'),
(97, 'Dr. Sandy', 11, 'wp_conversion_qa', '2025-03-26', '2025-03-20', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', '', '2025-03-01 07:02:35', '2025-03-02 07:23:54'),
(96, 'Dr. Strange', 14, 'wp_conversion_qa', '2025-03-26', '2025-03-13', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', '', '2025-03-01 06:44:02', '2025-03-02 14:05:52'),
(98, 'Dr. Sameera', 15, 'wp_conversion_qa', '2025-03-26', '2025-03-11', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', '', '2025-03-01 07:46:44', '2025-03-01 07:48:38'),
(99, 'Dr. Trump', 15, 'page_creation', '2025-03-26', '2025-03-11', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', '', '2025-03-01 07:49:10', '2025-03-02 17:29:13'),
(100, 'Dr. D', 15, 'completed', '2025-03-26', '2025-03-11', 'https://docs.google.com/spreadsheets/d/15q4UmKNmsqQtZB6lTD0fiSun8K0lVJKOVML1_ErPCe8/edit?gid=9#gid=9', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.espncricinfo.com/', 'https://www.emp-management.net/', '2025-03-01 08:10:49', '2025-03-01 08:14:18'),
(101, 'Test Past Deadline Project', 11, 'wp_conversion', '2025-03-14', '2025-03-01', NULL, NULL, NULL, NULL, '2025-03-04 14:06:52', '2025-03-04 14:06:52'),
(102, 'Test Past Deadline Project 2', 11, 'wp_conversion', '2025-03-14', '2025-03-05', NULL, NULL, NULL, NULL, '2025-03-04 14:15:54', '2025-03-04 14:54:10'),
(103, 'Test Past Deadline Project 3', 11, 'wp_conversion', '2025-03-14', '2025-03-05', NULL, NULL, NULL, NULL, '2025-03-04 14:26:15', '2025-03-04 14:54:01');

-- --------------------------------------------------------

--
-- Table structure for table `project_checklist_status`
--

DROP TABLE IF EXISTS `project_checklist_status`;
CREATE TABLE IF NOT EXISTS `project_checklist_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int DEFAULT NULL,
  `checklist_item_id` int DEFAULT NULL,
  `status` enum('idle','fixed','passed','failed') DEFAULT 'idle',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `checklist_item_id` (`checklist_item_id`),
  KEY `updated_by` (`updated_by`)
) ENGINE=MyISAM AUTO_INCREMENT=1291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_checklist_status`
--

INSERT INTO `project_checklist_status` (`id`, `project_id`, `checklist_item_id`, `status`, `updated_at`, `updated_by`, `is_archived`) VALUES
(1273, 100, 52, 'passed', '2025-03-01 08:13:52', 1, 0),
(1272, 100, 51, 'passed', '2025-03-01 08:13:40', 1, 0),
(1271, 100, 50, 'passed', '2025-03-01 08:13:36', 1, 0),
(1270, 100, 49, 'passed', '2025-03-01 08:13:33', 1, 0),
(1269, 100, 48, 'passed', '2025-03-01 08:13:29', 1, 0),
(1268, 99, 58, 'passed', '2025-03-02 17:29:13', 1, 0),
(1267, 99, 57, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1266, 99, 56, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1265, 99, 55, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1264, 99, 54, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1263, 99, 53, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1262, 99, 52, 'idle', '2025-03-01 07:49:10', NULL, 0),
(1261, 99, 51, 'passed', '2025-03-02 17:29:09', 1, 0),
(1260, 99, 50, 'passed', '2025-03-02 17:29:06', 1, 0),
(1259, 99, 49, 'passed', '2025-03-02 17:29:02', 1, 0),
(1258, 99, 48, 'passed', '2025-03-02 17:28:59', 1, 0),
(1257, 98, 58, 'passed', '2025-03-01 07:48:13', 1, 0),
(1256, 98, 57, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1255, 98, 56, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1254, 98, 55, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1253, 98, 54, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1252, 98, 53, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1251, 98, 52, 'idle', '2025-03-01 07:46:44', NULL, 0),
(1250, 98, 51, 'passed', '2025-03-01 07:48:09', 1, 0),
(1249, 98, 50, 'passed', '2025-03-01 07:48:05', 1, 0),
(1234, 96, 60, 'fixed', '2025-03-01 06:49:39', 14, 1),
(1221, 95, 60, 'passed', '2025-03-01 06:48:07', 1, 1),
(1247, 98, 48, 'fixed', '2025-03-01 07:48:38', 15, 0),
(1233, 96, 59, 'fixed', '2025-03-01 07:05:48', 14, 1),
(1220, 95, 59, 'passed', '2025-03-01 07:05:48', 1, 1),
(1246, 97, 59, 'fixed', '2025-03-02 17:28:31', 11, 1),
(1232, 96, 58, 'fixed', '2025-03-01 06:48:49', 14, 0),
(1219, 95, 58, 'passed', '2025-03-01 04:23:36', 1, 0),
(1245, 97, 58, 'fixed', '2025-03-02 17:28:27', 11, 0),
(1231, 96, 57, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1218, 95, 57, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1244, 97, 57, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1230, 96, 56, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1229, 96, 55, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1217, 95, 56, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1216, 95, 55, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1243, 97, 56, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1242, 97, 55, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1228, 96, 54, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1227, 96, 53, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1226, 96, 52, 'idle', '2025-03-01 06:44:02', NULL, 0),
(1225, 96, 51, 'fixed', '2025-03-01 06:48:45', 14, 0),
(1224, 96, 50, 'fixed', '2025-03-01 06:48:41', 14, 0),
(1223, 96, 49, 'fixed', '2025-03-01 06:48:36', 14, 0),
(1215, 95, 54, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1214, 95, 53, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1213, 95, 52, 'idle', '2025-02-27 15:31:00', NULL, 0),
(1212, 95, 51, 'passed', '2025-03-01 04:23:32', 1, 0),
(1211, 95, 50, 'fixed', '2025-03-01 04:25:49', 11, 0),
(1210, 95, 49, 'passed', '2025-03-01 03:07:26', 1, 0),
(1241, 97, 54, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1240, 97, 53, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1239, 97, 52, 'idle', '2025-03-01 07:02:35', NULL, 0),
(1238, 97, 51, 'fixed', '2025-03-02 17:28:23', 11, 0),
(1237, 97, 50, 'fixed', '2025-03-02 17:28:20', 11, 0),
(1236, 97, 49, 'fixed', '2025-03-02 17:28:15', 11, 0),
(1290, 101, 58, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1289, 101, 57, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1288, 101, 56, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1287, 101, 55, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1286, 101, 54, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1285, 101, 53, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1284, 101, 52, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1283, 101, 51, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1282, 101, 50, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1281, 101, 49, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1280, 101, 48, 'idle', '2025-03-04 14:14:36', NULL, 0),
(1279, 100, 58, 'passed', '2025-03-01 08:13:43', 1, 0),
(1278, 100, 57, 'passed', '2025-03-01 08:14:18', 1, 0),
(1277, 100, 56, 'passed', '2025-03-01 08:14:15', 1, 0),
(1276, 100, 55, 'passed', '2025-03-01 08:14:11', 1, 0),
(1275, 100, 54, 'passed', '2025-03-01 08:14:03', 1, 0),
(1235, 97, 48, 'fixed', '2025-03-02 17:28:12', 11, 0),
(1209, 95, 48, 'fixed', '2025-03-01 04:25:44', 11, 0),
(1222, 96, 48, 'fixed', '2025-03-01 06:44:37', 14, 0),
(1248, 98, 49, 'passed', '2025-03-01 07:48:02', 1, 0),
(1274, 100, 53, 'passed', '2025-03-01 08:13:58', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `project_stage_status`
--

DROP TABLE IF EXISTS `project_stage_status`;
CREATE TABLE IF NOT EXISTS `project_stage_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `stage` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_stage` (`project_id`,`stage`)
) ENGINE=MyISAM AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_stage_status`
--

INSERT INTO `project_stage_status` (`id`, `project_id`, `stage`, `status`, `created_at`, `updated_at`) VALUES
(1, 58, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 14:24:00', '2025-02-22 14:24:00'),
(2, 58, 'page_creation', 'page_creation_qa', '2025-02-22 14:30:27', '2025-02-22 14:30:27'),
(3, 59, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 14:36:28', '2025-02-22 14:36:28'),
(4, 59, 'page_creation', 'page_creation_qa', '2025-02-22 14:37:02', '2025-02-22 14:37:02'),
(5, 60, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 14:43:05', '2025-02-22 14:43:05'),
(6, 60, 'page_creation', 'page_creation_qa', '2025-02-22 14:47:57', '2025-02-22 14:47:57'),
(7, 61, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 14:51:28', '2025-02-22 14:51:28'),
(8, 61, 'page_creation', 'page_creation_qa', '2025-02-22 14:51:52', '2025-02-22 14:51:52'),
(9, 62, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 14:56:30', '2025-02-22 14:56:30'),
(10, 62, 'page_creation', 'page_creation_qa', '2025-02-22 14:56:49', '2025-02-22 14:56:49'),
(11, 63, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 15:01:38', '2025-02-22 15:01:38'),
(12, 63, 'page_creation', 'page_creation_qa', '2025-02-22 15:01:53', '2025-02-22 15:01:53'),
(13, 64, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 15:05:43', '2025-02-22 15:05:43'),
(14, 64, 'page_creation', 'page_creation_qa', '2025-02-22 15:05:59', '2025-02-22 15:05:59'),
(15, 65, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 15:11:31', '2025-02-22 15:11:31'),
(16, 65, 'page_creation', 'page_creation_qa', '2025-02-22 15:12:02', '2025-02-22 15:12:02'),
(17, 66, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 15:16:54', '2025-02-22 15:16:54'),
(18, 66, 'page_creation', 'page_creation_qa', '2025-02-22 15:17:17', '2025-02-22 15:17:17'),
(19, 67, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 15:31:59', '2025-02-22 15:31:59'),
(20, 67, 'page_creation', 'page_creation_qa', '2025-02-22 15:32:16', '2025-02-22 15:32:16'),
(21, 68, 'wp_conversion', 'wp_conversion', '2025-02-22 15:36:32', '2025-02-22 15:38:28'),
(22, 68, 'page_creation', 'page_creation_qa', '2025-02-22 15:36:54', '2025-02-22 15:36:54'),
(23, 69, 'wp_conversion', 'wp_conversion', '2025-02-22 15:50:39', '2025-02-22 15:52:01'),
(24, 69, 'page_creation', 'page_creation_qa', '2025-02-22 15:50:56', '2025-02-22 15:50:56'),
(25, 71, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:11:37', '2025-02-22 16:11:58'),
(26, 71, 'page_creation', 'page_creation_qa', '2025-02-22 16:12:11', '2025-02-22 16:12:20'),
(27, 72, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:23:11', '2025-02-22 16:23:11'),
(28, 72, 'page_creation', 'page_creation_qa', '2025-02-22 16:23:27', '2025-02-22 16:23:27'),
(29, 73, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:26:53', '2025-02-22 16:26:53'),
(30, 74, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:28:13', '2025-02-22 16:28:13'),
(31, 75, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:32:36', '2025-02-22 16:32:57'),
(32, 75, 'page_creation', 'page_creation_qa', '2025-02-22 16:32:36', '2025-02-22 16:33:50'),
(33, 75, 'golive', 'golive', '2025-02-22 16:32:36', '2025-02-22 16:32:36'),
(34, 76, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:38:11', '2025-02-22 16:38:11'),
(35, 76, 'page_creation', 'page_creation_qa', '2025-02-22 16:38:28', '2025-02-22 16:38:28'),
(36, 77, 'wp_conversion', 'wp_conversion_qa', '2025-02-22 16:46:19', '2025-02-22 16:46:19'),
(37, 77, 'page_creation', 'page_creation_qa', '2025-02-22 16:46:57', '2025-02-22 16:46:57'),
(38, 77, 'golive', 'golive_qa', '2025-02-22 16:47:24', '2025-02-22 16:47:24');

-- --------------------------------------------------------

--
-- Table structure for table `qa_assignments`
--

DROP TABLE IF EXISTS `qa_assignments`;
CREATE TABLE IF NOT EXISTS `qa_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `qa_user_id` int NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `qa_user_id` (`qa_user_id`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=MyISAM AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','qa_manager','qa_reporter','webmaster') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `unique_email` (`email`(191))
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@temp.com', '$2y$10$WvTWAvXyF3xy9lphncN8MuYiBu5ProZmB74QRRnDHATH6BjTqv4r6', 'admin', '2025-01-30 09:56:02'),
(11, 'janith', 'janith@temp.com', '$2y$10$Pb/iIND1q00h2TlyZKL1A.Ra4Rc/bx3tZELnxWZmE0pqO6T5sRuJu', 'webmaster', '2025-02-19 12:10:26'),
(12, 'nadeeka', 'nadeeka@temp.com', '$2y$10$sIMJKg4FLuo68WqDRRDRu.ogJs21U.CkWDnpUkTzF08qXTAkJ40sW', 'qa_manager', '2025-02-19 12:19:17'),
(13, 'shifnas', 'shifnas@temp.com', '$2y$10$aZjHEBE/E4OYxzZZE.oc0OmaA5/Pgh/npHUKM3/LnXy6uLsd6Gmzy', 'qa_reporter', '2025-02-19 12:19:28'),
(14, 'menuka', 'menuka@ek.com', '$2y$10$jIN6ykoF17Y/a9.7GIGshepr/4lsblZrcsEWZm035Z2Wfi0dhgAAm', 'webmaster', '2025-02-20 12:53:35'),
(15, 'sam', 'sam@ekwa.com', '$2y$10$nRYjquWJvA45UdwupGP5bendB36wt67ZyHbacPxtEKiPxIwd6k5Xu', 'webmaster', '2025-02-22 13:53:27');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
