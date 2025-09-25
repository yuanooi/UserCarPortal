-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: localhost    Database: car_portal
-- ------------------------------------------------------
-- Server version	8.0.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acquisition_images`
--

DROP TABLE IF EXISTS `acquisition_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acquisition_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `acquisition_id` int NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_type` enum('exterior','interior','engine','documents') DEFAULT 'exterior',
  `is_main` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `acquisition_id` (`acquisition_id`),
  CONSTRAINT `acquisition_images_ibfk_1` FOREIGN KEY (`acquisition_id`) REFERENCES `vehicle_acquisitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acquisition_images`
--

LOCK TABLES `acquisition_images` WRITE;
/*!40000 ALTER TABLE `acquisition_images` DISABLE KEYS */;
INSERT INTO `acquisition_images` VALUES (1,2,'uploads/acquisitions/2_1758673754_0.jpg','exterior',1,'2025-09-24 00:29:14'),(2,2,'uploads/acquisitions/2_1758673754_1.jpg','exterior',0,'2025-09-24 00:29:14'),(3,2,'uploads/acquisitions/2_1758673754_2.jpg','exterior',0,'2025-09-24 00:29:14'),(4,2,'uploads/acquisitions/2_1758673754_3.jpg','exterior',0,'2025-09-24 00:29:14'),(5,2,'uploads/acquisitions/2_1758673754_4.jpg','exterior',0,'2025-09-24 00:29:14');
/*!40000 ALTER TABLE `acquisition_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `acquisition_notifications`
--

DROP TABLE IF EXISTS `acquisition_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acquisition_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `acquisition_id` int NOT NULL,
  `user_id` int NOT NULL,
  `notification_type` enum('offer_made','offer_accepted','offer_rejected','payment_completed','status_update') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `acquisition_id` (`acquisition_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `acquisition_notifications_ibfk_1` FOREIGN KEY (`acquisition_id`) REFERENCES `vehicle_acquisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `acquisition_notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acquisition_notifications`
--

LOCK TABLES `acquisition_notifications` WRITE;
/*!40000 ALTER TABLE `acquisition_notifications` DISABLE KEYS */;
INSERT INTO `acquisition_notifications` VALUES (1,1,8,'status_update','Acquisition Application Submitted','Your vehicle acquisition application has been successfully submitted and is waiting for admin review.',0,'2025-09-23 08:22:48'),(2,1,8,'offer_made','Acquisition Application Approved','Congratulations! Your vehicle acquisition application has been approved with a purchase price of RM 1,000.00. Please review the details and decide whether to accept.',0,'2025-09-23 08:23:20'),(3,1,8,'offer_accepted','Offer Accepted','You have accepted our acquisition offer, we will arrange payment matters.',0,'2025-09-23 08:24:22'),(4,2,8,'status_update','Acquisition Application Submitted','Your vehicle acquisition application has been successfully submitted and is waiting for admin review.',0,'2025-09-24 00:29:14'),(5,1,8,'payment_completed','Payment Completed','Your vehicle acquisition payment has been completed successfully!',0,'2025-09-24 00:35:04');
/*!40000 ALTER TABLE `acquisition_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_notifications`
--

DROP TABLE IF EXISTS `admin_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `notification_type` enum('offer_response','message','acquisition','human_reply_needed','urgent_message') DEFAULT 'message',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_car_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL COMMENT '用户ID',
  `status` enum('unread','read') DEFAULT 'unread' COMMENT '通知状态',
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `notification_type` (`notification_type`),
  KEY `is_read` (`is_read`),
  KEY `idx_admin_notifications_admin_id` (`admin_id`),
  KEY `idx_admin_notifications_user_id` (`user_id`),
  KEY `idx_admin_notifications_status` (`status`),
  KEY `idx_admin_notifications_related_car_id` (`related_car_id`),
  KEY `idx_admin_notifications_created_at` (`created_at`),
  CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_notifications`
--

LOCK TABLES `admin_notifications` WRITE;
/*!40000 ALTER TABLE `admin_notifications` DISABLE KEYS */;
INSERT INTO `admin_notifications` VALUES (2,6,'acquisition','New Vehicle Acquisition Request','Test Brand Test Model (2023)',5,1,'2025-09-24 00:33:28',5,'read');
/*!40000 ALTER TABLE `admin_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_purchase_offers`
--

DROP TABLE IF EXISTS `admin_purchase_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_purchase_offers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `user_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `admin_notes` text,
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`),
  KEY `status` (`status`),
  CONSTRAINT `admin_purchase_offers_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_purchase_offers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_purchase_offers_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_purchase_offers`
