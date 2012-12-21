CREATE DATABASE  IF NOT EXISTS `ha_gui_dev` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `ha_gui_dev`;
-- MySQL dump 10.13  Distrib 5.5.16, for Win32 (x86)
--
-- Host: 10.0.1.13    Database: ha_gui_dev
-- ------------------------------------------------------
-- Server version	5.5.16-log

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
-- Table structure for table `infobox_data`
--

DROP TABLE IF EXISTS `infobox_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `infobox_data` (
  `iddata` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `context` varchar(45) NOT NULL COMMENT 'e.g. weather, calendar etc. Identifies the javascript class that will load the data',
  `state` int(11) NOT NULL DEFAULT '1' COMMENT '1 if active, 0 if not active',
  `data` longtext NOT NULL COMMENT 'json object to be loaded into the infobox',
  PRIMARY KEY (`iddata`)
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kvp`
--

DROP TABLE IF EXISTS `kvp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kvp` (
  `idkvp` int(11) NOT NULL AUTO_INCREMENT,
  `context` varchar(45) DEFAULT NULL,
  `type` varchar(45) NOT NULL DEFAULT 'data' COMMENT 'ui=ui information, data=information to be used to generate service output',
  `key` varchar(45) NOT NULL,
  `value` text,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idkvp`),
  UNIQUE KEY `contxt` (`context`,`key`)
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8 COMMENT='Key value pair table with context. Information is primarily generated through the setup configuration. Is also used for additional kvps defined by the service to keep persistent data';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'ha_gui_dev'
--

--
-- Dumping routines for database 'ha_gui_dev'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2012-12-21  8:16:47
