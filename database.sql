-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 17, 2026 at 08:33 AM
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
-- Database: `familytree_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', 'password', '2025-01-04 09:54:32');

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

DROP TABLE IF EXISTS `family_members`;
CREATE TABLE IF NOT EXISTS `family_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `age` int DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `place_of_birth` varchar(100) DEFAULT NULL,
  `living_place` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `zodiac` varchar(20) DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `spouse_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `spouse_id` (`spouse_id`)
) ENGINE=MyISAM AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `family_members`
--

-- --------------------------------------------------------

--
-- Table structure for table `member_parents`
--

DROP TABLE IF EXISTS `member_parents`;
CREATE TABLE IF NOT EXISTS `member_parents` (
  `member_id` int NOT NULL,
  `parent_id` int NOT NULL,
  `relationship_type` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`member_id`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_parents`
--


-- --------------------------------------------------------

--
-- Table structure for table `member_spouses`
--

DROP TABLE IF EXISTS `member_spouses`;
CREATE TABLE IF NOT EXISTS `member_spouses` (
  `member_id` int NOT NULL,
  `spouse_id` int NOT NULL,
  `marriage_date` date DEFAULT NULL,
  `marriage_status` enum('Current','Divorced','Deceased') DEFAULT 'Current',
  PRIMARY KEY (`member_id`,`spouse_id`),
  KEY `spouse_id` (`spouse_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_spouses`
--


-- --------------------------------------------------------
--
-- Table structure for table `countries`
--
DROP TABLE IF EXISTS `countries`;
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(5) DEFAULT NULL,
  `has_states` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `countries` (`id`, `name`, `code`, `has_states`) VALUES
(1, 'India', 'IN', 1),
(2, 'Other', NULL, 0);

-- --------------------------------------------------------
--
-- Table structure for table `states`
--
DROP TABLE IF EXISTS `states`;
CREATE TABLE IF NOT EXISTS `states` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `states` (`country_id`, `name`) VALUES
(1, 'Andhra Pradesh'),
(1, 'Arunachal Pradesh'),
(1, 'Assam'),
(1, 'Bihar'),
(1, 'Chhattisgarh'),
(1, 'Goa'),
(1, 'Gujarat'),
(1, 'Haryana'),
(1, 'Himachal Pradesh'),
(1, 'Jharkhand'),
(1, 'Karnataka'),
(1, 'Kerala'),
(1, 'Madhya Pradesh'),
(1, 'Maharashtra'),
(1, 'Manipur'),
(1, 'Meghalaya'),
(1, 'Mizoram'),
(1, 'Nagaland'),
(1, 'Odisha'),
(1, 'Punjab'),
(1, 'Rajasthan'),
(1, 'Sikkim'),
(1, 'Tamil Nadu'),
(1, 'Tamilnadu'),
(1, 'Telangana'),
(1, 'Tripura'),
(1, 'Uttar Pradesh'),
(1, 'Uttarakhand'),
(1, 'West Bengal'),
(1, 'Andaman and Nicobar Islands'),
(1, 'Chandigarh'),
(1, 'Dadra and Nagar Haveli and Daman and Diu'),
(1, 'Delhi'),
(1, 'Jammu and Kashmir'),
(1, 'Ladakh'),
(1, 'Lakshadweep'),
(1, 'Puducherry'),
(1, 'Pondicherry');

-- --------------------------------------------------------
--
-- Table structure for table `admin_suggestions`
-- Feature improvement / suggestion remarks from admin UI
--
DROP TABLE IF EXISTS `admin_suggestions`;
CREATE TABLE IF NOT EXISTS `admin_suggestions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `remarks` text NOT NULL,
  `status` enum('Pending','In Progress','Implemented','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