--

LOCK TABLES `admin_purchase_offers` WRITE;
/*!40000 ALTER TABLE `admin_purchase_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_purchase_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `car_images`
--

DROP TABLE IF EXISTS `car_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `car_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `image` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `car_images_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_images`
--

LOCK TABLES `car_images` WRITE;
/*!40000 ALTER TABLE `car_images` DISABLE KEYS */;
INSERT INTO `car_images` VALUES (19,12,'12_1757557151_0.jpeg',1,'2025-09-11 02:19:11'),(20,12,'12_1757557151_1.jpeg',0,'2025-09-11 02:19:11'),(21,12,'12_1757557151_2.jpeg',0,'2025-09-11 02:19:11'),(22,12,'12_1757557151_3.jpeg',0,'2025-09-11 02:19:11'),(23,12,'12_1757557151_4.jpeg',0,'2025-09-11 02:19:11'),(24,13,'13_1757573453_0.jpg',1,'2025-09-11 06:50:53'),(25,13,'13_1757573453_1.jpg',0,'2025-09-11 06:50:53'),(26,13,'13_1757573453_2.jpg',0,'2025-09-11 06:50:53'),(27,13,'13_1757573453_3.jpg',0,'2025-09-11 06:50:53'),(28,13,'13_1757573453_4.jpg',0,'2025-09-11 06:50:53'),(29,14,'14_1758075871_0.jpg',1,'2025-09-17 02:24:31'),(30,14,'14_1758075871_1.jpg',0,'2025-09-17 02:24:31'),(31,14,'14_1758075871_2.jpg',0,'2025-09-17 02:24:31'),(32,14,'14_1758075871_3.jpg',0,'2025-09-17 02:24:31'),(33,14,'14_1758075871_4.jpg',0,'2025-09-17 02:24:31'),(34,15,'15_1758078127_0.jpg',1,'2025-09-17 03:02:07'),(35,15,'15_1758078127_1.jpg',0,'2025-09-17 03:02:07'),(36,15,'15_1758078127_2.jpg',0,'2025-09-17 03:02:07'),(37,15,'15_1758078127_3.jpg',0,'2025-09-17 03:02:07'),(38,15,'15_1758078127_4.jpg',0,'2025-09-17 03:02:07'),(39,16,'16_1758079699_0.jpg',1,'2025-09-17 03:28:19'),(41,16,'16_1758079699_2.jpg',0,'2025-09-17 03:28:19'),(42,16,'16_1758079699_3.jpg',0,'2025-09-17 03:28:19'),(43,16,'16_1758079699_4.jpg',0,'2025-09-17 03:28:19'),(44,18,'18_1758089648_0.jpg',1,'2025-09-17 06:14:08'),(45,19,'19_1758096317_0.jpg',1,'2025-09-17 08:05:17'),(46,18,'images/car_1758096379_0.jpg',0,'2025-09-17 08:06:19'),(47,17,'images/car_1758096414_0.jpg',1,'2025-09-17 08:06:54'),(48,21,'21_1758612538_0.jpg',1,'2025-09-23 07:28:58'),(49,21,'21_1758612538_1.jpg',0,'2025-09-23 07:28:58'),(50,21,'21_1758612538_2.jpg',0,'2025-09-23 07:28:58'),(51,21,'21_1758612538_3.jpg',0,'2025-09-23 07:28:58'),(52,21,'21_1758612538_4.jpg',0,'2025-09-23 07:28:58'),(53,22,'22_1758613055_0.jpg',1,'2025-09-23 07:37:35'),(54,22,'22_1758613055_1.jpg',0,'2025-09-23 07:37:35'),(55,22,'22_1758613055_2.jpg',0,'2025-09-23 07:37:35'),(56,22,'22_1758613055_3.jpg',0,'2025-09-23 07:37:35'),(57,22,'22_1758613055_4.jpg',0,'2025-09-23 07:37:35');
/*!40000 ALTER TABLE `car_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `car_reviews`
--

DROP TABLE IF EXISTS `car_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `car_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `comment` text NOT NULL,
  `reply` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rating` int DEFAULT NULL,
  `reviewer_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `fk_reviewer_id` (`reviewer_id`),
  CONSTRAINT `car_reviews_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviewer_id` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `car_reviews_chk_1` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_reviews`
--

LOCK TABLES `car_reviews` WRITE;
/*!40000 ALTER TABLE `car_reviews` DISABLE KEYS */;
INSERT INTO `car_reviews` VALUES (2,12,'rrhawh',NULL,'2025-09-12 06:26:38',5,5);
/*!40000 ALTER TABLE `car_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cars`
--

DROP TABLE IF EXISTS `cars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text,
  `status` enum('available','reserved','sold','pending','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `body_type` varchar(50) DEFAULT NULL,
  `instalment` decimal(10,2) DEFAULT NULL,
  `transmission` varchar(20) DEFAULT NULL,
  `mileage` int DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `insurance` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cars_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cars`
--

LOCK TABLES `cars` WRITE;
/*!40000 ALTER TABLE `cars` DISABLE KEYS */;
INSERT INTO `cars` VALUES (12,6,'proton','saga',2023,5200.00,'','available','2025-09-11 02:19:11',NULL,NULL,NULL,NULL,NULL,NULL),(13,6,'proton','efee',2000,1230.00,'aeffe','available','2025-09-11 06:50:53',NULL,NULL,NULL,NULL,NULL,NULL),(14,7,'proton','saga',2000,1234.00,'aegg','available','2025-09-17 02:24:31','SUV',NULL,'Automatic',200,'0',200.00),(15,6,'proton','saga',2000,1234.00,'thhjtdh','sold','2025-09-17 03:02:07','Sedan',NULL,'Automatic',200,'0',123.00),(16,7,'proton','saga',2000,1234.00,'0','available','2025-09-17 03:28:19','SUV',NULL,'0',123,NULL,1234.00),(17,6,'Honda','Civic Type R（FL5）',2024,399000.00,'0','rejected','2025-09-17 06:13:32','Hatchback',NULL,'0',6000,NULL,2500.00),(18,6,'Honda','Civic Type R（FL5）',2024,399000.00,'0','available','2025-09-17 06:14:08','Hatchback',NULL,'0',6000,NULL,2500.00),(19,6,'Toyota','Supra Mk5',2021,300000.00,'','available','2025-09-17 08:05:17','Hatchback',NULL,'Automatic',5000,'0',3000.00),(20,8,'Honda','Civic Type R（FL5）',2000,200.00,'','available','2025-09-23 03:13:27','Hatchback',NULL,'Automatic',200,'0',20000.00),(21,8,'toyota','Civic Type R（FL5）',2015,2000.00,'bhbv','available','2025-09-23 07:28:58','SUV',NULL,'Manual',200,'grey',NULL),(22,8,'Honda','Supra Mk5',2007,20000.00,'wrwsg4','available','2025-09-23 07:37:35','Sedan',NULL,'Automatic',200,'white',NULL);
/*!40000 ALTER TABLE `cars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_message`
--

DROP TABLE IF EXISTS `contact_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_message` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `admin_reply` text,
  `created_at` datetime NOT NULL,
  `replied_at` datetime DEFAULT NULL,
  `user_reply` text,
  `user_replied_at` datetime DEFAULT NULL,
  `reply_type` enum('human','ai') DEFAULT 'human' COMMENT '回复类型：human=人工回复，ai=AI回复',
  `needs_human_reply` tinyint(1) DEFAULT '0' COMMENT '是否需要人工回复：0=不需要，1=需要',
  `ai_processed_at` timestamp NULL DEFAULT NULL COMMENT 'AI处理时间',
  `ai_confidence_score` decimal(3,2) DEFAULT NULL COMMENT 'AI回复置信度分数',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_contact_message_needs_human_reply` (`needs_human_reply`),
  KEY `idx_contact_message_reply_type` (`reply_type`),
  CONSTRAINT `contact_message_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_message`
--

LOCK TABLES `contact_message` WRITE;
/*!40000 ALTER TABLE `contact_message` DISABLE KEYS */;
INSERT INTO `contact_message` VALUES (1,5,'ajjl','buyer@gmail.com','0123456789','halo','halo','2025-09-11 13:30:32','2025-09-11 13:52:11',NULL,NULL,'human',0,NULL,NULL),(2,5,'user1','',NULL,'Is this vehicle available for test drive?',NULL,'2025-09-23 14:01:56',NULL,NULL,NULL,'human',0,NULL,NULL),(4,5,'user1','user1@gmail.com',NULL,'What is the best price for this vehicle?','For the best price on this Toyota Camry 2020, I recommend contacting our sales team directly. They can provide you with current promotions and financing options.','2025-09-23 14:09:39','2025-09-23 14:09:39',NULL,NULL,'ai',0,NULL,NULL),(5,5,'user1','',NULL,'Is this vehicle available for test drive?','Yes! Test drives are available for this Honda Civic Type R（FL5） 2024. Please contact us to schedule an appointment at your convenience.','2025-09-23 14:12:01','2025-09-23 14:12:01',NULL,NULL,'ai',0,NULL,NULL),(6,5,'user1','',NULL,'halo','Hello! How can I help you today?','2025-09-23 14:12:54','2025-09-23 14:22:50',NULL,NULL,'ai',0,'2025-09-23 06:12:54',NULL),(7,5,'user1','',NULL,'halo','Hello! How can I help you today?','2025-09-23 14:13:08','2025-09-23 14:22:50',NULL,NULL,'ai',0,'2025-09-23 06:13:08',NULL),(8,5,'user1','user1@gmail.com',NULL,'What is the best price for this vehicle?','For the best price on this Toyota Camry 2020, I recommend contacting our sales team directly. They can provide you with current promotions and financing options.','2025-09-23 14:19:46','2025-09-23 14:19:46',NULL,NULL,'ai',0,NULL,NULL),(9,5,'user1','',NULL,'halo','Hello! How can I help you today?','2025-09-23 14:20:43','2025-09-23 14:22:50',NULL,NULL,'ai',0,'2025-09-23 06:20:43',NULL),(10,5,'user1','',NULL,'halo','Hello! How can I help you today?','2025-09-23 14:30:12','2025-09-23 14:30:12',NULL,NULL,'ai',0,NULL,NULL);
/*!40000 ALTER TABLE `contact_message` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `car_id` int NOT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`car_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `car_id` int NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,6,15,'Your car (proton saga) has been approved. Reason: ok','unread','2025-09-17 11:05:47'),(2,6,18,'Your car (Honda Civic Type R（FL5）) has been approved. Reason: halo','unread','2025-09-17 14:14:45'),(3,7,16,'Your car (proton saga) has been approved. Reason: halo','unread','2025-09-17 14:38:22'),(4,6,17,'Your car (Honda Civic Type R（FL5）) has been approved. Reason: 666','unread','2025-09-17 16:05:35'),(5,6,19,'Your car (Toyota Supra Mk5) has been approved. Reason: 555','unread','2025-09-17 16:05:38'),(6,6,17,'Your car (Honda Civic Type R（FL5） (2024)) has been unlisted by admin. Reason: asvz','unread','2025-09-22 13:41:17'),(7,8,20,'Your car (Honda Civic Type R（FL5）) has been approved. Reason: 2000','unread','2025-09-23 15:03:40'),(8,8,21,'Your car (toyota Civic Type R（FL5）) has been approved with price RM 2,000.00. Reason: ','unread','2025-09-23 15:31:59'),(9,8,22,'Your car (Honda Supra Mk5) has been approved with price RM 20,000.00. Reason: ','unread','2025-09-23 15:38:08');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_status` enum('ordered','completed','cancelled') DEFAULT 'ordered',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,15,5,'cancelled','2025-09-17 04:59:41','2025-09-17 05:33:12'),(2,15,5,'cancelled','2025-09-17 06:09:17','2025-09-17 06:09:33'),(3,15,5,'completed','2025-09-17 06:09:41','2025-09-17 06:25:14'),(4,18,5,'cancelled','2025-09-23 05:55:56','2025-09-23 05:56:22'),(5,22,5,'cancelled','2025-09-24 06:14:52','2025-09-24 06:17:22');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,'admin@gmail.com','8d9a63c97c717e0e3f8602369e54d1023e23f11902e1c4b592898c2ce4324683','2025-09-18 05:38:15','2025-09-18 02:38:15'),(2,'admin@gmail.com','6fc6ce75fd722790750982624e7e648c6575060cbcd2426fdac0f66d105d8a5b','2025-09-18 06:08:01','2025-09-18 03:08:01'),(3,'admin@gmail.com','2041802d67b7845bb81faeccdfa91bb8e813154f90a6ead6aaf1e68a9aa707bb','2025-09-18 06:15:23','2025-09-18 03:15:23'),(7,'darrenweihen@gmail.com','637d349f8b175d1478fce23cdd956590811cc8270cfa6e2804eb9f67c49bb570','2025-09-18 11:09:58','2025-09-18 08:09:58');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_tokens`
--

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller_notifications`
--

DROP TABLE IF EXISTS `seller_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seller_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_car_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `notification_type` (`notification_type`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `seller_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller_notifications`
--

LOCK TABLES `seller_notifications` WRITE;
/*!40000 ALTER TABLE `seller_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `seller_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_drives`
--

DROP TABLE IF EXISTS `test_drives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_drives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `test_date` date NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `test_drives_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  CONSTRAINT `test_drives_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_drives`
--

LOCK TABLES `test_drives` WRITE;
/*!40000 ALTER TABLE `test_drives` DISABLE KEYS */;
INSERT INTO `test_drives` VALUES (1,13,5,'Ooi Chee Yuan','buyer@gmail.com','2025-09-12','approved','2025-09-12 02:57:04');
/*!40000 ALTER TABLE `test_drives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_history`
--

DROP TABLE IF EXISTS `user_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `car_id` int NOT NULL,
  `viewed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `user_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_history_ibfk_2` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_history`
--

LOCK TABLES `user_history` WRITE;
/*!40000 ALTER TABLE `user_history` DISABLE KEYS */;
INSERT INTO `user_history` VALUES (4,6,12,'2025-09-17 11:15:40'),(5,7,14,'2025-09-17 11:28:56'),(7,6,15,'2025-09-17 14:24:56'),(8,6,18,'2025-09-23 14:34:39'),(10,6,16,'2025-09-17 15:37:32'),(12,7,18,'2025-09-18 16:29:31'),(13,5,19,'2025-09-22 09:47:50'),(14,8,19,'2025-09-22 10:10:32'),(15,6,19,'2025-09-22 13:38:53'),(16,5,18,'2025-09-23 14:30:06'),(17,5,13,'2025-09-22 16:19:09'),(18,8,13,'2025-09-23 11:27:01'),(19,8,18,'2025-09-25 09:40:14'),(20,8,16,'2025-09-23 10:49:48'),(21,8,20,'2025-09-23 15:34:39'),(22,8,21,'2025-09-25 09:34:46'),(23,8,22,'2025-09-25 09:39:42'),(24,6,21,'2025-09-25 09:49:56'),(25,5,22,'2025-09-25 09:52:57'),(26,5,21,'2025-09-25 08:27:01'),(27,6,22,'2025-09-25 09:33:21');
/*!40000 ALTER TABLE `user_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `user_type` enum('buyer','seller') NOT NULL DEFAULT 'buyer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (5,'user1','$2y$10$.KNBvL6hnEgl9YZqOfnlF.v/tgNxnG7g3qXiYBC1bYvtntwdV6MLS','0123456789','user1@gmail.com','2025-09-03 06:11:43','user','buyer'),(6,'admin','$2y$10$.KNBvL6hnEgl9YZqOfnlF.v/tgNxnG7g3qXiYBC1bYvtntwdV6MLS','+60164187503','admin@gmail.com','2025-09-03 06:12:15','admin','buyer'),(7,'user2','$2y$10$Xshd5L7FfdzIDpoBotEueu9i1Txbp5LXVsjGo.AjfGIguV/iTuYNS','0123456789','user2@gmail.com','2025-09-09 00:57:40','user','buyer'),(8,'seller2','$2y$10$qxrhgQE7R7wan.I6ju1DHOqMQKcns9Y8U1hPdc21gqZ0zVkSCwJny','1234567890','seller1@gmail.com','2025-09-22 01:49:49','user','seller');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_acquisitions`
--

DROP TABLE IF EXISTS `vehicle_acquisitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_acquisitions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int NOT NULL,
  `mileage` int DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `transmission` varchar(20) DEFAULT NULL,
  `body_type` varchar(50) DEFAULT NULL,
  `condition_description` text,
  `user_expected_price` decimal(10,2) DEFAULT NULL,
  `admin_offer_price` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','offered','accepted','rejected','completed','cancelled') DEFAULT 'pending',
  `admin_notes` text,
  `user_response` text,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `admin_reviewed_at` timestamp NULL DEFAULT NULL,
  `user_responded_at` timestamp NULL DEFAULT NULL,
  `payment_completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `vehicle_acquisitions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_acquisitions`
--

LOCK TABLES `vehicle_acquisitions` WRITE;
/*!40000 ALTER TABLE `vehicle_acquisitions` DISABLE KEYS */;
INSERT INTO `vehicle_acquisitions` VALUES (1,8,'toyota','Civic Type R（FL5）',2021,200,'red','Manual','SUV','',2000.00,1000.00,'completed','','','Bank Transfer','completed','','2025-09-23 08:22:48','2025-09-24 00:35:04','2025-09-23 08:23:20','2025-09-23 08:24:22','2025-09-24 00:35:04'),(2,8,'toyota','Civic Type R（FL5）',2020,200,'red','Manual','SUV','',2000.00,NULL,'pending',NULL,NULL,NULL,'pending',NULL,'2025-09-24 00:29:14','2025-09-24 00:29:14',NULL,NULL,NULL),(4,5,'Test Brand','Test Model',2023,50000,'Red','Automatic','Sedan','Good condition',50000.00,NULL,'pending',NULL,NULL,NULL,'pending',NULL,'2025-09-24 00:33:16','2025-09-24 00:33:16',NULL,NULL,NULL),(5,5,'Test Brand','Test Model',2023,50000,'Red','Automatic','Sedan','Good condition',50000.00,NULL,'pending',NULL,NULL,NULL,'pending',NULL,'2025-09-24 00:33:28','2025-09-24 00:33:28',NULL,NULL,NULL);
/*!40000 ALTER TABLE `vehicle_acquisitions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_issue_history`
--

DROP TABLE IF EXISTS `vehicle_issue_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_issue_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `issue_id` int NOT NULL,
  `action_type` enum('created','updated','resolved','rejected') NOT NULL,
  `action_description` text,
  `admin_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `vehicle_issue_history_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `vehicle_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_issue_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_issue_history`
--

LOCK TABLES `vehicle_issue_history` WRITE;
/*!40000 ALTER TABLE `vehicle_issue_history` DISABLE KEYS */;
INSERT INTO `vehicle_issue_history` VALUES (1,1,'created','Issue created by admin on 3D model',6,'2025-09-22 05:38:21');
/*!40000 ALTER TABLE `vehicle_issue_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_issues`
--

DROP TABLE IF EXISTS `vehicle_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_issues` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `issue_type` enum('engine','transmission','brakes','electrical','body','interior','suspension','exhaust','other') NOT NULL,
  `issue_description` text NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `model_position` varchar(255) DEFAULT NULL COMMENT 'Position on 3D model',
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `vehicle_issues_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_issues`
--

LOCK TABLES `vehicle_issues` WRITE;
/*!40000 ALTER TABLE `vehicle_issues` DISABLE KEYS */;
INSERT INTO `vehicle_issues` VALUES (1,18,'body','kk','medium','pending','','2025-09-22 05:38:21','2025-09-22 05:38:21','');
/*!40000 ALTER TABLE `vehicle_issues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_models`
--

DROP TABLE IF EXISTS `vehicle_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_models` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_name` varchar(100) NOT NULL,
  `model_description` text,
  `issue_categories` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_name` (`model_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_models`
--

LOCK TABLES `vehicle_models` WRITE;
/*!40000 ALTER TABLE `vehicle_models` DISABLE KEYS */;
INSERT INTO `vehicle_models` VALUES (1,'Proton Saga','Proton Saga 常见问题','[\"engine\", \"transmission\", \"electrical\", \"body\"]','2025-09-22 02:47:01'),(2,'Proton Persona','Proton Persona 常见问题','[\"engine\", \"transmission\", \"brakes\", \"electrical\"]','2025-09-22 02:47:01'),(3,'Honda Civic','Honda Civic 常见问题','[\"engine\", \"transmission\", \"suspension\", \"electrical\"]','2025-09-22 02:47:01'),(4,'Toyota Camry','Toyota Camry 常见问题','[\"engine\", \"transmission\", \"brakes\", \"interior\"]','2025-09-22 02:47:01'),(5,'Perodua Myvi','Perodua Myvi 常见问题','[\"engine\", \"transmission\", \"electrical\", \"body\"]','2025-09-22 02:47:01'),(6,'Nissan Almera','Nissan Almera 常见问题','[\"engine\", \"transmission\", \"brakes\", \"electrical\"]','2025-09-22 02:47:01');
/*!40000 ALTER TABLE `vehicle_models` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-25 10:09:08
