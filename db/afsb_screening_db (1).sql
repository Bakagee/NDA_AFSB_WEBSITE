-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2025 at 05:23 PM
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
-- Database: `afsb_screening_db`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `GenerateChestNumber` (`state_code` VARCHAR(2), `candidate_count` INT) RETURNS VARCHAR(10) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
    DECLARE chest_number VARCHAR(10);
    
    -- Format: [STATE_CODE]-[NUMBER]
    -- For example: AB-01, AD-69
    SET chest_number = CONCAT(state_code, '-', LPAD(candidate_count, 2, '0'));
    
    RETURN chest_number;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `user_role` varchar(20) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `user_type`, `user_role`, `activity_type`, `activity_details`, `ip_address`, `created_at`) VALUES
(1, NULL, NULL, 'officer', 'login_failed', 'Failed login attempt for username: officer1', '::1', '2025-04-19 16:10:11'),
(2, NULL, NULL, 'officer', 'login_failed', 'Failed login attempt for username: officer1', '::1', '2025-04-19 16:10:33'),
(3, NULL, NULL, 'officer', 'login_failed', 'Failed login attempt for username: officer1', '::1', '2025-04-19 16:11:18'),
(4, 2, 'admin', 'admin', 'toggled stage status', 'Stage ID: 1', '::1', '2025-06-02 15:21:41'),
(5, 2, 'admin', 'admin', 'toggled stage status', 'Stage ID: 1', '::1', '2025-06-02 15:21:43'),
(6, 2, 'admin', 'admin', 'toggled stage status', 'Stage ID: 1', '::1', '2025-06-02 15:22:28'),
(7, 2, 'admin', 'admin', 'toggled stage status', 'Stage ID: 1', '::1', '2025-06-02 15:23:08');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default_admin.png',
  `role` varchar(20) DEFAULT 'admin',
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `email`, `phone`, `profile_image`, `role`, `last_login`, `status`, `created_at`, `updated_at`) VALUES
(2, 'admin', '$2y$10$F5R034TTw0X6cQnFXuCpdObaq9JQAn44m4ow7glQEmeZt0pi4Yz3G', 'UMAR ABDULLAHI', 'admin@gmail.com', '+2349022698720', 'admin.png', 'admin', '2025-06-02 12:07:03', 'active', '2025-04-19 20:05:57', '2025-06-02 11:07:03');

-- --------------------------------------------------------

--
-- Table structure for table `board_interview_assessments`
--

CREATE TABLE `board_interview_assessments` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`test_results`)),
  `assessment_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`assessment_summary`)),
  `assessed_by` int(11) NOT NULL,
  `assessed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `board_interview_assessments`
--

INSERT INTO `board_interview_assessments` (`id`, `candidate_id`, `test_results`, `assessment_summary`, `assessed_by`, `assessed_at`) VALUES
(1, 16, '{\"appearance\":{\"points\":\"6\"},\"communication\":{\"points\":\"8\"},\"knowledge\":{\"points\":\"5\"},\"attitude\":{\"points\":\"8\"}}', '{\"total_points\":27,\"max_points\":40,\"percentage\":67.5}', 3, '2025-06-01 13:46:02'),
(2, 9, '{\"appearance\":{\"points\":\"6\"},\"communication\":{\"points\":\"9\"},\"knowledge\":{\"points\":\"8\"},\"attitude\":{\"points\":\"4\"}}', '{\"total_points\":27,\"max_points\":40,\"percentage\":67.5}', 3, '2025-06-01 14:01:18'),
(3, 13, '{\"appearance\":{\"points\":\"6\"},\"communication\":{\"points\":\"9\"},\"knowledge\":{\"points\":\"7\"},\"attitude\":{\"points\":\"3\"}}', '{\"total_points\":25,\"max_points\":40,\"percentage\":62.5}', 3, '2025-06-01 14:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `candidate_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `other_name` varchar(50) DEFAULT NULL,
  `jamb_number` varchar(15) NOT NULL,
  `sex` enum('M','F') NOT NULL,
  `nda_application_number` varchar(20) NOT NULL,
  `jamb_score` int(11) NOT NULL,
  `state_id` int(11) NOT NULL,
  `service_choice_1` enum('Army','Navy','Air Force') NOT NULL,
  `service_choice_2` enum('Army','Navy','Air Force') DEFAULT NULL,
  `chest_number` varchar(10) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT 'default_candidate.jpg',
  `current_stage` enum('documentation','medical','physical','sand_modelling','interview','completed','failed') DEFAULT 'documentation',
  `status` enum('active','failed','passed','withdrawn') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_of_birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`candidate_id`, `first_name`, `surname`, `other_name`, `jamb_number`, `sex`, `nda_application_number`, `jamb_score`, `state_id`, `service_choice_1`, `service_choice_2`, `chest_number`, `profile_picture`, `current_stage`, `status`, `created_by`, `created_at`, `updated_at`, `date_of_birth`) VALUES
