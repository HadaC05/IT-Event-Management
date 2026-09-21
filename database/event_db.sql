-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: event_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tbl_academic_periods`
--

DROP TABLE IF EXISTS `tbl_academic_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_academic_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_year_id` bigint(20) unsigned NOT NULL,
  `term_code` varchar(30) NOT NULL,
  `term_name` varchar(80) NOT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_period_school_year_term_unique` (`school_year_id`,`term_code`),
  KEY `academic_period_active_index` (`is_active`,`starts_on`,`ends_on`),
  CONSTRAINT `academic_period_school_year_foreign` FOREIGN KEY (`school_year_id`) REFERENCES `tbl_school_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_academic_periods`
--

LOCK TABLES `tbl_academic_periods` WRITE;
/*!40000 ALTER TABLE `tbl_academic_periods` DISABLE KEYS */;
INSERT INTO `tbl_academic_periods` VALUES (1,1,'first_semester','First Semester',NULL,NULL,1,'2026-09-21 10:26:34','2026-09-21 10:26:34');
/*!40000 ALTER TABLE `tbl_academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_account_password_reset_tokens`
--

DROP TABLE IF EXISTS `tbl_account_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_account_password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_password_reset_tokens_user_id_unique` (`user_id`),
  CONSTRAINT `account_password_reset_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_account_password_reset_tokens`
--

LOCK TABLES `tbl_account_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `tbl_account_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_account_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_activities`
--

DROP TABLE IF EXISTS `tbl_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type_id` bigint(20) unsigned NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `activities_event_type_label_unique` (`event_type_id`,`label`),
  KEY `activities_event_type_id_foreign` (`event_type_id`),
  CONSTRAINT `activities_event_type_id_foreign` FOREIGN KEY (`event_type_id`) REFERENCES `tbl_event_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_activities`
--

LOCK TABLES `tbl_activities` WRITE;
/*!40000 ALTER TABLE `tbl_activities` DISABLE KEYS */;
INSERT INTO `tbl_activities` VALUES (1,'123',NULL,1,'active','2026-09-17 17:34:43','2026-09-17 17:34:43'),(2,'Event duties',NULL,1,'active','2026-09-15 17:13:40','2026-09-17 12:31:00'),(3,'jkj',NULL,1,'active','2026-09-17 13:16:06','2026-09-17 13:16:06');
/*!40000 ALTER TABLE `tbl_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_activity_logs`
--

DROP TABLE IF EXISTS `tbl_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `subject_user_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `officer_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `acting_role` varchar(50) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_actor_id_foreign` (`actor_id`),
  KEY `activity_logs_subject_user_id_foreign` (`subject_user_id`),
  KEY `activity_logs_event_id_foreign` (`event_id`),
  KEY `activity_logs_officer_assignment_id_foreign` (`officer_assignment_id`),
  CONSTRAINT `activity_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_officer_assignment_id_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `tbl_sbo_officer_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_subject_user_id_foreign` FOREIGN KEY (`subject_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_activity_logs`
--

LOCK TABLES `tbl_activity_logs` WRITE;
/*!40000 ALTER TABLE `tbl_activity_logs` DISABLE KEYS */;
INSERT INTO `tbl_activity_logs` VALUES (1,2,NULL,NULL,NULL,NULL,'post_submitted','Student','Post #1 was submitted for adviser review.','2026-09-11 15:46:57','2026-09-11 15:46:57'),(2,14,NULL,NULL,NULL,NULL,'post_approved','SBO Adviser','Post #1 was approved.','2026-09-11 15:47:39','2026-09-11 15:47:39'),(3,14,14,NULL,NULL,1,'event_assigned',NULL,'SBO Adviser was assigned to IT Days 2026.','2026-09-11 15:48:49','2026-09-11 15:48:49'),(4,14,NULL,NULL,NULL,1,'event_created',NULL,'IT Days 2026 was created.','2026-09-11 15:48:50','2026-09-11 15:48:50'),(5,14,NULL,NULL,NULL,NULL,'team_members_randomized',NULL,'12 active students were distributed across 4 tribes for SY 2026.','2026-09-12 05:06:41','2026-09-12 05:06:41'),(6,14,15,'02-2026-000003',1,NULL,'officer_assigned','SBO Adviser','Student First Year 3 was assigned as attendance for 2027.','2026-09-12 05:09:22','2026-09-12 05:09:22'),(7,2,NULL,NULL,NULL,NULL,'post_submitted','Student','Post #2 was submitted for adviser review.','2026-09-13 06:18:43','2026-09-13 06:18:43'),(8,14,15,'02-2026-000003',1,NULL,'officer_password_changed','SBO Adviser','The password for SBO Officer login student.blue was changed by the adviser.','2026-09-14 11:16:48','2026-09-14 11:16:48'),(9,14,15,'02-2026-000003',1,NULL,'officer_password_changed','SBO Adviser','The password for SBO Officer login student.blue was changed by the adviser.','2026-09-14 11:18:54','2026-09-14 11:18:54'),(10,15,15,NULL,NULL,NULL,'password_changed','SBO Officer','SBO Officer completed the required password change.','2026-09-14 12:32:36','2026-09-14 12:32:36'),(11,14,NULL,NULL,NULL,2,'event_created','SBO Adviser','IT Days 2026 was created.','2026-09-15 16:56:32','2026-09-15 16:56:32'),(12,14,15,'02-2026-000003',1,NULL,'officer_password_changed','SBO Adviser','The password for SBO Officer login student.blue was changed by the adviser.','2026-09-15 17:12:33','2026-09-15 17:12:33'),(13,15,15,NULL,NULL,NULL,'password_changed','SBO Officer','SBO Officer completed the required password change.','2026-09-15 17:13:19','2026-09-15 17:13:19'),(14,14,NULL,NULL,1,2,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-15 17:13:40','2026-09-15 17:13:40'),(15,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-15 17:15:15','2026-09-15 17:15:15'),(16,14,NULL,NULL,1,2,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-16 00:45:57','2026-09-16 00:45:57'),(17,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 02:50:00','2026-09-16 02:50:00'),(18,14,16,'02-2026-000001',2,NULL,'officer_assigned','SBO Adviser','Student First Year 1 was assigned as attendance for 2026.','2026-09-16 02:57:06','2026-09-16 02:57:06'),(19,16,16,NULL,NULL,NULL,'password_changed','SBO Officer','SBO Officer completed the required password change.','2026-09-16 03:02:33','2026-09-16 03:02:33'),(20,14,NULL,NULL,NULL,NULL,'team_created',NULL,'Leaderboard green was created without assigned members.','2026-09-16 04:40:00','2026-09-16 04:40:00'),(21,14,NULL,NULL,NULL,NULL,'team_created',NULL,'Blue Cobalt was created without assigned members.','2026-09-16 04:40:38','2026-09-16 04:40:38'),(22,14,17,NULL,NULL,NULL,'user_created',NULL,'Micah D Lago was added as Student.','2026-09-16 06:56:44','2026-09-16 06:56:44'),(23,14,18,NULL,NULL,NULL,'user_created',NULL,'jessie D Parajes was added as Student.','2026-09-16 06:59:28','2026-09-16 06:59:28'),(24,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 07:00:22','2026-09-16 07:00:22'),(25,14,19,'02-2324-011281',3,NULL,'officer_assigned','SBO Adviser','jessie D Parajes was assigned as Attendance Officer for 2026-2027.','2026-09-16 07:03:06','2026-09-16 07:03:06'),(26,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Blue Cobalt was updated with 1 members.','2026-09-16 07:03:30','2026-09-16 07:03:30'),(27,19,19,NULL,NULL,NULL,'password_changed','SBO Officer','SBO Officer completed the required password change.','2026-09-16 07:16:08','2026-09-16 07:16:08'),(28,14,NULL,NULL,3,2,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-16 07:16:51','2026-09-16 07:16:51'),(29,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 07:18:36','2026-09-16 07:18:36'),(30,19,NULL,NULL,3,2,'sbo_attendance_scanned','SBO Officer','Recorded time in for 02-2324-011280.','2026-09-16 07:23:56','2026-09-16 07:23:56'),(31,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 07:31:54','2026-09-16 07:31:54'),(32,14,20,NULL,NULL,NULL,'user_created',NULL,'mjay D calunsag was added as Student.','2026-09-16 07:34:24','2026-09-16 07:34:24'),(33,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 07:35:19','2026-09-16 07:35:19'),(34,14,NULL,NULL,NULL,2,'event_updated','SBO Adviser','IT Days 2026 was updated.','2026-09-16 07:35:32','2026-09-16 07:35:32'),(35,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Blue Cobalt was updated with 2 members.','2026-09-16 07:37:35','2026-09-16 07:37:35'),(36,14,NULL,NULL,3,2,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-16 07:39:54','2026-09-16 07:39:54'),(37,14,NULL,NULL,3,2,'sbo_event_unassigned','SBO Adviser','Ended an SBO event responsibility.','2026-09-16 07:41:44','2026-09-16 07:41:44'),(38,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Blue Cobalt was updated with 1 members.','2026-09-16 07:44:58','2026-09-16 07:44:58'),(39,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Green Falcons was updated with 4 members.','2026-09-16 07:45:09','2026-09-16 07:45:09'),(41,20,NULL,NULL,NULL,NULL,'post_submitted','Student','Post #3 was submitted for adviser review.','2026-09-16 07:48:56','2026-09-16 07:48:56'),(42,14,NULL,NULL,NULL,NULL,'post_approved','SBO Adviser','Post #3 was approved.','2026-09-16 07:49:16','2026-09-16 07:49:16'),(43,14,NULL,NULL,NULL,3,'event_created','SBO Adviser','try balay scanning was created.','2026-09-17 12:29:05','2026-09-17 12:29:05'),(44,14,21,'02-2324-011280',4,NULL,'officer_assigned','SBO Adviser','Micah D Lago was assigned as officer for 2027.','2026-09-17 12:30:50','2026-09-17 12:30:50'),(45,14,NULL,NULL,4,3,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-17 12:31:00','2026-09-17 12:31:00'),(46,21,21,NULL,NULL,NULL,'password_changed','SBO Officer','User completed the required password change.','2026-09-17 12:33:22','2026-09-17 12:33:22'),(47,14,22,NULL,NULL,NULL,'user_created',NULL,'Jessie James c Jessie James was added as Student.','2026-09-17 13:04:16','2026-09-17 13:04:16'),(48,22,22,NULL,NULL,NULL,'password_changed','Student','User completed the required password change.','2026-09-17 13:05:28','2026-09-17 13:05:28'),(49,14,NULL,NULL,NULL,3,'event_updated','SBO Adviser','try balay scanning was updated.','2026-09-17 13:15:17','2026-09-17 13:15:17'),(50,14,NULL,NULL,3,3,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for jkj.','2026-09-17 13:16:06','2026-09-17 13:16:06'),(51,14,NULL,NULL,NULL,3,'event_updated','SBO Adviser','try balay scanning was updated.','2026-09-17 13:35:38','2026-09-17 13:35:38'),(52,14,NULL,NULL,3,3,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for Event duties.','2026-09-17 13:38:37','2026-09-17 13:38:37'),(53,14,19,'02-2324-011281',3,NULL,'officer_scanner_configured','SBO Adviser','Scanner set to specific; allowed team ID 1.','2026-09-17 13:39:46','2026-09-17 13:39:46'),(54,14,19,'02-2324-011281',3,NULL,'officer_scanner_configured','SBO Adviser','Scanner set to specific; allowed team ID 3.','2026-09-17 13:41:25','2026-09-17 13:41:25'),(55,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Green Falcons was updated with 5 members.','2026-09-17 13:45:19','2026-09-17 13:45:19'),(56,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Green Falcons was updated with 4 members.','2026-09-17 13:48:55','2026-09-17 13:48:55'),(57,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Yellow Tigers was updated with 4 members.','2026-09-17 13:49:04','2026-09-17 13:49:04'),(67,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Yellow Tigers was updated with 3 members.','2026-09-17 14:02:04','2026-09-17 14:02:04'),(68,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Green Falcons was updated with 5 members.','2026-09-17 14:02:14','2026-09-17 14:02:14'),(69,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Green Falcons was updated with 4 members.','2026-09-17 14:03:11','2026-09-17 14:03:11'),(70,14,NULL,NULL,NULL,NULL,'team_updated',NULL,'Yellow Tigers was updated with 4 members.','2026-09-17 14:03:16','2026-09-17 14:03:16'),(71,19,NULL,NULL,3,3,'sbo_attendance_scanned','SBO Officer','Recorded time in for 02-2324-09738.','2026-09-17 14:03:28','2026-09-17 14:03:28'),(72,20,20,NULL,NULL,NULL,'password_changed','Student','User completed the required password change.','2026-09-17 14:33:35','2026-09-17 14:33:35'),(73,21,NULL,NULL,4,3,'sbo_attendance_scanned','SBO Officer','Recorded time in for 02-2324-011290.','2026-09-17 14:34:05','2026-09-17 14:34:05'),(74,14,NULL,NULL,NULL,4,'event_created','SBO Adviser','dasds was created.','2026-09-17 16:58:36','2026-09-17 16:58:36'),(75,14,NULL,NULL,NULL,4,'event_updated','SBO Adviser','dasds was updated.','2026-09-17 16:58:52','2026-09-17 16:58:52'),(76,14,NULL,NULL,NULL,4,'event_updated','SBO Adviser','dasds was updated.','2026-09-17 16:59:12','2026-09-17 16:59:12'),(77,14,NULL,NULL,3,4,'sbo_event_assigned','SBO Adviser','Assigned attendance responsibility for 123.','2026-09-17 17:34:43','2026-09-17 17:34:43'),(78,14,NULL,NULL,NULL,4,'event_updated','SBO Adviser','dasds was updated.','2026-09-18 03:59:27','2026-09-18 03:59:27'),(79,19,NULL,NULL,3,4,'sbo_attendance_scanned','SBO Officer','Recorded time in for 02-2324-09738.','2026-09-18 04:03:23','2026-09-18 04:03:23');
/*!40000 ALTER TABLE `tbl_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attendance_entries`
--

DROP TABLE IF EXISTS `tbl_attendance_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attendance_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attendance_id` bigint(20) unsigned NOT NULL,
  `event_schedule_id` bigint(20) unsigned NOT NULL,
  `sbo_event_assignment_id` bigint(20) unsigned NOT NULL,
  `session_code` varchar(12) NOT NULL,
  `phase` varchar(3) NOT NULL DEFAULT 'in',
  `activity_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `scanned_at` datetime NOT NULL,
  `scan_latitude` decimal(10,7) DEFAULT NULL,
  `scan_longitude` decimal(10,7) DEFAULT NULL,
  `location_accuracy_m` decimal(10,2) DEFAULT NULL,
  `distance_from_venue_m` decimal(10,2) DEFAULT NULL,
  `location_status` enum('inside','outside','unavailable') NOT NULL DEFAULT 'unavailable',
  `location_captured_at` datetime DEFAULT NULL,
  `location_unavailable_reason` varchar(120) DEFAULT NULL,
  `venue_location_id` bigint(20) unsigned DEFAULT NULL,
  `venue_name_snapshot` varchar(255) DEFAULT NULL,
  `venue_latitude_snapshot` decimal(10,7) DEFAULT NULL,
  `venue_longitude_snapshot` decimal(10,7) DEFAULT NULL,
  `venue_radius_snapshot_m` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_entries_session_phase_unique` (`attendance_id`,`event_schedule_id`,`session_code`,`phase`,`activity_id`,`team_id`),
  UNIQUE KEY `attendance_entries_student_phase_unique` (`attendance_id`,`event_schedule_id`,`session_code`,`phase`),
  KEY `attendance_entries_assignment_index` (`sbo_event_assignment_id`,`scanned_at`),
  KEY `attendance_entries_recorded_by_foreign` (`recorded_by`),
  KEY `attendance_entries_schedule_foreign` (`event_schedule_id`),
  KEY `attendance_entries_activity_foreign` (`activity_id`),
  KEY `attendance_entries_team_foreign` (`team_id`),
  KEY `attendance_entries_recent_event_index` (`event_schedule_id`,`session_code`,`scanned_at`),
  KEY `attendance_entries_location_status_index` (`location_status`,`scanned_at`),
  KEY `attendance_entries_venue_location_index` (`venue_location_id`),
  CONSTRAINT `attendance_entries_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_entries_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_entries_attendance_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `tbl_attendances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_entries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_entries_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_entries_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_entries_venue_location_foreign` FOREIGN KEY (`venue_location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attendance_entries`
--

LOCK TABLES `tbl_attendance_entries` WRITE;
/*!40000 ALTER TABLE `tbl_attendance_entries` DISABLE KEYS */;
INSERT INTO `tbl_attendance_entries` VALUES (1,1,2,3,'whole_day','in',1,6,19,'2026-09-16 15:23:56',8.4819630,124.6361358,10.63,33.90,'inside','2026-09-16 15:23:56',NULL,1,'PHINMA COC Carmen Campus',8.4822620,124.6361958,50.00,'present','2026-09-16 07:23:56','2026-09-17 16:23:34'),(12,12,3,7,'whole_day','in',2,3,19,'2026-09-17 22:03:28',8.4699942,124.6343571,61.14,3.67,'inside','2026-09-17 22:03:28',NULL,6,'balay',8.4700140,124.6343304,20.00,'present','2026-09-17 14:03:28','2026-09-17 16:23:34'),(13,13,3,5,'whole_day','in',2,1,21,'2026-09-17 22:34:05',8.4807440,124.7328238,13.22,10897.88,'outside','2026-09-17 22:34:02',NULL,6,'balay',8.4700140,124.6343304,20.00,'present','2026-09-17 14:34:05','2026-09-17 16:23:34'),(14,14,4,8,'whole_day','in',4,3,19,'2026-09-18 12:03:23',8.4819443,124.6363390,15.95,38.78,'inside','2026-09-18 12:03:17',NULL,1,'PHINMA COC Carmen Campus',8.4822630,124.6361958,50.00,'present','2026-09-18 04:03:23','2026-09-18 04:03:23');
/*!40000 ALTER TABLE `tbl_attendance_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attendance_qr_tokens`
--

DROP TABLE IF EXISTS `tbl_attendance_qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attendance_qr_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `session` varchar(12) NOT NULL,
  `phase` varchar(3) NOT NULL DEFAULT 'in',
  `schedule_date` date DEFAULT NULL,
  `token` varchar(48) NOT NULL,
  `issued_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_qr_tokens_token_unique` (`token`),
  UNIQUE KEY `attendance_qr_tokens_student_phase_unique` (`event_id`,`user_id`,`session`,`phase`),
  KEY `attendance_qr_tokens_user_id_foreign` (`user_id`),
  CONSTRAINT `attendance_qr_tokens_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_qr_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attendance_qr_tokens`
--

LOCK TABLES `tbl_attendance_qr_tokens` WRITE;
/*!40000 ALTER TABLE `tbl_attendance_qr_tokens` DISABLE KEYS */;
INSERT INTO `tbl_attendance_qr_tokens` VALUES (1,2,1,'whole_day','in','2026-09-16','h6j3e5JHzbZpQ6GtNyB3ie_PS9aZzXee','2026-09-16 01:46:57','2026-09-16 01:47:27',NULL,'2026-09-15 17:00:27','2026-09-15 17:46:57'),(2,2,17,'whole_day','in','2026-09-16','apUKg8XUfgINKOLFvhGvWeSxC882hYw-','2026-09-16 15:23:51','2026-09-16 15:24:21','2026-09-16 15:23:56','2026-09-16 07:12:47','2026-09-16 07:23:56'),(3,2,20,'whole_day','in','2026-09-16','04N4M0PXLSALKL7MjWziGsSpfHuFo1Pm','2026-09-16 15:48:10','2026-09-16 15:48:40',NULL,'2026-09-16 07:43:34','2026-09-16 07:48:10'),(4,3,22,'whole_day','in','2026-09-17','gMFwTLGevJrINHB9SHXgFHX3RqHN2J7Z','2026-09-17 22:03:10','2026-09-17 22:03:40','2026-09-17 22:03:28','2026-09-17 13:40:10','2026-09-17 14:03:28'),(5,3,20,'whole_day','in','2026-09-17','cSoMcRMiY582Qt5-TlgJ85DWhWyUGSOs','2026-09-17 22:34:00','2026-09-17 22:34:30','2026-09-17 22:34:05','2026-09-17 14:34:00','2026-09-17 14:34:05'),(6,3,22,'whole_day','out','2026-09-17','teeI7h3GAdutPKihz0szMceapIc7v3sh','2026-09-17 23:32:51','2026-09-17 23:33:21',NULL,'2026-09-17 15:32:51','2026-09-17 15:32:51'),(7,4,22,'whole_day','in','2026-09-18','x7MVwEmKcQyyHvAKHaYCyyGgNq_YWisL','2026-09-18 12:02:54','2026-09-18 12:03:24','2026-09-18 12:03:23','2026-09-18 03:59:28','2026-09-18 04:03:23');
/*!40000 ALTER TABLE `tbl_attendance_qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attendance_session_modes`
--

DROP TABLE IF EXISTS `tbl_attendance_session_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attendance_session_modes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_session_modes_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attendance_session_modes`
--

LOCK TABLES `tbl_attendance_session_modes` WRITE;
/*!40000 ALTER TABLE `tbl_attendance_session_modes` DISABLE KEYS */;
INSERT INTO `tbl_attendance_session_modes` VALUES (1,'none','No attendance scanning','2026-09-11 15:45:45','2026-09-11 15:45:45'),(2,'whole_day','Whole day — one sign in and sign out','2026-09-11 15:45:45','2026-09-11 15:45:45'),(3,'two_sessions','Morning and afternoon — two sign-ins and sign-outs','2026-09-11 15:45:45','2026-09-11 15:45:45');
/*!40000 ALTER TABLE `tbl_attendance_session_modes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attendances`
--

DROP TABLE IF EXISTS `tbl_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `manual_status` varchar(20) DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `morning_in_at` timestamp NULL DEFAULT NULL,
  `morning_out_at` timestamp NULL DEFAULT NULL,
  `afternoon_in_at` timestamp NULL DEFAULT NULL,
  `afternoon_out_at` timestamp NULL DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `manual_corrected_by` bigint(20) unsigned DEFAULT NULL,
  `manual_corrected_at` timestamp NULL DEFAULT NULL,
  `manual_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_event_user_date_unique` (`event_id`,`user_id`,`attendance_date`),
  KEY `attendances_user_id_foreign` (`user_id`),
  KEY `attendances_recorded_by_foreign` (`recorded_by`),
  KEY `attendances_event_id_status_index` (`event_id`,`status`),
  KEY `attendances_manual_corrected_by_foreign` (`manual_corrected_by`),
  KEY `attendances_event_manual_status_index` (`event_id`,`manual_status`),
  CONSTRAINT `attendances_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_manual_corrected_by_foreign` FOREIGN KEY (`manual_corrected_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attendances`
--

LOCK TABLES `tbl_attendances` WRITE;
/*!40000 ALTER TABLE `tbl_attendances` DISABLE KEYS */;
INSERT INTO `tbl_attendances` VALUES (1,2,17,'2026-09-16','present',NULL,'2026-09-16 07:23:56','2026-09-16 07:23:56',NULL,NULL,NULL,19,NULL,NULL,NULL,NULL,'2026-09-16 07:23:56','2026-09-16 07:23:56'),(12,3,22,'2026-09-17','present',NULL,'2026-09-17 14:03:28','2026-09-17 14:03:28',NULL,NULL,NULL,19,NULL,NULL,NULL,NULL,'2026-09-17 14:03:28','2026-09-17 14:03:28'),(13,3,20,'2026-09-17','present',NULL,'2026-09-17 14:34:05','2026-09-17 14:34:05',NULL,NULL,NULL,21,NULL,NULL,NULL,NULL,'2026-09-17 14:34:05','2026-09-17 14:34:05'),(14,4,22,'2026-09-18','present',NULL,'2026-09-18 04:03:23','2026-09-18 04:03:23',NULL,NULL,NULL,19,NULL,NULL,NULL,NULL,'2026-09-18 04:03:23','2026-09-18 04:03:23');
/*!40000 ALTER TABLE `tbl_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_cache`
--

DROP TABLE IF EXISTS `tbl_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_cache`
--

LOCK TABLES `tbl_cache` WRITE;
/*!40000 ALTER TABLE `tbl_cache` DISABLE KEYS */;
INSERT INTO `tbl_cache` VALUES ('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba','i:2;',1789278229),('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1789278229;',1789278229);
/*!40000 ALTER TABLE `tbl_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_cache_locks`
--

DROP TABLE IF EXISTS `tbl_cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_cache_locks`
--

LOCK TABLES `tbl_cache_locks` WRITE;
/*!40000 ALTER TABLE `tbl_cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_activities`
--

DROP TABLE IF EXISTS `tbl_event_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `activity_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_activities_event_name_unique` (`event_id`,`name`),
  KEY `event_activities_created_by_foreign` (`created_by`),
  KEY `event_activities_activity_id_foreign` (`activity_id`),
  CONSTRAINT `event_activities_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_activities` (`id`),
  CONSTRAINT `event_activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_activities_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_activities`
--

LOCK TABLES `tbl_event_activities` WRITE;
/*!40000 ALTER TABLE `tbl_event_activities` DISABLE KEYS */;
INSERT INTO `tbl_event_activities` VALUES (1,2,2,'Event duties','active',14,'2026-09-15 17:13:40','2026-09-21 10:26:35'),(2,3,2,'Event duties','active',14,'2026-09-17 12:31:00','2026-09-21 10:26:35'),(3,3,3,'jkj','active',14,'2026-09-17 13:16:06','2026-09-21 10:26:35'),(4,4,1,'123','active',14,'2026-09-17 17:34:43','2026-09-21 10:26:35');
/*!40000 ALTER TABLE `tbl_event_activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_attendance_schedules`
--

DROP TABLE IF EXISTS `tbl_event_attendance_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_attendance_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `schedule_date` date NOT NULL,
  `attendance_session_mode_id` bigint(20) unsigned NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_attendance_schedules_event_id_schedule_date_unique` (`event_id`,`schedule_date`),
  KEY `event_attendance_schedules_attendance_session_mode_id_foreign` (`attendance_session_mode_id`),
  CONSTRAINT `event_attendance_schedules_attendance_session_mode_id_foreign` FOREIGN KEY (`attendance_session_mode_id`) REFERENCES `tbl_attendance_session_modes` (`id`),
  CONSTRAINT `event_attendance_schedules_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_attendance_schedules`
--

LOCK TABLES `tbl_event_attendance_schedules` WRITE;
/*!40000 ALTER TABLE `tbl_event_attendance_schedules` DISABLE KEYS */;
INSERT INTO `tbl_event_attendance_schedules` VALUES (1,1,'2026-09-12',2,'08:00:00','17:30:00','18:00:00','17:30:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 15:48:49','2026-09-11 15:48:49'),(2,2,'2026-09-16',2,'15:08:00','15:50:00','22:00:00','21:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-15 16:56:32','2026-09-16 07:35:32'),(3,3,'2026-09-17',2,'18:00:00','23:20:00','23:56:00','23:30:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-17 12:29:05','2026-09-17 13:35:38'),(4,4,'2026-09-18',2,'00:00:00','14:00:00','18:00:00','17:00:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-17 16:58:36','2026-09-18 03:59:27');
/*!40000 ALTER TABLE `tbl_event_attendance_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_locations`
--

DROP TABLE IF EXISTS `tbl_event_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_locations` (
  `event_id` bigint(20) unsigned NOT NULL,
  `location_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`,`location_id`),
  KEY `event_locations_location_id_index` (`location_id`),
  KEY `event_locations_primary_index` (`event_id`,`is_primary`),
  CONSTRAINT `event_locations_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_locations_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `tbl_locations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_locations`
--

LOCK TABLES `tbl_event_locations` WRITE;
/*!40000 ALTER TABLE `tbl_event_locations` DISABLE KEYS */;
INSERT INTO `tbl_event_locations` VALUES (1,1,1,'2026-09-17 16:11:03','2026-09-17 16:11:03'),(2,1,1,'2026-09-17 16:11:03','2026-09-17 16:11:03'),(3,6,1,'2026-09-17 16:11:03','2026-09-17 16:11:03'),(4,1,1,'2026-09-18 03:59:27','2026-09-18 03:59:27'),(4,6,0,'2026-09-18 03:59:27','2026-09-18 03:59:27');
/*!40000 ALTER TABLE `tbl_event_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_membership_snapshots`
--

DROP TABLE IF EXISTS `tbl_event_membership_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_membership_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `student_id_number` varchar(50) DEFAULT NULL,
  `student_name` varchar(255) NOT NULL,
  `year_level_id` bigint(20) unsigned DEFAULT NULL,
  `year_level_label` varchar(80) DEFAULT NULL,
  `team_name` varchar(255) DEFAULT NULL,
  `team_color` varchar(20) DEFAULT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_membership_snapshot_event_user_unique` (`event_id`,`user_id`),
  KEY `event_membership_snapshot_event_team_index` (`event_id`,`team_id`),
  KEY `event_membership_snapshot_period_index` (`academic_period_id`),
  KEY `event_membership_snapshot_user_foreign` (`user_id`),
  KEY `event_membership_snapshot_team_foreign` (`team_id`),
  CONSTRAINT `event_membership_snapshot_event_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_membership_snapshot_period_foreign` FOREIGN KEY (`academic_period_id`) REFERENCES `tbl_academic_periods` (`id`),
  CONSTRAINT `event_membership_snapshot_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_membership_snapshot_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_membership_snapshots`
--

LOCK TABLES `tbl_event_membership_snapshots` WRITE;
/*!40000 ALTER TABLE `tbl_event_membership_snapshots` DISABLE KEYS */;
INSERT INTO `tbl_event_membership_snapshots` VALUES (1,1,1,1,4,'02-2026-000001','Student First Year 1',1,'First Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(2,1,1,2,3,'02-2026-000002','Student First Year 2',1,'First Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(3,1,1,3,2,'02-2026-000003','Student First Year 3',1,'First Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(4,1,1,4,3,'02-2026-000004','Student Second Year 1',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(5,1,1,5,3,'02-2026-000005','Student Second Year 2',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(6,1,1,6,1,'02-2026-000006','Student Second Year 3',2,'Second Year','Green Falcons','#397565','2026-09-21 10:26:34'),(7,1,1,7,4,'02-2026-000007','Student Third Year 1',3,'Third Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(8,1,1,8,1,'02-2026-000008','Student Third Year 2',3,'Third Year','Green Falcons','#397565','2026-09-21 10:26:34'),(9,1,1,9,2,'02-2026-000009','Student Third Year 3',3,'Third Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(10,1,1,10,1,'02-2026-000010','Student Fourth Year 1',4,'Fourth Year','Green Falcons','#397565','2026-09-21 10:26:34'),(11,1,1,11,2,'02-2026-000011','Student Fourth Year 2',4,'Fourth Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(12,1,1,12,4,'02-2026-000012','Student Fourth Year 3',4,'Fourth Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(13,1,1,17,6,'02-2324-011280','Micah D Lago',4,'Fourth Year','Blue Cobalt','#2F3AE0','2026-09-21 10:26:34'),(14,1,1,18,NULL,'02-2324-011281','jessie D Parajes',1,'First Year',NULL,NULL,'2026-09-21 10:26:34'),(15,2,1,1,4,'02-2026-000001','Student First Year 1',1,'First Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(16,2,1,2,3,'02-2026-000002','Student First Year 2',1,'First Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(17,2,1,3,2,'02-2026-000003','Student First Year 3',1,'First Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(18,2,1,4,3,'02-2026-000004','Student Second Year 1',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(19,2,1,5,3,'02-2026-000005','Student Second Year 2',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(20,2,1,6,1,'02-2026-000006','Student Second Year 3',2,'Second Year','Green Falcons','#397565','2026-09-21 10:26:34'),(21,2,1,7,4,'02-2026-000007','Student Third Year 1',3,'Third Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(22,2,1,8,1,'02-2026-000008','Student Third Year 2',3,'Third Year','Green Falcons','#397565','2026-09-21 10:26:34'),(23,2,1,9,2,'02-2026-000009','Student Third Year 3',3,'Third Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(24,2,1,10,1,'02-2026-000010','Student Fourth Year 1',4,'Fourth Year','Green Falcons','#397565','2026-09-21 10:26:34'),(25,2,1,11,2,'02-2026-000011','Student Fourth Year 2',4,'Fourth Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(26,2,1,12,4,'02-2026-000012','Student Fourth Year 3',4,'Fourth Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(27,2,1,17,6,'02-2324-011280','Micah D Lago',4,'Fourth Year','Blue Cobalt','#2F3AE0','2026-09-21 10:26:34'),(28,2,1,18,NULL,'02-2324-011281','jessie D Parajes',1,'First Year',NULL,NULL,'2026-09-21 10:26:34'),(29,2,1,20,1,'02-2324-011290','mjay D calunsag',NULL,NULL,'Green Falcons','#397565','2026-09-21 10:26:34'),(30,2,1,22,3,'02-2324-09738','Jessie James c Jessie James',NULL,NULL,'Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(31,3,1,1,4,'02-2026-000001','Student First Year 1',1,'First Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(32,3,1,2,3,'02-2026-000002','Student First Year 2',1,'First Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(33,3,1,3,2,'02-2026-000003','Student First Year 3',1,'First Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(34,3,1,4,3,'02-2026-000004','Student Second Year 1',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(35,3,1,5,3,'02-2026-000005','Student Second Year 2',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(36,3,1,6,1,'02-2026-000006','Student Second Year 3',2,'Second Year','Green Falcons','#397565','2026-09-21 10:26:34'),(37,3,1,7,4,'02-2026-000007','Student Third Year 1',3,'Third Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(38,3,1,8,1,'02-2026-000008','Student Third Year 2',3,'Third Year','Green Falcons','#397565','2026-09-21 10:26:34'),(39,3,1,9,2,'02-2026-000009','Student Third Year 3',3,'Third Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(40,3,1,10,1,'02-2026-000010','Student Fourth Year 1',4,'Fourth Year','Green Falcons','#397565','2026-09-21 10:26:34'),(41,3,1,11,2,'02-2026-000011','Student Fourth Year 2',4,'Fourth Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(42,3,1,12,4,'02-2026-000012','Student Fourth Year 3',4,'Fourth Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(43,3,1,17,6,'02-2324-011280','Micah D Lago',4,'Fourth Year','Blue Cobalt','#2F3AE0','2026-09-21 10:26:34'),(44,3,1,18,NULL,'02-2324-011281','jessie D Parajes',1,'First Year',NULL,NULL,'2026-09-21 10:26:34'),(45,3,1,20,1,'02-2324-011290','mjay D calunsag',NULL,NULL,'Green Falcons','#397565','2026-09-21 10:26:34'),(46,3,1,22,3,'02-2324-09738','Jessie James c Jessie James',NULL,NULL,'Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(47,4,1,1,4,'02-2026-000001','Student First Year 1',1,'First Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(48,4,1,2,3,'02-2026-000002','Student First Year 2',1,'First Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(49,4,1,3,2,'02-2026-000003','Student First Year 3',1,'First Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(50,4,1,4,3,'02-2026-000004','Student Second Year 1',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(51,4,1,5,3,'02-2026-000005','Student Second Year 2',2,'Second Year','Yellow Tigers','#D4A017','2026-09-21 10:26:34'),(52,4,1,6,1,'02-2026-000006','Student Second Year 3',2,'Second Year','Green Falcons','#397565','2026-09-21 10:26:34'),(53,4,1,7,4,'02-2026-000007','Student Third Year 1',3,'Third Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(54,4,1,8,1,'02-2026-000008','Student Third Year 2',3,'Third Year','Green Falcons','#397565','2026-09-21 10:26:34'),(55,4,1,9,2,'02-2026-000009','Student Third Year 3',3,'Third Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(56,4,1,10,1,'02-2026-000010','Student Fourth Year 1',4,'Fourth Year','Green Falcons','#397565','2026-09-21 10:26:34'),(57,4,1,11,2,'02-2026-000011','Student Fourth Year 2',4,'Fourth Year','Blue Sharks','#2F3AE0','2026-09-21 10:26:34'),(58,4,1,12,4,'02-2026-000012','Student Fourth Year 3',4,'Fourth Year','Red Lions','#DC2626','2026-09-21 10:26:34'),(59,4,1,17,6,'02-2324-011280','Micah D Lago',4,'Fourth Year','Blue Cobalt','#2F3AE0','2026-09-21 10:26:34'),(60,4,1,18,NULL,'02-2324-011281','jessie D Parajes',1,'First Year',NULL,NULL,'2026-09-21 10:26:34'),(61,4,1,20,1,'02-2324-011290','mjay D calunsag',NULL,NULL,'Green Falcons','#397565','2026-09-21 10:26:34'),(62,4,1,22,3,'02-2324-09738','Jessie James c Jessie James',NULL,NULL,'Yellow Tigers','#D4A017','2026-09-21 10:26:34');
/*!40000 ALTER TABLE `tbl_event_membership_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_participants`
--

DROP TABLE IF EXISTS `tbl_event_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_participants_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `event_participants_user_id_foreign` (`user_id`),
  CONSTRAINT `event_participants_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_participants`
--

LOCK TABLES `tbl_event_participants` WRITE;
/*!40000 ALTER TABLE `tbl_event_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_event_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_statuses`
--

DROP TABLE IF EXISTS `tbl_event_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_statuses_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_statuses`
--

LOCK TABLES `tbl_event_statuses` WRITE;
/*!40000 ALTER TABLE `tbl_event_statuses` DISABLE KEYS */;
INSERT INTO `tbl_event_statuses` VALUES (1,'upcoming','2026-09-11 15:45:47','2026-09-11 15:45:47'),(2,'ongoing','2026-09-11 15:45:47','2026-09-11 15:45:47'),(3,'completed','2026-09-11 15:45:47','2026-09-11 15:45:47'),(4,'archived','2026-09-11 15:45:47','2026-09-11 15:45:47');
/*!40000 ALTER TABLE `tbl_event_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_team`
--

DROP TABLE IF EXISTS `tbl_event_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_team` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_team_event_id_team_id_unique` (`event_id`,`team_id`),
  KEY `event_team_team_id_foreign` (`team_id`),
  CONSTRAINT `event_team_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_team_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_team`
--

LOCK TABLES `tbl_event_team` WRITE;
/*!40000 ALTER TABLE `tbl_event_team` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_event_team` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_types`
--

DROP TABLE IF EXISTS `tbl_event_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_types_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_types`
--

LOCK TABLES `tbl_event_types` WRITE;
/*!40000 ALTER TABLE `tbl_event_types` DISABLE KEYS */;
INSERT INTO `tbl_event_types` VALUES (1,'IT Days','2026-09-11 15:45:47','2026-09-11 15:45:47'),(2,'IT Expo','2026-09-11 15:45:47','2026-09-11 15:45:47');
/*!40000 ALTER TABLE `tbl_event_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_user`
--

DROP TABLE IF EXISTS `tbl_event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_user_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `event_user_user_id_foreign` (`user_id`),
  CONSTRAINT `event_user_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_user`
--

LOCK TABLES `tbl_event_user` WRITE;
/*!40000 ALTER TABLE `tbl_event_user` DISABLE KEYS */;
INSERT INTO `tbl_event_user` VALUES (1,1,14,'2026-09-11 15:48:49','2026-09-11 15:48:49'),(9,2,14,'2026-09-16 07:35:32','2026-09-16 07:35:32'),(12,3,14,'2026-09-17 13:35:38','2026-09-17 13:35:38'),(16,4,14,'2026-09-18 03:59:27','2026-09-18 03:59:27');
/*!40000 ALTER TABLE `tbl_event_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_event_year_level`
--

DROP TABLE IF EXISTS `tbl_event_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_event_year_level` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `year_level_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_year_level_event_id_year_level_id_unique` (`event_id`,`year_level_id`),
  KEY `event_year_level_year_level_id_foreign` (`year_level_id`),
  CONSTRAINT `event_year_level_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_year_level_year_level_id_foreign` FOREIGN KEY (`year_level_id`) REFERENCES `tbl_year_levels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_event_year_level`
--

LOCK TABLES `tbl_event_year_level` WRITE;
/*!40000 ALTER TABLE `tbl_event_year_level` DISABLE KEYS */;
INSERT INTO `tbl_event_year_level` VALUES (1,1,1,'2026-09-11 15:48:49','2026-09-11 15:48:49'),(2,1,2,'2026-09-11 15:48:49','2026-09-11 15:48:49'),(3,1,3,'2026-09-11 15:48:49','2026-09-11 15:48:49'),(4,1,4,'2026-09-11 15:48:50','2026-09-11 15:48:50');
/*!40000 ALTER TABLE `tbl_event_year_level` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_events`
--

DROP TABLE IF EXISTS `tbl_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_location_policy` enum('off','warning','strict') NOT NULL DEFAULT 'off',
  `audience_type` varchar(30) NOT NULL DEFAULT 'all_students',
  `academic_period_id` bigint(20) unsigned NOT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_order` int(10) unsigned DEFAULT NULL,
  `featured_until` timestamp NULL DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `event_type_id` bigint(20) unsigned DEFAULT NULL,
  `event_status_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_event_type_id_foreign` (`event_type_id`),
  KEY `events_event_status_id_foreign` (`event_status_id`),
  KEY `events_created_by_foreign` (`created_by`),
  KEY `events_is_featured_featured_order_index` (`is_featured`,`featured_order`),
  KEY `events_location_id_index` (`location_id`),
  KEY `events_academic_period_index` (`academic_period_id`),
  CONSTRAINT `events_academic_period_foreign` FOREIGN KEY (`academic_period_id`) REFERENCES `tbl_academic_periods` (`id`),
  CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_event_status_id_foreign` FOREIGN KEY (`event_status_id`) REFERENCES `tbl_event_statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_event_type_id_foreign` FOREIGN KEY (`event_type_id`) REFERENCES `tbl_event_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_events`
--

LOCK TABLES `tbl_events` WRITE;
/*!40000 ALTER TABLE `tbl_events` DISABLE KEYS */;
INSERT INTO `tbl_events` VALUES (1,'IT Days 2026','test','PHINMA COC Carmen Campus',1,'off','selected_year_levels',1,'assets/uploads/event-posters/W70sh2mMP9zsCz9YO3UPVGVwrOIHFGGbNVLF268j.png',0,NULL,NULL,'2026-09-12 08:00:00','2026-09-12 18:00:00',1,3,14,'2026-09-11 15:48:49','2026-09-17 17:13:03',NULL),(2,'IT Days 2026',NULL,'PHINMA COC Carmen Campus',1,'off','all_students',1,NULL,0,NULL,NULL,'2026-09-16 15:08:00','2026-09-16 22:00:00',1,3,14,'2026-09-15 16:56:32','2026-09-17 17:13:03',NULL),(3,'try balay scanning',NULL,'balay',6,'warning','all_students',1,NULL,0,NULL,NULL,'2026-09-17 18:00:00','2026-09-17 23:56:00',1,3,14,'2026-09-17 12:29:05','2026-09-17 17:04:38',NULL),(4,'dasds',NULL,'PHINMA COC Carmen Campus',1,'warning','all_students',1,NULL,0,NULL,NULL,'2026-09-18 00:00:00','2026-09-18 18:00:00',1,3,14,'2026-09-17 16:58:36','2026-09-18 03:59:27',NULL);
/*!40000 ALTER TABLE `tbl_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_failed_jobs`
--

DROP TABLE IF EXISTS `tbl_failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_failed_jobs`
--

LOCK TABLES `tbl_failed_jobs` WRITE;
/*!40000 ALTER TABLE `tbl_failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_job_batches`
--

DROP TABLE IF EXISTS `tbl_job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_job_batches`
--

LOCK TABLES `tbl_job_batches` WRITE;
/*!40000 ALTER TABLE `tbl_job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_jobs`
--

DROP TABLE IF EXISTS `tbl_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_jobs`
--

LOCK TABLES `tbl_jobs` WRITE;
/*!40000 ALTER TABLE `tbl_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_locations`
--

DROP TABLE IF EXISTS `tbl_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('general','specific') NOT NULL,
  `parent_location_id` bigint(20) unsigned DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius` decimal(10,2) DEFAULT NULL COMMENT 'Square geofence center-to-edge distance in meters',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_name_unique` (`name`),
  KEY `locations_parent_location_id_index` (`parent_location_id`),
  CONSTRAINT `locations_parent_location_id_foreign` FOREIGN KEY (`parent_location_id`) REFERENCES `tbl_locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_locations`
--

LOCK TABLES `tbl_locations` WRITE;
/*!40000 ALTER TABLE `tbl_locations` DISABLE KEYS */;
INSERT INTO `tbl_locations` VALUES (1,'PHINMA COC Carmen Campus','general',NULL,8.4822630,124.6361958,50.00,'2026-09-11 15:45:47','2026-09-17 17:13:03'),(2,'MS Computer Lab 1','specific',1,NULL,NULL,NULL,'2026-09-11 15:45:47','2026-09-11 15:45:47'),(3,'PH 310','specific',1,NULL,NULL,NULL,'2026-09-11 15:45:47','2026-09-11 15:45:47'),(4,'jessss','general',NULL,8.4702571,124.6341782,1.00,'2026-09-14 16:00:32','2026-09-14 16:00:32'),(5,'Kk','general',NULL,8.4699237,124.6342058,20.00,'2026-09-14 16:55:10','2026-09-14 16:55:10'),(6,'balay','general',NULL,8.4700140,124.6343304,20.00,'2026-09-17 12:25:35','2026-09-17 17:04:38');
/*!40000 ALTER TABLE `tbl_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_migrations`
--

DROP TABLE IF EXISTS `tbl_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_migrations`
--

LOCK TABLES `tbl_migrations` WRITE;
/*!40000 ALTER TABLE `tbl_migrations` DISABLE KEYS */;
INSERT INTO `tbl_migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_02_234744_create_roles_table',1),(5,'2026_09_03_020127_create_year_levels_table',1),(6,'2026_09_03_021642_create_user_statuses_table',1),(7,'2026_09_03_023253_add_custom_fields_to_user_table',1),(8,'2026_09_03_040614_create_school_years_table',1),(9,'2026_09_03_042239_create_event_types_table',1),(10,'2026_09_03_043911_create_event_statuses_table',1),(11,'2026_09_04_131102_event_db',1),(12,'2026_09_05_000001_create_events_table',1),(13,'2026_09_05_000002_create_event_user_table',1),(14,'2026_09_05_000003_create_activity_logs_table',1),(15,'2026_09_05_000004_add_management_fields_to_events_table',1),(16,'2026_09_06_000005_create_teams_table',1),(17,'2026_09_06_000006_create_team_user_table',1),(18,'2026_09_06_000007_create_attendances_table',1),(19,'2026_09_06_000008_create_scores_table',1),(20,'2026_09_07_000009_add_audience_to_events',1),(21,'2026_09_07_000010_create_score_categories_table',1),(22,'2026_09_07_000011_add_officer_attendance_scans',1),(23,'2026_09_08_000012_create_student_profiles_and_officer_assignments',1),(24,'2026_09_08_000013_finalize_linked_student_account_audit',1),(25,'2026_09_09_000014_seed_it_games_attendance_test_data',1),(26,'2026_09_09_000015_create_attendance_qr_tokens_table',1),(27,'2026_09_09_000016_add_teams_randomized_at_to_school_years',1),(28,'2026_09_09_000017_add_daily_event_attendance_schedules',1),(29,'2026_09_09_000018_add_session_mode_to_event_attendance_schedules',1),(30,'2026_09_09_000019_remove_student_profiles',1),(31,'2026_09_09_000020_create_attendance_session_modes',1),(32,'2026_09_09_000021_remove_event_attendance_time_columns',1),(33,'2026_09_09_000022_create_locations_table',1),(34,'2026_09_09_000023_change_location_type_to_enum',1),(35,'2026_09_10_000024_replace_active_event_status',1),(36,'2026_09_11_000025_create_student_portal_tables',1),(37,'2026_09_11_000026_add_soft_deletes_to_posts',1),(38,'2026_09_11_000027_add_feature_fields_to_events_table',1),(39,'2026_09_12_000028_create_post_engagement_tables',2);
/*!40000 ALTER TABLE `tbl_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_notifications`
--

DROP TABLE IF EXISTS `tbl_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_notifications`
--

LOCK TABLES `tbl_notifications` WRITE;
/*!40000 ALTER TABLE `tbl_notifications` DISABLE KEYS */;
INSERT INTO `tbl_notifications` VALUES ('7a87c08e-e938-4e3c-b84f-6b84799be5e6','App\\Notifications\\PostReviewed','App\\Models\\User',2,'{\"post_id\":1,\"status\":\"approved\",\"reason\":null,\"message\":\"Your post was approved.\"}','2026-09-13 06:02:08','2026-09-11 15:47:39','2026-09-13 06:02:08'),('7c0d0018-7c84-492e-a416-adc91b36bcf7','App\\Notifications\\PostReviewed','App\\Models\\User',20,'{\"post_id\":3,\"status\":\"approved\",\"reason\":null,\"message\":\"Your post was approved.\"}',NULL,'2026-09-16 07:49:16','2026-09-16 07:49:16'),('9976f955-0bb1-4462-9f84-d79301352eb4','App\\Notifications\\PostReviewSubmitted','App\\Models\\User',14,'{\"post_id\":1,\"message\":\"Student First Year 2 submitted a post for review.\"}',NULL,'2026-09-11 15:46:58','2026-09-11 15:46:58'),('d147ff4e-b949-4ee0-a5ae-20b3010bbf4f','post_submitted','AppModelsUser',14,'{\"post_id\":3,\"student_id\":20,\"message\":\"mjay D calunsag submitted a post for review.\"}',NULL,'2026-09-16 07:48:56','2026-09-16 07:48:56'),('f22c5e82-abac-4ec4-82bb-9cfa235c8569','post_submitted','AppModelsUser',14,'{\"post_id\":2,\"student_id\":2,\"message\":\"Student First Year 2 submitted a post for review.\"}',NULL,'2026-09-13 06:18:43','2026-09-13 06:18:43');
/*!40000 ALTER TABLE `tbl_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_officer_responsibilities`
--

DROP TABLE IF EXISTS `tbl_officer_responsibilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_officer_responsibilities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `label` varchar(60) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `officer_responsibilities_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_officer_responsibilities`
--

LOCK TABLES `tbl_officer_responsibilities` WRITE;
/*!40000 ALTER TABLE `tbl_officer_responsibilities` DISABLE KEYS */;
INSERT INTO `tbl_officer_responsibilities` VALUES (1,'attendance','Attendance','2026-09-21 10:26:34','2026-09-21 10:26:34'),(2,'scoring','Scoring','2026-09-21 10:26:34','2026-09-21 10:26:34'),(3,'media','Media','2026-09-21 10:26:34','2026-09-21 10:26:34');
/*!40000 ALTER TABLE `tbl_officer_responsibilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_password_reset_tokens`
--

DROP TABLE IF EXISTS `tbl_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_password_reset_tokens`
--

LOCK TABLES `tbl_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `tbl_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_post_audits`
--

DROP TABLE IF EXISTS `tbl_post_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_post_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_audits_post_id_foreign` (`post_id`),
  KEY `post_audits_actor_id_foreign` (`actor_id`),
  CONSTRAINT `post_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `post_audits_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_post_audits`
--

LOCK TABLES `tbl_post_audits` WRITE;
/*!40000 ALTER TABLE `tbl_post_audits` DISABLE KEYS */;
INSERT INTO `tbl_post_audits` VALUES (1,1,2,'submitted',NULL,'pending',NULL,'2026-09-11 15:46:57','2026-09-11 15:46:57'),(2,1,14,'approved','pending','approved',NULL,'2026-09-11 15:47:39','2026-09-11 15:47:39'),(3,2,2,'submitted',NULL,'pending',NULL,'2026-09-13 06:18:43','2026-09-13 06:18:43'),(4,3,20,'submitted',NULL,'pending',NULL,'2026-09-16 07:48:56','2026-09-16 07:48:56'),(5,3,14,'approved','pending','approved',NULL,'2026-09-16 07:49:16','2026-09-16 07:49:16');
/*!40000 ALTER TABLE `tbl_post_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_post_comments`
--

DROP TABLE IF EXISTS `tbl_post_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_post_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_comments_user_id_foreign` (`user_id`),
  KEY `post_comments_post_id_created_at_index` (`post_id`,`created_at`),
  CONSTRAINT `post_comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_post_comments`
--

LOCK TABLES `tbl_post_comments` WRITE;
/*!40000 ALTER TABLE `tbl_post_comments` DISABLE KEYS */;
INSERT INTO `tbl_post_comments` VALUES (1,1,2,'basta',0,'2026-09-11 16:26:38','2026-09-11 16:26:38');
/*!40000 ALTER TABLE `tbl_post_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_post_reactions`
--

DROP TABLE IF EXISTS `tbl_post_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_post_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'like',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_reactions_post_id_user_id_unique` (`post_id`,`user_id`),
  KEY `post_reactions_user_id_foreign` (`user_id`),
  CONSTRAINT `post_reactions_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_post_reactions`
--

LOCK TABLES `tbl_post_reactions` WRITE;
/*!40000 ALTER TABLE `tbl_post_reactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_post_reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_posts`
--

DROP TABLE IF EXISTS `tbl_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `sbo_event_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'general',
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `posts_event_id_foreign` (`event_id`),
  KEY `posts_reviewed_by_foreign` (`reviewed_by`),
  KEY `posts_status_created_at_index` (`status`,`created_at`),
  KEY `posts_user_id_status_index` (`user_id`,`status`),
  KEY `posts_official_status_created_index` (`is_official`,`status`,`created_at`),
  KEY `posts_activity_foreign` (`activity_id`),
  KEY `posts_sbo_assignment_foreign` (`sbo_event_assignment_id`),
  CONSTRAINT `posts_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `posts_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `posts_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `posts_sbo_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_posts`
--

LOCK TABLES `tbl_posts` WRITE;
/*!40000 ALTER TABLE `tbl_posts` DISABLE KEYS */;
INSERT INTO `tbl_posts` VALUES (1,2,NULL,NULL,NULL,'special-events','test','assets/uploads/posts/zIkJIDrCYdSHIFcoPDfABkPa9OmoB9RT3wZMuRUX.png',NULL,'approved',NULL,14,'2026-09-11 15:47:39','2026-09-11 15:46:57','2026-09-11 15:47:39',NULL,0),(2,2,NULL,NULL,NULL,'general','asdas',NULL,NULL,'pending',NULL,NULL,NULL,'2026-09-13 06:18:43','2026-09-13 06:18:43',NULL,0),(3,20,NULL,NULL,NULL,'general','Mga bayot',NULL,NULL,'approved',NULL,14,'2026-09-16 07:49:16','2026-09-16 07:48:56','2026-09-16 07:49:16',NULL,0);
/*!40000 ALTER TABLE `tbl_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_roles`
--

DROP TABLE IF EXISTS `tbl_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_roles`
--

LOCK TABLES `tbl_roles` WRITE;
/*!40000 ALTER TABLE `tbl_roles` DISABLE KEYS */;
INSERT INTO `tbl_roles` VALUES (1,'SBO Officer','2026-09-11 15:45:43','2026-09-11 15:45:43'),(2,'SBO Adviser','2026-09-11 15:45:47','2026-09-11 15:45:47'),(3,'SBO','2026-09-11 15:45:47','2026-09-11 15:45:47'),(4,'Faculty','2026-09-11 15:45:47','2026-09-11 15:45:47'),(5,'Student','2026-09-11 15:45:47','2026-09-11 15:45:47'),(6,'Admin','2026-09-21 10:26:33','2026-09-21 10:26:33');
/*!40000 ALTER TABLE `tbl_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_sbo_event_assignments`
--

DROP TABLE IF EXISTS `tbl_sbo_event_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_sbo_event_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `officer_assignment_id` bigint(20) unsigned NOT NULL,
  `event_schedule_id` bigint(20) unsigned NOT NULL,
  `session_code` varchar(12) NOT NULL,
  `activity_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `responsibility_id` bigint(20) unsigned NOT NULL,
  `scanner_mode` enum('specific','general') DEFAULT NULL,
  `scanner_team_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `ended_by` bigint(20) unsigned DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sbo_event_assignments_officer_index` (`officer_assignment_id`,`status`),
  KEY `sbo_event_assignments_schedule_index` (`event_schedule_id`,`session_code`,`status`),
  KEY `sbo_event_assignments_activity_foreign` (`activity_id`),
  KEY `sbo_event_assignments_team_foreign` (`team_id`),
  KEY `sbo_event_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `sbo_event_assignments_ended_by_foreign` (`ended_by`),
  KEY `sbo_event_assignments_responsibility_id_foreign` (`responsibility_id`),
  KEY `sbo_event_assignments_scanner_team_id_foreign` (`scanner_team_id`),
  CONSTRAINT `sbo_event_assignments_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sbo_event_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sbo_event_assignments_ended_by_foreign` FOREIGN KEY (`ended_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sbo_event_assignments_officer_foreign` FOREIGN KEY (`officer_assignment_id`) REFERENCES `tbl_sbo_officer_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sbo_event_assignments_responsibility_id_foreign` FOREIGN KEY (`responsibility_id`) REFERENCES `tbl_officer_responsibilities` (`id`),
  CONSTRAINT `sbo_event_assignments_scanner_team_id_foreign` FOREIGN KEY (`scanner_team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sbo_event_assignments_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sbo_event_assignments_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_sbo_event_assignments`
--

LOCK TABLES `tbl_sbo_event_assignments` WRITE;
/*!40000 ALTER TABLE `tbl_sbo_event_assignments` DISABLE KEYS */;
INSERT INTO `tbl_sbo_event_assignments` VALUES (1,1,2,'whole_day',1,2,1,'specific',2,'active',14,NULL,NULL,'2026-09-15 17:13:40','2026-09-21 10:26:34'),(2,1,2,'whole_day',1,1,1,'specific',2,'active',14,NULL,NULL,'2026-09-16 00:45:57','2026-09-21 10:26:34'),(3,3,2,'whole_day',1,6,1,'specific',3,'inactive',14,14,'2026-09-16 07:41:44','2026-09-16 07:16:51','2026-09-21 10:26:34'),(4,3,2,'whole_day',1,1,1,'specific',3,'active',14,NULL,NULL,'2026-09-16 07:39:54','2026-09-21 10:26:34'),(5,4,3,'whole_day',2,1,1,'specific',1,'active',14,NULL,NULL,'2026-09-17 12:31:00','2026-09-21 10:26:34'),(6,3,3,'whole_day',3,6,1,'specific',3,'active',14,NULL,NULL,'2026-09-17 13:16:06','2026-09-21 10:26:34'),(7,3,3,'whole_day',2,1,1,'specific',3,'active',14,NULL,NULL,'2026-09-17 13:38:37','2026-09-21 10:26:34'),(8,3,4,'whole_day',4,6,1,'specific',3,'active',14,NULL,NULL,'2026-09-17 17:34:43','2026-09-21 10:26:34');
/*!40000 ALTER TABLE `tbl_sbo_event_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_sbo_officer_assignments`
--

DROP TABLE IF EXISTS `tbl_sbo_officer_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_sbo_officer_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` varchar(255) NOT NULL,
  `officer_user_id` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_by` bigint(20) unsigned DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sbo_officer_assignments_officer_user_id_foreign` (`officer_user_id`),
  KEY `sbo_officer_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `sbo_officer_assignments_ended_by_foreign` (`ended_by`),
  KEY `officer_student_status_index` (`student_id`,`status`),
  CONSTRAINT `sbo_officer_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sbo_officer_assignments_ended_by_foreign` FOREIGN KEY (`ended_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sbo_officer_assignments_officer_user_id_foreign` FOREIGN KEY (`officer_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_sbo_officer_assignments`
--

LOCK TABLES `tbl_sbo_officer_assignments` WRITE;
/*!40000 ALTER TABLE `tbl_sbo_officer_assignments` DISABLE KEYS */;
INSERT INTO `tbl_sbo_officer_assignments` VALUES (1,'02-2026-000003',15,14,'2026-09-21 10:26:34',NULL,NULL,'Active','2026-09-12 05:09:22','2026-09-21 10:26:34'),(2,'02-2026-000001',16,14,'2026-09-21 10:26:34',NULL,NULL,'Active','2026-09-16 02:57:06','2026-09-21 10:26:34'),(3,'02-2324-011281',19,14,'2026-09-21 10:26:34',NULL,NULL,'Active','2026-09-16 07:03:06','2026-09-21 10:26:34'),(4,'02-2324-011280',21,14,'2026-09-21 10:26:34',NULL,NULL,'Active','2026-09-17 12:30:50','2026-09-21 10:26:34');
/*!40000 ALTER TABLE `tbl_sbo_officer_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_sbo_scan_rate_limits`
--

DROP TABLE IF EXISTS `tbl_sbo_scan_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_sbo_scan_rate_limits` (
  `officer_user_id` bigint(20) unsigned NOT NULL,
  `window_started_at` datetime NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`officer_user_id`),
  CONSTRAINT `sbo_scan_rate_limits_officer_foreign` FOREIGN KEY (`officer_user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_sbo_scan_rate_limits`
--

LOCK TABLES `tbl_sbo_scan_rate_limits` WRITE;
/*!40000 ALTER TABLE `tbl_sbo_scan_rate_limits` DISABLE KEYS */;
INSERT INTO `tbl_sbo_scan_rate_limits` VALUES (19,'2026-09-18 12:03:23',1,'2026-09-18 12:03:23'),(21,'2026-09-17 22:34:05',1,'2026-09-17 22:34:05');
/*!40000 ALTER TABLE `tbl_sbo_scan_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_school_years`
--

DROP TABLE IF EXISTS `tbl_school_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_school_years` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `teams_randomized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_years_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_school_years`
--

LOCK TABLES `tbl_school_years` WRITE;
/*!40000 ALTER TABLE `tbl_school_years` DISABLE KEYS */;
INSERT INTO `tbl_school_years` VALUES (1,'2026','2026-09-12 05:06:41','2026-09-11 15:45:47','2026-09-12 05:06:41');
/*!40000 ALTER TABLE `tbl_school_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_score_categories`
--

DROP TABLE IF EXISTS `tbl_score_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_score_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `activity_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `min_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_points` decimal(10,2) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `score_categories_event_activity_name_unique` (`event_id`,`activity_id`,`name`),
  KEY `score_categories_event_id_sort_order_index` (`event_id`,`sort_order`),
  KEY `score_categories_activity_foreign` (`activity_id`),
  CONSTRAINT `score_categories_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_categories_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_score_categories`
--

LOCK TABLES `tbl_score_categories` WRITE;
/*!40000 ALTER TABLE `tbl_score_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_score_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_score_sheets`
--

DROP TABLE IF EXISTS `tbl_score_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_score_sheets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `activity_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned DEFAULT NULL,
  `event_schedule_id` bigint(20) unsigned DEFAULT NULL,
  `session_code` varchar(12) DEFAULT NULL,
  `sbo_event_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `reopened_by` bigint(20) unsigned DEFAULT NULL,
  `reopened_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `score_sheets_event_activity_team_unique` (`event_id`,`activity_id`,`team_id`),
  KEY `score_sheets_assignment_foreign` (`sbo_event_assignment_id`),
  KEY `score_sheets_submitted_by_foreign` (`submitted_by`),
  KEY `score_sheets_reopened_by_foreign` (`reopened_by`),
  KEY `score_sheets_activity_foreign` (`activity_id`),
  KEY `score_sheets_team_foreign` (`team_id`),
  KEY `score_sheets_schedule_foreign` (`event_schedule_id`),
  CONSTRAINT `score_sheets_activity_foreign` FOREIGN KEY (`activity_id`) REFERENCES `tbl_event_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_sheets_assignment_foreign` FOREIGN KEY (`sbo_event_assignment_id`) REFERENCES `tbl_sbo_event_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_sheets_event_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_sheets_reopened_by_foreign` FOREIGN KEY (`reopened_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `score_sheets_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `score_sheets_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `score_sheets_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_score_sheets`
--

LOCK TABLES `tbl_score_sheets` WRITE;
/*!40000 ALTER TABLE `tbl_score_sheets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_score_sheets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_scores`
--

DROP TABLE IF EXISTS `tbl_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `score_category_id` bigint(20) unsigned DEFAULT NULL,
  `points` decimal(10,2) NOT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scores_event_team_category_unique` (`event_id`,`team_id`,`score_category_id`),
  KEY `scores_recorded_by_foreign` (`recorded_by`),
  KEY `scores_team_id_points_index` (`team_id`,`points`),
  KEY `scores_score_category_id_foreign` (`score_category_id`),
  KEY `scores_event_id_index` (`event_id`),
  CONSTRAINT `scores_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scores_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scores_score_category_id_foreign` FOREIGN KEY (`score_category_id`) REFERENCES `tbl_score_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scores_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_scores`
--

LOCK TABLES `tbl_scores` WRITE;
/*!40000 ALTER TABLE `tbl_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_sessions`
--

DROP TABLE IF EXISTS `tbl_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_sessions`
--

LOCK TABLES `tbl_sessions` WRITE;
/*!40000 ALTER TABLE `tbl_sessions` DISABLE KEYS */;
INSERT INTO `tbl_sessions` VALUES ('1PKW7DbThzrSyo1Bk5IASUs1WgH7pQ3ZXbKZOMgR',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoidU9zYzN0YnlJemRsNmkwdVFNUVdyMGVlN1E3M3VSbHBQYzZIODFKdyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789278170),('2EOXjBmV1cNw55CFG1zpbAjpFVjI6NkYtmJH34rU',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoib2hUVXVRZEZMSFFBRzRoQVVzZlJtQmc1WTd5TUlSb3doSUtFYXFtaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789278180),('gzykAL2cjBSMB4cvldK1UcUIw9oQJQFgObaYfufs',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiYjVNVFEzbzlvVm1LUGVieFdwMDBwc1dzenFhTFAyRkYwT0JOdG1BciI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozMToiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2Rhc2hib2FyZCI7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMxOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjk6ImRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjI7fQ==',1789203441),('lPEJh3MwkZtQAuwGbKxwKzxb7EXA5tTibPmSle8S',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.137.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWUU4Z1RrV0hLalR4cW1ZTFB4UzNzWlNQcWowYjllMGdYU1RUNVVOZiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789278145),('q8Ih5Sqq1OvHLzYFL8gt1Kwa5WT9xtom9AF9rD8r',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiNzVtcEdJa2RiTFRsUXhGN0RyTWUxbHQyTDFIR1RZdGRSNWYwaXdtbyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Mjt9',1789278191),('R6xB5NXHsNecXtcOCuhV1ywmzmm4wuMm50u9bOgi',15,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR0R0dTBuSTRrSm9XUHFxeDhpdEhjQzdsc3lDU2ZrTks1QkdORHhSQSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozODoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL2FkdmlzZXIvb2ZmaWNlcnMiO31zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czo0MDoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL29mZmljZXIvYXR0ZW5kYW5jZSI7czo1OiJyb3V0ZSI7czoyNDoib2ZmaWNlci5hdHRlbmRhbmNlLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTU7fQ==',1789190384),('WRi4gOvTWGgzhd0DKJUS63vRK4MzhD5m5nIJBjDY',14,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiTkZoZHdMMUo2WjlIdE1kZ0NLVUY5YmtYSE56dmN2VEwyVHByc3FmViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzg6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZHZpc2VyL29mZmljZXJzIjtzOjU6InJvdXRlIjtzOjIyOiJhZHZpc2VyLm9mZmljZXJzLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTQ7fQ==',1789190026),('Y0O7RAOrPwfdrG7efAi8sulAIH2V3xXEXHVRs8QM',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.137.0 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoidEtOMmxTcnlDM1BSTFV0bnZNYlJKVTl3eEFidDNybzVzZzNlS3F4VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789189105);
/*!40000 ALTER TABLE `tbl_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_student_import_batches`
--

DROP TABLE IF EXISTS `tbl_student_import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_student_import_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_filename` varchar(255) NOT NULL,
  `file_sha256` char(64) NOT NULL,
  `mode` enum('preview','replace') NOT NULL DEFAULT 'preview',
  `status` enum('previewed','applying','completed','failed') NOT NULL DEFAULT 'previewed',
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `ready_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `warning_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `review_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `blocked_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `imported_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `imported_by` bigint(20) unsigned DEFAULT NULL,
  `failure_message` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `applied_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_import_batches_status_index` (`status`),
  KEY `student_import_batches_imported_by_index` (`imported_by`),
  CONSTRAINT `student_import_batches_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_student_import_batches`
--

LOCK TABLES `tbl_student_import_batches` WRITE;
/*!40000 ALTER TABLE `tbl_student_import_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_student_import_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_student_import_rows`
--

DROP TABLE IF EXISTS `tbl_student_import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_student_import_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `source_row` int(10) unsigned NOT NULL,
  `record_key` varchar(255) DEFAULT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `official_name` varchar(500) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `campus` varchar(255) DEFAULT NULL,
  `program` varchar(255) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `section_name` varchar(255) DEFAULT NULL,
  `tribe` varchar(255) DEFAULT NULL,
  `school_year` varchar(100) DEFAULT NULL,
  `enrollment_status` varchar(255) DEFAULT NULL,
  `source_files` text DEFAULT NULL,
  `source_issues` text DEFAULT NULL,
  `review_resolution` varchar(100) DEFAULT NULL,
  `source_row_status` varchar(50) DEFAULT NULL,
  `validation_status` enum('ready','warning','review','blocked','imported') NOT NULL,
  `flags_json` longtext NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_import_rows_batch_status_index` (`batch_id`,`validation_status`),
  KEY `student_import_rows_student_id_index` (`student_id`),
  KEY `student_import_rows_record_key_index` (`record_key`),
  KEY `student_import_rows_user_index` (`user_id`),
  CONSTRAINT `student_import_rows_batch_foreign` FOREIGN KEY (`batch_id`) REFERENCES `tbl_student_import_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_import_rows_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_student_import_rows`
--

LOCK TABLES `tbl_student_import_rows` WRITE;
/*!40000 ALTER TABLE `tbl_student_import_rows` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_student_import_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_student_profiles`
--

DROP TABLE IF EXISTS `tbl_student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_student_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `record_key` varchar(255) NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `campus` varchar(255) DEFAULT NULL,
  `program` varchar(255) DEFAULT NULL,
  `section_name` varchar(255) DEFAULT NULL,
  `school_year_label` varchar(100) DEFAULT NULL,
  `enrollment_status` varchar(255) DEFAULT NULL,
  `source_files` text DEFAULT NULL,
  `last_import_batch_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `student_profiles_record_key_unique` (`record_key`),
  KEY `student_profiles_last_import_batch_index` (`last_import_batch_id`),
  CONSTRAINT `student_profiles_last_import_batch_foreign` FOREIGN KEY (`last_import_batch_id`) REFERENCES `tbl_student_import_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_profiles_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_student_profiles`
--

LOCK TABLES `tbl_student_profiles` WRITE;
/*!40000 ALTER TABLE `tbl_student_profiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_student_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_team_user`
--

DROP TABLE IF EXISTS `tbl_team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_team_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`),
  KEY `team_user_user_id_foreign` (`user_id`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_team_user`
--

LOCK TABLES `tbl_team_user` WRITE;
/*!40000 ALTER TABLE `tbl_team_user` DISABLE KEYS */;
INSERT INTO `tbl_team_user` VALUES (4,2,9,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(5,2,11,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(6,2,3,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(10,4,7,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(11,4,12,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(12,4,1,'2026-09-12 05:06:41','2026-09-12 05:06:41'),(16,6,17,'2026-09-16 07:44:58','2026-09-16 07:44:58'),(42,1,20,'2026-09-17 14:03:11','2026-09-17 14:03:11'),(43,1,10,'2026-09-17 14:03:11','2026-09-17 14:03:11'),(44,1,6,'2026-09-17 14:03:11','2026-09-17 14:03:11'),(45,1,8,'2026-09-17 14:03:11','2026-09-17 14:03:11'),(46,3,2,'2026-09-17 14:03:16','2026-09-17 14:03:16'),(47,3,22,'2026-09-17 14:03:16','2026-09-17 14:03:16'),(48,3,4,'2026-09-17 14:03:16','2026-09-17 14:03:16'),(49,3,5,'2026-09-17 14:03:16','2026-09-17 14:03:16');
/*!40000 ALTER TABLE `tbl_team_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_teams`
--

DROP TABLE IF EXISTS `tbl_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_year_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#41B06E',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_school_year_id_name_unique` (`school_year_id`,`name`),
  CONSTRAINT `teams_school_year_id_foreign` FOREIGN KEY (`school_year_id`) REFERENCES `tbl_school_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_teams`
--

LOCK TABLES `tbl_teams` WRITE;
/*!40000 ALTER TABLE `tbl_teams` DISABLE KEYS */;
INSERT INTO `tbl_teams` VALUES (1,1,'Green Falcons','#397565',1,'2026-09-12 05:05:33','2026-09-17 14:03:11'),(2,1,'Blue Sharks','#2F3AE0',1,'2026-09-12 05:05:33','2026-09-12 05:05:33'),(3,1,'Yellow Tigers','#D4A017',1,'2026-09-12 05:05:33','2026-09-17 14:03:16'),(4,1,'Red Lions','#DC2626',1,'2026-09-12 05:05:33','2026-09-12 05:05:33'),(5,1,'Leaderboard green','#397565',1,'2026-09-16 04:40:00','2026-09-16 04:40:00'),(6,1,'Blue Cobalt','#2F3AE0',1,'2026-09-16 04:40:38','2026-09-16 07:44:58');
/*!40000 ALTER TABLE `tbl_teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_user_statuses`
--

DROP TABLE IF EXISTS `tbl_user_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_user_statuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_statuses_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_user_statuses`
--

LOCK TABLES `tbl_user_statuses` WRITE;
/*!40000 ALTER TABLE `tbl_user_statuses` DISABLE KEYS */;
INSERT INTO `tbl_user_statuses` VALUES (1,'active','2026-09-11 15:45:47','2026-09-11 15:45:47'),(2,'inactive','2026-09-11 15:45:47','2026-09-11 15:45:47');
/*!40000 ALTER TABLE `tbl_user_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_users`
--

DROP TABLE IF EXISTS `tbl_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `year_level` bigint(20) unsigned DEFAULT NULL,
  `officer_team_id` bigint(20) unsigned DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `status` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `bio` varchar(280) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_year_level_foreign` (`year_level`),
  KEY `users_status_foreign` (`status`),
  KEY `users_officer_team_id_foreign` (`officer_team_id`),
  CONSTRAINT `users_officer_team_id_foreign` FOREIGN KEY (`officer_team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `tbl_roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_status_foreign` FOREIGN KEY (`status`) REFERENCES `tbl_user_statuses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_year_level_foreign` FOREIGN KEY (`year_level`) REFERENCES `tbl_year_levels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_users`
--

LOCK TABLES `tbl_users` WRITE;
/*!40000 ALTER TABLE `tbl_users` DISABLE KEYS */;
INSERT INTO `tbl_users` VALUES (1,5,'02-2026-000001','Student',NULL,'First Year 1',1,NULL,'student.1','$2y$10$niFXH2gKuGscfKUcvNhFpuvYNetSEql8kLJGdIIGkTtaYZMzi5Jqa',0,1,'student1@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:48','2026-09-15 16:52:57'),(2,5,'02-2026-000002','Student',NULL,'First Year 2',1,NULL,'student.2','$2y$12$d9sSWozwG.nSbuDBnpBK.uhKqIhbgOSJer/olqnnO3oHwp6SD9TSe',0,1,'student2@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:48','2026-09-11 15:45:48'),(3,5,'02-2026-000003','Student',NULL,'First Year 3',1,NULL,'student.3','$2y$12$x4cgSsAwXCZAG0XWS7xe5eGmbOq/BesCPYnvF9ZXgHsqi4y1/Wzkq',0,1,'student3@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:48','2026-09-11 15:45:48'),(4,5,'02-2026-000004','Student',NULL,'Second Year 1',2,NULL,'student.4','$2y$12$rhHOhM91kaoVYzipsl4jpeqMU9gC.p8l/58XwC9LlJ5XBZ4VUSX8S',0,1,'student4@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:49','2026-09-11 15:45:49'),(5,5,'02-2026-000005','Student',NULL,'Second Year 2',2,NULL,'student.5','$2y$12$GXp0FQfRodYhEbb3uAvSZubV26zGLJ3IiISRdrAFu3W7DQuUp80Ve',0,1,'student5@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:49','2026-09-11 15:45:49'),(6,5,'02-2026-000006','Student',NULL,'Second Year 3',2,NULL,'student.6','$2y$12$wm3wrb90coKsXqIhn9sKs.I0jQP3jqQdBu1Y8nHxCC08wAr4mcIvS',0,1,'student6@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:50','2026-09-11 15:45:50'),(7,5,'02-2026-000007','Student',NULL,'Third Year 1',3,NULL,'student.7','$2y$12$5AF4xt8t4H9AqjfH02POceDAIMAbYK9MyekAqsh6X7bEw7MxfXEHu',0,1,'student7@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:50','2026-09-11 15:45:50'),(8,5,'02-2026-000008','Student',NULL,'Third Year 2',3,NULL,'student.8','$2y$12$EoSdqnXjmDhJ.8ysPYy8SO5bdVVBcYW43ko4enmrdjcJnBwPO1r/u',0,1,'student8@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:50','2026-09-11 15:45:50'),(9,5,'02-2026-000009','Student',NULL,'Third Year 3',3,NULL,'student.9','$2y$12$4xr5EuAhfxINZ794n/kUU.nLWvUvzD9I/gri2WH8oEF1NOkrhyZ4a',0,1,'student9@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:51','2026-09-11 15:45:51'),(10,5,'02-2026-000010','Student',NULL,'Fourth Year 1',4,NULL,'student.10','$2y$12$NGmLrLAKPkTf1tBK0wzV1uMS14UM/XJprZFWIC2nZ9Di6.JGtrlfS',0,1,'student10@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:51','2026-09-11 15:45:51'),(11,5,'02-2026-000011','Student',NULL,'Fourth Year 2',4,NULL,'student.11','$2y$12$UDNq9yx2i.PEDcXy7Qv9su/n/Dk7bpiMr9EFHIUdOH327r4/A3OPa',0,1,'student11@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:52','2026-09-11 15:45:52'),(12,5,'02-2026-000012','Student',NULL,'Fourth Year 3',4,NULL,'student.12','$2y$12$N7xg6/.SwG2J8g1xy0kbhOq2ETl.yjVdphRw9MloUjku5.4nlscEm',0,1,'student12@cite.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:52','2026-09-11 15:45:52'),(13,NULL,'00000001','Test',NULL,'User',NULL,NULL,'testuser','$2y$12$yK34qNcXoqCznpPFqu2IluUZtK0vuSF53yvfgoc7xzpgVf4./by.a',0,NULL,'test@example.com',NULL,NULL,NULL,NULL,'2026-09-11 15:45:53','2026-09-11 15:45:53'),(14,2,'SBO-ADV-001','SBO',NULL,'Adviser',NULL,NULL,'sbo.adviser','$2y$12$L5TwgVhfKq6uscpYKpQSOua44hO5wWiuk06mSzw1xJlLFL1LCzkl6',0,1,'adviser@itevents.local',NULL,NULL,NULL,NULL,'2026-09-11 15:45:54','2026-09-11 15:45:54'),(15,1,'02-2026-000003','Student',NULL,'First Year 3',1,NULL,'student.blue','$2y$10$fVlGoT7gvEpRM7397xlEpeD6ffynOvzmSasBFbC4KwR64MBK/C5CG',0,1,'student.blue@officer.itevents.local',NULL,NULL,NULL,NULL,'2026-09-12 05:09:22','2026-09-21 10:26:34'),(16,1,'02-2026-000001','Student',NULL,'First Year 1',1,NULL,'student.green','$2y$10$G6zrjkmyQs8/G69UVHF8j.vlWnRmsU3uXU58XMTEZUXLKl1ZsCagC',0,1,'student.green@officer.itevents.local',NULL,NULL,NULL,NULL,'2026-09-16 02:57:06','2026-09-21 10:26:34'),(17,5,'02-2324-011280','Micah','D','Lago',4,NULL,'micah2026','$2y$10$r/cROsPk7hWW4e8zCncwkeKBRUd1T82qA.wbX81rqoi2wG/6O.ZMy',0,1,'micah@gmail.com',NULL,NULL,NULL,NULL,'2026-09-16 06:56:44','2026-09-16 06:56:44'),(18,5,'02-2324-011281','jessie','D','Parajes',1,NULL,'jessiejames','$2y$10$/htQc9UinMa3oCIA2WwPNui4EfBjuP3kLx2MhVIWDn3/4vAp51Vqm',0,1,'jessiejames123@gmai.com',NULL,NULL,NULL,NULL,'2026-09-16 06:59:28','2026-09-16 06:59:28'),(19,1,'02-2324-011281','jessie','D','Parajes',1,NULL,'jessie.blue','$2y$10$4H1qzt4iQcstc8y2ZoCg6.jIOZ04p.FShJ.QjBXK2eT3N6D4leicS',0,1,'jessie.blue@officer.itevents.local',NULL,NULL,NULL,NULL,'2026-09-16 07:03:06','2026-09-21 10:26:34'),(20,5,'02-2324-011290','mjay','D','calunsag',NULL,NULL,'mjay','$2y$10$L6KFRln/m31zcH1P1Q.twObwSMiHzQ7nmcPAMGtfN4I/GDJ0g18w.',0,1,'mjay@gmail.com',NULL,NULL,NULL,NULL,'2026-09-16 07:34:24','2026-09-17 14:33:35'),(21,1,'02-2324-011280','Micah','D','Lago',4,NULL,'micah.green','$2y$10$KVjBuTJ0DOjNyTHIjGcEzetxXlPjEnMWnebKuxL5dDf5X0FU2L0vu',0,1,'micah.green@officer.itevents.local',NULL,NULL,NULL,NULL,'2026-09-17 12:30:50','2026-09-21 10:26:34'),(22,5,'02-2324-09738','Jessie James','c','Jessie James',NULL,NULL,'jess.student','$2y$10$y4RB0QpPhpJhUu7bmFuklu5im4rqv.orTXV6cGva.EqxhBs0gcW0e',0,1,'jeca.parajes.coc@phinmaed.com',NULL,NULL,NULL,NULL,'2026-09-17 13:04:16','2026-09-17 13:05:28');
/*!40000 ALTER TABLE `tbl_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_year_levels`
--

DROP TABLE IF EXISTS `tbl_year_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_year_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_levels_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_year_levels`
--

LOCK TABLES `tbl_year_levels` WRITE;
/*!40000 ALTER TABLE `tbl_year_levels` DISABLE KEYS */;
INSERT INTO `tbl_year_levels` VALUES (1,'First Year','2026-09-11 15:45:47','2026-09-11 15:45:47'),(2,'Second Year','2026-09-11 15:45:47','2026-09-11 15:45:47'),(3,'Third Year','2026-09-11 15:45:47','2026-09-11 15:45:47'),(4,'Fourth Year','2026-09-11 15:45:47','2026-09-11 15:45:47');
/*!40000 ALTER TABLE `tbl_year_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `vw_attendance_effective`
--

DROP TABLE IF EXISTS `vw_attendance_effective`;
/*!50001 DROP VIEW IF EXISTS `vw_attendance_effective`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_attendance_effective` AS SELECT
 1 AS `id`,
  1 AS `event_id`,
  1 AS `user_id`,
  1 AS `attendance_date`,
  1 AS `status`,
  1 AS `manual_status`,
  1 AS `checked_in_at`,
  1 AS `morning_in_at`,
  1 AS `morning_out_at`,
  1 AS `afternoon_in_at`,
  1 AS `afternoon_out_at`,
  1 AS `recorded_by`,
  1 AS `manual_corrected_by`,
  1 AS `manual_corrected_at`,
  1 AS `manual_reason`,
  1 AS `notes`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `effective_status` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_finalized_scores`
--

DROP TABLE IF EXISTS `vw_finalized_scores`;
/*!50001 DROP VIEW IF EXISTS `vw_finalized_scores`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_finalized_scores` AS SELECT
 1 AS `id`,
  1 AS `event_id`,
  1 AS `team_id`,
  1 AS `score_category_id`,
  1 AS `points`,
  1 AS `recorded_by`,
  1 AS `notes`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'event_db'
--

--
-- Dumping routines for database 'event_db'
--

--
-- Final view structure for view `vw_attendance_effective`
--

/*!50001 DROP VIEW IF EXISTS `vw_attendance_effective`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_attendance_effective` AS select `attendance`.`id` AS `id`,`attendance`.`event_id` AS `event_id`,`attendance`.`user_id` AS `user_id`,`attendance`.`attendance_date` AS `attendance_date`,`attendance`.`status` AS `status`,`attendance`.`manual_status` AS `manual_status`,`attendance`.`checked_in_at` AS `checked_in_at`,`attendance`.`morning_in_at` AS `morning_in_at`,`attendance`.`morning_out_at` AS `morning_out_at`,`attendance`.`afternoon_in_at` AS `afternoon_in_at`,`attendance`.`afternoon_out_at` AS `afternoon_out_at`,`attendance`.`recorded_by` AS `recorded_by`,`attendance`.`manual_corrected_by` AS `manual_corrected_by`,`attendance`.`manual_corrected_at` AS `manual_corrected_at`,`attendance`.`manual_reason` AS `manual_reason`,`attendance`.`notes` AS `notes`,`attendance`.`created_at` AS `created_at`,`attendance`.`updated_at` AS `updated_at`,coalesce(`attendance`.`manual_status`,`attendance`.`status`) AS `effective_status` from `tbl_attendances` `attendance` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_finalized_scores`
--

/*!50001 DROP VIEW IF EXISTS `vw_finalized_scores`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_finalized_scores` AS select `score`.`id` AS `id`,`score`.`event_id` AS `event_id`,`score`.`team_id` AS `team_id`,`score`.`score_category_id` AS `score_category_id`,`score`.`points` AS `points`,`score`.`recorded_by` AS `recorded_by`,`score`.`notes` AS `notes`,`score`.`created_at` AS `created_at`,`score`.`updated_at` AS `updated_at` from ((`tbl_scores` `score` join `tbl_score_categories` `category` on(`category`.`id` = `score`.`score_category_id`)) join `tbl_score_sheets` `sheet` on(`sheet`.`event_id` = `score`.`event_id` and `sheet`.`activity_id` = `category`.`activity_id` and `sheet`.`team_id` = `score`.`team_id` and `sheet`.`status` = 'finalized')) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-21 18:29:39
