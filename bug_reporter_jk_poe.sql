-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 08, 2025 at 04:18 AM
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
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `stage`, `title`, `how_to_check`, `how_to_fix`, `created_by`) VALUES
(1, 'wp_conversion', 'Mobile responsive ness', 'check iphone', 'check iphone', 1),
(2, 'wp_conversion', 'WP conversion check list 1', 'WP conversion check list 1', 'WP conversion check list 1', 1),
(3, 'wp_conversion', 'WP conversion check list 2', 'WP conversion check list 2', 'WP conversion check list 2', 1),
(4, 'page_creation', 'Page creation check list 1', 'Page creation check list 1', 'Page creation check list 1', 1),
(5, 'page_creation', 'Page creation check list 2', 'Page creation check list 2', 'Page creation check list 2', 1),
(6, 'page_creation', 'Page creation check list 3', 'Page creation check list 3', 'Page creation check list 3', 1),
(7, 'golive', 'Golive check list 1', 'Golive check list 1', 'Golive check list 1', 1),
(8, 'golive', 'Golive check list 2', 'Golive check list 2', 'Golive check list 2', 1),
(9, 'golive', 'Golive check list 3', 'Golive check list 3', 'Golive check list 3', 1),
(10, 'wp_conversion', 'another one ', 'df', 'dfd', 1),
(11, 'golive', 'golive chck 1', 'aaa', 'aaa', 1),
(12, 'wp_conversion', 'here\\\'s another', 'test', 'test', 1);

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
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `project_id`, `checklist_item_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 2, 1, 3, 'Fixed ', '2025-02-01 02:47:15'),
(2, 2, 1, 2, 'not fixed this one', '2025-02-01 05:20:36'),
(3, 4, 4, 3, 'Fixed this ', '2025-02-05 01:06:09'),
(4, 4, 5, 3, 'Fixed this ', '2025-02-05 01:06:27'),
(5, 4, 6, 3, 'Fixed this too ', '2025-02-05 01:06:36'),
(6, 4, 4, 4, 'This is not done ', '2025-02-05 01:07:42'),
(7, 4, 5, 4, 'this is passed ', '2025-02-05 01:08:01'),
(8, 4, 4, 3, 'Fixed again', '2025-02-05 01:08:57'),
(9, 1, 1, 1, 'correct ~', '2025-02-06 01:37:08'),
(10, 1, 1, 1, 'correct ~', '2025-02-06 01:37:08'),
(11, 1, 2, 1, 'dfdfd', '2025-02-06 01:39:56'),
(12, 1, 2, 1, 'dfdfd', '2025-02-06 01:39:56'),
(13, 3, 4, 3, 'fxed it', '2025-02-06 02:18:33'),
(14, 6, 4, 1, 'why this happened ?', '2025-02-06 09:41:23'),
(15, 6, 4, 6, 'Fixed it , why happened non of ur business ', '2025-02-06 09:42:31'),
(16, 6, 4, 4, 'u are not doing it right', '2025-02-06 09:44:08'),
(17, 6, 4, 6, 'agan fixed told u non of ur business ', '2025-02-06 11:57:35'),
(18, 7, 2, 7, 'I fixed this ', '2025-02-08 02:09:07'),
(19, 7, 3, 7, 'Fixed this alos ', '2025-02-08 02:09:19'),
(20, 7, 2, 8, 'still not fixed ', '2025-02-08 02:09:53'),
(21, 7, 3, 8, 'no not good', '2025-02-08 02:10:07'),
(22, 7, 2, 7, 'ok again fixed ', '2025-02-08 02:10:36'),
(23, 7, 3, 7, 'again fixed ', '2025-02-08 02:10:49'),
(24, 7, 4, 7, 'fixed', '2025-02-08 02:11:58'),
(25, 7, 5, 7, 'fixed', '2025-02-08 02:12:04'),
(26, 7, 6, 7, 'fixed', '2025-02-08 02:12:10'),
(27, 7, 4, 8, 'passed', '2025-02-08 02:12:28'),
(28, 7, 5, 8, 'passed', '2025-02-08 02:12:38'),
(29, 7, 6, 8, 'passed', '2025-02-08 02:12:44'),
(30, 7, 6, 8, 'no failed', '2025-02-08 02:13:04');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `webmaster_id` int DEFAULT NULL,
  `current_status` enum('wp_conversion','wp_conversion_qa','page_creation','page_creation_qa','golive','golive_qa','completed') DEFAULT 'wp_conversion',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `webmaster_id` (`webmaster_id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `webmaster_id`, `current_status`, `created_at`, `updated_at`) VALUES