(5, 'Stephanie', 'Chidiebere', 'Kamsiyochukwu', '31455997CA', 'F', 'NDA2025AB001', 243, 1, '', 'Air Force', 'AB-01', 'profile1.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2005-05-01'),
(6, 'Victor', 'Adiele', 'Chinoso', '31141672GA', 'M', 'NDA2025AB002', 217, 1, '', 'Army', 'AB-02', 'profile2.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2006-07-15'),
(7, 'Emmanuel', 'Okezie', 'Chinechurum', '30279418EA', 'M', 'NDA2025AB003', 251, 1, '', 'Air Force', 'AB-03', 'profile3.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2008-06-03'),
(8, 'Christopher', 'Njoku', 'Chukwudi', '30296370JA', 'M', 'NDA2025AB004', 230, 1, '', 'Navy', 'AB-04', 'profile4.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2009-02-25'),
(9, 'Philemon', 'Egbu', 'Chukwuemeka', '31006288FF', 'M', 'NDA2025AB005', 239, 1, '', 'Army', 'AB-05', 'profile5.jpeg', 'documentation', '', 2, '2025-04-20 13:58:17', '2025-06-01 12:49:30', '2004-01-08'),
(10, 'Bishop', 'Nwankpa', 'David', '30299880BA', 'M', 'NDA2025AB006', 220, 1, '', 'Air Force', 'AB-06', 'profile6.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2007-07-03'),
(11, 'Joe', 'Johnson', 'Otisi', '31209805BF', 'M', 'NDA2025AB007', 232, 1, '', 'Army', 'AB-07', 'profile7.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2006-08-25'),
(12, 'Princewill', 'Ojiugo', 'Amanze', '31326418DA', 'M', 'NDA2025AB008', 226, 1, '', 'Navy', 'AB-08', 'profile8.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2005-11-27'),
(13, 'Gideon', 'Ejemele', 'Kelechi', '31564570DJ', 'M', 'NDA2025AB009', 240, 1, '', 'Army', 'AB-09', 'profile9.jpeg', 'physical', '', 2, '2025-04-20 13:58:17', '2025-06-01 13:06:28', '2005-12-03'),
(14, 'Daniel', 'Uzo-Chinonye', 'Onyekidika', '30500757JA', 'M', 'NDA2025AB010', 229, 1, '', 'Navy', 'AB-10', 'profile10.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2006-03-06'),
(15, 'David', 'Onyinyechukwu', 'Chukwunenye', '31004484EF', 'M', 'NDA2025AB011', 248, 1, '', 'Army', 'AB-11', 'profile11.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2003-09-01'),
(16, 'Bassey', 'Bassey', 'Junior', '31185265AF', 'M', 'NDA2025AB012', 237, 1, '', 'Army', 'AB-12', 'profile12.jpeg', 'physical', '', 2, '2025-04-20 13:58:17', '2025-06-01 12:41:18', '2006-03-23'),
(17, 'Gospel', 'Sondou', 'Ozioma', '31077701FE', 'M', 'NDA2025AB013', 224, 1, '', 'Navy', 'AB-13', 'profile13.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2007-03-05'),
(18, 'Onyemaechi', 'Daniel', 'Ugokuchukwu', '31140102CA', 'M', 'NDA2025AB014', 221, 1, '', 'Navy', 'AB-14', 'profile14.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2003-12-04'),
(19, 'Richard', 'Onwubiko', 'Tobechukwu', '31420891GD', 'M', 'NDA2025AB015', 235, 1, '', 'Air Force', 'AB-15', 'profile15.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2003-07-05'),
(20, 'Destiny', 'Oluogu', 'Akachi', '30124115FA', 'F', 'NDA2025AB016', 218, 1, '', 'Navy', 'AB-16', 'profile16.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2007-06-26'),
(21, 'Blessing', 'Daniel', 'Chigozirim', '30407804IA', 'F', 'NDA2025AB017', 214, 1, '', 'Air Force', 'AB-17', 'profile17.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2004-01-23'),
(22, 'God\'spower', 'Ifowike', 'Elijah', '30299613IA', 'M', 'NDA2025AB018', 241, 1, '', 'Air Force', 'AB-18', 'profile18.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2004-11-10'),
(23, 'Chukwumeka', 'Onwuasoanya', 'Ifeanyi', '30294521FA', 'M', 'NDA2025AB019', 250, 1, '', 'Air Force', 'AB-19', 'profile19.jpeg', 'documentation', 'active', 2, '2025-04-20 13:58:17', '2025-05-25 12:26:04', '2004-09-24'),
(24, 'khalid', 'Muhammad', 'Umar', '10705436AD', 'M', 'NDA/77/006569', 217, 1, 'Army', 'Air Force', 'AB-20', 'uploads/candidates/candidate_1745157600_6804fde0a75a6.jpg', 'documentation', 'active', 2, '2025-04-20 14:00:00', '2025-05-25 12:26:04', '2004-07-09'),
(25, 'Muktar', 'Saliu', '', '23456789AE', 'F', 'NDA/77/03569', 187, 1, 'Air Force', 'Navy', 'AB-21', 'uploads/candidates/candidate_1745157661_6804fe1d4be5e.jpg', 'documentation', 'active', 2, '2025-04-20 14:01:01', '2025-05-25 12:26:04', '2009-05-03');

