-- phpMyAdmin SQL Dump
-- Database: `F1_Inspection`
-- FIA Formula 1 Scrutineering Management System (FSMS)
-- Full Homologated Schema & Initial Production Telemetry Data

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `F1_Inspection`
--

-- --------------------------------------------------------

--
-- Table structure for table `ROLES`
--

CREATE TABLE IF NOT EXISTS `ROLES` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ROLES`
--

INSERT INTO `ROLES` (`role_id`, `role_name`, `description`) VALUES
(1, 'Admin', 'FIA Central Administration & Race Director'),
(2, 'Scrutineer', 'FIA Technical Delegate / Scrutineer'),
(3, 'Steward', 'FIA Judicial Panel Steward'),
(4, 'Team Representative', 'Constructor Competitor Representative')
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`), `description` = VALUES(`description`);

-- --------------------------------------------------------

--
-- Table structure for table `USERS`
--

CREATE TABLE IF NOT EXISTS `USERS` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `avatar_url` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `preferred_language` varchar(50) DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `USERS`
--

INSERT INTO `USERS` (`user_id`, `role_id`, `full_name`, `email`, `password_hash`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Marzia Tabassum (Race Director)', 'admin@racecontrol.fia.com', 'pass123', '+41225444001', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00'),
(2, 2, 'Jo Bauer (Technical Delegate)', 'bauer@inspector.fia.com', 'pass123', '+491512345678', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00'),
(3, 3, 'Matteo Perini (FIA Steward)', 'steward@stewards.fia.com', 'pass123', '+390234567890', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00'),
(4, 4, 'Christian Horner (Team Rep)', 'horner@redbull.f1team.com', 'pass123', '+441908279700', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00'),
(5, 1, 'Marzia Tabassum', 'marzia@fia.com', 'pass123', '+41225444002', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00'),
(6, 2, 'Jo Bauer', 'bauer@fia.com', 'pass123', '+491512345679', 'active', '2026-08-21 16:39:00', '2026-08-21 16:39:00')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `role_id` = VALUES(`role_id`);

-- --------------------------------------------------------

--
-- Table structure for table `TEAMS`
--

CREATE TABLE IF NOT EXISTS `TEAMS` (
  `team_id` int(11) NOT NULL AUTO_INCREMENT,
  `team_name` varchar(150) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `TEAMS`
--

INSERT INTO `TEAMS` (`team_id`, `team_name`, `country`, `contact_email`, `status`) VALUES
(1, 'Oracle Red Bull Racing', 'Austria', 'contact@redbullracing.com', 'active'),
(2, 'Mercedes-AMG PETRONAS F1 Team', 'Germany', 'info@mercedesamgf1.com', 'active'),
(3, 'Scuderia Ferrari', 'Italy', 'info@ferrari.com', 'active'),
(4, 'McLaren Formula 1 Team', 'United Kingdom', 'media@mclaren.com', 'active'),
(5, 'Aston Martin Aramco F1 Team', 'United Kingdom', 'enquiries@astonmartinf1.com', 'active'),
(6, 'BWT Alpine F1 Team', 'France', 'info@alpinef1.com', 'active'),
(7, 'Williams Racing', 'United Kingdom', 'contact@williamsf1.com', 'active'),
(8, 'Visa Cash App RB F1 Team', 'Italy', 'info@visacashapprb.com', 'active'),
(9, 'Stake F1 Team Kick Sauber', 'Switzerland', 'contact@sauber-motorsport.com', 'active'),
(10, 'MoneyGram Haas F1 Team', 'United States', 'info@haasf1team.com', 'active')
ON DUPLICATE KEY UPDATE `team_name` = VALUES(`team_name`);

-- --------------------------------------------------------

--
-- Table structure for table `DRIVERS`
--

CREATE TABLE IF NOT EXISTS `DRIVERS` (
  `driver_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `license_number` varchar(100) NOT NULL,
  `license_grade` varchar(20) DEFAULT 'Super License',
  `contact_email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`driver_id`),
  UNIQUE KEY `license_number` (`license_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `DRIVERS`
--

INSERT INTO `DRIVERS` (`driver_id`, `full_name`, `country`, `date_of_birth`, `license_number`, `license_grade`, `contact_email`, `phone`) VALUES
(1, 'Max Verstappen', 'Netherlands', '1997-09-30', 'FIA-DRV-001', 'Super License', 'max@redbull.com', '+31100000001'),
(2, 'Sergio Perez', 'Mexico', '1990-01-26', 'FIA-DRV-011', 'Super License', 'sergio@redbull.com', '+52100000011'),
(3, 'Lewis Hamilton', 'United Kingdom', '1985-01-07', 'FIA-DRV-044', 'Super License', 'lewis@mercedes.com', '+44100000044'),
(4, 'George Russell', 'United Kingdom', '1998-02-15', 'FIA-DRV-063', 'Super License', 'george@mercedes.com', '+44100000063'),
(5, 'Charles Leclerc', 'Monaco', '1997-10-16', 'FIA-DRV-016', 'Super License', 'charles@ferrari.com', '+37700000016'),
(6, 'Carlos Sainz', 'Spain', '1994-09-01', 'FIA-DRV-055', 'Super License', 'carlos@ferrari.com', '+34100000055'),
(7, 'Lando Norris', 'United Kingdom', '1999-11-13', 'FIA-DRV-004', 'Super License', 'lando@mclaren.com', '+44100000004'),
(8, 'Oscar Piastri', 'Australia', '2001-04-06', 'FIA-DRV-081', 'Super License', 'oscar@mclaren.com', '+61100000081'),
(9, 'Fernando Alonso', 'Spain', '1981-07-29', 'FIA-DRV-014', 'Super License', 'fernando@astonmartin.com', '+34100000014'),
(10, 'Lance Stroll', 'Canada', '1998-10-29', 'FIA-DRV-018', 'Super License', 'lance@astonmartin.com', '+11100000018'),
(11, 'Pierre Gasly', 'France', '1996-02-07', 'FIA-DRV-010', 'Super License', 'pierre@alpine.com', '+33100000010'),
(12, 'Esteban Ocon', 'France', '1996-09-17', 'FIA-DRV-031', 'Super License', 'esteban@alpine.com', '+33100000031'),
(13, 'Alexander Albon', 'Thailand', '1996-03-23', 'FIA-DRV-023', 'Super License', 'alex@williams.com', '+66100000023'),
(14, 'Logan Sargeant', 'United States', '2000-12-31', 'FIA-DRV-002', 'Super License', 'logan@williams.com', '+11100000002'),
(15, 'Yuki Tsunoda', 'Japan', '2000-05-11', 'FIA-DRV-022', 'Super License', 'yuki@visacashapprb.com', '+81100000022'),
(16, 'Daniel Ricciardo', 'Australia', '1989-07-01', 'FIA-DRV-003', 'Super License', 'daniel@visacashapprb.com', '+61100000003'),
(17, 'Valtteri Bottas', 'Finland', '1989-08-28', 'FIA-DRV-077', 'Super License', 'valtteri@sauber.com', '+35800000077'),
(18, 'Zhou Guanyu', 'China', '1999-05-30', 'FIA-DRV-024', 'Super License', 'zhou@sauber.com', '+86100000024'),
(19, 'Nico Hulkenberg', 'Germany', '1987-08-19', 'FIA-DRV-027', 'Super License', 'nico@haas.com', '+49100000027'),
(20, 'Kevin Magnussen', 'Denmark', '1992-10-05', 'FIA-DRV-020', 'Super License', 'kevin@haas.com', '+45100000020')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- --------------------------------------------------------

--
-- Table structure for table `CARS`
--

CREATE TABLE IF NOT EXISTS `CARS` (
  `car_id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `car_name` varchar(100) DEFAULT NULL,
  `chassis_number` varchar(100) NOT NULL,
  `engine_type` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'Formula 1',
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`car_id`),
  UNIQUE KEY `chassis_number` (`chassis_number`),
  KEY `team_id` (`team_id`),
  KEY `driver_id` (`driver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `CARS`
--

INSERT INTO `CARS` (`car_id`, `team_id`, `driver_id`, `car_name`, `chassis_number`, `engine_type`, `category`, `status`) VALUES
(1, 1, 1, 'Red Bull RB20 #1', 'CHASSIS-RB20-01', 'Honda RBPTH002 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(2, 1, 2, 'Red Bull RB20 #2', 'CHASSIS-RB20-02', 'Honda RBPTH002 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(3, 2, 3, 'Mercedes W15 #1', 'CHASSIS-W15-01', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(4, 2, 4, 'Mercedes W15 #2', 'CHASSIS-W15-02', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(5, 3, 5, 'Ferrari SF-24 #1', 'CHASSIS-SF24-01', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(6, 3, 6, 'Ferrari SF-24 #2', 'CHASSIS-SF24-02', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(7, 4, 7, 'McLaren MCL38 #1', 'CHASSIS-MCL38-01', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(8, 4, 8, 'McLaren MCL38 #2', 'CHASSIS-MCL38-02', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(9, 5, 9, 'Aston Martin AMR24 #1', 'CHASSIS-AMR24-01', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(10, 5, 10, 'Aston Martin AMR24 #2', 'CHASSIS-AMR24-02', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(11, 6, 11, 'Alpine A524 #1', 'CHASSIS-A524-01', 'Renault E-Tech RE24 1.6L V6', 'Formula 1', 'active'),
(12, 6, 12, 'Alpine A524 #2', 'CHASSIS-A524-02', 'Renault E-Tech RE24 1.6L V6', 'Formula 1', 'active'),
(13, 7, 13, 'Williams FW46 #1', 'CHASSIS-FW46-01', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(14, 7, 14, 'Williams FW46 #2', 'CHASSIS-FW46-02', 'Mercedes-AMG M15 E Performance 1.6L V6', 'Formula 1', 'active'),
(15, 8, 15, 'RB VCARB 01 #1', 'CHASSIS-VCARB-01', 'Honda RBPTH002 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(16, 8, 16, 'RB VCARB 01 #2', 'CHASSIS-VCARB-02', 'Honda RBPTH002 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(17, 9, 17, 'Kick Sauber C44 #1', 'CHASSIS-C44-01', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(18, 9, 18, 'Kick Sauber C44 #2', 'CHASSIS-C44-02', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(19, 10, 19, 'Haas VF-24 #1', 'CHASSIS-VF24-01', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active'),
(20, 10, 20, 'Haas VF-24 #2', 'CHASSIS-VF24-02', 'Ferrari 066/12 1.6L V6 Turbo Hybrid', 'Formula 1', 'active')
ON DUPLICATE KEY UPDATE `car_name` = VALUES(`car_name`);

-- --------------------------------------------------------

--
-- Table structure for table `REGULATIONS`
--

CREATE TABLE IF NOT EXISTS `REGULATIONS` (
  `regulation_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `version` varchar(20) NOT NULL DEFAULT '2026.1',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `document_url` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`regulation_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `REGULATIONS`
--

INSERT INTO `REGULATIONS` (`regulation_id`, `title`, `description`, `content`, `version`, `status`, `created_by`) VALUES
(1, 'Art 3.5.1 Rear Wing Deflection', 'Aerodynamic deflection under load', 'The rear wing deflection must not exceed 2.0mm when subjected to 1000N vertical load.', '2026.1', 'active', 1),
(2, 'Art 4.1 Minimum Car Mass', 'Overall vehicle weight limits', 'The minimum weight of the car with driver must not be less than 798.0kg at all times.', '2026.1', 'active', 1),
(3, 'Art 5.2 Fuel Temperature', 'Fuel temperature threshold', 'Fuel must not be more than 10°C below ambient temperature one hour prior to the session.', '2026.1', 'active', 1),
(4, 'Art 3.5.9 Skid Block Plank Wear', 'Underfloor plank thickness', 'The minimum thickness of the plank assembly must be at least 9.0mm across all test points.', '2026.1', 'active', 1),
(5, 'Art 3.4.1 Front Wing Height', 'Front wing mainplane elevation', 'Front wing main plane trailing edge must not exceed 100mm height limit under 50N test load.', '2026.1', 'active', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------

--
-- Table structure for table `INSPECTION_SESSIONS`
--

CREATE TABLE IF NOT EXISTS `INSPECTION_SESSIONS` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `car_id` int(11) NOT NULL,
  `session_type` varchar(50) DEFAULT 'Pre-Event Scrutineering',
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'completed',
  `location` varchar(150) DEFAULT 'FIA Technical Garage Bay 1',
  `inspector_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `car_id` (`car_id`),
  KEY `inspector_id` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `INSPECTION_SESSIONS`
--

INSERT INTO `INSPECTION_SESSIONS` (`session_id`, `car_id`, `session_type`, `scheduled_at`, `started_at`, `completed_at`, `status`, `location`, `inspector_id`) VALUES
(1, 1, 'Pre-Qualifying Scrutineering', '2026-08-21 23:09:21', '2026-08-21 23:10:00', '2026-08-21 23:45:00', 'completed', 'FIA Garage Bay 1', 2),
(2, 3, 'Post-Qualifying Scrutineering', '2026-08-22 02:04:16', '2026-08-22 02:05:00', '2026-08-22 02:50:00', 'completed', 'FIA Technical Garage Bay 2', 2),
(3, 5, 'Post-Race Scrutineering', '2026-08-22 16:30:00', '2026-08-22 16:30:00', '2026-08-22 17:15:00', 'completed', 'Parc Ferme Verification Area', 2)
ON DUPLICATE KEY UPDATE `session_type` = VALUES(`session_type`);

-- --------------------------------------------------------

--
-- Table structure for table `COMPONENTS`
--

CREATE TABLE IF NOT EXISTS `COMPONENTS` (
  `component_id` int(11) NOT NULL AUTO_INCREMENT,
  `car_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `part_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(150) DEFAULT NULL,
  `specification` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`component_id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `COMPONENTS`
--

INSERT INTO `COMPONENTS` (`component_id`, `car_id`, `name`, `part_number`, `manufacturer`, `specification`, `status`) VALUES
(1, 1, 'Front Wing Assembly', 'FW-RB20-001', 'Red Bull Technologies', 'Carbon Composite High Downforce Spec', 'active'),
(2, 1, 'Underfloor Skid Plank', 'PLK-RB20-004', 'Red Bull Technologies', 'Homologated Jabroc Beechwood Skid Assembly', 'active'),
(3, 3, 'Rear Wing DRS Mainplane', 'RW-W15-002', 'Mercedes-AMG F1', 'High-Speed Low Drag Monza Spec', 'active'),
(4, 5, 'Internal Combustion Engine', 'ICE-066-12-03', 'Scuderia Ferrari', '1.6L 90-deg V6 15,000 RPM Max', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- --------------------------------------------------------

--
-- Table structure for table `INSPECTION_MEASUREMENTS`
--

CREATE TABLE IF NOT EXISTS `INSPECTION_MEASUREMENTS` (
  `measurement_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `component_id` int(11) DEFAULT NULL,
  `regulation_id` int(11) NOT NULL,
  `measurement_name` varchar(150) NOT NULL,
  `expected_value` varchar(100) DEFAULT NULL,
  `actual_value` varchar(100) DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `result` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`measurement_id`),
  KEY `session_id` (`session_id`),
  KEY `component_id` (`component_id`),
  KEY `regulation_id` (`regulation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `INSPECTION_MEASUREMENTS`
--

INSERT INTO `INSPECTION_MEASUREMENTS` (`measurement_id`, `session_id`, `component_id`, `regulation_id`, `measurement_name`, `expected_value`, `actual_value`, `unit`, `result`, `notes`) VALUES
(1, 1, 1, 1, 'Rear Wing Slot Gap Deflection', '160', '1.7', 'mm', 'Passed', 'Load test under 1000N vertical rig passed within statutory tolerance.'),
(2, 1, 1, 5, 'Front Wing Height Trailing Edge', '100', '150', 'mm', 'Failed', 'Front wing mainplane exceeded allowable 100mm height limit under 50N load.'),
(3, 2, 2, 4, 'Skid Block Plank Thickness', '9.0', '8.2', 'mm', 'Failed', 'Post-qualifying ultrasonic measurement revealed excessive plank wear at forward skid block.')
ON DUPLICATE KEY UPDATE `measurement_name` = VALUES(`measurement_name`);

-- --------------------------------------------------------

--
-- Table structure for table `VIOLATIONS`
--

CREATE TABLE IF NOT EXISTS `VIOLATIONS` (
  `violation_id` int(11) NOT NULL AUTO_INCREMENT,
  `measurement_id` int(11) NOT NULL,
  `violation_description` text NOT NULL,
  `severity` varchar(50) DEFAULT 'Major',
  `status` varchar(50) DEFAULT 'open',
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `detected_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`violation_id`),
  KEY `measurement_id` (`measurement_id`),
  KEY `detected_by` (`detected_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `VIOLATIONS`
--

INSERT INTO `VIOLATIONS` (`violation_id`, `measurement_id`, `violation_description`, `severity`, `status`, `detected_by`) VALUES
(1, 2, 'Front Wing Height exceeds maximum allowable limit by 50mm under aerodynamic load.', 'Major', 'rectified', 2),
(2, 3, 'Skid block plank wear measured at 8.2mm, below the 9.0mm mandatory minimum.', 'Critical', 'open', 2)
ON DUPLICATE KEY UPDATE `violation_description` = VALUES(`violation_description`);

-- --------------------------------------------------------

--
-- Table structure for table `PENALTIES`
--

CREATE TABLE IF NOT EXISTS `PENALTIES` (
  `penalty_id` int(11) NOT NULL AUTO_INCREMENT,
  `violation_id` int(11) NOT NULL,
  `steward_id` int(11) DEFAULT NULL,
  `penalty_type` varchar(100) NOT NULL,
  `penalty_value` varchar(100) DEFAULT NULL,
  `decision_date` datetime DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`penalty_id`),
  KEY `violation_id` (`violation_id`),
  KEY `steward_id` (`steward_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `PENALTIES`
--

INSERT INTO `PENALTIES` (`penalty_id`, `violation_id`, `steward_id`, `penalty_type`, `penalty_value`, `decision_date`, `comments`, `status`) VALUES
(1, 1, 3, 'Disqualification from Qualifying (DSQ)', 'Pit Lane Start', '2026-08-22 01:00:00', 'Aerodynamic deflection test non-compliant under Article 3.5.1.', 'confirmed'),
(2, 2, 3, '10-Place Grid Drop', '10 Grid Positions', '2026-08-22 03:30:00', 'Excessive underfloor skid block plank wear under Technical Reg Article 3.5.9.', 'confirmed')
ON DUPLICATE KEY UPDATE `penalty_type` = VALUES(`penalty_type`);

-- --------------------------------------------------------

--
-- Table structure for table `REPAIRS`
--

CREATE TABLE IF NOT EXISTS `REPAIRS` (
  `repair_id` int(11) NOT NULL AUTO_INCREMENT,
  `violation_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `performed_at` datetime DEFAULT NULL,
  `performed_by` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`repair_id`),
  KEY `violation_id` (`violation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `REPAIRS`
--

INSERT INTO `REPAIRS` (`repair_id`, `violation_id`, `description`, `performed_at`, `performed_by`, `status`) VALUES
(1, 1, 'Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower mainplane to 100mm regulation.', '2026-08-22 02:00:00', 'Red Bull Racing - Aero Crew', 'Completed'),
(2, 2, 'Replaced worn forward jabroc plank assembly with compliant 9.5 mm baseline FIA homologated skid block unit.', '2026-08-22 04:00:00', 'Scuderia Ferrari - Chassis Team', 'Completed')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- --------------------------------------------------------

--
-- Table structure for table `RE_INSPECTIONS`
--

CREATE TABLE IF NOT EXISTS `RE_INSPECTIONS` (
  `reinspection_id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `inspector_id` int(11) DEFAULT NULL,
  `result` varchar(50) DEFAULT 'Passed',
  `inspected_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reinspection_id`),
  KEY `repair_id` (`repair_id`),
  KEY `session_id` (`session_id`),
  KEY `inspector_id` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `RE_INSPECTIONS`
--

INSERT INTO `RE_INSPECTIONS` (`reinspection_id`, `repair_id`, `session_id`, `inspector_id`, `result`, `inspected_at`) VALUES
(1, 1, 1, 2, 'Passed', '2026-08-22 02:30:00'),
(2, 2, 2, 2, 'Passed', '2026-08-22 04:30:00')
ON DUPLICATE KEY UPDATE `result` = VALUES(`result`);

-- --------------------------------------------------------

--
-- Table structure for table `APPEALS`
--

CREATE TABLE IF NOT EXISTS `APPEALS` (
  `appeal_id` int(11) NOT NULL AUTO_INCREMENT,
  `penalty_id` int(11) NOT NULL,
  `appeal_reason` text NOT NULL,
  `status` varchar(50) DEFAULT 'Under Review',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `decision_at` datetime DEFAULT NULL,
  `decision_by` int(11) DEFAULT NULL,
  `decision_summary` text DEFAULT NULL,
  PRIMARY KEY (`appeal_id`),
  KEY `penalty_id` (`penalty_id`),
  KEY `decision_by` (`decision_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `APPEALS`
--

INSERT INTO `APPEALS` (`appeal_id`, `penalty_id`, `appeal_reason`, `status`, `submitted_at`, `decision_at`, `decision_by`, `decision_summary`) VALUES
(1, 1, 'Team telemetry demonstrates kerb strike on Turn 14 caused localized mounting fracture, not intentional aerodynamic non-compliance.', 'Upheld', '2026-08-22 03:00:00', '2026-08-22 05:00:00', 3, 'Steward panel verified kerb impact force trace exceeding 25kN. Grid drop substituted.')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- --------------------------------------------------------

--
-- Table structure for table `APPEAL_EVIDENCE`
--

CREATE TABLE IF NOT EXISTS `APPEAL_EVIDENCE` (
  `evidence_id` int(11) NOT NULL AUTO_INCREMENT,
  `appeal_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT 'PDF Technical Report',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL,
  PRIMARY KEY (`evidence_id`),
  KEY `appeal_id` (`appeal_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `APPEAL_EVIDENCE`
--

INSERT INTO `APPEAL_EVIDENCE` (`evidence_id`, `appeal_id`, `file_name`, `file_url`, `file_type`, `uploaded_by`, `description`) VALUES
(1, 1, 'FIA_T14_Kerb_Telemetry_Trace.pdf', 'https://fia.com/telemetry/RB20_T14_strain_gauge.pdf', 'PDF Technical Report', 4, 'High-speed strain gauge telemetry data demonstrating transient kerb impact force exceeding 25kN at apex.')
ON DUPLICATE KEY UPDATE `file_name` = VALUES(`file_name`);

-- --------------------------------------------------------

--
-- Table structure for table `AUDIT_LOGS`
--

CREATE TABLE IF NOT EXISTS `AUDIT_LOGS` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT '127.0.0.1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `AUDIT_LOGS`
--

INSERT INTO `AUDIT_LOGS` (`log_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`) VALUES
(1, 1, 'SYSTEM_INIT: FIA FSMS Database Telemetry Synchronized', 'SYSTEM_PORTAL', 1, '{"status":"All 20 homologated cars active","system":"FIA FSMS v3.0"}', '127.0.0.1'),
(2, 2, 'INSERT: Logged Technical Violation for Red Bull RB20 #1 (Front Wing Flex deflection > 2.0mm)', 'VIOLATIONS', 1, '{"chassis":"RB20-01","parameter":"Front Wing Load Deflection","measured_value":"2.45 mm","statutory_limit":"2.00 mm"}', '192.168.1.104'),
(3, 3, 'INSERT: Issued 10-Second Time Penalty to Car #16 (Charles Leclerc) for Causing Collision', 'PENALTIES', 1, '{"driver":"Charles Leclerc","car_number":16,"penalty_type":"10-Second Time Penalty","penalty_points":2}', '192.168.1.112'),
(4, 2, 'UPDATE: Certified Technical Scrutineering Re-Inspection for Mercedes W15 #63', 'REPAIRS', 2, '{"chassis":"W15-02","repair_action":"Replaced DRS hydraulic actuator seal","inspector_verdict":"Passed & Sealed"}', '192.168.1.108')
ON DUPLICATE KEY UPDATE `action` = VALUES(`action`);

-- --------------------------------------------------------

--
-- Table structure for table `PASSWORD_RESETS`
--

CREATE TABLE IF NOT EXISTS `PASSWORD_RESETS` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `NOTIFICATIONS`
--

CREATE TABLE IF NOT EXISTS `NOTIFICATIONS` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_role_id` int(11) DEFAULT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(50) DEFAULT 'GENERAL',
  `target_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `NOTIFICATIONS`
--

INSERT INTO `NOTIFICATIONS` (`notification_id`, `recipient_role_id`, `recipient_user_id`, `title`, `message`, `category`, `target_url`, `is_read`) VALUES
(1, NULL, NULL, 'PARC FERMÉ BREACH: Car #44 Wing Deflection Alert', 'Car #44 rear wing deflection exceeded 85mm tolerance during post-session scrutineering verification.', 'VIOLATION', 'feature5.php', 0),
(2, 3, NULL, 'STEWARDS HEARING: Incident Turn 4 Car #16 & Car #55', 'Investigation summoned for Turn 4 contact between Charles Leclerc and Carlos Sainz. Hearing at 17:30 UTC.', 'PENALTY', 'stewards_infringements.php', 0),
(3, 4, NULL, 'POWER UNIT ALLOCATION NOTICE: Mercedes ICE Cap Warning', 'Car #63 has fitted the 4th Internal Combustion Engine of the championship season. Further replacements will trigger a 10-place grid drop.', 'INSPECTION', 'feature4.php', 0),
(4, 2, NULL, 'SCRUTINEERING CLEARANCE: Red Bull RB20 Re-Inspection', 'Scrutineering bay #1 has verified and resealed the front wing pylon bracket for chassis RB20-01.', 'REPAIR', 'feature8.php', 1)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------

--
-- Table structure for table `component_allocations`
--

CREATE TABLE IF NOT EXISTS `component_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `car_id` int(11) NOT NULL,
  `component_type` varchar(60) NOT NULL,
  `units_used` int(11) DEFAULT 1,
  `max_limit` int(11) DEFAULT 4,
  `penalty_threshold_reached` tinyint(1) DEFAULT 0,
  `last_replaced_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `component_allocations`
--

INSERT INTO `component_allocations` (`id`, `car_id`, `component_type`, `units_used`, `max_limit`, `penalty_threshold_reached`) VALUES
(1, 1, 'Internal Combustion Engine (ICE)', 4, 4, 0),
(2, 1, 'Turbocharger (TC)', 3, 4, 0),
(3, 1, 'MGU-H', 3, 4, 0),
(4, 1, 'MGU-K', 3, 4, 0),
(5, 1, 'Energy Store (ES)', 2, 2, 0),
(6, 1, 'Control Electronics (CE)', 2, 2, 0),
(7, 3, 'Internal Combustion Engine (ICE)', 5, 4, 1),
(8, 3, 'Turbocharger (TC)', 4, 4, 0),
(9, 5, 'Energy Store (ES)', 3, 2, 1),
(10, 7, 'Turbocharger (TC)', 3, 4, 0)
ON DUPLICATE KEY UPDATE `units_used` = VALUES(`units_used`);

-- --------------------------------------------------------

--
-- Table structure for table `scrutineering_bulletins`
--

CREATE TABLE IF NOT EXISTS `scrutineering_bulletins` (
  `bulletin_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_no` varchar(50) NOT NULL,
  `grand_prix_name` varchar(100) NOT NULL,
  `session_type` varchar(50) NOT NULL DEFAULT 'Qualifying',
  `status` varchar(50) NOT NULL DEFAULT 'Official',
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `summary_notes` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`bulletin_id`),
  UNIQUE KEY `document_no` (`document_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scrutineering_bulletins`
--

INSERT INTO `scrutineering_bulletins` (`bulletin_id`, `document_no`, `grand_prix_name`, `session_type`, `status`, `summary_notes`, `issued_by`) VALUES
(1, 'DOC-04-MCO', 'Monaco Grand Prix 2026', 'FP1', 'Official', 'Initial technical checks and weight verifications for all 20 chassis completed without anomaly.', 1),
(2, 'DOC-12-MCO', 'Monaco Grand Prix 2026', 'FP3', 'Official', 'PU Component replacements logged: Car 3 (Mercedes W15) 5th ICE installed. Grid penalty notice issued.', 1),
(3, 'DOC-28-MCO', 'Monaco Grand Prix 2026', 'Qualifying', 'Official', 'Post-Qualifying scrutineering: Front wing flex and fuel temperature tests passed. Car 11 rear wing under further FIA review.', 1),
(4, 'DOC-39-MCO', 'Monaco Grand Prix 2026', 'Race', 'Pending Sign-off', 'Pre-Race parc fermé technical delegating in progress. Tyre pressures, cooling fans, and seal locks verified.', 1)
ON DUPLICATE KEY UPDATE `document_no` = VALUES(`document_no`);

-- --------------------------------------------------------

--
-- Table structure for table `fia_regulations`
--

CREATE TABLE IF NOT EXISTS `fia_regulations` (
  `regulation_id` int(11) NOT NULL AUTO_INCREMENT,
  `article_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT 'Sporting',
  `description` text DEFAULT NULL,
  `penalty_guideline` varchar(150) DEFAULT 'Time Penalty / Grid Drop',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`regulation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fia_regulations`
--

INSERT INTO `fia_regulations` (`regulation_id`, `article_code`, `title`, `category`, `description`, `penalty_guideline`) VALUES
(1, 'ISC App. L, Ch. IV, Art. 2(c)', 'Causing an Avoidable Collision', 'Driving Conduct', 'Causing an avoidable collision or forcing another competitor off the racing surface.', '10-Second Time Penalty + 2 Penalty Points'),
(2, 'Art 33.4 Sporting Regs', 'Impeding Competitor in Qualifying', 'Sporting', 'Unnecessarily impeding another driver on a hot lap during qualifying session.', '3-Place Grid Drop / Official Reprimand'),
(3, 'Art 3.5.1 Technical Regs', 'Aerodynamic Deflection Over Limit', 'Technical', 'Rear wing slot gap or aerodynamic element flexing beyond 2.0mm threshold.', 'Disqualification from Session / Parc Fermé Start'),
(4, 'Art 34.14 Sporting Regs', 'Pit Lane Speeding Violation', 'Pit Lane', 'Exceeding the designated 80.0 km/h pit lane speed limit.', '€1,000 Fine + 5-Second Time Penalty'),
(5, 'Art 55.7 Sporting Regs', 'Safety Car Delta Infringement', 'Safety Car', 'Failing to maintain minimum delta time during Virtual / Full Safety Car period.', '5-Second Time Penalty + 1 Penalty Point'),
(6, 'Art 12.2.1.i ISC', 'Failure to Respect Double Yellow Flags', 'Safety', 'Not significantly reducing speed and being prepared to stop under double yellow flags.', '10-Place Grid Drop + 3 Penalty Points'),
(7, 'Art 4.1 Technical Regs', 'Under Minimum Weight Limit', 'Technical', 'Car and driver combined mass below 798.0 kg at post-race scrutineering.', 'Disqualification from Race Results'),
(8, 'Art 33.3 Sporting Regs', 'Persistent Track Limits Abuse', 'Sporting', 'Exceeding track limits on 4 or more occasions without justifiable reason.', '5-Second Time Penalty')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- --------------------------------------------------------

--
-- Table structure for table `steward_infringements`
--

CREATE TABLE IF NOT EXISTS `steward_infringements` (
  `infringement_id` int(11) NOT NULL AUTO_INCREMENT,
  `case_number` varchar(50) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `car_id` int(11) DEFAULT NULL,
  `regulation_id` int(11) NOT NULL,
  `session_name` varchar(100) DEFAULT 'Race',
  `lap_or_turn` varchar(100) DEFAULT NULL,
  `incident_description` text NOT NULL,
  `penalty_type` varchar(100) DEFAULT 'Under Investigation',
  `penalty_points` int(11) DEFAULT 0,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Under Investigation',
  `steward_notes` text DEFAULT NULL,
  `steward_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`infringement_id`),
  UNIQUE KEY `case_number` (`case_number`),
  KEY `driver_id` (`driver_id`),
  KEY `car_id` (`car_id`),
  KEY `regulation_id` (`regulation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `steward_infringements`
--

INSERT INTO `steward_infringements` (`infringement_id`, `case_number`, `driver_id`, `car_id`, `regulation_id`, `session_name`, `lap_or_turn`, `incident_description`, `penalty_type`, `penalty_points`, `fine_amount`, `status`, `steward_notes`, `steward_id`) VALUES
(1, 'DOC-48-FIA', 5, 5, 1, 'Race', 'Lap 24, Turn 4', 'Car 16 made contact with Car 55 at the entry of Turn 4 causing Car 55 to spin off the racing circuit.', '10-Second Time Penalty', 2, 0.00, 'Penalty Applied', 'Telemetry confirms Car 16 did not achieve significant overlap prior to apex.', 3),
(2, 'DOC-52-FIA', 1, 1, 4, 'FP2', 'Pit Lane Entry', 'Car 1 exceeded the pit lane speed limit of 80.0 km/h by 4.2 km/h (measured at 84.2 km/h).', '€500 Fine', 0, 500.00, 'Penalty Applied', 'Pit speed radar sensor calibration verified.', 3),
(3, 'DOC-61-FIA', 3, 3, 2, 'Qualifying', 'Q2, Turn 10', 'Car 3 alleged to have impeded Car 14 on a hot lap between Turns 9 and 10.', 'Official Reprimand', 0, 0.00, 'Resolved (No Penalty)', 'Car 14 did not have to abort lap; telemetry confirms minimal delta loss.', 3)
ON DUPLICATE KEY UPDATE `case_number` = VALUES(`case_number`);

-- --------------------------------------------------------

--
-- Table structure for table `technical_violations`
--

CREATE TABLE IF NOT EXISTS `technical_violations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) DEFAULT NULL,
  `vehicle_name` varchar(150) NOT NULL,
  `chassis_code` varchar(50) NOT NULL,
  `parameter` varchar(100) NOT NULL,
  `expected_value` varchar(50) NOT NULL,
  `actual_value` varchar(50) NOT NULL,
  `severity` varchar(60) NOT NULL,
  `delegate` varchar(100) DEFAULT 'Jo Bauer',
  `verification_status` varchar(60) DEFAULT 'Under Investigation',
  `notes` text DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technical_violations`
--

INSERT INTO `technical_violations` (`id`, `vehicle_id`, `vehicle_name`, `chassis_code`, `parameter`, `expected_value`, `actual_value`, `severity`, `delegate`, `verification_status`, `notes`) VALUES
(101, 1, 'Red Bull RB20 #1 (Max Verstappen)', 'CHASSIS-RB20-01', 'Front Wing Height', '100 mm', '150 mm', 'Major Safety Violation', 'Jo Bauer', 'Pending Stewards', 'Front wing main plane trailing edge exceeds 100mm height limit under 50N test load.'),
(102, 3, 'Ferrari SF-24 #16 (Charles Leclerc)', 'CHASSIS-SF24-02', 'Plank Wear Thickness', '9.0 mm', '8.2 mm', 'Critical Performance Advantage', 'Matteo Perini', 'Under Investigation', 'Post-qualifying ultrasonic gauge measured 8.2mm at forward skid block holes.')
ON DUPLICATE KEY UPDATE `vehicle_name` = VALUES(`vehicle_name`);

-- --------------------------------------------------------

--
-- Table structure for table `steward_decisions`
--

CREATE TABLE IF NOT EXISTS `steward_decisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `decision_code` varchar(50) NOT NULL,
  `violation_id` int(11) DEFAULT NULL,
  `target_entity` varchar(150) NOT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `team_name` varchar(100) DEFAULT NULL,
  `penalty_type` varchar(100) NOT NULL,
  `rationale` text NOT NULL,
  `steward_name` varchar(100) DEFAULT 'Garry Connelly',
  `decision_status` varchar(60) DEFAULT 'Confirmed & Published',
  `hearing_time` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `decision_code` (`decision_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `steward_decisions`
--

INSERT INTO `steward_decisions` (`id`, `decision_code`, `violation_id`, `target_entity`, `driver_name`, `team_name`, `penalty_type`, `rationale`, `steward_name`, `decision_status`, `hearing_time`) VALUES
(201, 'PEN-201', 101, 'Max Verstappen / Red Bull Racing', 'Max Verstappen #1', 'RED BULL RACING', 'Disqualification from Qualifying', 'Car #1 front wing flap assembly failed vertical load deflection test by 50mm.', 'Garry Connelly', 'Confirmed & Published', '04/09/2026, 17:30'),
(202, 'PEN-202', 102, 'Charles Leclerc / Scuderia Ferrari', 'Charles Leclerc #16', 'SCUDERIA FERRARI', '10-Place Grid Drop', 'Skid block plank wear exceeded 1.0mm tolerance under Technical Reg Article 3.5.9.', 'Derek Warwick', 'Confirmed & Published', '04/09/2026, 17:45')
ON DUPLICATE KEY UPDATE `decision_code` = VALUES(`decision_code`);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_repairs`
--

CREATE TABLE IF NOT EXISTS `vehicle_repairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `violation_id` int(11) DEFAULT NULL,
  `vehicle_name` varchar(150) NOT NULL,
  `chassis_code` varchar(50) NOT NULL,
  `violation_desc` text NOT NULL,
  `repair_description` text NOT NULL,
  `performed_by` varchar(150) NOT NULL,
  `status` varchar(50) DEFAULT 'Completed',
  `performed_at` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_repairs`
--

INSERT INTO `vehicle_repairs` (`id`, `violation_id`, `vehicle_name`, `chassis_code`, `violation_desc`, `repair_description`, `performed_by`, `status`, `performed_at`) VALUES
(1, 101, 'Red Bull RB20 #1', 'CHASSIS-RB20-01', 'Automatic violation detected: Front Wing Height. Expected: 100 mm, Actual: 150 mm', 'Adjusted front wing pylon mounting brackets and removed upper spacer shims to lower the front wing mainplane, reducing height from 150 mm down to the required 100 mm specification.', 'Red Bull Racing - Aero Crew', 'Completed', '2026-09-03 00:35:00'),
(2, 102, 'Ferrari SF-24 #16', 'CHASSIS-SF24-02', 'Automatic violation detected: Plank Wear. Expected: 9.0 mm, Actual: 8.2 mm', 'Replaced worn forward jabroc plank assembly with compliant 9.5 mm baseline FIA homologated skid block unit.', 'Scuderia Ferrari - Chassis Team', 'Completed', '2026-09-03 01:15:00')
ON DUPLICATE KEY UPDATE `vehicle_name` = VALUES(`vehicle_name`);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_reinspections`
--

CREATE TABLE IF NOT EXISTS `vehicle_reinspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_record_id` int(11) NOT NULL,
  `vehicle_name` varchar(150) NOT NULL,
  `chassis_code` varchar(50) NOT NULL,
  `inspection_session` varchar(100) NOT NULL,
  `inspector` varchar(100) NOT NULL,
  `result` varchar(50) DEFAULT 'Passed',
  `inspected_at` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_reinspections`
--

INSERT INTO `vehicle_reinspections` (`id`, `repair_record_id`, `vehicle_name`, `chassis_code`, `inspection_session`, `inspector`, `result`, `inspected_at`) VALUES
(1, 1, 'Red Bull RB20 #1', 'CHASSIS-RB20-01', 'Post-Qualifying Scrutineering', 'Jo Bauer', 'Passed', '2026-09-03 00:37:00'),
(2, 2, 'Ferrari SF-24 #16', 'CHASSIS-SF24-02', 'Post-Qualifying Scrutineering', 'Matteo Perini', 'Passed', '2026-09-03 01:22:00')
ON DUPLICATE KEY UPDATE `vehicle_name` = VALUES(`vehicle_name`);

-- --------------------------------------------------------

--
-- Foreign Key Constraints
--

ALTER TABLE `USERS`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `ROLES` (`role_id`) ON DELETE SET NULL;

ALTER TABLE `CARS`
  ADD CONSTRAINT `cars_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `TEAMS` (`team_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cars_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `DRIVERS` (`driver_id`) ON DELETE SET NULL;

ALTER TABLE `COMPONENTS`
  ADD CONSTRAINT `components_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `CARS` (`car_id`) ON DELETE CASCADE;

ALTER TABLE `INSPECTION_SESSIONS`
  ADD CONSTRAINT `inspection_sessions_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `CARS` (`car_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_sessions_ibfk_2` FOREIGN KEY (`inspector_id`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `INSPECTION_MEASUREMENTS`
  ADD CONSTRAINT `inspection_measurements_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `INSPECTION_SESSIONS` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_measurements_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `COMPONENTS` (`component_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inspection_measurements_ibfk_3` FOREIGN KEY (`regulation_id`) REFERENCES `REGULATIONS` (`regulation_id`);

ALTER TABLE `VIOLATIONS`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`measurement_id`) REFERENCES `INSPECTION_MEASUREMENTS` (`measurement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`detected_by`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `PENALTIES`
  ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`violation_id`) REFERENCES `VIOLATIONS` (`violation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penalties_ibfk_2` FOREIGN KEY (`steward_id`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `REPAIRS`
  ADD CONSTRAINT `repairs_ibfk_1` FOREIGN KEY (`violation_id`) REFERENCES `VIOLATIONS` (`violation_id`) ON DELETE CASCADE;

ALTER TABLE `RE_INSPECTIONS`
  ADD CONSTRAINT `re_inspections_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `REPAIRS` (`repair_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `re_inspections_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `INSPECTION_SESSIONS` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `re_inspections_ibfk_3` FOREIGN KEY (`inspector_id`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `APPEALS`
  ADD CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`penalty_id`) REFERENCES `PENALTIES` (`penalty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`decision_by`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `APPEAL_EVIDENCE`
  ADD CONSTRAINT `appeal_evidence_ibfk_1` FOREIGN KEY (`appeal_id`) REFERENCES `APPEALS` (`appeal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appeal_evidence_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `AUDIT_LOGS`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `PASSWORD_RESETS`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `USERS` (`user_id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