(1, 'Dr. Dolittle', 3, 'page_creation', '2025-01-30 10:01:53', '2025-02-06 01:37:08'),
(2, 'Dr. Strange', 3, 'completed', '2025-01-30 10:02:06', '2025-02-03 05:00:58'),
(3, 'Dr. Sam', 3, 'golive', '2025-02-03 05:02:36', '2025-02-06 04:00:23'),
(4, 'Dr. Alan Walker', 3, 'completed', '2025-02-04 02:05:44', '2025-02-06 00:53:17'),
(5, 'Dr. Sam', 5, 'page_creation', '2025-02-06 00:59:13', '2025-02-06 07:09:35'),
(6, 'Dr. Sameera', 6, 'golive', '2025-02-06 07:15:28', '2025-02-07 02:21:19'),
(7, 'Dr. ABC', 7, 'completed', '2025-02-08 02:05:34', '2025-02-08 02:15:37'),
(8, 'Dr. A', 3, 'wp_conversion', '2025-02-08 03:40:35', '2025-02-08 03:40:35'),
(9, 'Dr. B', 3, 'wp_conversion', '2025-02-08 03:40:42', '2025-02-08 03:40:42'),
(10, 'Dr. C', 3, 'wp_conversion', '2025-02-08 03:40:50', '2025-02-08 03:40:50'),
(11, 'Dr. D', 3, 'wp_conversion', '2025-02-08 03:40:56', '2025-02-08 03:40:56'),
(12, 'Dr. B', 3, 'wp_conversion', '2025-02-08 03:41:02', '2025-02-08 03:41:02'),
(13, 'Dr. D', 3, 'wp_conversion', '2025-02-08 03:41:16', '2025-02-08 03:41:16'),
(14, 'Dr. E', 3, 'wp_conversion', '2025-02-08 03:41:25', '2025-02-08 03:41:25'),
(15, 'Dr. F', 3, 'wp_conversion', '2025-02-08 03:41:32', '2025-02-08 03:41:32'),
(16, 'Dr. G', 3, 'wp_conversion', '2025-02-08 03:41:42', '2025-02-08 03:41:42'),
(17, 'Dr. H', 3, 'wp_conversion', '2025-02-08 03:41:47', '2025-02-08 03:41:47'),
(18, 'Dr. I ', 3, 'page_creation', '2025-02-08 03:41:53', '2025-02-08 04:09:34'),
(19, 'Dr. M', 7, 'page_creation', '2025-02-08 04:01:15', '2025-02-08 04:11:00');

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
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `checklist_item_id` (`checklist_item_id`),
  KEY `updated_by` (`updated_by`)
) ENGINE=MyISAM AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `project_checklist_status`
--