--
-- Triggers `candidates`
--
DELIMITER $$
CREATE TRIGGER `BeforeInsertCandidate` BEFORE INSERT ON `candidates` FOR EACH ROW BEGIN
    DECLARE state_code VARCHAR(2);
    DECLARE candidate_count INT;
    
    -- Get state code from state_id
    SELECT states.state_code INTO state_code 
    FROM states 
    WHERE states.id = NEW.state_id;
    
    -- Count existing candidates from this state
    SELECT COUNT(*) + 1 INTO candidate_count 
    FROM candidates 
    WHERE state_id = NEW.state_id;
    
    -- Only assign chest number if count is 70 or less
    IF candidate_count <= 70 THEN
        SET NEW.chest_number = GenerateChestNumber(state_code, candidate_count);
    ELSE
        -- For candidates beyond 70, leave chest_number NULL or set a waiting list indicator
        SET NEW.chest_number = CONCAT(state_code, '-W', LPAD(candidate_count - 70, 2, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_documents`
--

CREATE TABLE `candidate_documents` (
  `document_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `issuing_authority` varchar(100) DEFAULT NULL,
  `verification_status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_stages`
--

CREATE TABLE `candidate_stages` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','passed','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `officer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_stages`
--

INSERT INTO `candidate_stages` (`id`, `candidate_id`, `stage_id`, `status`, `notes`, `officer_id`, `created_at`, `updated_at`, `updated_by`, `created_by`) VALUES
(1, 5, 1, '', 'Initial documentation stage', 3, '2025-04-21 17:33:22', '2025-04-23 11:20:34', NULL, NULL),
(2, 6, 1, 'passed', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-05-25 14:00:46', 3, NULL),
(3, 7, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(4, 8, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(5, 9, 2, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-05-31 13:21:01', NULL, NULL),
(6, 10, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(7, 11, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(8, 12, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(9, 13, 2, 'passed', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-06-01 13:01:55', NULL, NULL),
(10, 14, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(11, 15, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(12, 16, 2, 'passed', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-06-01 11:53:48', NULL, NULL),
(13, 17, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(14, 18, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(15, 19, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(16, 20, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(17, 21, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(18, 22, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(19, 23, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(20, 24, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(21, 25, 1, 'pending', 'Initial documentation stage', NULL, '2025-04-21 17:33:22', '2025-04-21 17:33:22', NULL, NULL),
(33, 6, 2, 'pending', NULL, NULL, '2025-05-25 12:39:52', '2025-05-25 12:39:52', NULL, 3),
(34, 9, 4, 'pending', NULL, NULL, '2025-06-01 11:52:59', '2025-06-01 11:52:59', NULL, 3),
(35, 16, 3, 'pending', NULL, NULL, '2025-06-01 11:53:48', '2025-06-01 11:53:48', NULL, 3),
(36, 16, 4, 'pending', NULL, NULL, '2025-06-01 11:54:17', '2025-06-01 11:54:17', NULL, 3),
(37, 9, 5, 'passed', NULL, NULL, '2025-06-01 12:00:36', '2025-06-01 12:49:30', NULL, 3),
(38, 16, 5, 'passed', NULL, NULL, '2025-06-01 12:31:42', '2025-06-01 12:41:18', NULL, 3),
(39, 13, 3, 'pending', NULL, NULL, '2025-06-01 13:01:55', '2025-06-01 13:01:55', NULL, 3),
(40, 13, 4, 'pending', NULL, NULL, '2025-06-01 13:02:20', '2025-06-01 13:02:20', NULL, 3),
(41, 13, 5, 'passed', NULL, NULL, '2025-06-01 13:03:04', '2025-06-01 13:06:28', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `document_verifications`
--

CREATE TABLE `document_verifications` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `verified_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of document types that have been verified' CHECK (json_valid(`verified_documents`)),
  `verification_notes` text DEFAULT NULL COMMENT 'Notes from the verification officer',
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL COMMENT 'Officer ID who verified the documents',
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verification_details` text DEFAULT NULL,
  `auto_processed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_verifications`
--

INSERT INTO `document_verifications` (`id`, `candidate_id`, `verified_documents`, `verification_notes`, `verification_status`, `verified_by`, `verified_at`, `created_at`, `updated_at`, `verification_details`, `auto_processed`) VALUES
(1, 6, '[\"birth_certificate\",\"waec_result\",\"jamb_result\",\"state_of_origin_certificate\",\"passport_photograph\",\"primary_school_certificate\",\"primary_school_testimonial\",\"secondary_school_testimonial\",\"indigene_certificate\",\"bvn\",\"nin\",\"nda_admission_card\",\"attestation_letter\",\"parent_consent_form\",\"acknowledgement_form\"]', '', 'verified', 3, '2025-05-31 12:37:21', '2025-05-25 12:32:34', '2025-05-31 11:37:21', '{\"flags\":[],\"disqualification_reason\":\"\"}', 1),
(2, 16, '[\"birth_certificate\",\"state_of_origin_certificate\",\"indigene_certificate\",\"passport_photograph\",\"bvn\",\"nin\",\"waec_result\",\"jamb_result\",\"primary_school_certificate\",\"primary_school_testimonial\",\"secondary_school_testimonial\",\"nda_admission_card\",\"attestation_letter\",\"parent_consent_form\",\"acknowledgement_form\"]', '', 'verified', 3, '2025-05-31 14:21:45', '2025-05-31 11:44:51', '2025-05-31 13:21:45', '{\"flags\":[],\"disqualification_reason\":\"\"}', 0),
(3, 5, '[]', '', 'rejected', 3, '2025-05-31 13:48:00', '2025-05-31 12:48:00', '2025-05-31 12:48:00', '{\"flags\":[],\"disqualification_reason\":\"\"}', 0),
(4, 21, '[]', '', 'rejected', 3, '2025-05-31 14:08:14', '2025-05-31 13:01:17', '2025-05-31 13:08:14', '{\"flags\":[],\"disqualification_reason\":\"\"}', 0),
(5, 9, '[\"birth_certificate\",\"state_of_origin_certificate\",\"indigene_certificate\",\"passport_photograph\",\"bvn\",\"nin\",\"waec_result\",\"jamb_result\",\"primary_school_certificate\",\"primary_school_testimonial\",\"secondary_school_testimonial\",\"nda_admission_card\",\"attestation_letter\",\"parent_consent_form\",\"acknowledgement_form\"]', '', 'verified', 3, '2025-05-31 14:21:01', '2025-05-31 13:09:16', '2025-05-31 13:21:01', '{\"flags\":[],\"disqualification_reason\":\"\"}', 0),
(6, 18, '[]', '', 'rejected', 3, '2025-05-31 14:23:10', '2025-05-31 13:23:10', '2025-05-31 13:23:10', '{\"flags\":[\"missing_documents\",\"name_mismatch\"],\"disqualification_reason\":\"His name does not tally with the one in his bvn\"}', 0),
(7, 13, '[\"birth_certificate\",\"state_of_origin_certificate\",\"indigene_certificate\",\"passport_photograph\",\"bvn\",\"nin\",\"waec_result\",\"jamb_result\",\"primary_school_certificate\",\"primary_school_testimonial\",\"secondary_school_testimonial\",\"nda_admission_card\",\"attestation_letter\",\"parent_consent_form\",\"acknowledgement_form\"]', '', 'verified', 3, '2025-06-01 09:23:32', '2025-06-01 08:23:32', '2025-06-01 08:23:32', '{\"flags\":[],\"disqualification_reason\":\"\"}', 0);

-- --------------------------------------------------------

--
-- Table structure for table `flags`
--

CREATE TABLE `flags` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `flag_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','resolved','ignored') DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_screening`
--

CREATE TABLE `medical_screening` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`test_results`)),
  `overall_fitness` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`overall_fitness`)),
  `notes` text DEFAULT NULL,
  `screened_by` int(11) NOT NULL,
  `screened_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_screening`
--

INSERT INTO `medical_screening` (`id`, `candidate_id`, `test_results`, `overall_fitness`, `notes`, `screened_by`, `screened_at`) VALUES
(1, 9, '{\"height\":{\"status\":\"passed\",\"reason\":null},\"weight\":{\"status\":\"passed\",\"reason\":null},\"blood_pressure\":{\"status\":\"passed\",\"reason\":null},\"pulse_rate\":{\"status\":\"passed\",\"reason\":null},\"vision\":{\"left_eye\":{\"status\":\"passed\",\"reason\":null},\"right_eye\":{\"status\":\"passed\",\"reason\":null}},\"urine_test\":{\"status\":\"passed\",\"reason\":null},\"blood_test\":{\"status\":\"passed\",\"reason\":null},\"ecg\":{\"status\":\"passed\",\"reason\":null},\"xray\":{\"status\":\"passed\",\"reason\":null}}', '{\"status\":\"fit\",\"reason\":null}', '', 3, '2025-06-01 09:27:51'),
(2, 6, '{\"height\":{\"status\":\"failed\",\"reason\":\"height does not meet requirement\"},\"weight\":{\"status\":\"passed\",\"reason\":null},\"blood_pressure\":{\"status\":\"passed\",\"reason\":null},\"pulse_rate\":{\"status\":\"passed\",\"reason\":null},\"vision\":{\"left_eye\":{\"status\":\"passed\",\"reason\":null},\"right_eye\":{\"status\":\"passed\",\"reason\":null}},\"urine_test\":{\"status\":\"passed\",\"reason\":null},\"blood_test\":{\"status\":\"passed\",\"reason\":null},\"ecg\":{\"status\":\"passed\",\"reason\":null},\"xray\":{\"status\":\"passed\",\"reason\":null}}', '{\"status\":\"not_fit\",\"reason\":\"Height: height does not meet requirement\"}', '', 3, '2025-06-01 09:52:04'),
(3, 16, '{\"height\":{\"status\":\"passed\",\"reason\":null},\"weight\":{\"status\":\"passed\",\"reason\":null},\"blood_pressure\":{\"status\":\"passed\",\"reason\":null},\"pulse_rate\":{\"status\":\"passed\",\"reason\":null},\"vision\":{\"left_eye\":{\"status\":\"passed\",\"reason\":null},\"right_eye\":{\"status\":\"passed\",\"reason\":null}},\"urine_test\":{\"status\":\"passed\",\"reason\":null},\"blood_test\":{\"status\":\"passed\",\"reason\":null},\"ecg\":{\"status\":\"passed\",\"reason\":null},\"xray\":{\"status\":\"passed\",\"reason\":null}}', '{\"status\":\"fit\",\"reason\":null}', '', 3, '2025-06-01 12:53:48'),
(4, 13, '{\"height\":{\"status\":\"passed\",\"reason\":null},\"weight\":{\"status\":\"passed\",\"reason\":null},\"blood_pressure\":{\"status\":\"passed\",\"reason\":null},\"pulse_rate\":{\"status\":\"passed\",\"reason\":null},\"vision\":{\"left_eye\":{\"status\":\"passed\",\"reason\":null},\"right_eye\":{\"status\":\"passed\",\"reason\":null}},\"urine_test\":{\"status\":\"passed\",\"reason\":null},\"blood_test\":{\"status\":\"passed\",\"reason\":null},\"ecg\":{\"status\":\"passed\",\"reason\":null},\"xray\":{\"status\":\"passed\",\"reason\":null}}', '{\"status\":\"fit\",\"reason\":null}', '', 3, '2025-06-01 14:01:55');

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `officer_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rank` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `assigned_state` varchar(50) NOT NULL,
  `profile_image` varchar(255) DEFAULT 'default_officer.png',
  `role` varchar(20) DEFAULT 'officer',
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `username`, `password`, `rank`, `full_name`, `email`, `phone`, `assigned_state`, `profile_image`, `role`, `last_login`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 'officer1', '$2y$10$rW0t/yzJPB/BNimuifG3Wu/hrz986glgNggeWzXbHAaZ9KVXwPrmC', 'Captain', 'UMAR ABDULLAHI', 'umarfaroukk77@gmail.com', '09033445566', 'Abia', 'default_officer.png', 'officer', '2025-06-02 16:22:18', 'active', NULL, '2025-04-19 16:29:52', '2025-06-02 15:22:18');

-- --------------------------------------------------------

--
-- Table structure for table `physical_assessments`
--

CREATE TABLE `physical_assessments` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`test_results`)),
  `assessment_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`assessment_summary`)),
  `notes` text DEFAULT NULL,
  `assessed_by` int(11) NOT NULL,
  `assessed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `physical_assessments`
--

INSERT INTO `physical_assessments` (`id`, `candidate_id`, `test_results`, `assessment_summary`, `notes`, `assessed_by`, `assessed_at`) VALUES
(1, 9, '{\"race_3_2km\":{\"cage\":\"1\",\"points\":\"10\"},\"individual_obstacle\":{\"grade\":\"B\",\"points\":\"8\",\"notes\":\"\"},\"group_obstacle\":{\"grade\":\"C\",\"points\":\"6\",\"notes\":\"\"},\"rope_climbing\":{\"points\":\"7\",\"notes\":\"\"}}', '{\"total_points\":31,\"max_points\":40,\"percentage\":77.5}', '', 3, '2025-06-01 12:52:59'),
(2, 16, '{\"race_3_2km\":{\"cage\":\"3\",\"points\":\"5\"},\"individual_obstacle\":{\"grade\":\"D\",\"points\":\"4\",\"notes\":\"\"},\"group_obstacle\":{\"grade\":\"B\",\"points\":\"8\",\"notes\":\"\"},\"rope_climbing\":{\"points\":\"5\",\"notes\":\"\"}}', '{\"total_points\":22,\"max_points\":40,\"percentage\":55.00000000000001}', '', 3, '2025-06-01 12:54:17'),
(3, 13, '{\"race_3_2km\":{\"cage\":\"2\",\"points\":\"7\"},\"individual_obstacle\":{\"grade\":\"D\",\"points\":\"4\",\"notes\":\"\"},\"group_obstacle\":{\"grade\":\"C\",\"points\":\"6\",\"notes\":\"\"},\"rope_climbing\":{\"points\":\"8\",\"notes\":\"\"}}', '{\"total_points\":25,\"max_points\":40,\"percentage\":62.5}', '', 3, '2025-06-01 14:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `sand_modelling_assessments`
--

CREATE TABLE `sand_modelling_assessments` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`test_results`)),
  `assessment_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`assessment_summary`)),
  `notes` text DEFAULT NULL,
  `assessed_by` int(11) NOT NULL,
  `assessed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sand_modelling_assessments`
--

INSERT INTO `sand_modelling_assessments` (`id`, `candidate_id`, `test_results`, `assessment_summary`, `notes`, `assessed_by`, `assessed_at`) VALUES
(1, 9, '{\"spatial_awareness\":{\"points\":\"5\"},\"problem_solving\":{\"points\":\"4\"},\"creativity\":{\"points\":\"3\"},\"teamwork\":{\"points\":\"4\"}}', '{\"total_points\":16,\"max_points\":20,\"percentage\":80}', '', 3, '2025-06-01 14:03:57'),
(2, 16, '{\"spatial_awareness\":{\"points\":\"4\"},\"problem_solving\":{\"points\":\"4\"},\"creativity\":{\"points\":\"4\"},\"teamwork\":{\"points\":\"4\"}}', '{\"total_points\":16,\"max_points\":20,\"percentage\":80}', '', 3, '2025-06-01 13:32:09'),
(3, 13, '{\"spatial_awareness\":{\"points\":\"2\"},\"problem_solving\":{\"points\":\"3\"},\"creativity\":{\"points\":\"2\"},\"teamwork\":{\"points\":\"4\"}}', '{\"total_points\":11,\"max_points\":20,\"percentage\":55.00000000000001}', '', 3, '2025-06-01 14:03:04');

-- --------------------------------------------------------

--
-- Table structure for table `screening_forms`
--

CREATE TABLE `screening_forms` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL COMMENT 'Path to the uploaded form file',
  `ocr_text` text DEFAULT NULL COMMENT 'Raw OCR text extracted from the form',
  `processed_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured data extracted from the form' CHECK (json_valid(`processed_data`)),
  `processed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL COMMENT 'Officer ID who processed the form',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screening_forms`
--

INSERT INTO `screening_forms` (`id`, `candidate_id`, `file_path`, `ocr_text`, `processed_data`, `processed_at`, `processed_by`, `created_at`) VALUES
(1, 6, '../uploads/screening_forms/6/screening_form_1748181646_6833228e7158a.JPG', 'OCR processing unavailable: TesseractOCR class not found. Please install the Tesseract OCR library.', '{\"success\":false,\"message\":\"\",\"candidate_info\":[],\"documents\":{\"original\":{\"primary_school_certificate\":false,\"primary_school_testimonial\":false,\"ssce_certificate\":false,\"ssce_testimonial\":false,\"nin_slip\":false,\"bvn_printout\":false,\"certificate_of_indigene\":false,\"jamb_result_slip\":false,\"birth_certificate\":false},\"filled\":{\"acknowledgement_form\":false,\"nda_screening_card\":false,\"letter_of_attestation\":false,\"letter_of_consent\":false}},\"verification_issues\":{\"name_mismatch\":\"No\",\"dob_issue\":\"No Issue\",\"alteration\":\"No\",\"fake_document\":\"No\",\"missing_document\":\"No\"},\"officer_info\":[],\"verification_date\":\"2025-05-25\"}', '2025-05-25 15:00:46', 3, '2025-05-25 11:52:33');

-- --------------------------------------------------------

--
-- Table structure for table `screening_stages`
--

CREATE TABLE `screening_stages` (
  `stage_id` int(11) NOT NULL,
  `stage_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

CREATE TABLE `stages` (
  `id` int(11) NOT NULL,
  `stage_name` enum('documentation','medical','physical','sand_modelling','interview') NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `sequence_number` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stages`
--

INSERT INTO `stages` (`id`, `stage_name`, `display_name`, `description`, `sequence_number`, `is_active`) VALUES
(1, 'documentation', 'Documentation Verification', 'Initial verification of candidate documents and credentials', 1, 1),
(2, 'medical', 'Medical Examination', 'Health assessment and medical fitness evaluation', 2, 1),
(3, 'physical', 'Physical Assessment', 'Physical fitness and endurance testing', 3, 1),
(4, 'sand_modelling', 'Sand Modelling', 'Spatial awareness and creative problem-solving assessment', 4, 1),
(5, 'interview', 'Board Interview', 'Final interview with the selection board', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `state_code` varchar(2) NOT NULL,
  `state_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `state_code`, `state_name`) VALUES
(1, 'AB', 'Abia'),
(2, 'AD', 'Adamawa'),
(3, 'AK', 'Akwa Ibom'),
(4, 'AN', 'Anambra'),
(5, 'BA', 'Bauchi'),
(6, 'BY', 'Bayelsa'),
(7, 'BE', 'Benue'),
(8, 'BO', 'Borno'),
(9, 'CR', 'Cross River'),
(10, 'DE', 'Delta'),
(11, 'EB', 'Ebonyi'),
(12, 'ED', 'Edo'),
(13, 'EK', 'Ekiti'),
(14, 'EN', 'Enugu'),
(15, 'FC', 'FCT'),
(16, 'GO', 'Gombe'),
(17, 'IM', 'Imo'),
(18, 'JI', 'Jigawa'),
(19, 'KD', 'Kaduna'),
(20, 'KN', 'Kano'),
(21, 'KT', 'Katsina'),
(22, 'KE', 'Kebbi'),
(23, 'KO', 'Kogi'),
(24, 'KW', 'Kwara'),
(25, 'LA', 'Lagos'),
(26, 'NA', 'Nasarawa'),
(27, 'NI', 'Niger'),
(28, 'OG', 'Ogun'),
(29, 'ON', 'Ondo'),
(30, 'OS', 'Osun'),
(31, 'OY', 'Oyo'),
(32, 'PL', 'Plateau'),
(33, 'RI', 'Rivers'),
(34, 'SO', 'Sokoto'),
(35, 'TA', 'Taraba'),
(36, 'YO', 'Yobe'),
(37, 'ZA', 'Zamfara');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `user_role` (`user_role`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `board_interview_assessments`
--
ALTER TABLE `board_interview_assessments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidate_id` (`candidate_id`),
  ADD KEY `assessed_by` (`assessed_by`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`candidate_id`),
  ADD UNIQUE KEY `jamb_number` (`jamb_number`),
  ADD UNIQUE KEY `nda_application_number` (`nda_application_number`),
  ADD UNIQUE KEY `chest_number` (`chest_number`),
  ADD KEY `state_id` (`state_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `candidate_documents`
--
ALTER TABLE `candidate_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `candidate_document_type_unique` (`candidate_id`,`document_type`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `candidate_stages`
--
ALTER TABLE `candidate_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidate_stage_unique` (`candidate_id`,`stage_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `officer_id` (`officer_id`),
  ADD KEY `fk_candidate_stages_officer_updated` (`updated_by`),
  ADD KEY `fk_candidate_stages_officer_created` (`created_by`);

--
-- Indexes for table `document_verifications`
--
ALTER TABLE `document_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidate_id` (`candidate_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `flags`
--
ALTER TABLE `flags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `medical_screening`
--
ALTER TABLE `medical_screening`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidate_id` (`candidate_id`),
  ADD KEY `screened_by` (`screened_by`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`officer_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `physical_assessments`
--
ALTER TABLE `physical_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `assessed_by` (`assessed_by`);

--
-- Indexes for table `sand_modelling_assessments`
--
ALTER TABLE `sand_modelling_assessments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `candidate_id` (`candidate_id`),
  ADD KEY `assessed_by` (`assessed_by`);

--
-- Indexes for table `screening_forms`
--
ALTER TABLE `screening_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `screening_stages`
--
ALTER TABLE `screening_stages`
  ADD PRIMARY KEY (`stage_id`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `state_code` (`state_code`),
  ADD UNIQUE KEY `state_name` (`state_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `board_interview_assessments`
--
ALTER TABLE `board_interview_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `candidate_documents`
--
ALTER TABLE `candidate_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidate_stages`
--
ALTER TABLE `candidate_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `document_verifications`
--
ALTER TABLE `document_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `flags`
--
ALTER TABLE `flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_screening`
--
ALTER TABLE `medical_screening`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `officer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `physical_assessments`
--
ALTER TABLE `physical_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sand_modelling_assessments`
--
ALTER TABLE `sand_modelling_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `screening_forms`
--
ALTER TABLE `screening_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `board_interview_assessments`
--
ALTER TABLE `board_interview_assessments`
  ADD CONSTRAINT `board_interview_assessments_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`),
  ADD CONSTRAINT `board_interview_assessments_ibfk_2` FOREIGN KEY (`assessed_by`) REFERENCES `officers` (`officer_id`);

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  ADD CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `candidate_documents`
--
ALTER TABLE `candidate_documents`
  ADD CONSTRAINT `candidate_documents_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL;

--
-- Constraints for table `candidate_stages`
--
ALTER TABLE `candidate_stages`
  ADD CONSTRAINT `candidate_stages_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_stages_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_stages_ibfk_3` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_candidate_stages_officer_created` FOREIGN KEY (`created_by`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_candidate_stages_officer_updated` FOREIGN KEY (`updated_by`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL;

--
-- Constraints for table `document_verifications`
--
ALTER TABLE `document_verifications`
  ADD CONSTRAINT `fk_document_verifications_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_document_verifications_officer` FOREIGN KEY (`verified_by`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `flags`
--
ALTER TABLE `flags`
  ADD CONSTRAINT `flags_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_screening`
--
ALTER TABLE `medical_screening`
  ADD CONSTRAINT `medical_screening_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_screening_ibfk_2` FOREIGN KEY (`screened_by`) REFERENCES `officers` (`officer_id`) ON DELETE CASCADE;

--
-- Constraints for table `officers`
--
ALTER TABLE `officers`
  ADD CONSTRAINT `officers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `physical_assessments`
--
ALTER TABLE `physical_assessments`
  ADD CONSTRAINT `physical_assessments_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`),
  ADD CONSTRAINT `physical_assessments_ibfk_2` FOREIGN KEY (`assessed_by`) REFERENCES `officers` (`officer_id`);

--
-- Constraints for table `sand_modelling_assessments`
--
ALTER TABLE `sand_modelling_assessments`
  ADD CONSTRAINT `sand_modelling_assessments_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`),
  ADD CONSTRAINT `sand_modelling_assessments_ibfk_2` FOREIGN KEY (`assessed_by`) REFERENCES `officers` (`officer_id`);

--
-- Constraints for table `screening_forms`
--
ALTER TABLE `screening_forms`
  ADD CONSTRAINT `fk_screening_forms_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_screening_forms_officer` FOREIGN KEY (`processed_by`) REFERENCES `officers` (`officer_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
