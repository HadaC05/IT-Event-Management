-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: it_event_management
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
-- Table structure for table `account_password_reset_tokens`
--

DROP TABLE IF EXISTS `account_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_password_reset_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_password_reset_tokens_user_id_unique` (`user_id`),
  CONSTRAINT `fk_account_password_reset_tokens_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_password_reset_tokens`
--

LOCK TABLES `account_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `account_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `actor_id` bigint(20) DEFAULT NULL,
  `subject_user_id` bigint(20) DEFAULT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `officer_assignment_id` bigint(20) DEFAULT NULL,
  `acting_role` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activity_logs_officer_assignment_id_sbo_officer__53599f58` (`officer_assignment_id`),
  KEY `fk_activity_logs_actor_id_users` (`actor_id`),
  KEY `fk_activity_logs_subject_user_id_users` (`subject_user_id`),
  KEY `fk_activity_logs_event_id_events` (`event_id`),
  CONSTRAINT `fk_activity_logs_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_activity_logs_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_activity_logs_officer_assignment_id_sbo_officer__53599f58` FOREIGN KEY (`officer_assignment_id`) REFERENCES `sbo_officer_assignments` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_activity_logs_subject_user_id_users` FOREIGN KEY (`subject_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_qr_tokens`
--

DROP TABLE IF EXISTS `attendance_qr_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_qr_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `session` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_qr_tokens_token_unique` (`token`),
  UNIQUE KEY `attendance_qr_tokens_event_id_user_id_session_unique` (`event_id`,`user_id`,`session`),
  KEY `fk_attendance_qr_tokens_user_id_users` (`user_id`),
  CONSTRAINT `fk_attendance_qr_tokens_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_attendance_qr_tokens_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_qr_tokens`
--

LOCK TABLES `attendance_qr_tokens` WRITE;
/*!40000 ALTER TABLE `attendance_qr_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_qr_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_session_modes`
--

DROP TABLE IF EXISTS `attendance_session_modes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_session_modes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_session_modes_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_session_modes`
--

LOCK TABLES `attendance_session_modes` WRITE;
/*!40000 ALTER TABLE `attendance_session_modes` DISABLE KEYS */;
INSERT INTO `attendance_session_modes` VALUES (1,'none','No attendance scanning','2026-09-12 23:47:26','2026-09-12 23:47:26'),(2,'whole_day','Whole day — one sign in and sign out','2026-09-12 23:47:26','2026-09-12 23:47:26'),(3,'two_sessions','Morning and afternoon — two sign-ins and sign-outs','2026-09-12 23:47:26','2026-09-12 23:47:26');
/*!40000 ALTER TABLE `attendance_session_modes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendances` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `status` varchar(255) NOT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `recorded_by` bigint(20) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `morning_in_at` datetime DEFAULT NULL,
  `morning_out_at` datetime DEFAULT NULL,
  `afternoon_in_at` datetime DEFAULT NULL,
  `afternoon_out_at` datetime DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_event_user_date_unique` (`event_id`,`user_id`,`attendance_date`),
  KEY `attendances_event_id_status_index` (`event_id`,`status`),
  KEY `fk_attendances_recorded_by_users` (`recorded_by`),
  KEY `fk_attendances_user_id_users` (`user_id`),
  CONSTRAINT `fk_attendances_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_attendances_recorded_by_users` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_attendances_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` longtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba','i:2;',1789228319),('cite-events-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1789228319;',1789228319);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_attendance_schedules`
--

DROP TABLE IF EXISTS `event_attendance_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_attendance_schedules` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `schedule_date` date NOT NULL,
  `morning_in_time` time DEFAULT NULL,
  `morning_out_time` time DEFAULT NULL,
  `afternoon_in_time` time DEFAULT NULL,
  `afternoon_out_time` time DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `attendance_session_mode_id` bigint(20) NOT NULL,
  `whole_day_in_time` time DEFAULT NULL,
  `whole_day_out_time` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_attendance_schedules_event_id_schedule_date_unique` (`event_id`,`schedule_date`),
  KEY `fk_event_attendance_schedules_attendance_session_mo_bad01593` (`attendance_session_mode_id`),
  CONSTRAINT `fk_event_attendance_schedules_attendance_session_mo_bad01593` FOREIGN KEY (`attendance_session_mode_id`) REFERENCES `attendance_session_modes` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_attendance_schedules_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_attendance_schedules`
--

LOCK TABLES `event_attendance_schedules` WRITE;
/*!40000 ALTER TABLE `event_attendance_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_attendance_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_participants`
--

DROP TABLE IF EXISTS `event_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_participants` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_participants_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `fk_event_participants_user_id_users` (`user_id`),
  CONSTRAINT `fk_event_participants_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_participants_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_participants`
--

LOCK TABLES `event_participants` WRITE;
/*!40000 ALTER TABLE `event_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_statuses`
--

DROP TABLE IF EXISTS `event_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_statuses` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_statuses_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_statuses`
--

LOCK TABLES `event_statuses` WRITE;
/*!40000 ALTER TABLE `event_statuses` DISABLE KEYS */;
INSERT INTO `event_statuses` VALUES (1,'upcoming','2026-09-12 23:48:01','2026-09-12 23:48:01'),(2,'ongoing','2026-09-12 23:48:01','2026-09-12 23:48:01'),(3,'completed','2026-09-12 23:48:01','2026-09-12 23:48:01'),(4,'archived','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `event_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_team`
--

DROP TABLE IF EXISTS `event_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_team` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `team_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_team_event_id_team_id_unique` (`event_id`,`team_id`),
  KEY `fk_event_team_team_id_teams` (`team_id`),
  CONSTRAINT `fk_event_team_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_team_team_id_teams` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_team`
--

LOCK TABLES `event_team` WRITE;
/*!40000 ALTER TABLE `event_team` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_team` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_types`
--

DROP TABLE IF EXISTS `event_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_types` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_types_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_types`
--

LOCK TABLES `event_types` WRITE;
/*!40000 ALTER TABLE `event_types` DISABLE KEYS */;
INSERT INTO `event_types` VALUES (1,'IT Days','2026-09-12 23:48:01','2026-09-12 23:48:01'),(2,'IT Expo','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `event_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_user`
--

DROP TABLE IF EXISTS `event_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_user` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_user_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `fk_event_user_user_id_users` (`user_id`),
  CONSTRAINT `fk_event_user_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_user_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_user`
--

LOCK TABLES `event_user` WRITE;
/*!40000 ALTER TABLE `event_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_year_level`
--

DROP TABLE IF EXISTS `event_year_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_year_level` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `year_level_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_year_level_event_id_year_level_id_unique` (`event_id`,`year_level_id`),
  KEY `fk_event_year_level_year_level_id_year_levels` (`year_level_id`),
  CONSTRAINT `fk_event_year_level_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_event_year_level_year_level_id_year_levels` FOREIGN KEY (`year_level_id`) REFERENCES `year_levels` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_year_level`
--

LOCK TABLES `event_year_level` WRITE;
/*!40000 ALTER TABLE `event_year_level` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_year_level` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `event_type_id` bigint(20) DEFAULT NULL,
  `event_status_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `audience_type` varchar(255) NOT NULL DEFAULT 'all_students',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_order` bigint(20) DEFAULT NULL,
  `featured_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_is_featured_featured_order_index` (`is_featured`,`featured_order`),
  KEY `fk_events_created_by_users` (`created_by`),
  KEY `fk_events_event_type_id_event_types` (`event_type_id`),
  KEY `fk_events_event_status_id_event_statuses` (`event_status_id`),
  CONSTRAINT `fk_events_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_events_event_status_id_event_statuses` FOREIGN KEY (`event_status_id`) REFERENCES `event_statuses` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_events_event_type_id_event_types` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` longtext NOT NULL,
  `queue` longtext NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` bigint(20) NOT NULL,
  `pending_jobs` bigint(20) NOT NULL,
  `failed_jobs` bigint(20) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` longtext DEFAULT NULL,
  `cancelled_at` bigint(20) DEFAULT NULL,
  `created_at` bigint(20) NOT NULL,
  `finished_at` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` bigint(20) NOT NULL,
  `reserved_at` bigint(20) DEFAULT NULL,
  `available_at` bigint(20) NOT NULL,
  `created_at` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
INSERT INTO `locations` VALUES (1,'PHINMA COC Carmen Campus','general','2026-09-12 23:48:01','2026-09-12 23:48:01'),(2,'MS Computer Lab 1','specific','2026-09-12 23:48:01','2026-09-12 23:48:01'),(3,'PH 310','specific','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_02_234744_create_roles_table',1),(5,'2026_09_03_020127_create_year_levels_table',1),(6,'2026_09_03_021642_create_user_statuses_table',1),(7,'2026_09_03_023253_add_custom_fields_to_user_table',1),(8,'2026_09_03_040614_create_school_years_table',1),(9,'2026_09_03_042239_create_event_types_table',1),(10,'2026_09_03_043911_create_event_statuses_table',1),(11,'2026_09_04_131102_event_db',1),(12,'2026_09_05_000001_create_events_table',1),(13,'2026_09_05_000002_create_event_user_table',1),(14,'2026_09_05_000003_create_activity_logs_table',1),(15,'2026_09_05_000004_add_management_fields_to_events_table',1),(16,'2026_09_06_000005_create_teams_table',1),(17,'2026_09_06_000006_create_team_user_table',1),(18,'2026_09_06_000007_create_attendances_table',1),(19,'2026_09_06_000008_create_scores_table',1),(20,'2026_09_07_000009_add_audience_to_events',1),(21,'2026_09_07_000010_create_score_categories_table',1),(22,'2026_09_07_000011_add_officer_attendance_scans',1),(23,'2026_09_08_000012_create_student_profiles_and_officer_assignments',1),(24,'2026_09_08_000013_finalize_linked_student_account_audit',1),(25,'2026_09_09_000014_seed_it_games_attendance_test_data',1),(26,'2026_09_09_000015_create_attendance_qr_tokens_table',1),(27,'2026_09_09_000016_add_teams_randomized_at_to_school_years',1),(28,'2026_09_09_000017_add_daily_event_attendance_schedules',1),(29,'2026_09_09_000018_add_session_mode_to_event_attendance_schedules',1),(30,'2026_09_09_000019_remove_student_profiles',1),(31,'2026_09_09_000020_create_attendance_session_modes',1),(32,'2026_09_09_000021_remove_event_attendance_time_columns',1),(33,'2026_09_09_000022_create_locations_table',1),(34,'2026_09_09_000023_change_location_type_to_enum',1),(35,'2026_09_10_000024_replace_active_event_status',1),(36,'2026_09_11_000025_create_student_portal_tables',1),(37,'2026_09_11_000026_add_soft_deletes_to_posts',1),(38,'2026_09_11_000027_add_feature_fields_to_events_table',1),(39,'2026_09_12_000028_create_post_engagement_tables',1),(40,'2026_09_12_000029_add_official_flag_to_posts',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) NOT NULL,
  `data` longtext NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_audits`
--

DROP TABLE IF EXISTS `post_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_audits` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) NOT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_post_audits_actor_id_users` (`actor_id`),
  KEY `fk_post_audits_post_id_posts` (`post_id`),
  CONSTRAINT `fk_post_audits_actor_id_users` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_post_audits_post_id_posts` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_audits`
--

LOCK TABLES `post_audits` WRITE;
/*!40000 ALTER TABLE `post_audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_comments`
--

DROP TABLE IF EXISTS `post_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `body` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_comments_post_id_created_at_index` (`post_id`,`created_at`),
  KEY `fk_post_comments_user_id_users` (`user_id`),
  CONSTRAINT `fk_post_comments_post_id_posts` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_post_comments_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_comments`
--

LOCK TABLES `post_comments` WRITE;
/*!40000 ALTER TABLE `post_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `post_reactions`
--

DROP TABLE IF EXISTS `post_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_reactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'like',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_reactions_post_id_user_id_unique` (`post_id`,`user_id`),
  KEY `fk_post_reactions_user_id_users` (`user_id`),
  CONSTRAINT `fk_post_reactions_post_id_posts` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_post_reactions_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `post_reactions`
--

LOCK TABLES `post_reactions` WRITE;
/*!40000 ALTER TABLE `post_reactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `content` longtext NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `rejection_reason` longtext DEFAULT NULL,
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `posts_official_status_created_index` (`is_official`,`status`,`created_at`),
  KEY `posts_user_id_status_index` (`user_id`,`status`),
  KEY `posts_status_created_at_index` (`status`,`created_at`),
  KEY `fk_posts_reviewed_by_users` (`reviewed_by`),
  KEY `fk_posts_event_id_events` (`event_id`),
  CONSTRAINT `fk_posts_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_posts_reviewed_by_users` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_posts_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'SBO Officer','2026-09-12 23:47:26','2026-09-12 23:47:26'),(2,'SBO Adviser','2026-09-12 23:48:01','2026-09-12 23:48:01'),(3,'SBO','2026-09-12 23:48:01','2026-09-12 23:48:01'),(4,'Faculty','2026-09-12 23:48:01','2026-09-12 23:48:01'),(5,'Student','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sbo_officer_assignments`
--

DROP TABLE IF EXISTS `sbo_officer_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sbo_officer_assignments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(255) NOT NULL,
  `officer_user_id` bigint(20) NOT NULL,
  `team_id` bigint(20) DEFAULT NULL,
  `position` varchar(255) NOT NULL,
  `term` varchar(255) NOT NULL,
  `assigned_by` bigint(20) DEFAULT NULL,
  `assigned_at` datetime NOT NULL,
  `ended_by` bigint(20) DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `officer_student_term_status_index` (`student_id`,`term`,`status`),
  KEY `fk_sbo_officer_assignments_ended_by_users` (`ended_by`),
  KEY `fk_sbo_officer_assignments_assigned_by_users` (`assigned_by`),
  KEY `fk_sbo_officer_assignments_team_id_teams` (`team_id`),
  KEY `fk_sbo_officer_assignments_officer_user_id_users` (`officer_user_id`),
  CONSTRAINT `fk_sbo_officer_assignments_assigned_by_users` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_sbo_officer_assignments_ended_by_users` FOREIGN KEY (`ended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_sbo_officer_assignments_officer_user_id_users` FOREIGN KEY (`officer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_sbo_officer_assignments_team_id_teams` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sbo_officer_assignments`
--

LOCK TABLES `sbo_officer_assignments` WRITE;
/*!40000 ALTER TABLE `sbo_officer_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sbo_officer_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_years`
--

DROP TABLE IF EXISTS `school_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_years` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `teams_randomized_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_years_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_years`
--

LOCK TABLES `school_years` WRITE;
/*!40000 ALTER TABLE `school_years` DISABLE KEYS */;
INSERT INTO `school_years` VALUES (1,'2026','2026-09-12 23:48:01','2026-09-12 23:48:01',NULL);
/*!40000 ALTER TABLE `school_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `score_categories`
--

DROP TABLE IF EXISTS `score_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `score_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `max_points` decimal(12,2) NOT NULL,
  `sort_order` bigint(20) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `score_categories_event_id_name_unique` (`event_id`,`name`),
  KEY `score_categories_event_id_sort_order_index` (`event_id`,`sort_order`),
  CONSTRAINT `fk_score_categories_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `score_categories`
--

LOCK TABLES `score_categories` WRITE;
/*!40000 ALTER TABLE `score_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `score_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scores`
--

DROP TABLE IF EXISTS `scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `team_id` bigint(20) NOT NULL,
  `points` decimal(12,2) NOT NULL,
  `recorded_by` bigint(20) DEFAULT NULL,
  `notes` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `score_category_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scores_event_team_category_unique` (`event_id`,`team_id`,`score_category_id`),
  KEY `scores_event_id_index` (`event_id`),
  KEY `scores_team_id_points_index` (`team_id`,`points`),
  KEY `fk_scores_score_category_id_score_categories` (`score_category_id`),
  KEY `fk_scores_recorded_by_users` (`recorded_by`),
  CONSTRAINT `fk_scores_event_id_events` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_scores_recorded_by_users` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_scores_score_category_id_score_categories` FOREIGN KEY (`score_category_id`) REFERENCES `score_categories` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_scores_team_id_teams` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scores`
--

LOCK TABLES `scores` WRITE;
/*!40000 ALTER TABLE `scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` longtext DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  KEY `sessions_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('G5faIiaXWffPaaDfu1VlFzPQdMQkaaEFiTHASxuJ',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoibHRkWThYRVBDUjMyenZaYkRoVGRMalNrdDh4WXA0blNmQ2V0blQ4cSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9fQ==',1789228346),('vuFgLtzziVsMS62iXxHg8DOkj8gqpvjcFk4ts6BU',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.136.1 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVlhmWWNUMVlDTmNBSDVCZGpLQ0hJcUxweXhLZzFwaFc3bGljdGxqNSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1789228177);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_user`
--

DROP TABLE IF EXISTS `team_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_user` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`),
  KEY `fk_team_user_user_id_users` (`user_id`),
  CONSTRAINT `fk_team_user_team_id_teams` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_team_user_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_user`
--

LOCK TABLES `team_user` WRITE;
/*!40000 ALTER TABLE `team_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `team_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `school_year_id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL DEFAULT '#41B06E',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_school_year_id_name_unique` (`school_year_id`,`name`),
  CONSTRAINT `fk_teams_school_year_id_school_years` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_statuses`
--

DROP TABLE IF EXISTS `user_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_statuses` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_statuses_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_statuses`
--

LOCK TABLES `user_statuses` WRITE;
/*!40000 ALTER TABLE `user_statuses` DISABLE KEYS */;
INSERT INTO `user_statuses` VALUES (1,'active','2026-09-12 23:48:01','2026-09-12 23:48:01'),(2,'inactive','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `user_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_number` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `role_id` bigint(20) DEFAULT NULL,
  `year_level` bigint(20) DEFAULT NULL,
  `status` bigint(20) DEFAULT NULL,
  `officer_team_id` bigint(20) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `bio` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `fk_users_officer_team_id_teams` (`officer_team_id`),
  KEY `fk_users_role_id_roles` (`role_id`),
  KEY `fk_users_year_level_year_levels` (`year_level`),
  KEY `fk_users_status_user_statuses` (`status`),
  CONSTRAINT `fk_users_officer_team_id_teams` FOREIGN KEY (`officer_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_users_role_id_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_users_status_user_statuses` FOREIGN KEY (`status`) REFERENCES `user_statuses` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `fk_users_year_level_year_levels` FOREIGN KEY (`year_level`) REFERENCES `year_levels` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'02-2026-000001','Student',NULL,'First Year 1','student.1','$2y$12$YxiKg8oBOqe4Q4Rdr4EVZud18EVdCEGalgcotenso5A.SjwnAqAbm','student1@cite.local',NULL,NULL,'2026-09-12 23:48:01','2026-09-12 23:48:01',5,1,1,NULL,0,NULL,NULL),(2,'02-2026-000002','Student',NULL,'First Year 2','student.2','$2y$12$rVyrEOFHK64f4AVBuQoYMuZo3Zv7kyjiPRQvceWtNDyGWtS7ldHa2','student2@cite.local',NULL,NULL,'2026-09-12 23:48:01','2026-09-12 23:48:01',5,1,1,NULL,0,NULL,NULL),(3,'02-2026-000003','Student',NULL,'First Year 3','student.3','$2y$12$bnmapwzOLsfC./o6IpovmeA5ar5Ok61iAxbtlZbh5xSe0M7MdyJQG','student3@cite.local',NULL,NULL,'2026-09-12 23:48:02','2026-09-12 23:48:02',5,1,1,NULL,0,NULL,NULL),(4,'02-2026-000004','Student',NULL,'Second Year 1','student.4','$2y$12$EEhn75a/hBswqiFkcLRPd.0xGKU.T6IJo/rLmoOqaSAbn0SU9t4Fe','student4@cite.local',NULL,NULL,'2026-09-12 23:48:02','2026-09-12 23:48:02',5,2,1,NULL,0,NULL,NULL),(5,'02-2026-000005','Student',NULL,'Second Year 2','student.5','$2y$12$dCVIrUaL1l3lCW8pObbJ1e1lRltWiTynOqf3qfpAVYkUbpvnLxQcC','student5@cite.local',NULL,NULL,'2026-09-12 23:48:02','2026-09-12 23:48:02',5,2,1,NULL,0,NULL,NULL),(6,'02-2026-000006','Student',NULL,'Second Year 3','student.6','$2y$12$ef47PDYGHcMZe5x8FGJP.OGdBiaNoO/MIFHKtOvXCYE1O1hq51S.C','student6@cite.local',NULL,NULL,'2026-09-12 23:48:02','2026-09-12 23:48:02',5,2,1,NULL,0,NULL,NULL),(7,'02-2026-000007','Student',NULL,'Third Year 1','student.7','$2y$12$QAIQx.bAqs/QCUjnvA/heenOYek8zJ198nXahMTtqjYI/zTp5TNv2','student7@cite.local',NULL,NULL,'2026-09-12 23:48:03','2026-09-12 23:48:03',5,3,1,NULL,0,NULL,NULL),(8,'02-2026-000008','Student',NULL,'Third Year 2','student.8','$2y$12$OgMvLeS4FGiCmrquyFyFyeNLwJgZYG0X/MhaFe6DaE0yllzwMU4wG','student8@cite.local',NULL,NULL,'2026-09-12 23:48:03','2026-09-12 23:48:03',5,3,1,NULL,0,NULL,NULL),(9,'02-2026-000009','Student',NULL,'Third Year 3','student.9','$2y$12$9XAAwgBagpFr7HW7E/H7C.C2lxxoxxpFxeRtOzuNjv1OrKqtDW/re','student9@cite.local',NULL,NULL,'2026-09-12 23:48:03','2026-09-12 23:48:03',5,3,1,NULL,0,NULL,NULL),(10,'02-2026-000010','Student',NULL,'Fourth Year 1','student.10','$2y$12$Y82i1txN3WB9cczHM4yyz.XTcvtyBOeyaRl3QFiwFx0xfn.xOIQ4y','student10@cite.local',NULL,NULL,'2026-09-12 23:48:04','2026-09-12 23:48:04',5,4,1,NULL,0,NULL,NULL),(11,'02-2026-000011','Student',NULL,'Fourth Year 2','student.11','$2y$12$n8Jh3nDQ3US1tqCF57DIhuWoLeuzqKPCij8jkDdOPXUJRXQOo9X5G','student11@cite.local',NULL,NULL,'2026-09-12 23:48:04','2026-09-12 23:48:04',5,4,1,NULL,0,NULL,NULL),(12,'02-2026-000012','Student',NULL,'Fourth Year 3','student.12','$2y$12$L2pNbeZfKuB2AeJIjGfWr.rXG/uV0noESBI7u045ORGo51hrlIvBy','student12@cite.local',NULL,NULL,'2026-09-12 23:48:04','2026-09-12 23:48:04',5,4,1,NULL,0,NULL,NULL),(13,'00000001','Test',NULL,'User','testuser','$2y$12$H2/fHz88.cfZ8WrQSzvuqes20JPhh0SxzQpMFmWNuh8E4zruVeN5S','test@example.com',NULL,NULL,'2026-09-12 23:48:04','2026-09-12 23:48:04',NULL,NULL,NULL,NULL,0,NULL,NULL),(14,'SBO-ADV-001','SBO',NULL,'Adviser','sbo.adviser','$2y$12$cZ0mRqmametl1oE9dcWT6.VJJ7EoY87oBogxdNy2wbTp3yDuDmdby','adviser@itevents.local',NULL,NULL,'2026-09-12 23:48:05','2026-09-12 23:48:05',2,NULL,1,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `year_levels`
--

DROP TABLE IF EXISTS `year_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `year_levels` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_levels_label_unique` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `year_levels`
--

LOCK TABLES `year_levels` WRITE;
/*!40000 ALTER TABLE `year_levels` DISABLE KEYS */;
INSERT INTO `year_levels` VALUES (1,'First Year','2026-09-12 23:48:01','2026-09-12 23:48:01'),(2,'Second Year','2026-09-12 23:48:01','2026-09-12 23:48:01'),(3,'Third Year','2026-09-12 23:48:01','2026-09-12 23:48:01'),(4,'Fourth Year','2026-09-12 23:48:01','2026-09-12 23:48:01');
/*!40000 ALTER TABLE `year_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'it_event_management'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-13 12:47:54
