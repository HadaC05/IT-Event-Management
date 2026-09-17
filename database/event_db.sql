-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 05:17 PM
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
-- Database: `event_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_account_password_reset_tokens`
--

CREATE TABLE `tbl_account_password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_activity_logs`
--

CREATE TABLE `tbl_activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `officer_assignment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `acting_role` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_activity_logs`
--

INSERT INTO `tbl_activity_logs` (`id`, `actor_id`, `subject_user_id`, `student_id`, `officer_assignment_id`, `event_id`, `action`, `acting_role`, `description`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, NULL, NULL, NULL, 'post_submitted', 'Student', 'Post #1 was submitted for adviser review.', '2026-09-11 15:46:57', '2026-09-11 15:46:57'),
(2, 14, NULL, NULL, NULL, NULL, 'post_approved', 'SBO Adviser', 'Post #1 was approved.', '2026-09-11 15:47:39', '2026-09-11 15:47:39'),
(3, 14, 14, NULL, NULL, 1, 'event_assigned', NULL, 'SBO Adviser was assigned to IT Days 2026.', '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(4, 14, NULL, NULL, NULL, 1, 'event_created', NULL, 'IT Days 2026 was created.', '2026-09-11 15:48:50', '2026-09-11 15:48:50'),
(5, 14, NULL, NULL, NULL, NULL, 'team_members_randomized', NULL, '12 active students were distributed across 4 tribes for SY 2026.', '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(6, 14, 15, '02-2026-000003', 1, NULL, 'officer_assigned', 'SBO Adviser', 'Student First Year 3 was assigned as attendance for 2027.', '2026-09-12 05:09:22', '2026-09-12 05:09:22'),
(7, 2, NULL, NULL, NULL, NULL, 'post_submitted', 'Student', 'Post #2 was submitted for adviser review.', '2026-09-13 06:18:43', '2026-09-13 06:18:43'),
(8, 14, 15, '02-2026-000003', 1, NULL, 'officer_password_changed', 'SBO Adviser', 'The password for SBO Officer login student.blue was changed by the adviser.', '2026-09-14 11:16:48', '2026-09-14 11:16:48'),
(9, 14, 15, '02-2026-000003', 1, NULL, 'officer_password_changed', 'SBO Adviser', 'The password for SBO Officer login student.blue was changed by the adviser.', '2026-09-14 11:18:54', '2026-09-14 11:18:54'),
(10, 15, 15, NULL, NULL, NULL, 'password_changed', 'SBO Officer', 'SBO Officer completed the required password change.', '2026-09-14 12:32:36', '2026-09-14 12:32:36'),
(11, 14, NULL, NULL, NULL, 2, 'event_created', 'SBO Adviser', 'IT Days 2026 was created.', '2026-09-15 16:56:32', '2026-09-15 16:56:32'),
(12, 14, 15, '02-2026-000003', 1, NULL, 'officer_password_changed', 'SBO Adviser', 'The password for SBO Officer login student.blue was changed by the adviser.', '2026-09-15 17:12:33', '2026-09-15 17:12:33'),
(13, 15, 15, NULL, NULL, NULL, 'password_changed', 'SBO Officer', 'SBO Officer completed the required password change.', '2026-09-15 17:13:19', '2026-09-15 17:13:19'),
(14, 14, NULL, NULL, 1, 2, 'sbo_event_assigned', 'SBO Adviser', 'Assigned attendance responsibility for Event duties.', '2026-09-15 17:13:40', '2026-09-15 17:13:40'),
(15, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-15 17:15:15', '2026-09-15 17:15:15'),
(16, 14, NULL, NULL, 1, 2, 'sbo_event_assigned', 'SBO Adviser', 'Assigned attendance responsibility for Event duties.', '2026-09-16 00:45:57', '2026-09-16 00:45:57'),
(17, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 02:50:00', '2026-09-16 02:50:00'),
(18, 14, 16, '02-2026-000001', 2, NULL, 'officer_assigned', 'SBO Adviser', 'Student First Year 1 was assigned as attendance for 2026.', '2026-09-16 02:57:06', '2026-09-16 02:57:06'),
(19, 16, 16, NULL, NULL, NULL, 'password_changed', 'SBO Officer', 'SBO Officer completed the required password change.', '2026-09-16 03:02:33', '2026-09-16 03:02:33'),
(20, 14, NULL, NULL, NULL, NULL, 'team_created', NULL, 'Leaderboard green was created without assigned members.', '2026-09-16 04:40:00', '2026-09-16 04:40:00'),
(21, 14, NULL, NULL, NULL, NULL, 'team_created', NULL, 'Blue Cobalt was created without assigned members.', '2026-09-16 04:40:38', '2026-09-16 04:40:38'),
(22, 14, 17, NULL, NULL, NULL, 'user_created', NULL, 'Micah D Lago was added as Student.', '2026-09-16 06:56:44', '2026-09-16 06:56:44'),
(23, 14, 18, NULL, NULL, NULL, 'user_created', NULL, 'jessie D Parajes was added as Student.', '2026-09-16 06:59:28', '2026-09-16 06:59:28'),
(24, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 07:00:22', '2026-09-16 07:00:22'),
(25, 14, 19, '02-2324-011281', 3, NULL, 'officer_assigned', 'SBO Adviser', 'jessie D Parajes was assigned as Attendance Officer for 2026-2027.', '2026-09-16 07:03:06', '2026-09-16 07:03:06'),
(26, 14, NULL, NULL, NULL, NULL, 'team_updated', NULL, 'Blue Cobalt was updated with 1 members.', '2026-09-16 07:03:30', '2026-09-16 07:03:30'),
(27, 19, 19, NULL, NULL, NULL, 'password_changed', 'SBO Officer', 'SBO Officer completed the required password change.', '2026-09-16 07:16:08', '2026-09-16 07:16:08'),
(28, 14, NULL, NULL, 3, 2, 'sbo_event_assigned', 'SBO Adviser', 'Assigned attendance responsibility for Event duties.', '2026-09-16 07:16:51', '2026-09-16 07:16:51'),
(29, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 07:18:36', '2026-09-16 07:18:36'),
(30, 19, NULL, NULL, 3, 2, 'sbo_attendance_scanned', 'SBO Officer', 'Recorded time in for 02-2324-011280.', '2026-09-16 07:23:56', '2026-09-16 07:23:56'),
(31, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 07:31:54', '2026-09-16 07:31:54'),
(32, 14, 20, NULL, NULL, NULL, 'user_created', NULL, 'mjay D calunsag was added as Student.', '2026-09-16 07:34:24', '2026-09-16 07:34:24'),
(33, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 07:35:19', '2026-09-16 07:35:19'),
(34, 14, NULL, NULL, NULL, 2, 'event_updated', 'SBO Adviser', 'IT Days 2026 was updated.', '2026-09-16 07:35:32', '2026-09-16 07:35:32'),
(35, 14, NULL, NULL, NULL, NULL, 'team_updated', NULL, 'Blue Cobalt was updated with 2 members.', '2026-09-16 07:37:35', '2026-09-16 07:37:35'),
(36, 14, NULL, NULL, 3, 2, 'sbo_event_assigned', 'SBO Adviser', 'Assigned attendance responsibility for Event duties.', '2026-09-16 07:39:54', '2026-09-16 07:39:54'),
(37, 14, NULL, NULL, 3, 2, 'sbo_event_unassigned', 'SBO Adviser', 'Ended an SBO event responsibility.', '2026-09-16 07:41:44', '2026-09-16 07:41:44'),
(38, 14, NULL, NULL, NULL, NULL, 'team_updated', NULL, 'Blue Cobalt was updated with 1 members.', '2026-09-16 07:44:58', '2026-09-16 07:44:58'),
(39, 14, NULL, NULL, NULL, NULL, 'team_updated', NULL, 'Green Falcons was updated with 4 members.', '2026-09-16 07:45:09', '2026-09-16 07:45:09'),
(41, 20, NULL, NULL, NULL, NULL, 'post_submitted', 'Student', 'Post #3 was submitted for adviser review.', '2026-09-16 07:48:56', '2026-09-16 07:48:56'),
(42, 14, NULL, NULL, NULL, NULL, 'post_approved', 'SBO Adviser', 'Post #3 was approved.', '2026-09-16 07:49:16', '2026-09-16 07:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attendances`
--

