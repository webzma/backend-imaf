/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: IMAF_DB
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB

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
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1776726014),
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1776726014;',1776726014);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
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
-- Table structure for table `cursos`
--

DROP TABLE IF EXISTS `cursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cursos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `profesor_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `limite_cupo` int(10) unsigned NOT NULL DEFAULT 30,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `codigo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `requisitos` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `whatsapp_url` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cursos_codigo_unique` (`codigo`),
  KEY `cursos_profesor_id_foreign` (`profesor_id`),
  CONSTRAINT `cursos_profesor_id_foreign` FOREIGN KEY (`profesor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cursos`
--

LOCK TABLES `cursos` WRITE;
/*!40000 ALTER TABLE `cursos` DISABLE KEYS */;
INSERT INTO `cursos` VALUES
(1,11,'barberia',30,NULL,NULL,'bar-01','aprende a afeitar como un pro',NULL,0.00,NULL,'activo','2026-04-06 00:10:12','2026-04-06 00:10:12'),
(2,11,'algo',30,NULL,NULL,'1212','algo',NULL,0.00,NULL,'activo','2026-04-06 00:27:43','2026-04-06 00:27:43'),
(3,11,'algebra lineal',30,NULL,NULL,'al-01','eso',NULL,0.00,NULL,'activo','2026-04-06 00:36:57','2026-04-06 00:36:57'),
(4,11,'estre',30,NULL,NULL,'12','12',NULL,0.00,NULL,'activo','2026-04-06 00:43:29','2026-04-06 00:43:29'),
(5,11,'como ser como ilon musk',30,NULL,NULL,'3','seunprobro',NULL,0.00,NULL,'activo','2026-04-06 01:49:04','2026-04-06 01:49:04'),
(6,11,'samsung',30,NULL,NULL,'3434','ci',NULL,0.00,NULL,'activo','2026-04-06 02:02:40','2026-04-06 02:02:40'),
(7,11,'si',15,'2026-04-09','2026-04-30','CUR-Z44VCB','asdsasdasad',NULL,200.00,'https://grupo','activo','2026-04-09 03:00:25','2026-04-09 03:00:25');
/*!40000 ALTER TABLE `cursos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiantes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `curso_id` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `cedula` varchar(255) NOT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('masculino','femenino','otro') DEFAULT NULL,
  `fecha_inscripcion` date NOT NULL,
  `estado` enum('activo','inactivo','graduado') NOT NULL DEFAULT 'activo',
  `estado_pago` enum('pendiente','aprobado','reprobado') NOT NULL DEFAULT 'pendiente',
  `estado_aprobacion_curso` enum('pendiente','aprobado','reprobado') NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `estudiantes_cedula_unique` (`cedula`),
  KEY `estudiantes_user_id_foreign` (`user_id`),
  KEY `estudiantes_curso_id_foreign` (`curso_id`),
  CONSTRAINT `estudiantes_curso_id_foreign` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `estudiantes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiantes`
--

LOCK TABLES `estudiantes` WRITE;
/*!40000 ALTER TABLE `estudiantes` DISABLE KEYS */;
INSERT INTO `estudiantes` VALUES
(2,10,NULL,'Juan Perez','33138569','04125249315','2026-03-18','masculino','2026-03-18','activo','pendiente','pendiente','2026-03-18 21:10:18','2026-03-18 21:10:18'),
(3,12,NULL,'Yosneidy Alvarez','33138559','04125249315','2002-12-04','femenino','2026-03-18','activo','pendiente','pendiente','2026-03-19 01:10:28','2026-03-19 01:10:28'),
(4,13,NULL,'coryo','31248908','04121111112','2013-06-27','masculino','2026-04-05','activo','pendiente','pendiente','2026-04-06 00:07:44','2026-04-06 00:07:44'),
(5,14,NULL,'coryo','11111111','04121111111','2000-07-03','masculino','2026-04-08','activo','pendiente','pendiente','2026-04-09 03:28:03','2026-04-09 03:28:03'),
(6,16,NULL,'emma','31432223','0412231212','1997-07-21','masculino','2026-04-20','activo','pendiente','pendiente','2026-04-21 02:59:14','2026-04-21 02:59:14');
/*!40000 ALTER TABLE `estudiantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
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
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`)
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
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_03_11_191412_create_personal_access_tokens_table',1),
(5,'2026_03_18_160528_create_estudiantes_table',2),
(6,'2026_03_18_160735_create_cursos_table',2),
(7,'2026_03_18_160921_add_curso_id_foreign_to_estudiantes_table',2),
(8,'2026_03_18_161227_create_profesores_table',3),
(9,'2026_04_04_205706_create_notifications_table',4),
(10,'2026_04_05_143507_add_estado_pago_and_aprobacion_curso_to_estudiantes_table',4),
(11,'2026_04_05_155823_create_pagos_table',5),
(12,'2026_04_05_211607_add_nota_admin_to_pagos_table',6),
(13,'2026_04_08_000001_update_cursos_table',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
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
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES
('003b6ad4-cec7-4864-8c25-831c24befccc','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en algo.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 00:28:49','2026-04-06 00:28:26','2026-04-06 00:28:49'),
('21a9aefe-b348-4c35-a1d1-a57033db3cc0','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en samsung.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 02:09:53','2026-04-06 02:09:30','2026-04-06 02:09:53'),
('267c01bc-a6b5-401c-8af1-67e66d1c048c','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado!\",\"mensaje\":\"Tu pago para el curso samsung ha sido aprobado. Ya est\\u00e1s inscrito en el curso.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 02:10:46','2026-04-06 02:10:01','2026-04-06 02:10:46'),
('29e5beda-c230-4e09-ac2d-827e4b343bd5','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"Pago Rechazado\",\"mensaje\":\"Tu pago para el curso algo ha sido rechazado. Motivo: no quiero xdxd\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:30','2026-04-06 01:38:51','2026-04-06 01:50:30'),
('2ec605ba-ac4f-4f15-9fa8-e1a2f7fea74e','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado! \",\"mensaje\":\"Tu pago para el curso algebra lineal ha sido aprobado. Ya puedes acceder al contenido.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:30','2026-04-06 01:43:12','2026-04-06 01:50:30'),
('30ee2c7a-89ec-4954-b91c-597ed41d96da','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado!\",\"mensaje\":\"Tu pago para el curso algebra lineal ha sido aprobado. Ya est\\u00e1s inscrito en el curso.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:30','2026-04-06 01:43:13','2026-04-06 01:50:30'),
('4ba7a733-c690-4225-9793-4c0bd6533ed0','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en estre.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 00:44:26','2026-04-06 00:44:09','2026-04-06 00:44:26'),
('4bc8c7d4-a92e-4fec-831b-f832f35bbc31','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en algebra lineal.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 00:38:18','2026-04-06 00:37:49','2026-04-06 00:38:18'),
('51089c23-301b-4e34-b03d-2f9faae4ab74','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado! \",\"mensaje\":\"Tu pago para el curso como ser como ilon musk ha sido aprobado. Ya puedes acceder al contenido.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 02:03:00','2026-04-06 01:52:45','2026-04-06 02:03:00'),
('780a5850-0800-4fbe-9157-673cb0ef805f','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"Pago Rechazado\",\"mensaje\":\"Tu pago para el curso algo ha sido rechazado. Motivo: no quiero xdxd\",\"url\":\"\\/estudiante\\/notificaciones\"}','2026-04-06 01:50:30','2026-04-06 01:38:51','2026-04-06 01:50:30'),
('a777c1cc-0412-49f5-9df1-2f98d86ad415','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en como ser como ilon musk.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 01:52:33','2026-04-06 01:50:57','2026-04-06 01:52:33'),
('abfef51f-f47d-4efc-ba06-a3062be8b7cd','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"Pago Rechazado\",\"mensaje\":\"Tu pago para el curso estre ha sido rechazado. Motivo: asas\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:19','2026-04-06 01:45:06','2026-04-06 01:50:19'),
('aced5f2c-907c-4c91-bee4-7d134a0d5b33','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado!\",\"mensaje\":\"Tu pago para el curso como ser como ilon musk ha sido aprobado. Ya est\\u00e1s inscrito en el curso.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 02:03:05','2026-04-06 01:52:45','2026-04-06 02:03:05'),
('adfb52ac-d8b1-4f31-8ad8-7baf1a7542f9','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado! \",\"mensaje\":\"Tu pago para el curso barberia ha sido aprobado. Ya puedes acceder al contenido.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:30','2026-04-06 01:42:49','2026-04-06 01:50:30'),
('cfc704a0-0b97-4411-9114-e475e730128e','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"Pago Rechazado\",\"mensaje\":\"Tu pago para el curso estre ha sido rechazado. Motivo: asas\",\"url\":\"\\/estudiante\\/notificaciones\"}','2026-04-06 01:50:30','2026-04-06 01:45:06','2026-04-06 01:50:30'),
('e3a7fb52-b183-4dc7-a2b8-cdd642c7b131','App\\Notifications\\GenericNotification','App\\Models\\User',13,'{\"titulo\":\"\\u00a1Pago Aprobado!\",\"mensaje\":\"Tu pago para el curso barberia ha sido aprobado. Ya est\\u00e1s inscrito en el curso.\",\"url\":\"\\/estudiante\\/curso\"}','2026-04-06 01:50:30','2026-04-06 01:42:49','2026-04-06 01:50:30'),
('ee1665d3-6f7a-4c9b-ba0d-9859f4699388','App\\Notifications\\GenericNotification','App\\Models\\User',7,'{\"titulo\":\"Nueva Solicitud de Pago\",\"mensaje\":\"El estudiante coryo ha solicitado inscribirse en barberia.\",\"url\":\"\\/admin\\/pagos\"}','2026-04-06 00:18:50','2026-04-06 00:14:46','2026-04-06 00:18:50');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `curso_id` bigint(20) unsigned NOT NULL,
  `referencia` varchar(255) NOT NULL,
  `banco_origen` varchar(255) NOT NULL,
  `comprobante` varchar(255) NOT NULL,
  `estado` varchar(255) NOT NULL DEFAULT 'pendiente',
  `nota_admin` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pagos_user_id_foreign` (`user_id`),
  KEY `pagos_curso_id_foreign` (`curso_id`),
  CONSTRAINT `pagos_curso_id_foreign` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES
(1,13,1,'1234567890','mercantil','1775420086_screen.png','aprobado',NULL,'2026-04-06 00:14:46','2026-04-06 01:42:49'),
(2,13,2,'1234567222','venezuela','1775420906_logo-minecraft-removebg-preview.png','rechazado','no quiero xdxd','2026-04-06 00:28:26','2026-04-06 01:38:51'),
(3,13,3,'12121212','mercantil','1775421469_logo-minecraft-removebg-preview.png','aprobado',NULL,'2026-04-06 00:37:49','2026-04-06 01:43:12'),
(4,13,4,'1212121212','mercantil','1775421849_logo-minecraft-removebg-preview.png','rechazado','asas','2026-04-06 00:44:09','2026-04-06 01:45:06'),
(5,13,5,'1212324354','venezuela','1775425857_logo-minecraft-removebg-preview.png','aprobado',NULL,'2026-04-06 01:50:57','2026-04-06 01:52:45'),
(6,13,6,'146778888','venezuela','1775426970_logo-minecraft-removebg-preview.png','aprobado',NULL,'2026-04-06 02:09:30','2026-04-06 02:10:01');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
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
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES
(1,'App\\Models\\User',1,'auth_token','ee634ec294eab22a2d9fe48ab2a2a73ba98040c2560b311ca4075d26d0a881c8','[\"*\"]',NULL,NULL,'2026-03-11 23:29:55','2026-03-11 23:29:55'),
(2,'App\\Models\\User',1,'auth_token','33dc1bab76eb512d20664c867e169fc48a0bd4a691a9568e7acb40c46be2c238','[\"*\"]','2026-03-11 23:44:17',NULL,'2026-03-11 23:40:59','2026-03-11 23:44:17'),
(3,'App\\Models\\User',1,'auth_token','5723bfd5c70553207db3b863089279c463ea627cc8768c2f41c57eb295877ffe','[\"*\"]',NULL,NULL,'2026-03-12 00:25:12','2026-03-12 00:25:12'),
(4,'App\\Models\\User',1,'auth_token','28ff016627717791a82cdd56ea1f912a84239d28381eac792b49e20913ce8fd9','[\"*\"]',NULL,NULL,'2026-03-12 00:52:14','2026-03-12 00:52:14'),
(5,'App\\Models\\User',2,'auth_token','746d77408e200bc40471fc3c63dd4c48cc09db26e334d094a1fe6c492bfb8c58','[\"*\"]',NULL,NULL,'2026-03-12 01:01:42','2026-03-12 01:01:42'),
(6,'App\\Models\\User',4,'auth_token','a897539d7b43191b4c2dc5f026e05f197299a977d3c407b0f0a2a216de7b9ff9','[\"*\"]',NULL,NULL,'2026-03-12 01:12:44','2026-03-12 01:12:44'),
(7,'App\\Models\\User',5,'auth_token','c71f3047ec1b2d37663d4cbb5b4270a026c8ea6b4244aa73c79a4ce394d3179d','[\"*\"]',NULL,NULL,'2026-03-18 19:55:26','2026-03-18 19:55:26'),
(8,'App\\Models\\User',5,'auth_token','38cb52ec462a65ba89a555be734ecefdccafe99ab3afab6d13bd0552906a42ce','[\"*\"]',NULL,NULL,'2026-03-18 19:57:47','2026-03-18 19:57:47'),
(9,'App\\Models\\User',7,'auth_token','1c3ef9a78401a77b5202625d3ef479ed2400460081cc849056c819d272ef9aa8','[\"*\"]',NULL,NULL,'2026-03-18 20:26:17','2026-03-18 20:26:17'),
(10,'App\\Models\\User',7,'auth_token','94eb2fbb5582a3de6e6c7a63a3fbe09f0835068d0c27da6c6fbfc7cbd59651ae','[\"*\"]','2026-03-18 20:30:25',NULL,'2026-03-18 20:27:05','2026-03-18 20:30:25'),
(11,'App\\Models\\User',8,'auth_token','705400a6b4fc9ef4b9fcae928e2c548d830e2723a347d0c8b1eeb24a5d1fbec4','[\"*\"]',NULL,NULL,'2026-03-18 20:32:04','2026-03-18 20:32:04'),
(12,'App\\Models\\User',9,'auth_token','6cf790e23d86b5f4acbbae799b6a3f16ed87f1baa433091ee147d69b9ac9d900','[\"*\"]',NULL,NULL,'2026-03-18 20:50:23','2026-03-18 20:50:23'),
(13,'App\\Models\\User',10,'auth_token','e006772fbaeceae0cebf0994780076891257c65bcf33631772e6208688129a8b','[\"*\"]',NULL,NULL,'2026-03-18 21:10:18','2026-03-18 21:10:18'),
(14,'App\\Models\\User',7,'auth_token','66896988cb96ec636e988283b9d4e9c22f0db32dbc3873fdb6ff3b4656c2c9eb','[\"*\"]',NULL,NULL,'2026-03-18 22:23:46','2026-03-18 22:23:46'),
(15,'App\\Models\\User',10,'auth_token','ef4bfe4559b448fc751f5c5106b60c715212816ad69009c55ef2cf7924bc2310','[\"*\"]',NULL,NULL,'2026-03-18 22:26:38','2026-03-18 22:26:38'),
(16,'App\\Models\\User',7,'auth_token','7c9f34d555ba7a82e04b56e66ebe5cd495fb3db28f5a6f0362f2fa4e3708b32d','[\"*\"]','2026-03-19 00:26:54',NULL,'2026-03-19 00:18:51','2026-03-19 00:26:54'),
(17,'App\\Models\\User',7,'auth_token','c80bce74afa68e178ded7cd291afdb29e5e66848e8a3b6b411ddc2dfba9c54b5','[\"*\"]','2026-03-19 00:38:57',NULL,'2026-03-19 00:38:40','2026-03-19 00:38:57'),
(18,'App\\Models\\User',7,'auth_token','af6cda150a2347eed6d0db82f3e5015a134979b04266d5283d87e12114b5447c','[\"*\"]','2026-03-19 01:10:28',NULL,'2026-03-19 00:48:31','2026-03-19 01:10:28'),
(37,'App\\Models\\User',13,'auth_token','69b6f160be7a6bc205b0d68c2a76059ef2027a8f644f1ee0a7eaee56f0c89b96','[\"*\"]','2026-04-06 02:47:46',NULL,'2026-04-06 02:10:32','2026-04-06 02:47:46'),
(49,'App\\Models\\User',13,'auth_token','e308b936d86b06cf2781fd1dc2a12a050f38388a0e307accac9314754428bd3b','[\"*\"]','2026-04-09 05:38:55',NULL,'2026-04-09 05:36:55','2026-04-09 05:38:55'),
(56,'App\\Models\\User',7,'auth_token','67b78403611340fd24b23948903d7225dd8f95309df465a4db3cb3c7012b7732','[\"*\"]','2026-04-10 06:02:12',NULL,'2026-04-10 05:29:39','2026-04-10 06:02:12'),
(60,'App\\Models\\User',7,'auth_token','b795a942873bef2d3d1ca1df4a5bff3d9c779b194706511b2d9cd6639d2d7148','[\"*\"]','2026-04-11 20:17:52',NULL,'2026-04-11 19:48:16','2026-04-11 20:17:52'),
(62,'App\\Models\\User',16,'auth_token','e42af604e6f4d0bf2188e646d3ce2eb0a426b21ef3bcffc4fc6bdde2a303b414','[\"*\"]',NULL,NULL,'2026-04-21 02:59:14','2026-04-21 02:59:14');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profesores`
--

DROP TABLE IF EXISTS `profesores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `profesores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `cedula` varchar(255) NOT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `especialidad` varchar(255) DEFAULT NULL,
  `titulo` enum('licenciatura','maestria','doctorado') DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('masculino','femenino','otro') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profesores_cedula_unique` (`cedula`),
  KEY `profesores_user_id_foreign` (`user_id`),
  CONSTRAINT `profesores_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profesores`
--

LOCK TABLES `profesores` WRITE;
/*!40000 ALTER TABLE `profesores` DISABLE KEYS */;
INSERT INTO `profesores` VALUES
(1,11,'30795259','04125249315','Repostería','licenciatura','Educación','2028-03-29','masculino',NULL,NULL),
(2,15,'11111111','04121111111','the victor','doctorado','war deparment','2000-07-03','masculino','2026-04-09 03:32:02','2026-04-09 03:32:02');
/*!40000 ALTER TABLE `profesores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
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
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES
('DNKhrSBPRyuID4b0vZXhMeLCaDnLHhKS2eXOHGPg',NULL,'127.0.0.1','Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiUU5FT3RpMnlNWnp5Sm9mcHAweGxvTGlyUmVvaWVTT3h3ODlWb3dtSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1775401282),
('OeaplX2h4PV66noCuBuFGtCjN8gahLPeMDGCcu2a',NULL,'127.0.0.1','insomnia/12.2.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoib255UUt1S2MyS2Q1Yk9aaGN2Z2gzbjlMN2NXVTBRWlB4VVROWHhhaCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1773852754),
('zWLBkGsCa21Io1KkcmTyGGM2iIw1um30eKYfjSVz',NULL,'127.0.0.1','Apidog/1.0.0 (https://apidog.com)','YTozOntzOjY6Il90b2tlbiI7czo0MDoia0hDSGFFc1owWHpXQnBPZkRtMGJLdzZKV2pWTlJqZ2NIZmZZdmptTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1773858515);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','profesor','estudiante') NOT NULL DEFAULT 'estudiante',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(7,'ADMIN','admin@gmail.com',NULL,'$2y$12$U0UrY8OMllI07OERWMN45OXb4gsodYbAoGWA1/yWsvl695iRKyAF6','admin',NULL,'2026-03-18 20:26:17','2026-03-18 20:26:17'),
(10,'Juan Perez','juan@gmail.com',NULL,'$2y$12$Hi6dTgC2xmsIxARUqJH2det5Om69mgyD6kLgJLpQn5bNXpAXonvSi','estudiante',NULL,'2026-03-18 21:10:18','2026-03-18 21:10:18'),
(11,'Wilberk','wil@gmail.com',NULL,'webzma123$','profesor',NULL,NULL,NULL),
(12,'Yosneidy Alvarez','yoss@gmail.com',NULL,'$2y$12$s5CqOIo2AThv6DmRCW4NduceGfP5fIgNTRiKDobe.7GpwZo6OuvOG','estudiante',NULL,'2026-03-19 01:10:28','2026-03-19 01:10:28'),
(13,'coryo','emmanuelcastroh2002@gmail.com',NULL,'$2y$12$NeU.2aNQVJVQEascAyZJjuxfMbYGDdne65c4gBb.N9CAvKtzCOCFS','estudiante',NULL,'2026-04-06 00:07:44','2026-04-06 00:07:44'),
(14,'coryo','emmanuelltowers@gmail.com',NULL,'$2y$12$bztdbAG6NnLHZVi60IGiiO30ONqzH1AR8p9c/YXz.qefRAaHfrBC2','estudiante',NULL,'2026-04-09 03:28:03','2026-04-09 03:28:03'),
(15,'coryo','coryo@gmail.com',NULL,'$2y$12$UIXrtRKJ2Jn7y9zwQDCbO.DFeR.GYn0WVWuGVbE/4LYNRLuuzuhL6','profesor',NULL,'2026-04-09 03:32:02','2026-04-09 03:32:02'),
(16,'emma','emma@gmail.com',NULL,'$2y$12$Fy4yxlblVSmS10Vs7JJX0eKvwWjXE2/01MTk./llyJxuGpg5Uvo8G','estudiante',NULL,'2026-04-21 02:59:14','2026-04-21 02:59:14');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-20 19:20:04