INSERT INTO `project_checklist_status` (`id`, `project_id`, `checklist_item_id`, `status`, `updated_at`, `updated_by`) VALUES
(1, 3, 1, 'passed', '2025-02-03 05:20:01', 4),
(2, 3, 2, 'passed', '2025-02-03 05:10:48', 4),
(3, 3, 3, 'passed', '2025-02-03 05:10:44', 4),
(4, 3, 4, 'passed', '2025-02-06 04:00:10', 1),
(5, 3, 5, 'passed', '2025-02-06 04:00:18', 1),
(6, 3, 6, 'passed', '2025-02-06 04:00:23', 1),
(7, 3, 7, 'idle', '2025-02-03 05:02:36', NULL),
(8, 3, 8, 'idle', '2025-02-03 05:02:36', NULL),
(9, 3, 9, 'idle', '2025-02-03 05:02:36', NULL),
(10, 4, 1, 'passed', '2025-02-05 00:56:15', 1),
(11, 4, 2, 'passed', '2025-02-05 00:55:56', 1),
(12, 4, 3, 'passed', '2025-02-05 00:57:25', 1),
(13, 4, 4, 'passed', '2025-02-05 01:09:30', 4),
(14, 4, 5, 'passed', '2025-02-05 01:08:01', 4),
(15, 4, 6, 'passed', '2025-02-05 01:08:11', 4),
(16, 4, 7, 'passed', '2025-02-06 00:53:17', 1),
(17, 4, 8, 'passed', '2025-02-06 00:53:16', 1),
(18, 4, 9, 'passed', '2025-02-06 00:53:15', 1),
(19, 5, 1, 'passed', '2025-02-06 05:30:36', 1),
(20, 5, 2, 'passed', '2025-02-06 05:32:38', 1),
(21, 5, 3, 'passed', '2025-02-06 05:33:05', 1),
(22, 5, 4, 'passed', '2025-02-06 07:09:26', 1),
(23, 5, 5, 'failed', '2025-02-06 07:09:35', 1),
(24, 5, 6, 'fixed', '2025-02-06 07:08:58', 5),
(25, 5, 7, 'idle', '2025-02-06 00:59:13', NULL),
(26, 5, 8, 'idle', '2025-02-06 00:59:13', NULL),
(27, 5, 9, 'idle', '2025-02-06 00:59:13', NULL),
(28, 6, 1, 'passed', '2025-02-06 07:48:25', 1),
(29, 6, 2, 'passed', '2025-02-06 07:48:33', 1),
(30, 6, 3, 'passed', '2025-02-06 07:48:39', 1),
(31, 6, 4, 'passed', '2025-02-07 02:21:19', 4),
(32, 6, 5, 'passed', '2025-02-06 09:03:19', 4),
(33, 6, 6, 'passed', '2025-02-06 09:03:30', 4),
(34, 6, 7, 'idle', '2025-02-06 07:15:28', NULL),
(35, 6, 8, 'idle', '2025-02-06 07:15:28', NULL),
(36, 6, 9, 'idle', '2025-02-06 07:15:28', NULL),
(37, 7, 1, 'passed', '2025-02-08 02:08:08', 8),
(38, 7, 2, 'passed', '2025-02-08 02:11:21', 8),
(39, 7, 3, 'passed', '2025-02-08 02:11:26', 8),
(40, 7, 4, 'passed', '2025-02-08 02:12:28', 8),
(41, 7, 5, 'passed', '2025-02-08 02:12:38', 8),
(42, 7, 6, 'passed', '2025-02-08 02:14:14', 8),
(43, 7, 7, 'passed', '2025-02-08 02:15:37', 8),
(44, 7, 8, 'passed', '2025-02-08 02:15:14', 8),
(45, 7, 9, 'passed', '2025-02-08 02:15:16', 8),
(46, 8, 1, 'idle', '2025-02-08 03:40:35', NULL),
(47, 8, 2, 'idle', '2025-02-08 03:40:35', NULL),
(48, 8, 3, 'idle', '2025-02-08 03:40:35', NULL),
(49, 8, 4, 'idle', '2025-02-08 03:40:35', NULL),
(50, 8, 5, 'idle', '2025-02-08 03:40:35', NULL),
(51, 8, 6, 'idle', '2025-02-08 03:40:35', NULL),
(52, 8, 7, 'idle', '2025-02-08 03:40:35', NULL),
(53, 8, 8, 'idle', '2025-02-08 03:40:35', NULL),
(54, 8, 9, 'idle', '2025-02-08 03:40:35', NULL),
(55, 9, 1, 'idle', '2025-02-08 03:40:42', NULL),
(56, 9, 2, 'idle', '2025-02-08 03:40:42', NULL),
(57, 9, 3, 'idle', '2025-02-08 03:40:42', NULL),
(58, 9, 4, 'idle', '2025-02-08 03:40:42', NULL),
(59, 9, 5, 'idle', '2025-02-08 03:40:42', NULL),
(60, 9, 6, 'idle', '2025-02-08 03:40:42', NULL),
(61, 9, 7, 'idle', '2025-02-08 03:40:42', NULL),
(62, 9, 8, 'idle', '2025-02-08 03:40:42', NULL),
(63, 9, 9, 'idle', '2025-02-08 03:40:42', NULL),
(64, 10, 1, 'idle', '2025-02-08 03:40:50', NULL),
(65, 10, 2, 'idle', '2025-02-08 03:40:50', NULL),
(66, 10, 3, 'idle', '2025-02-08 03:40:50', NULL),
(67, 10, 4, 'idle', '2025-02-08 03:40:50', NULL),
(68, 10, 5, 'idle', '2025-02-08 03:40:50', NULL),
(69, 10, 6, 'idle', '2025-02-08 03:40:50', NULL),
(70, 10, 7, 'idle', '2025-02-08 03:40:50', NULL),
(71, 10, 8, 'idle', '2025-02-08 03:40:50', NULL),
(72, 10, 9, 'idle', '2025-02-08 03:40:50', NULL),
(73, 11, 1, 'idle', '2025-02-08 03:40:56', NULL),
(74, 11, 2, 'idle', '2025-02-08 03:40:56', NULL),
(75, 11, 3, 'idle', '2025-02-08 03:40:56', NULL),
(76, 11, 4, 'idle', '2025-02-08 03:40:56', NULL),
(77, 11, 5, 'idle', '2025-02-08 03:40:56', NULL),
(78, 11, 6, 'idle', '2025-02-08 03:40:56', NULL),
(79, 11, 7, 'idle', '2025-02-08 03:40:56', NULL),
(80, 11, 8, 'idle', '2025-02-08 03:40:56', NULL),
(81, 11, 9, 'idle', '2025-02-08 03:40:56', NULL),
(82, 12, 1, 'idle', '2025-02-08 03:41:02', NULL),
(83, 12, 2, 'idle', '2025-02-08 03:41:02', NULL),
(84, 12, 3, 'idle', '2025-02-08 03:41:02', NULL),
(85, 12, 4, 'idle', '2025-02-08 03:41:02', NULL),
(86, 12, 5, 'idle', '2025-02-08 03:41:02', NULL),
(87, 12, 6, 'idle', '2025-02-08 03:41:02', NULL),
(88, 12, 7, 'idle', '2025-02-08 03:41:02', NULL),
(89, 12, 8, 'idle', '2025-02-08 03:41:02', NULL),
(90, 12, 9, 'idle', '2025-02-08 03:41:02', NULL),
(91, 13, 1, 'idle', '2025-02-08 03:41:16', NULL),
(92, 13, 2, 'idle', '2025-02-08 03:41:16', NULL),
(93, 13, 3, 'idle', '2025-02-08 03:41:16', NULL),
(94, 13, 4, 'idle', '2025-02-08 03:41:16', NULL),
(95, 13, 5, 'idle', '2025-02-08 03:41:16', NULL),
(96, 13, 6, 'idle', '2025-02-08 03:41:16', NULL),
(97, 13, 7, 'idle', '2025-02-08 03:41:16', NULL),
(98, 13, 8, 'idle', '2025-02-08 03:41:16', NULL),
(99, 13, 9, 'idle', '2025-02-08 03:41:16', NULL),
(100, 14, 1, 'idle', '2025-02-08 03:41:25', NULL),
(101, 14, 2, 'idle', '2025-02-08 03:41:25', NULL),
(102, 14, 3, 'idle', '2025-02-08 03:41:25', NULL),
(103, 14, 4, 'idle', '2025-02-08 03:41:25', NULL),
(104, 14, 5, 'idle', '2025-02-08 03:41:25', NULL),
(105, 14, 6, 'idle', '2025-02-08 03:41:25', NULL),
(106, 14, 7, 'idle', '2025-02-08 03:41:25', NULL),
(107, 14, 8, 'idle', '2025-02-08 03:41:25', NULL),
(108, 14, 9, 'idle', '2025-02-08 03:41:25', NULL),
(109, 15, 1, 'idle', '2025-02-08 03:41:32', NULL),
(110, 15, 2, 'idle', '2025-02-08 03:41:32', NULL),
(111, 15, 3, 'idle', '2025-02-08 03:41:32', NULL),
(112, 15, 4, 'idle', '2025-02-08 03:41:32', NULL),
(113, 15, 5, 'idle', '2025-02-08 03:41:32', NULL),
(114, 15, 6, 'idle', '2025-02-08 03:41:32', NULL),
(115, 15, 7, 'idle', '2025-02-08 03:41:32', NULL),
(116, 15, 8, 'idle', '2025-02-08 03:41:32', NULL),
(117, 15, 9, 'idle', '2025-02-08 03:41:32', NULL),
(118, 16, 1, 'idle', '2025-02-08 03:41:42', NULL),
(119, 16, 2, 'idle', '2025-02-08 03:41:42', NULL),
(120, 16, 3, 'idle', '2025-02-08 03:41:42', NULL),
(121, 16, 4, 'idle', '2025-02-08 03:41:42', NULL),
(122, 16, 5, 'idle', '2025-02-08 03:41:42', NULL),
(123, 16, 6, 'idle', '2025-02-08 03:41:42', NULL),
(124, 16, 7, 'idle', '2025-02-08 03:41:42', NULL),
(125, 16, 8, 'idle', '2025-02-08 03:41:42', NULL),
(126, 16, 9, 'idle', '2025-02-08 03:41:42', NULL),
(127, 17, 1, 'idle', '2025-02-08 03:41:47', NULL),
(128, 17, 2, 'idle', '2025-02-08 03:41:47', NULL),
(129, 17, 3, 'idle', '2025-02-08 03:41:47', NULL),
(130, 17, 4, 'idle', '2025-02-08 03:41:47', NULL),
(131, 17, 5, 'idle', '2025-02-08 03:41:47', NULL),
(132, 17, 6, 'idle', '2025-02-08 03:41:47', NULL),
(133, 17, 7, 'idle', '2025-02-08 03:41:47', NULL),
(134, 17, 8, 'idle', '2025-02-08 03:41:47', NULL),
(135, 17, 9, 'idle', '2025-02-08 03:41:47', NULL),
(136, 18, 1, 'passed', '2025-02-08 04:09:26', 1),
(137, 18, 2, 'passed', '2025-02-08 04:09:31', 1),
(138, 18, 3, 'passed', '2025-02-08 04:09:34', 1),
(139, 18, 4, 'idle', '2025-02-08 03:41:53', NULL),
(140, 18, 5, 'idle', '2025-02-08 03:41:53', NULL),
(141, 18, 6, 'idle', '2025-02-08 03:41:53', NULL),
(142, 18, 7, 'idle', '2025-02-08 03:41:53', NULL),
(143, 18, 8, 'idle', '2025-02-08 03:41:53', NULL),
(144, 18, 9, 'idle', '2025-02-08 03:41:53', NULL),
(145, 19, 1, 'passed', '2025-02-08 04:10:44', 1),
(146, 19, 2, 'passed', '2025-02-08 04:10:49', 1),
(147, 19, 3, 'passed', '2025-02-08 04:10:52', 1),
(148, 19, 4, 'idle', '2025-02-08 04:01:15', NULL),
(149, 19, 5, 'idle', '2025-02-08 04:01:15', NULL),
(150, 19, 6, 'idle', '2025-02-08 04:01:15', NULL),
(151, 19, 7, 'idle', '2025-02-08 04:01:15', NULL),
(152, 19, 8, 'idle', '2025-02-08 04:01:15', NULL),
(153, 19, 9, 'idle', '2025-02-08 04:01:15', NULL),
(154, 19, 10, 'passed', '2025-02-08 04:10:56', 1),
(155, 19, 11, 'idle', '2025-02-08 04:01:15', NULL),
(156, 19, 12, 'passed', '2025-02-08 04:11:00', 1);

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
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `qa_assignments`
--

