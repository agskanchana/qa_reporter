-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 09, 2025 at 01:46 PM
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
) ENGINE=MyISAM AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `stage`, `title`, `how_to_check`, `how_to_fix`, `created_by`, `is_archived`, `archived_at`, `archived_by`) VALUES
(87, 'golive', 'golive checklist 2', 'd', 'd', 1, 0, NULL, NULL),
(83, 'wp_conversion', 'wp conversion checklist 2', 'd', 'd', 1, 0, NULL, NULL),
(84, 'page_creation', 'page creation checklist 1', 'd', 'd', 1, 0, NULL, NULL),
(86, 'golive', 'golive checklist 1', 'd', 'd', 1, 0, NULL, NULL),
(85, 'page_creation', 'page creation checklist 2', 'd', 'd', 1, 0, NULL, NULL),
(82, 'wp_conversion', 'wp conversion checklist 1', 'd', 'd', 1, 0, NULL, NULL);

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
) ENGINE=MyISAM AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `deadline_extension_requests`
--

INSERT INTO `deadline_extension_requests` (`id`, `project_id`, `requested_by`, `deadline_type`, `original_deadline`, `requested_deadline`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`, `review_comment`) VALUES
(58, 191, 15, 'wp_conversion', '2025-03-07', '2025-03-10', 'You have missed the Wp conversion deadline on 2025-03-07.', 'approved', 1, '2025-03-09 10:11:15', '2025-03-09 04:41:05', ''),
(59, 192, 15, 'wp_conversion', '2025-03-07', '2025-03-10', 'The WP Conversion deadline (March 7, 2025) was missed.', 'approved', 1, '2025-03-09 10:19:26', '2025-03-09 04:49:13', '');

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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `missed_deadlines`
--

INSERT INTO `missed_deadlines` (`id`, `project_id`, `deadline_type`, `original_deadline`, `reason`, `reason_provided_by`, `reason_provided_at`, `recorded_at`) VALUES
(67, 191, 'wp_conversion', '2025-03-07', 'You have missed the Wp conversion deadline on 2025-03-07.', 15, '2025-03-09 10:10:52', '2025-03-09 10:10:35'),
(68, 192, 'wp_conversion', '2025-03-07', 'The WP Conversion deadline (March 7, 2025) was missed.', 15, '2025-03-09 10:19:04', '2025-03-09 10:19:00'),
(69, 195, 'wp_conversion', '2025-03-07', 'The WP Conversion deadline (March 7, 2025) was missed.', 15, '2025-03-09 11:18:29', '2025-03-09 11:18:22'),
(70, 196, 'wp_conversion', '2025-03-08', 'The WP Conversion deadline (March 8, 2025) was missed.', 15, '2025-03-09 11:47:27', '2025-03-09 11:47:23'),
(71, 197, 'wp_conversion', '2025-03-08', 'The WP Conversion deadline (March 8, 2025) was missed.', 15, '2025-03-09 12:06:25', '2025-03-09 12:06:21'),
(72, 200, 'wp_conversion', '2025-03-03', 'The WP Conversion deadline (March 3, 2025) was missed.', 15, '2025-03-09 13:13:53', '2025-03-09 13:13:48'),
(73, 200, 'project', '2025-03-08', 'Deadline Missed: Dr. Sandy', 15, '2025-03-09 13:30:47', '2025-03-09 13:30:33');

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
) ENGINE=MyISAM AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `role`, `message`, `type`, `is_read`, `created_at`) VALUES
(207, 1, 'admin', 'Project deadline missed for project \'Dr. Sandy\'. The deadline was 2025-03-08.', 'warning', 1, '2025-03-09 08:00:33'),
(208, 15, 'webmaster', 'You have missed the project deadline for project \'Dr. Sandy\'. The deadline was 2025-03-08.', 'warning', 1, '2025-03-09 08:00:33'),
(206, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Sandy\'. The deadline was 2025-03-03. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 07:43:48'),
(202, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Sam\'. The deadline was 2025-03-08. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 06:17:23'),
(203, 1, NULL, 'WP conversion deadline missed for project \'Dr. Sandy\'. The deadline was 2025-03-08.', 'warning', 1, '2025-03-09 06:36:21'),
(204, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Sandy\'. The deadline was 2025-03-08. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 06:36:21'),
(200, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Strange\'. The deadline was 2025-03-07. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 05:48:22'),
(205, 1, NULL, 'WP conversion deadline missed for project \'Dr. Sandy\'. The deadline was 2025-03-03.', 'warning', 1, '2025-03-09 07:43:48'),
(201, 1, NULL, 'WP conversion deadline missed for project \'Dr. Sam\'. The deadline was 2025-03-08.', 'warning', 1, '2025-03-09 06:17:23'),
(199, 1, NULL, 'WP conversion deadline missed for project \'Dr. Strange\'. The deadline was 2025-03-07.', 'warning', 1, '2025-03-09 05:48:22'),
(198, 15, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sam\' has been approved.', 'success', 1, '2025-03-09 04:49:26'),
(196, 1, NULL, 'WP conversion deadline missed for project \'Dr. Sam\'. The deadline was 2025-03-07.', 'warning', 1, '2025-03-09 04:49:00'),
(197, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Sam\'. The deadline was 2025-03-07. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 04:49:00'),
(194, 1, 'admin', 'New deadline extension request for project \'Dr. Sandy\'. Deadline type: Wp conversion', 'info', 1, '2025-03-09 04:41:05'),
(195, 15, NULL, 'Your WP Conversion deadline extension request for project \'Dr. Sandy\' has been approved.', 'success', 1, '2025-03-09 04:41:15'),
(193, 15, NULL, 'You have missed the WP conversion deadline for project \'Dr. Sandy\'. The deadline was 2025-03-07. Please provide a reason and request an extension if needed.', 'warning', 1, '2025-03-09 04:40:35'),
(192, 1, NULL, 'WP conversion deadline missed for project \'Dr. Sandy\'. The deadline was 2025-03-07.', 'warning', 1, '2025-03-09 04:40:35');

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
  `admin_notes` text,
  `webmaster_notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_name` (`name`),
  KEY `webmaster_id` (`webmaster_id`)
) ENGINE=MyISAM AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `webmaster_id`, `current_status`, `project_deadline`, `wp_conversion_deadline`, `gp_link`, `ticket_link`, `test_site_link`, `live_site_link`, `created_at`, `updated_at`, `admin_notes`, `webmaster_notes`) VALUES
(199, 'Dr. Sam', 15, 'completed', '2025-04-02', '2025-03-18', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.emp-management.net/', 'https://www.emp-management.net/', '2025-03-09 07:30:47', '2025-03-09 07:33:51', '', ''),
(200, 'Dr. Sandy', 15, 'completed', '2025-03-08', '2025-03-03', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.emp-management.net/', 'https://www.emp-management.net/', '2025-03-09 07:43:43', '2025-03-09 09:04:58', '', ''),
(201, 'Dr. Sameera', 15, 'wp_conversion', '2025-04-02', '2025-03-18', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', '', '', '2025-03-09 13:16:56', '2025-03-09 13:16:56', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1', 'https://www.ekwaservice.com/support/staff/index.php?/Tickets/Ticket/View/1550137/inbox/159/297/-1');

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
) ENGINE=MyISAM AUTO_INCREMENT=1907 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_checklist_status`
--

INSERT INTO `project_checklist_status` (`id`, `project_id`, `checklist_item_id`, `status`, `updated_at`, `updated_by`, `is_archived`) VALUES
(1906, 201, 82, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1905, 201, 85, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1904, 201, 86, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1903, 201, 84, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1902, 201, 83, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1901, 201, 87, 'idle', '2025-03-09 13:16:56', NULL, 0),
(1900, 200, 82, 'passed', '2025-03-09 07:59:18', 1, 0),
(1899, 200, 85, 'passed', '2025-03-09 07:59:55', 1, 0),
(1898, 200, 86, 'passed', '2025-03-09 09:04:55', 1, 0),
(1897, 200, 84, 'passed', '2025-03-09 07:59:47', 1, 0),
(1896, 200, 83, 'passed', '2025-03-09 07:59:24', 1, 0),
(1895, 200, 87, 'passed', '2025-03-09 09:04:58', 1, 0),
(1894, 199, 82, 'passed', '2025-03-09 07:32:17', 1, 0),
(1893, 199, 85, 'passed', '2025-03-09 07:32:44', 1, 0),
(1892, 199, 86, 'passed', '2025-03-09 07:33:51', 1, 0),
(1891, 199, 84, 'passed', '2025-03-09 07:32:41', 1, 0),
(1890, 199, 83, 'passed', '2025-03-09 07:32:00', 1, 0),
(1889, 199, 87, 'passed', '2025-03-09 07:33:21', 1, 0);

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

-- --------------------------------------------------------

--
-- Table structure for table `project_status_history`
--

DROP TABLE IF EXISTS `project_status_history`;
CREATE TABLE IF NOT EXISTS `project_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `status` varchar(50) NOT NULL,
  `action` varchar(20) NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_status_history`
--

INSERT INTO `project_status_history` (`id`, `project_id`, `status`, `action`, `created_by`, `created_at`) VALUES
(1, 199, 'wp_conversion_qa', 'updated', 15, '2025-03-09 07:31:08'),
(2, 199, 'wp_conversion', 'updated', 1, '2025-03-09 07:32:00'),
(3, 199, 'wp_conversion_qa', 'updated', 15, '2025-03-09 07:32:10'),
(4, 199, 'page_creation', 'updated', 1, '2025-03-09 07:32:17'),
(5, 199, 'page_creation_qa', 'updated', 15, '2025-03-09 07:32:31'),
(6, 199, 'golive', 'updated', 1, '2025-03-09 07:32:44'),
(7, 199, 'golive_qa', 'updated', 15, '2025-03-09 07:32:59'),
(8, 199, 'golive', 'updated', 1, '2025-03-09 07:33:21'),
(9, 199, 'golive_qa', 'updated', 15, '2025-03-09 07:33:39'),
(10, 199, 'completed', 'updated', 1, '2025-03-09 07:33:51'),
(11, 200, 'wp_conversion_qa', 'updated', 15, '2025-03-09 07:59:03'),
(12, 200, 'page_creation', 'updated', 1, '2025-03-09 07:59:24'),
(13, 200, 'page_creation_qa', 'updated', 15, '2025-03-09 07:59:39'),
(14, 200, 'golive', 'updated', 1, '2025-03-09 07:59:55'),
(15, 200, 'golive_qa', 'updated', 15, '2025-03-09 08:01:04'),
(16, 200, 'completed', 'updated', 1, '2025-03-09 09:04:58');

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
) ENGINE=MyISAM AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