CREATE TABLE `tbl_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `morning_in_at` timestamp NULL DEFAULT NULL,
  `morning_out_at` timestamp NULL DEFAULT NULL,
  `afternoon_in_at` timestamp NULL DEFAULT NULL,
  `afternoon_out_at` timestamp NULL DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_attendances`
--

INSERT INTO `tbl_attendances` (`id`, `event_id`, `user_id`, `attendance_date`, `status`, `checked_in_at`, `morning_in_at`, `morning_out_at`, `afternoon_in_at`, `afternoon_out_at`, `recorded_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 17, '2026-09-16', 'present', '2026-09-16 07:23:56', '2026-09-16 07:23:56', NULL, NULL, NULL, 19, NULL, '2026-09-16 07:23:56', '2026-09-16 07:23:56');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attendance_entries`
--

CREATE TABLE `tbl_attendance_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `event_schedule_id` bigint(20) UNSIGNED NOT NULL,
  `sbo_event_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `session_code` varchar(12) NOT NULL,
  `phase` varchar(3) NOT NULL DEFAULT 'in',
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `scanned_at` datetime NOT NULL,
  `scan_latitude` decimal(10,7) DEFAULT NULL,
  `scan_longitude` decimal(10,7) DEFAULT NULL,
  `location_accuracy_m` decimal(10,2) DEFAULT NULL,
  `distance_from_venue_m` decimal(10,2) DEFAULT NULL,
  `location_status` enum('inside','outside','unavailable') NOT NULL DEFAULT 'unavailable',
  `location_captured_at` datetime DEFAULT NULL,
  `location_unavailable_reason` varchar(120) DEFAULT NULL,
  `venue_name_snapshot` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_attendance_entries`
--

INSERT INTO `tbl_attendance_entries` (`id`, `attendance_id`, `event_schedule_id`, `sbo_event_assignment_id`, `session_code`, `phase`, `activity_id`, `team_id`, `recorded_by`, `scanned_at`, `scan_latitude`, `scan_longitude`, `location_accuracy_m`, `distance_from_venue_m`, `location_status`, `location_captured_at`, `location_unavailable_reason`, `venue_name_snapshot`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 3, 'whole_day', 'in', 1, 6, 19, '2026-09-16 15:23:56', 8.4819630, 124.6361358, 10.63, 33.90, 'inside', '2026-09-16 15:23:56', NULL, 'PHINMA COC Carmen Campus', 'present', '2026-09-16 07:23:56', '2026-09-16 07:23:56');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attendance_qr_tokens`
--

CREATE TABLE `tbl_attendance_qr_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session` varchar(12) NOT NULL,
  `phase` varchar(3) NOT NULL DEFAULT 'in',
  `schedule_date` date DEFAULT NULL,
  `token` varchar(48) NOT NULL,
  `issued_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_attendance_qr_tokens`
--

INSERT INTO `tbl_attendance_qr_tokens` (`id`, `event_id`, `user_id`, `session`, `phase`, `schedule_date`, `token`, `issued_at`, `expires_at`, `used_at`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'whole_day', 'in', '2026-09-16', 'h6j3e5JHzbZpQ6GtNyB3ie_PS9aZzXee', '2026-09-16 01:46:57', '2026-09-16 01:47:27', NULL, '2026-09-15 17:00:27', '2026-09-15 17:46:57'),
(2, 2, 17, 'whole_day', 'in', '2026-09-16', 'apUKg8XUfgINKOLFvhGvWeSxC882hYw-', '2026-09-16 15:23:51', '2026-09-16 15:24:21', '2026-09-16 15:23:56', '2026-09-16 07:12:47', '2026-09-16 07:23:56'),
(3, 2, 20, 'whole_day', 'in', '2026-09-16', '04N4M0PXLSALKL7MjWziGsSpfHuFo1Pm', '2026-09-16 15:48:10', '2026-09-16 15:48:40', NULL, '2026-09-16 07:43:34', '2026-09-16 07:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_attendance_session_modes`
--

CREATE TABLE `tbl_attendance_session_modes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_attendance_session_modes`
--

INSERT INTO `tbl_attendance_session_modes` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(1, 'none', 'No attendance scanning', '2026-09-11 15:45:45', '2026-09-11 15:45:45'),
(2, 'whole_day', 'Whole day — one sign in and sign out', '2026-09-11 15:45:45', '2026-09-11 15:45:45'),
(3, 'two_sessions', 'Morning and afternoon — two sign-ins and sign-outs', '2026-09-11 15:45:45', '2026-09-11 15:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cache`
--

CREATE TABLE `tbl_cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_cache`
--

INSERT INTO `tbl_cache` (`key`, `value`, `expiration`) VALUES
('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba', 'i:2;', 1789278229),
('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba:timer', 'i:1789278229;', 1789278229);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cache_locks`
--

CREATE TABLE `tbl_cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_events`
--

CREATE TABLE `tbl_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attendance_location_policy` enum('off','warning','strict') NOT NULL DEFAULT 'off',
  `audience_type` varchar(30) NOT NULL DEFAULT 'all_students',
  `poster_path` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_order` int(10) UNSIGNED DEFAULT NULL,
  `featured_until` timestamp NULL DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `event_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_status_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_events`
--

INSERT INTO `tbl_events` (`id`, `title`, `description`, `location`, `location_id`, `attendance_location_policy`, `audience_type`, `poster_path`, `is_featured`, `featured_order`, `featured_until`, `start_at`, `end_at`, `event_type_id`, `event_status_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'IT Days 2026', 'test', 'PHINMA COC Carmen Campus', 1, 'off', 'selected_year_levels', 'assets/uploads/event-posters/W70sh2mMP9zsCz9YO3UPVGVwrOIHFGGbNVLF268j.png', 0, NULL, NULL, '2026-09-12 08:00:00', '2026-09-12 18:00:00', 1, 3, 14, '2026-09-11 15:48:49', '2026-09-16 03:05:50', NULL),
(2, 'IT Days 2026', NULL, 'PHINMA COC Carmen Campus', 1, 'off', 'all_students', NULL, 0, NULL, NULL, '2026-09-16 15:08:00', '2026-09-16 22:00:00', 1, 2, 14, '2026-09-15 16:56:32', '2026-09-16 07:35:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_activities`
--

CREATE TABLE `tbl_event_activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_activities`
--

INSERT INTO `tbl_event_activities` (`id`, `event_id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'Event duties', NULL, 'active', 14, '2026-09-15 17:13:40', '2026-09-15 17:13:40');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_attendance_schedules`
--

CREATE TABLE `tbl_event_attendance_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_date` date NOT NULL,
  `attendance_session_mode_id` bigint(20) UNSIGNED NOT NULL,
  `whole_day_in_time` time DEFAULT NULL,
  `whole_day_in_close_time` time DEFAULT NULL,
  `whole_day_out_time` time DEFAULT NULL,
  `whole_day_out_open_time` time DEFAULT NULL,
  `morning_in_time` time DEFAULT NULL,
  `morning_in_close_time` time DEFAULT NULL,
  `morning_out_time` time DEFAULT NULL,
  `morning_out_open_time` time DEFAULT NULL,
  `afternoon_in_time` time DEFAULT NULL,
  `afternoon_in_close_time` time DEFAULT NULL,
  `afternoon_out_time` time DEFAULT NULL,
  `afternoon_out_open_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_attendance_schedules`
--

INSERT INTO `tbl_event_attendance_schedules` (`id`, `event_id`, `schedule_date`, `attendance_session_mode_id`, `whole_day_in_time`, `whole_day_in_close_time`, `whole_day_out_time`, `whole_day_out_open_time`, `morning_in_time`, `morning_in_close_time`, `morning_out_time`, `morning_out_open_time`, `afternoon_in_time`, `afternoon_in_close_time`, `afternoon_out_time`, `afternoon_out_open_time`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-09-12', 2, '08:00:00', '17:30:00', '18:00:00', '17:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(2, 2, '2026-09-16', 2, '15:08:00', '15:50:00', '22:00:00', '21:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-15 16:56:32', '2026-09-16 07:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_participants`
--

CREATE TABLE `tbl_event_participants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_statuses`
--

CREATE TABLE `tbl_event_statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_statuses`
--

INSERT INTO `tbl_event_statuses` (`id`, `label`, `created_at`, `updated_at`) VALUES
(1, 'upcoming', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(2, 'ongoing', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(3, 'completed', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(4, 'archived', '2026-09-11 15:45:47', '2026-09-11 15:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_team`
--

CREATE TABLE `tbl_event_team` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_types`
--

CREATE TABLE `tbl_event_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_types`
--

INSERT INTO `tbl_event_types` (`id`, `label`, `created_at`, `updated_at`) VALUES
(1, 'IT Days', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(2, 'IT Expo', '2026-09-11 15:45:47', '2026-09-11 15:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_user`
--

CREATE TABLE `tbl_event_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_user`
--

INSERT INTO `tbl_event_user` (`id`, `event_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 14, '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(9, 2, 14, '2026-09-16 07:35:32', '2026-09-16 07:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_event_year_level`
--

CREATE TABLE `tbl_event_year_level` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `year_level_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_event_year_level`
--

INSERT INTO `tbl_event_year_level` (`id`, `event_id`, `year_level_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(2, 1, 2, '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(3, 1, 3, '2026-09-11 15:48:49', '2026-09-11 15:48:49'),
(4, 1, 4, '2026-09-11 15:48:50', '2026-09-11 15:48:50');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_failed_jobs`
--

CREATE TABLE `tbl_failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_jobs`
--

CREATE TABLE `tbl_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_job_batches`
--

CREATE TABLE `tbl_job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_locations`
--

CREATE TABLE `tbl_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('general','specific') NOT NULL,
  `parent_location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius` decimal(10,2) DEFAULT NULL COMMENT 'Square geofence center-to-edge distance in meters',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_locations`
--

INSERT INTO `tbl_locations` (`id`, `name`, `type`, `parent_location_id`, `latitude`, `longitude`, `radius`, `created_at`, `updated_at`) VALUES
(1, 'PHINMA COC Carmen Campus', 'general', NULL, 8.4822620, 124.6361958, 50.00, '2026-09-11 15:45:47', '2026-09-16 03:05:50'),
(2, 'MS Computer Lab 1', 'specific', 1, NULL, NULL, NULL, '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(3, 'PH 310', 'specific', 1, NULL, NULL, NULL, '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(4, 'jessss', 'general', NULL, 8.4702571, 124.6341782, 1.00, '2026-09-14 16:00:32', '2026-09-14 16:00:32'),
(5, 'Kk', 'general', NULL, 8.4699237, 124.6342058, 20.00, '2026-09-14 16:55:10', '2026-09-14 16:55:10');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_migrations`
--

CREATE TABLE `tbl_migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_migrations`
--

INSERT INTO `tbl_migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_02_234744_create_roles_table', 1),
(5, '2026_09_03_020127_create_year_levels_table', 1),
(6, '2026_09_03_021642_create_user_statuses_table', 1),
(7, '2026_09_03_023253_add_custom_fields_to_user_table', 1),
(8, '2026_09_03_040614_create_school_years_table', 1),
(9, '2026_09_03_042239_create_event_types_table', 1),
(10, '2026_09_03_043911_create_event_statuses_table', 1),
(11, '2026_09_04_131102_event_db', 1),
(12, '2026_09_05_000001_create_events_table', 1),
(13, '2026_09_05_000002_create_event_user_table', 1),
(14, '2026_09_05_000003_create_activity_logs_table', 1),
(15, '2026_09_05_000004_add_management_fields_to_events_table', 1),
(16, '2026_09_06_000005_create_teams_table', 1),
(17, '2026_09_06_000006_create_team_user_table', 1),
(18, '2026_09_06_000007_create_attendances_table', 1),
(19, '2026_09_06_000008_create_scores_table', 1),
(20, '2026_09_07_000009_add_audience_to_events', 1),
(21, '2026_09_07_000010_create_score_categories_table', 1),
(22, '2026_09_07_000011_add_officer_attendance_scans', 1),
(23, '2026_09_08_000012_create_student_profiles_and_officer_assignments', 1),
(24, '2026_09_08_000013_finalize_linked_student_account_audit', 1),
(25, '2026_09_09_000014_seed_it_games_attendance_test_data', 1),
(26, '2026_09_09_000015_create_attendance_qr_tokens_table', 1),
(27, '2026_09_09_000016_add_teams_randomized_at_to_school_years', 1),
(28, '2026_09_09_000017_add_daily_event_attendance_schedules', 1),
(29, '2026_09_09_000018_add_session_mode_to_event_attendance_schedules', 1),
(30, '2026_09_09_000019_remove_student_profiles', 1),
(31, '2026_09_09_000020_create_attendance_session_modes', 1),
(32, '2026_09_09_000021_remove_event_attendance_time_columns', 1),
(33, '2026_09_09_000022_create_locations_table', 1),
(34, '2026_09_09_000023_change_location_type_to_enum', 1),
(35, '2026_09_10_000024_replace_active_event_status', 1),
(36, '2026_09_11_000025_create_student_portal_tables', 1),
(37, '2026_09_11_000026_add_soft_deletes_to_posts', 1),
(38, '2026_09_11_000027_add_feature_fields_to_events_table', 1),
(39, '2026_09_12_000028_create_post_engagement_tables', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notifications`
--

CREATE TABLE `tbl_notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_notifications`
--

INSERT INTO `tbl_notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('7a87c08e-e938-4e3c-b84f-6b84799be5e6', 'App\\Notifications\\PostReviewed', 'App\\Models\\User', 2, '{\"post_id\":1,\"status\":\"approved\",\"reason\":null,\"message\":\"Your post was approved.\"}', '2026-09-13 06:02:08', '2026-09-11 15:47:39', '2026-09-13 06:02:08'),
('7c0d0018-7c84-492e-a416-adc91b36bcf7', 'App\\Notifications\\PostReviewed', 'App\\Models\\User', 20, '{\"post_id\":3,\"status\":\"approved\",\"reason\":null,\"message\":\"Your post was approved.\"}', NULL, '2026-09-16 07:49:16', '2026-09-16 07:49:16'),
('9976f955-0bb1-4462-9f84-d79301352eb4', 'App\\Notifications\\PostReviewSubmitted', 'App\\Models\\User', 14, '{\"post_id\":1,\"message\":\"Student First Year 2 submitted a post for review.\"}', NULL, '2026-09-11 15:46:58', '2026-09-11 15:46:58'),
('d147ff4e-b949-4ee0-a5ae-20b3010bbf4f', 'post_submitted', 'AppModelsUser', 14, '{\"post_id\":3,\"student_id\":20,\"message\":\"mjay D calunsag submitted a post for review.\"}', NULL, '2026-09-16 07:48:56', '2026-09-16 07:48:56'),
('f22c5e82-abac-4ec4-82bb-9cfa235c8569', 'post_submitted', 'AppModelsUser', 14, '{\"post_id\":2,\"student_id\":2,\"message\":\"Student First Year 2 submitted a post for review.\"}', NULL, '2026-09-13 06:18:43', '2026-09-13 06:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_password_reset_tokens`
--

CREATE TABLE `tbl_password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_posts`
--

CREATE TABLE `tbl_posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sbo_event_assignment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'general',
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_posts`
--

INSERT INTO `tbl_posts` (`id`, `user_id`, `event_id`, `activity_id`, `sbo_event_assignment_id`, `category`, `content`, `image_path`, `video_path`, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`, `deleted_at`, `is_official`) VALUES
(1, 2, NULL, NULL, NULL, 'special-events', 'test', 'assets/uploads/posts/zIkJIDrCYdSHIFcoPDfABkPa9OmoB9RT3wZMuRUX.png', NULL, 'approved', NULL, 14, '2026-09-11 15:47:39', '2026-09-11 15:46:57', '2026-09-11 15:47:39', NULL, 0),
(2, 2, NULL, NULL, NULL, 'general', 'asdas', NULL, NULL, 'pending', NULL, NULL, NULL, '2026-09-13 06:18:43', '2026-09-13 06:18:43', NULL, 0),
(3, 20, NULL, NULL, NULL, 'general', 'Mga bayot', NULL, NULL, 'approved', NULL, 14, '2026-09-16 07:49:16', '2026-09-16 07:48:56', '2026-09-16 07:49:16', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_post_audits`
--

CREATE TABLE `tbl_post_audits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_post_audits`
--

INSERT INTO `tbl_post_audits` (`id`, `post_id`, `actor_id`, `action`, `from_status`, `to_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'submitted', NULL, 'pending', NULL, '2026-09-11 15:46:57', '2026-09-11 15:46:57'),
(2, 1, 14, 'approved', 'pending', 'approved', NULL, '2026-09-11 15:47:39', '2026-09-11 15:47:39'),
(3, 2, 2, 'submitted', NULL, 'pending', NULL, '2026-09-13 06:18:43', '2026-09-13 06:18:43'),
(4, 3, 20, 'submitted', NULL, 'pending', NULL, '2026-09-16 07:48:56', '2026-09-16 07:48:56'),
(5, 3, 14, 'approved', 'pending', 'approved', NULL, '2026-09-16 07:49:16', '2026-09-16 07:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_post_comments`
--

CREATE TABLE `tbl_post_comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_post_comments`
--

INSERT INTO `tbl_post_comments` (`id`, `post_id`, `user_id`, `body`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'basta', '2026-09-11 16:26:38', '2026-09-11 16:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_post_reactions`
--

CREATE TABLE `tbl_post_reactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'like',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roles`
--

CREATE TABLE `tbl_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_roles`
--

INSERT INTO `tbl_roles` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'SBO Officer', '2026-09-11 15:45:43', '2026-09-11 15:45:43'),
(2, 'SBO Adviser', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(3, 'SBO', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(4, 'Faculty', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(5, 'Student', '2026-09-11 15:45:47', '2026-09-11 15:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sbo_event_assignments`
--

CREATE TABLE `tbl_sbo_event_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `officer_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `event_schedule_id` bigint(20) UNSIGNED NOT NULL,
  `session_code` varchar(12) NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `responsibility` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `ended_by` bigint(20) UNSIGNED DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_sbo_event_assignments`
--

INSERT INTO `tbl_sbo_event_assignments` (`id`, `officer_assignment_id`, `event_schedule_id`, `session_code`, `activity_id`, `team_id`, `responsibility`, `status`, `assigned_by`, `ended_by`, `ended_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'whole_day', 1, 2, 'attendance', 'active', 14, NULL, NULL, '2026-09-15 17:13:40', '2026-09-15 17:13:40'),
(2, 1, 2, 'whole_day', 1, 1, 'attendance', 'active', 14, NULL, NULL, '2026-09-16 00:45:57', '2026-09-16 00:45:57'),
(3, 3, 2, 'whole_day', 1, 6, 'attendance', 'inactive', 14, 14, '2026-09-16 07:41:44', '2026-09-16 07:16:51', '2026-09-16 07:41:44'),
(4, 3, 2, 'whole_day', 1, 1, 'attendance', 'active', 14, NULL, NULL, '2026-09-16 07:39:54', '2026-09-16 07:39:54');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sbo_officer_assignments`
--

CREATE TABLE `tbl_sbo_officer_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` varchar(255) NOT NULL,
  `officer_user_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scanner_mode` varchar(8) NOT NULL DEFAULT 'specific',
  `position` varchar(100) NOT NULL,
  `term` varchar(100) NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_by` bigint(20) UNSIGNED DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_sbo_officer_assignments`
--

INSERT INTO `tbl_sbo_officer_assignments` (`id`, `student_id`, `officer_user_id`, `team_id`, `position`, `term`, `assigned_by`, `assigned_at`, `ended_by`, `ended_at`, `status`, `created_at`, `updated_at`) VALUES
(1, '02-2026-000003', 15, 2, 'attendance', '2027', 14, '2026-09-12 05:09:22', NULL, NULL, 'Active', '2026-09-12 05:09:22', '2026-09-12 05:09:22'),
(2, '02-2026-000001', 16, 1, 'attendance', '2026', 14, '2026-09-16 02:57:06', NULL, NULL, 'Active', '2026-09-16 02:57:06', '2026-09-16 02:57:06'),
(3, '02-2324-011281', 19, 6, 'Attendance Officer', '2026-2027', 14, '2026-09-16 07:03:06', NULL, NULL, 'Active', '2026-09-16 07:03:06', '2026-09-16 07:03:06');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sbo_scan_rate_limits`
--

CREATE TABLE `tbl_sbo_scan_rate_limits` (
  `officer_user_id` bigint(20) UNSIGNED NOT NULL,
  `window_started_at` datetime NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_sbo_scan_rate_limits`
--

INSERT INTO `tbl_sbo_scan_rate_limits` (`officer_user_id`, `window_started_at`, `attempts`, `updated_at`) VALUES
(19, '2026-09-16 15:47:12', 1, '2026-09-16 15:47:12');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_school_years`
--

CREATE TABLE `tbl_school_years` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `teams_randomized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_school_years`
--

INSERT INTO `tbl_school_years` (`id`, `label`, `teams_randomized_at`, `created_at`, `updated_at`) VALUES
(1, '2026', '2026-09-12 05:06:41', '2026-09-11 15:45:47', '2026-09-12 05:06:41');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_scores`
--

CREATE TABLE `tbl_scores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `score_category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `points` decimal(10,2) NOT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_score_categories`
--

CREATE TABLE `tbl_score_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `min_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_points` decimal(10,2) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_score_sheets`
--

CREATE TABLE `tbl_score_sheets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `sbo_event_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `reopened_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sessions`
--

CREATE TABLE `tbl_sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_sessions`
--

INSERT INTO `tbl_sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('1PKW7DbThzrSyo1Bk5IASUs1WgH7pQ3ZXbKZOMgR', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidU9zYzN0YnlJemRsNmkwdVFNUVdyMGVlN1E3M3VSbHBQYzZIODFKdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1789278170),
('2EOXjBmV1cNw55CFG1zpbAjpFVjI6NkYtmJH34rU', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoib2hUVXVRZEZMSFFBRzRoQVVzZlJtQmc1WTd5TUlSb3doSUtFYXFtaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1789278180),
('gzykAL2cjBSMB4cvldK1UcUIw9oQJQFgObaYfufs', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiYjVNVFEzbzlvVm1LUGVieFdwMDBwc1dzenFhTFAyRkYwT0JOdG1BciI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2Rhc2hib2FyZCI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMxOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjk6ImRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjI7fQ==', 1789203441),
('lPEJh3MwkZtQAuwGbKxwKzxb7EXA5tTibPmSle8S', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.137.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWUU4Z1RrV0hLalR4cW1ZTFB4UzNzWlNQcWowYjllMGdYU1RUNVVOZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1789278145),
('q8Ih5Sqq1OvHLzYFL8gt1Kwa5WT9xtom9AF9rD8r', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiNzVtcEdJa2RiTFRsUXhGN0RyTWUxbHQyTDFIR1RZdGRSNWYwaXdtbyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Mjt9', 1789278191),
('R6xB5NXHsNecXtcOCuhV1ywmzmm4wuMm50u9bOgi', 15, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR0R0dTBuSTRrSm9XUHFxeDhpdEhjQzdsc3lDU2ZrTks1QkdORHhSQSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozODoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2FkdmlzZXIvb2ZmaWNlcnMiO31zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czo0MDoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL29mZmljZXIvYXR0ZW5kYW5jZSI7czo1OiJyb3V0ZSI7czoyNDoib2ZmaWNlci5hdHRlbmRhbmNlLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTU7fQ==', 1789190384),
('WRi4gOvTWGgzhd0DKJUS63vRK4MzhD5m5nIJBjDY', 14, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiTkZoZHdMMUo2WjlIdE1kZ0NLVUY5YmtYSE56dmN2VEwyVHByc3FmViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzg6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZHZpc2VyL29mZmljZXJzIjtzOjU6InJvdXRlIjtzOjIyOiJhZHZpc2VyLm9mZmljZXJzLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTQ7fQ==', 1789190026),
('Y0O7RAOrPwfdrG7efAi8sulAIH2V3xXEXHVRs8QM', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.137.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoidEtOMmxTcnlDM1BSTFV0bnZNYlJKVTl3eEFidDNybzVzZzNlS3F4VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1789189105);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teams`
--

CREATE TABLE `tbl_teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `school_year_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#41B06E',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_teams`
--

INSERT INTO `tbl_teams` (`id`, `school_year_id`, `name`, `color`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Green Falcons', '#397565', 1, '2026-09-12 05:05:33', '2026-09-16 07:45:09'),
(2, 1, 'Blue Sharks', '#2F3AE0', 1, '2026-09-12 05:05:33', '2026-09-12 05:05:33'),
(3, 1, 'Yellow Tigers', '#D4A017', 1, '2026-09-12 05:05:33', '2026-09-12 05:05:33'),
(4, 1, 'Red Lions', '#DC2626', 1, '2026-09-12 05:05:33', '2026-09-12 05:05:33'),
(5, 1, 'Leaderboard green', '#397565', 1, '2026-09-16 04:40:00', '2026-09-16 04:40:00'),
(6, 1, 'Blue Cobalt', '#2F3AE0', 1, '2026-09-16 04:40:38', '2026-09-16 07:44:58');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_team_user`
--

CREATE TABLE `tbl_team_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_team_user`
--

INSERT INTO `tbl_team_user` (`id`, `team_id`, `user_id`, `created_at`, `updated_at`) VALUES
(4, 2, 9, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(5, 2, 11, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(6, 2, 3, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(7, 3, 2, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(8, 3, 5, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(9, 3, 4, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(10, 4, 7, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(11, 4, 12, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(12, 4, 1, '2026-09-12 05:06:41', '2026-09-12 05:06:41'),
(16, 6, 17, '2026-09-16 07:44:58', '2026-09-16 07:44:58'),
(17, 1, 20, '2026-09-16 07:45:09', '2026-09-16 07:45:09'),
(18, 1, 10, '2026-09-16 07:45:09', '2026-09-16 07:45:09'),
(19, 1, 6, '2026-09-16 07:45:09', '2026-09-16 07:45:09'),
(20, 1, 8, '2026-09-16 07:45:09', '2026-09-16 07:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `year_level` bigint(20) UNSIGNED DEFAULT NULL,
  `officer_team_id` bigint(20) UNSIGNED DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `status` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `bio` varchar(280) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`id`, `role_id`, `id_number`, `first_name`, `middle_name`, `last_name`, `year_level`, `officer_team_id`, `username`, `password`, `must_change_password`, `status`, `email`, `email_verified_at`, `profile_photo_path`, `bio`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 5, '02-2026-000001', 'Student', NULL, 'First Year 1', 1, NULL, 'student.1', '$2y$10$niFXH2gKuGscfKUcvNhFpuvYNetSEql8kLJGdIIGkTtaYZMzi5Jqa', 0, 1, 'student1@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:48', '2026-09-15 16:52:57'),
(2, 5, '02-2026-000002', 'Student', NULL, 'First Year 2', 1, NULL, 'student.2', '$2y$12$d9sSWozwG.nSbuDBnpBK.uhKqIhbgOSJer/olqnnO3oHwp6SD9TSe', 0, 1, 'student2@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:48', '2026-09-11 15:45:48'),
(3, 5, '02-2026-000003', 'Student', NULL, 'First Year 3', 1, NULL, 'student.3', '$2y$12$x4cgSsAwXCZAG0XWS7xe5eGmbOq/BesCPYnvF9ZXgHsqi4y1/Wzkq', 0, 1, 'student3@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:48', '2026-09-11 15:45:48'),
(4, 5, '02-2026-000004', 'Student', NULL, 'Second Year 1', 2, NULL, 'student.4', '$2y$12$rhHOhM91kaoVYzipsl4jpeqMU9gC.p8l/58XwC9LlJ5XBZ4VUSX8S', 0, 1, 'student4@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:49', '2026-09-11 15:45:49'),
(5, 5, '02-2026-000005', 'Student', NULL, 'Second Year 2', 2, NULL, 'student.5', '$2y$12$GXp0FQfRodYhEbb3uAvSZubV26zGLJ3IiISRdrAFu3W7DQuUp80Ve', 0, 1, 'student5@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:49', '2026-09-11 15:45:49'),
(6, 5, '02-2026-000006', 'Student', NULL, 'Second Year 3', 2, NULL, 'student.6', '$2y$12$wm3wrb90coKsXqIhn9sKs.I0jQP3jqQdBu1Y8nHxCC08wAr4mcIvS', 0, 1, 'student6@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:50', '2026-09-11 15:45:50'),
(7, 5, '02-2026-000007', 'Student', NULL, 'Third Year 1', 3, NULL, 'student.7', '$2y$12$5AF4xt8t4H9AqjfH02POceDAIMAbYK9MyekAqsh6X7bEw7MxfXEHu', 0, 1, 'student7@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:50', '2026-09-11 15:45:50'),
(8, 5, '02-2026-000008', 'Student', NULL, 'Third Year 2', 3, NULL, 'student.8', '$2y$12$EoSdqnXjmDhJ.8ysPYy8SO5bdVVBcYW43ko4enmrdjcJnBwPO1r/u', 0, 1, 'student8@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:50', '2026-09-11 15:45:50'),
(9, 5, '02-2026-000009', 'Student', NULL, 'Third Year 3', 3, NULL, 'student.9', '$2y$12$4xr5EuAhfxINZ794n/kUU.nLWvUvzD9I/gri2WH8oEF1NOkrhyZ4a', 0, 1, 'student9@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:51', '2026-09-11 15:45:51'),
(10, 5, '02-2026-000010', 'Student', NULL, 'Fourth Year 1', 4, NULL, 'student.10', '$2y$12$NGmLrLAKPkTf1tBK0wzV1uMS14UM/XJprZFWIC2nZ9Di6.JGtrlfS', 0, 1, 'student10@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:51', '2026-09-11 15:45:51'),
(11, 5, '02-2026-000011', 'Student', NULL, 'Fourth Year 2', 4, NULL, 'student.11', '$2y$12$UDNq9yx2i.PEDcXy7Qv9su/n/Dk7bpiMr9EFHIUdOH327r4/A3OPa', 0, 1, 'student11@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:52', '2026-09-11 15:45:52'),
(12, 5, '02-2026-000012', 'Student', NULL, 'Fourth Year 3', 4, NULL, 'student.12', '$2y$12$N7xg6/.SwG2J8g1xy0kbhOq2ETl.yjVdphRw9MloUjku5.4nlscEm', 0, 1, 'student12@cite.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:52', '2026-09-11 15:45:52'),
(13, NULL, '00000001', 'Test', NULL, 'User', NULL, NULL, 'testuser', '$2y$12$yK34qNcXoqCznpPFqu2IluUZtK0vuSF53yvfgoc7xzpgVf4./by.a', 0, NULL, 'test@example.com', NULL, NULL, NULL, NULL, '2026-09-11 15:45:53', '2026-09-11 15:45:53'),
(14, 2, 'SBO-ADV-001', 'SBO', NULL, 'Adviser', NULL, NULL, 'sbo.adviser', '$2y$12$L5TwgVhfKq6uscpYKpQSOua44hO5wWiuk06mSzw1xJlLFL1LCzkl6', 0, 1, 'adviser@itevents.local', NULL, NULL, NULL, NULL, '2026-09-11 15:45:54', '2026-09-11 15:45:54'),
(15, 1, '02-2026-000003', 'Student', NULL, 'First Year 3', 1, 2, 'student.blue', '$2y$10$fVlGoT7gvEpRM7397xlEpeD6ffynOvzmSasBFbC4KwR64MBK/C5CG', 0, 1, 'student3@cite.local', NULL, NULL, NULL, NULL, '2026-09-12 05:09:22', '2026-09-15 17:13:19'),
(16, 1, '02-2026-000001', 'Student', NULL, 'First Year 1', 1, 1, 'student.green', '$2y$10$G6zrjkmyQs8/G69UVHF8j.vlWnRmsU3uXU58XMTEZUXLKl1ZsCagC', 0, 1, 'student1@cite.local', NULL, NULL, NULL, NULL, '2026-09-16 02:57:06', '2026-09-16 03:02:33'),
(17, 5, '02-2324-011280', 'Micah', 'D', 'Lago', 4, NULL, 'micah2026', '$2y$10$r/cROsPk7hWW4e8zCncwkeKBRUd1T82qA.wbX81rqoi2wG/6O.ZMy', 0, 1, 'micah@gmail.com', NULL, NULL, NULL, NULL, '2026-09-16 06:56:44', '2026-09-16 06:56:44'),
(18, 5, '02-2324-011281', 'jessie', 'D', 'Parajes', 1, NULL, 'jessiejames', '$2y$10$/htQc9UinMa3oCIA2WwPNui4EfBjuP3kLx2MhVIWDn3/4vAp51Vqm', 0, 1, 'jessiejames123@gmai.com', NULL, NULL, NULL, NULL, '2026-09-16 06:59:28', '2026-09-16 06:59:28'),
(19, 1, '02-2324-011281', 'jessie', 'D', 'Parajes', 1, 6, 'jessie.blue', '$2y$10$4H1qzt4iQcstc8y2ZoCg6.jIOZ04p.FShJ.QjBXK2eT3N6D4leicS', 0, 1, 'jessiejames123@gmai.com', NULL, NULL, NULL, NULL, '2026-09-16 07:03:06', '2026-09-16 07:16:08'),
(20, 5, '02-2324-011290', 'mjay', 'D', 'calunsag', NULL, NULL, 'mjay', '$2y$10$vjr/1gJkgqjyCZlxVNtxmOyUIMiEeqLtVIGrlm28PseE2b0XlQAyO', 0, 1, 'mjay@gmail.com', NULL, NULL, NULL, NULL, '2026-09-16 07:34:24', '2026-09-16 07:43:15');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_statuses`
--

CREATE TABLE `tbl_user_statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_user_statuses`
--

INSERT INTO `tbl_user_statuses` (`id`, `label`, `created_at`, `updated_at`) VALUES
(1, 'active', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(2, 'inactive', '2026-09-11 15:45:47', '2026-09-11 15:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_year_levels`
--

CREATE TABLE `tbl_year_levels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_year_levels`
--

INSERT INTO `tbl_year_levels` (`id`, `label`, `created_at`, `updated_at`) VALUES
(1, 'First Year', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(2, 'Second Year', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(3, 'Third Year', '2026-09-11 15:45:47', '2026-09-11 15:45:47'),
(4, 'Fourth Year', '2026-09-11 15:45:47', '2026-09-11 15:45:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_account_password_reset_tokens`
--
ALTER TABLE `tbl_account_password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_password_reset_tokens_user_id_unique` (`user_id`);

--
-- Indexes for table `tbl_activity_logs`
--
ALTER TABLE `tbl_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_actor_id_foreign` (`actor_id`),
  ADD KEY `activity_logs_subject_user_id_foreign` (`subject_user_id`),
  ADD KEY `activity_logs_event_id_foreign` (`event_id`),
  ADD KEY `activity_logs_officer_assignment_id_foreign` (`officer_assignment_id`);

--
-- Indexes for table `tbl_attendances`
--
ALTER TABLE `tbl_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendances_event_user_date_unique` (`event_id`,`user_id`,`attendance_date`),
  ADD KEY `attendances_user_id_foreign` (`user_id`),
  ADD KEY `attendances_recorded_by_foreign` (`recorded_by`),
  ADD KEY `attendances_event_id_status_index` (`event_id`,`status`);

--
-- Indexes for table `tbl_attendance_entries`
--
ALTER TABLE `tbl_attendance_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendance_entries_session_phase_unique` (`attendance_id`,`event_schedule_id`,`session_code`,`phase`,`activity_id`,`team_id`),
  ADD UNIQUE KEY `attendance_entries_student_phase_unique` (`attendance_id`,`event_schedule_id`,`session_code`,`phase`),
  ADD KEY `attendance_entries_assignment_index` (`sbo_event_assignment_id`,`scanned_at`),
  ADD KEY `attendance_entries_recorded_by_foreign` (`recorded_by`),
  ADD KEY `attendance_entries_schedule_foreign` (`event_schedule_id`),
  ADD KEY `attendance_entries_activity_foreign` (`activity_id`),
  ADD KEY `attendance_entries_team_foreign` (`team_id`),
  ADD KEY `attendance_entries_recent_event_index` (`event_schedule_id`,`session_code`,`scanned_at`),
  ADD KEY `attendance_entries_location_status_index` (`location_status`,`scanned_at`);

--
-- Indexes for table `tbl_attendance_qr_tokens`
--
ALTER TABLE `tbl_attendance_qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendance_qr_tokens_token_unique` (`token`),
  ADD UNIQUE KEY `attendance_qr_tokens_student_phase_unique` (`event_id`,`user_id`,`session`,`phase`),
  ADD KEY `attendance_qr_tokens_user_id_foreign` (`user_id`);

--
-- Indexes for table `tbl_attendance_session_modes`
--
ALTER TABLE `tbl_attendance_session_modes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendance_session_modes_code_unique` (`code`);

--
-- Indexes for table `tbl_cache`
--
ALTER TABLE `tbl_cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `tbl_cache_locks`
--
ALTER TABLE `tbl_cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `tbl_events`
--
ALTER TABLE `tbl_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `events_event_type_id_foreign` (`event_type_id`),
  ADD KEY `events_event_status_id_foreign` (`event_status_id`),
  ADD KEY `events_created_by_foreign` (`created_by`),
  ADD KEY `events_is_featured_featured_order_index` (`is_featured`,`featured_order`),
  ADD KEY `events_location_id_index` (`location_id`);

--
-- Indexes for table `tbl_event_activities`
--
ALTER TABLE `tbl_event_activities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_activities_event_name_unique` (`event_id`,`name`),
  ADD KEY `event_activities_created_by_foreign` (`created_by`);

--
-- Indexes for table `tbl_event_attendance_schedules`
--
ALTER TABLE `tbl_event_attendance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_attendance_schedules_event_id_schedule_date_unique` (`event_id`,`schedule_date`),
  ADD KEY `event_attendance_schedules_attendance_session_mode_id_foreign` (`attendance_session_mode_id`);

--
-- Indexes for table `tbl_event_participants`
--
ALTER TABLE `tbl_event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_participants_event_id_user_id_unique` (`event_id`,`user_id`),
  ADD KEY `event_participants_user_id_foreign` (`user_id`);

--
-- Indexes for table `tbl_event_statuses`
--
ALTER TABLE `tbl_event_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_statuses_label_unique` (`label`);

--
-- Indexes for table `tbl_event_team`
--
ALTER TABLE `tbl_event_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_team_event_id_team_id_unique` (`event_id`,`team_id`),
  ADD KEY `event_team_team_id_foreign` (`team_id`);

--
-- Indexes for table `tbl_event_types`
--
ALTER TABLE `tbl_event_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_types_label_unique` (`label`);

--
-- Indexes for table `tbl_event_user`
--
ALTER TABLE `tbl_event_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user_event_id_user_id_unique` (`event_id`,`user_id`),
  ADD KEY `event_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `tbl_event_year_level`
--
ALTER TABLE `tbl_event_year_level`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_year_level_event_id_year_level_id_unique` (`event_id`,`year_level_id`),
  ADD KEY `event_year_level_year_level_id_foreign` (`year_level_id`);

--
-- Indexes for table `tbl_failed_jobs`
--
ALTER TABLE `tbl_failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `tbl_jobs`
--
ALTER TABLE `tbl_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `tbl_job_batches`
--
ALTER TABLE `tbl_job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_locations`
--
ALTER TABLE `tbl_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `locations_name_unique` (`name`),
  ADD KEY `locations_parent_location_id_index` (`parent_location_id`);

--
-- Indexes for table `tbl_migrations`
--
ALTER TABLE `tbl_migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_notifications`
--
ALTER TABLE `tbl_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `tbl_password_reset_tokens`
--
ALTER TABLE `tbl_password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `tbl_posts`
--
ALTER TABLE `tbl_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posts_event_id_foreign` (`event_id`),
  ADD KEY `posts_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `posts_status_created_at_index` (`status`,`created_at`),
  ADD KEY `posts_user_id_status_index` (`user_id`,`status`),
  ADD KEY `posts_official_status_created_index` (`is_official`,`status`,`created_at`),
  ADD KEY `posts_activity_foreign` (`activity_id`),
  ADD KEY `posts_sbo_assignment_foreign` (`sbo_event_assignment_id`);

--
-- Indexes for table `tbl_post_audits`
--
ALTER TABLE `tbl_post_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_audits_post_id_foreign` (`post_id`),
  ADD KEY `post_audits_actor_id_foreign` (`actor_id`);

--
-- Indexes for table `tbl_post_comments`
--
ALTER TABLE `tbl_post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_comments_user_id_foreign` (`user_id`),
  ADD KEY `post_comments_post_id_created_at_index` (`post_id`,`created_at`);

--
-- Indexes for table `tbl_post_reactions`
--
ALTER TABLE `tbl_post_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_reactions_post_id_user_id_unique` (`post_id`,`user_id`),
  ADD KEY `post_reactions_user_id_foreign` (`user_id`);

--
-- Indexes for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_unique` (`name`);

--
-- Indexes for table `tbl_sbo_event_assignments`
--
ALTER TABLE `tbl_sbo_event_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sbo_event_assignments_officer_index` (`officer_assignment_id`,`status`),
  ADD KEY `sbo_event_assignments_schedule_index` (`event_schedule_id`,`session_code`,`status`),
  ADD KEY `sbo_event_assignments_activity_foreign` (`activity_id`),
  ADD KEY `sbo_event_assignments_team_foreign` (`team_id`),
  ADD KEY `sbo_event_assignments_assigned_by_foreign` (`assigned_by`),
  ADD KEY `sbo_event_assignments_ended_by_foreign` (`ended_by`);

--
-- Indexes for table `tbl_sbo_officer_assignments`
--
ALTER TABLE `tbl_sbo_officer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sbo_officer_assignments_officer_user_id_foreign` (`officer_user_id`),
  ADD KEY `sbo_officer_assignments_team_id_foreign` (`team_id`),
  ADD KEY `sbo_officer_assignments_assigned_by_foreign` (`assigned_by`),
  ADD KEY `sbo_officer_assignments_ended_by_foreign` (`ended_by`),
  ADD KEY `officer_student_term_status_index` (`student_id`,`term`,`status`);

--
-- Indexes for table `tbl_sbo_scan_rate_limits`
--
ALTER TABLE `tbl_sbo_scan_rate_limits`
  ADD PRIMARY KEY (`officer_user_id`);

--
-- Indexes for table `tbl_school_years`
--
ALTER TABLE `tbl_school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_years_label_unique` (`label`);

--
-- Indexes for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `scores_event_team_category_unique` (`event_id`,`team_id`,`score_category_id`),
  ADD KEY `scores_recorded_by_foreign` (`recorded_by`),
  ADD KEY `scores_team_id_points_index` (`team_id`,`points`),
  ADD KEY `scores_score_category_id_foreign` (`score_category_id`),
  ADD KEY `scores_event_id_index` (`event_id`);

--
-- Indexes for table `tbl_score_categories`
--
ALTER TABLE `tbl_score_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `score_categories_event_id_name_unique` (`event_id`,`name`),
  ADD KEY `score_categories_event_id_sort_order_index` (`event_id`,`sort_order`),
  ADD KEY `score_categories_activity_foreign` (`activity_id`);

--
-- Indexes for table `tbl_score_sheets`
--
ALTER TABLE `tbl_score_sheets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `score_sheets_event_activity_unique` (`event_id`,`activity_id`),
  ADD KEY `score_sheets_assignment_foreign` (`sbo_event_assignment_id`),
  ADD KEY `score_sheets_submitted_by_foreign` (`submitted_by`),
  ADD KEY `score_sheets_reopened_by_foreign` (`reopened_by`),
  ADD KEY `score_sheets_activity_foreign` (`activity_id`);

--
-- Indexes for table `tbl_sessions`
--
ALTER TABLE `tbl_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `tbl_teams`
--
ALTER TABLE `tbl_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teams_school_year_id_name_unique` (`school_year_id`,`name`);

--
-- Indexes for table `tbl_team_user`
--
ALTER TABLE `tbl_team_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`),
  ADD KEY `team_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD KEY `users_role_id_foreign` (`role_id`),
  ADD KEY `users_year_level_foreign` (`year_level`),
  ADD KEY `users_status_foreign` (`status`),
  ADD KEY `users_officer_team_id_foreign` (`officer_team_id`);

--
-- Indexes for table `tbl_user_statuses`
--
ALTER TABLE `tbl_user_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_statuses_label_unique` (`label`);

--
-- Indexes for table `tbl_year_levels`
--
ALTER TABLE `tbl_year_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_levels_label_unique` (`label`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_account_password_reset_tokens`
--
ALTER TABLE `tbl_account_password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_activity_logs`
--
ALTER TABLE `tbl_activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `tbl_attendances`
--
ALTER TABLE `tbl_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_attendance_entries`
--
ALTER TABLE `tbl_attendance_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_attendance_qr_tokens`
--
ALTER TABLE `tbl_attendance_qr_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_attendance_session_modes`
--
ALTER TABLE `tbl_attendance_session_modes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_events`
--
ALTER TABLE `tbl_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_event_activities`
--
ALTER TABLE `tbl_event_activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_event_attendance_schedules`
--
ALTER TABLE `tbl_event_attendance_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_event_participants`
--
ALTER TABLE `tbl_event_participants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_event_statuses`
--
ALTER TABLE `tbl_event_statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_event_team`
--
ALTER TABLE `tbl_event_team`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_event_types`
--
ALTER TABLE `tbl_event_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_event_user`
--
ALTER TABLE `tbl_event_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_event_year_level`
--
ALTER TABLE `tbl_event_year_level`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_failed_jobs`
--
ALTER TABLE `tbl_failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_jobs`
--
ALTER TABLE `tbl_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_locations`
--
ALTER TABLE `tbl_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_migrations`
--
ALTER TABLE `tbl_migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `tbl_posts`
--
ALTER TABLE `tbl_posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_post_audits`
--
ALTER TABLE `tbl_post_audits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_post_comments`
--
ALTER TABLE `tbl_post_comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_post_reactions`
--
ALTER TABLE `tbl_post_reactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_roles`
--
ALTER TABLE `tbl_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_sbo_event_assignments`
--
ALTER TABLE `tbl_sbo_event_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_sbo_officer_assignments`
--
ALTER TABLE `tbl_sbo_officer_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_school_years`
--
ALTER TABLE `tbl_school_years`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_score_categories`
--
ALTER TABLE `tbl_score_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_score_sheets`
--
ALTER TABLE `tbl_score_sheets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_teams`
--
ALTER TABLE `tbl_teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_team_user`
--
ALTER TABLE `tbl_team_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_user_statuses`
--
ALTER TABLE `tbl_user_statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_year_levels`
--
ALTER TABLE `tbl_year_levels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_account_password_reset_tokens`
--
ALTER TABLE `tbl_account_password_reset_tokens`
  ADD CONSTRAINT `account_password_reset_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_activity_logs`
--
ALTER TABLE `tbl_activity_logs`
  ADD CONSTRAINT `activity_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_officer_assignment_id_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `tbl_sbo_officer_assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_logs_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_attendances`
--
ALTER TABLE `tbl_attendances`
  ADD CONSTRAINT `attendances_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_attendance_entries`
--
ALTER TABLE `tbl_attendance_entries`
  ADD CONSTRAINT `attendance_entries_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_entries_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_entries_attendance_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `tbl_attendances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_entries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_entries_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_entries_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_attendance_qr_tokens`
--
ALTER TABLE `tbl_attendance_qr_tokens`
  ADD CONSTRAINT `attendance_qr_tokens_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_qr_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_events`
--
ALTER TABLE `tbl_events`
  ADD CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_event_status_id_foreign` FOREIGN KEY (`event_status_id`) REFERENCES `tbl_event_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_event_type_id_foreign` FOREIGN KEY (`event_type_id`) REFERENCES `tbl_event_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `events_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_event_activities`
--
ALTER TABLE `tbl_event_activities`
  ADD CONSTRAINT `event_activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `event_activities_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_event_attendance_schedules`
--
ALTER TABLE `tbl_event_attendance_schedules`
  ADD CONSTRAINT `event_attendance_schedules_attendance_session_mode_id_foreign` FOREIGN KEY (`attendance_session_mode_id`) REFERENCES `tbl_attendance_session_modes` (`id`),
  ADD CONSTRAINT `event_attendance_schedules_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_event_participants`
--
ALTER TABLE `tbl_event_participants`
  ADD CONSTRAINT `event_participants_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_event_team`
--
ALTER TABLE `tbl_event_team`
  ADD CONSTRAINT `event_team_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_team_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_event_user`
--
ALTER TABLE `tbl_event_user`
  ADD CONSTRAINT `event_user_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_event_year_level`
--
ALTER TABLE `tbl_event_year_level`
  ADD CONSTRAINT `event_year_level_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_year_level_year_level_id_foreign` FOREIGN KEY (`year_level_id`) REFERENCES `tbl_year_levels` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_locations`
--
ALTER TABLE `tbl_locations`
  ADD CONSTRAINT `locations_parent_location_id_foreign` FOREIGN KEY (`parent_location_id`) REFERENCES `tbl_locations` (`id`);

--
-- Constraints for table `tbl_posts`
--
ALTER TABLE `tbl_posts`
  ADD CONSTRAINT `posts_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_sbo_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_post_audits`
--
ALTER TABLE `tbl_post_audits`
  ADD CONSTRAINT `post_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `post_audits_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_post_comments`
--
ALTER TABLE `tbl_post_comments`
  ADD CONSTRAINT `post_comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_post_reactions`
--
ALTER TABLE `tbl_post_reactions`
  ADD CONSTRAINT `post_reactions_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_sbo_event_assignments`
--
ALTER TABLE `tbl_sbo_event_assignments`
  ADD CONSTRAINT `sbo_event_assignments_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbo_event_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sbo_event_assignments_ended_by_foreign` FOREIGN KEY (`ended_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sbo_event_assignments_officer_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `tbl_sbo_officer_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbo_event_assignments_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbo_event_assignments_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_sbo_officer_assignments`
--
ALTER TABLE `tbl_sbo_officer_assignments`
  ADD CONSTRAINT `sbo_officer_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sbo_officer_assignments_ended_by_foreign` FOREIGN KEY (`ended_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sbo_officer_assignments_officer_user_id_foreign` FOREIGN KEY (`officer_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sbo_officer_assignments_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_sbo_scan_rate_limits`
--
ALTER TABLE `tbl_sbo_scan_rate_limits`
  ADD CONSTRAINT `sbo_scan_rate_limits_officer_foreign` FOREIGN KEY (`officer_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  ADD CONSTRAINT `scores_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scores_score_category_id_foreign` FOREIGN KEY (`score_category_id`) REFERENCES `tbl_score_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_score_categories`
--
ALTER TABLE `tbl_score_categories`
  ADD CONSTRAINT `score_categories_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `score_categories_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_score_sheets`
--
ALTER TABLE `tbl_score_sheets`
  ADD CONSTRAINT `score_sheets_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `score_sheets_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `score_sheets_event_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `score_sheets_reopened_by_foreign` FOREIGN KEY (`reopened_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `score_sheets_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_teams`
--
ALTER TABLE `tbl_teams`
  ADD CONSTRAINT `teams_school_year_id_foreign` FOREIGN KEY (`school_year_id`) REFERENCES `tbl_school_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_team_user`
--
ALTER TABLE `tbl_team_user`
  ADD CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `users_officer_team_id_foreign` FOREIGN KEY (`officer_team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `tbl_roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_status_foreign` FOREIGN KEY (`status`) REFERENCES `tbl_user_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_year_level_foreign` FOREIGN KEY (`year_level`) REFERENCES `tbl_year_levels` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