INSERT INTO `qa_assignments` (`id`, `project_id`, `qa_user_id`, `assigned_by`, `assigned_at`) VALUES
(1, 6, 4, 2, '2025-02-07 02:16:57'),
(2, 5, 4, 1, '2025-02-07 17:20:36'),
(3, 7, 8, 2, '2025-02-08 02:07:54'),
(4, 18, 8, 1, '2025-02-08 03:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','qa_manager','qa_reporter','webmaster') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$SQVKnic.JlJH5S2KiIVQe.846ZiQ4IevYKF2F.x47RJkt0zoOjx7C', 'admin', '2025-01-30 09:56:02'),
(2, 'nadeeka', '$2y$10$0o0SKJvLeLpmO7.yK7h4xuN784BQDz.rFsx/reqh855VG3Wj3QBhW', 'qa_manager', '2025-01-30 09:56:49'),
(3, 'webmaster', '$2y$10$h8KdQz7TOhr4JFaRSsJFxejGLbKKU7kC64frWgWoYFLAZ27FT55a6', 'webmaster', '2025-01-30 09:57:15'),
(4, 'qa_reporter', '$2y$10$CIzdwwRHOEXvCNX2nA06Qe/pjCuGJYPMVP8GbbxIazELOGHYxvE8K', 'qa_reporter', '2025-01-30 09:57:37'),
(5, 'janith', '$2y$10$Ei/Gg9tJq2HGinNHrj2Htu2onsWfxDZFniwBURvWHbkLgRF1CSdOO', 'webmaster', '2025-02-06 00:58:24'),
(6, 'sameera', '$2y$10$/iywSLAKSrProsclqCf5YOi/JVresZlamj9G3KFxe0VudfPv86nr2', 'webmaster', '2025-02-06 07:15:07'),
(7, 'menuka', '$2y$10$0VLnuWQKvGh1pBiTGKu3Aupz2ChnuZcTAgiocs/iT9qh5tsBOFYPe', 'webmaster', '2025-02-08 02:05:09'),
(8, 'shifnas', '$2y$10$P6VDkkIJpJXWOGJtZDGT9Ove7v8RLXNYH7x3vlo7u1fA0OVKReMua', 'qa_reporter', '2025-02-08 02:07:05');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
