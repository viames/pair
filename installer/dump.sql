-- MySQL dump 10.13  Distrib 5.7.15, for osx10.11 (x86_64)
--
-- Host: localhost    Database: pair
-- ------------------------------------------------------
-- Server version	5.7.15

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acl`
--

DROP TABLE IF EXISTS `acl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `acl` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` int(3) unsigned NOT NULL,
  `group_id` int(3) unsigned DEFAULT NULL,
  `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_id` (`rule_id`,`group_id`),
  KEY `group_id` (`group_id`) USING BTREE,
  CONSTRAINT `acl_group_id` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `acl_rules_id` FOREIGN KEY (`rule_id`) REFERENCES `rules` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acl`
--

LOCK TABLES `acl` WRITE;
/*!40000 ALTER TABLE `acl` DISABLE KEYS */;
INSERT INTO `acl` VALUES (2,2,1,0),(3,3,1,0),(4,4,1,0),(5,5,1,0),(6,6,1,0),(7,7,1,0),(8,8,1,0),(9,9,1,0),(10,10,1,1),(11,1,1,0);
/*!40000 ALTER TABLE `acl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `error_logs`
--

DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `error_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_time` datetime NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `module` varchar(20) NOT NULL,
  `action` varchar(20) NOT NULL,
  `get_data` text NOT NULL,
  `post_data` text NOT NULL,
  `cookie_data` text NOT NULL,
  `description` varchar(255) NOT NULL,
  `user_messages` text NOT NULL,
  `referer` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_time` (`created_time`),
  CONSTRAINT `error_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `error_logs`
--

LOCK TABLES `error_logs` WRITE;
/*!40000 ALTER TABLE `error_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `error_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `is_default` int(1) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,'Default',1);
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(2) NOT NULL,
  `representation` varchar(5) NOT NULL,
  `language_name` varchar(30) NOT NULL,
  `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `full_code` (`representation`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'en','en_UK','English (UK)',1),(2,'it','it_IT','Italian',0);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `version` varchar(10) NOT NULL,
  `date_released` datetime NOT NULL,
  `app_version` varchar(10) NOT NULL DEFAULT '1',
  `installed_by` int(4) unsigned NOT NULL,
  `date_installed` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `installed_by` (`installed_by`),
  KEY `date_installed` (`date_installed`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,'api','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(2,'developer','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(3,'languages','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(4,'modules','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(5,'options','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(6,'rules','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(7,'selftest','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(8,'templates','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(9,'user','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00'),(10,'users','1.0','2017-01-01 00:00:00','1.0',1,'2017-01-01 00:00:00');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `options` (
  `name` varchar(30) NOT NULL,
  `label` varchar(30) NOT NULL DEFAULT '',
  `type` enum('text','bool','int','list','custom') NOT NULL DEFAULT 'text',
  `value` varchar(60) NOT NULL,
  `list_options` text,
  `group` varchar(12) NOT NULL,
  PRIMARY KEY (`name`),
  KEY `group` (`group`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `options`
--

LOCK TABLES `options` WRITE;
/*!40000 ALTER TABLE `options` DISABLE KEYS */;
INSERT INTO `options` VALUES ('development','DEVELOPMENT','bool','1',NULL,'debug'),('pagination_pages','ITEMS_PER_PAGE','int','12',NULL,'site'),('session_time','SESSION_TIME','int','120',NULL,'site'),('show_log','SHOW_LOG','bool','1',NULL,'debug'),('webservice_timeout','WEBSERVICE_TIMEOUT','int','8',NULL,'services'),('admin_emails','ADMIN_EMAILS','text','em@il.address',NULL,'site');
/*!40000 ALTER TABLE `options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rules`
--

DROP TABLE IF EXISTS `rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rules` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(30) DEFAULT NULL,
  `admin_only` tinyint(1) NOT NULL DEFAULT '0',
  `module_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_action` (`module_id`,`action`) USING BTREE,
  CONSTRAINT `rules_module_id` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rules`
--

LOCK TABLES `rules` WRITE;
/*!40000 ALTER TABLE `rules` DISABLE KEYS */;
INSERT INTO `rules` VALUES (1,NULL,0,1),(2,NULL,1,2),(3,NULL,0,3),(4,NULL,0,4),(5,NULL,1,5),(6,NULL,1,6),(7,NULL,1,7),(8,NULL,0,8),(9,NULL,0,9),(10,NULL,0,10);
/*!40000 ALTER TABLE `rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id_session` varchar(100) NOT NULL,
  `id_user` int(4) unsigned DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `timezone_offset` decimal(2,1) DEFAULT NULL,
  `timezone_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_session`),
  KEY `user_id` (`id_user`,`start_time`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `templates` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `version` varchar(10) NOT NULL,
  `date_released` datetime NOT NULL,
  `app_version` varchar(10) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `installed_by` int(4) unsigned NOT NULL,
  `date_installed` datetime NOT NULL,
  `derived` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `palette` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `installed_by` (`installed_by`),
  KEY `date_installed` (`date_installed`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `templates`
--

LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
INSERT INTO `templates` VALUES (1,'Default','1.0','2017-01-07 12:00:00','1.0',1,1,'2017-01-07 12:00:00',0,'#1AB394,#1C84C6,#9C9C9C,#636363');
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(4) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(3) unsigned NOT NULL,
  `language_id` int(3) unsigned NOT NULL,
  `ldap_user` varchar(50) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `hash` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `enabled` int(1) unsigned NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `faults` int(2) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `group_id` (`group_id`),
  KEY `admin` (`admin`),
  KEY `language_id` (`language_id`),
  KEY `ldap_user` (`ldap_user`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,1,'','admin','$2a$12$WfnHFTmVnZ.f8DO.rAi7OeU1Eco2/5gJ8w/2E5qHoVN3yN.luri/.','Administrator','User','admin@pair',1,1,'2017-03-01 19:47:50',0);
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

-- Dump completed on 2017-03-01 20:48:19
