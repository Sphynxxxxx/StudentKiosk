-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2025 at 05:43 PM
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
-- Database: `isatu_kiosk_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year_start` year(4) NOT NULL,
  `year_end` year(4) NOT NULL,
  `semester` enum('1st','2nd','summer') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_start`, `year_end`, `semester`, `is_active`, `created_at`) VALUES
(3, '2025', '2026', '1st', 1, '2025-09-26 07:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `class_sections`
--

CREATE TABLE `class_sections` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `section_name` varchar(50) NOT NULL,
  `schedule` varchar(100) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `max_students` int(11) DEFAULT 40,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_sections`
--

INSERT INTO `class_sections` (`id`, `subject_id`, `faculty_id`, `academic_year_id`, `section_id`, `section_name`, `schedule`, `room`, `max_students`, `status`, `created_at`) VALUES
(25, 32, 70, 3, 66, 'A', '', '', 40, 'active', '2025-09-26 08:37:33'),
(29, 32, 70, 3, 65, 'B', '', '', 40, 'active', '2025-09-27 17:57:28'),
(30, 36, 81, 3, 65, 'B', '', '', 40, 'active', '2025-09-30 12:41:14'),
(31, 37, 70, 3, 65, 'B', '', '', 40, 'active', '2025-09-30 17:18:15'),
(32, 38, 81, 3, 65, 'B', '', '', 40, 'active', '2025-09-30 17:43:54'),
(33, 39, 70, 3, 65, 'B', '', '', 40, 'active', '2025-10-03 18:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `head_faculty_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `head_faculty_id`, `created_at`, `status`) VALUES
(12, 'CCI', '001', '', 70, '2025-09-26 07:23:22', 'active'),
(13, 'CAS', '002', '', NULL, '2025-09-27 19:25:59', 'active'),
(16, 'CIT', '003', '', NULL, '2025-09-27 19:31:10', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `sender_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_at`, `status`, `created_at`) VALUES
(1, NULL, 'larrydenver.biaco@students.isatu.edu.ph', 'Larry  Denver', 'Congratulations on Your Academic Achievement!', 'Dear Student,\r\n\r\nCongratulations on your outstanding academic performance! Your dedication and hard work have truly paid off. Keep up the excellent work!\r\n\r\nBest regards,\r\n[Your Name]', '2025-10-01 02:10:07', 'failed', '2025-09-30 18:10:07'),
(2, NULL, 'larrydenver.biaco@students.isatu.edu.ph', 'Larry  Denver', 'Congratulations on Your Academic Achievement!', 'Dear Student,\r\n\r\nCongratulations on your outstanding academic performance! Your dedication and hard work have truly paid off. Keep up the excellent work!\r\n\r\nBest regards,\r\n[Your Name]', '2025-10-01 02:12:09', 'failed', '2025-09-30 18:12:09'),
(3, NULL, 'larrydenver.biaco@students.isatu.edu.ph', 'Larry  Denver', 'Congratulations on Your Academic Achievement!', 'Dear Student,\r\n\r\nCongratulations on your outstanding academic performance! Your dedication and hard work have truly paid off. Keep up the excellent work!\r\n\r\nBest regards,\r\n[Your Name]', '2025-10-01 02:15:49', 'failed', '2025-09-30 18:15:49'),
(4, NULL, 'larrydenver.biaco@students.isatu.edu.ph', 'Larry  Denver', 'Congratulations on Your Academic Achievement!', 'Dear Student,\r\n\r\nCongratulations on your outstanding academic performance! Your dedication and hard work have truly paid off. Keep up the excellent work!\r\n\r\nBest regards,\r\n[Your Name]', '2025-10-01 02:17:53', 'sent', '2025-09-30 18:17:53');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_section_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','enrolled','dropped','completed','rejected') DEFAULT 'pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `class_section_id`, `enrollment_date`, `status`, `updated_at`) VALUES
(67, 80, 29, '2025-09-27 17:57:39', 'enrolled', '2025-09-27 18:28:48'),
(68, 80, 30, '2025-09-30 12:41:52', 'enrolled', '2025-09-30 13:12:26'),
(69, 80, 31, '2025-09-30 17:18:24', 'enrolled', '2025-09-30 17:18:32'),
(70, 80, 32, '2025-09-30 17:44:02', 'enrolled', '2025-09-30 17:44:30'),
(71, 80, 33, '2025-10-03 18:16:19', 'enrolled', '2025-10-03 18:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_logs`
--

CREATE TABLE `enrollment_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `confirmed_by` int(11) NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_logs`
--

INSERT INTO `enrollment_logs` (`id`, `student_id`, `action_type`, `confirmed_by`, `action_date`, `notes`, `created_at`) VALUES
(9, 80, 'bulk_enrollment_approved', 80, '2025-09-27 17:45:41', 'Bulk approved 1 enrollments', '2025-09-27 17:45:41'),
(10, 80, 'enrollment_approved', 80, '2025-09-27 17:47:42', 'Approved 1 enrollments', '2025-09-27 17:47:42'),
(11, 80, 'enrollment_approved', 80, '2025-09-27 17:48:12', 'Approved 1 enrollments', '2025-09-27 17:48:12'),
(12, 80, 'enrollment_approved', 80, '2025-09-27 17:58:45', 'Approved 1 enrollments', '2025-09-27 17:58:45'),
(13, 80, 'enrollment_approved', 80, '2025-09-27 18:01:54', 'Approved 1 enrollments', '2025-09-27 18:01:54'),
(14, 80, 'enrollment_approved', 80, '2025-09-27 18:03:45', 'Approved 1 enrollments', '2025-09-27 18:03:45'),
(15, 80, 'enrollment_approved', 80, '2025-09-27 18:06:00', 'Approved 1 enrollments', '2025-09-27 18:06:00'),
(16, 80, 'enrollment_approved', 80, '2025-09-27 18:08:35', 'Approved 1 enrollments', '2025-09-27 18:08:35'),
(17, 80, 'enrollment_approved', 80, '2025-09-27 18:13:40', 'Approved 1 enrollments', '2025-09-27 18:13:40'),
(18, 80, 'enrollment_approved', 80, '2025-09-27 18:18:43', 'Approved 1 enrollments', '2025-09-27 18:18:43'),
(19, 80, 'enrollment_approved', 80, '2025-09-27 18:23:48', 'Approved 1 enrollments', '2025-09-27 18:23:48'),
(20, 80, 'enrollment_approved', 80, '2025-09-27 18:28:52', 'Approved 1 enrollments', '2025-09-27 18:28:52'),
(21, 80, 'enrollment_approved', 80, '2025-09-30 12:42:07', 'Approved 1 enrollments', '2025-09-30 12:42:07'),
(22, 80, 'enrollment_approved', 80, '2025-09-30 12:47:11', 'Approved 1 enrollments', '2025-09-30 12:47:11'),
(23, 80, 'enrollment_approved', 80, '2025-09-30 12:52:15', 'Approved 1 enrollments', '2025-09-30 12:52:15'),
(24, 80, 'enrollment_approved', 80, '2025-09-30 12:57:18', 'Approved 1 enrollments', '2025-09-30 12:57:18'),
(25, 80, 'enrollment_approved', 80, '2025-09-30 13:02:22', 'Approved 1 enrollments', '2025-09-30 13:02:22'),
(26, 80, 'enrollment_approved', 80, '2025-09-30 13:07:26', 'Approved 1 enrollments', '2025-09-30 13:07:26'),
(27, 80, 'enrollment_approved', 80, '2025-09-30 13:12:31', 'Approved 1 enrollments', '2025-09-30 13:12:31'),
(28, 80, 'enrollment_approved', 80, '2025-09-30 17:18:36', 'Approved 1 enrollments', '2025-09-30 17:18:36'),
(29, 80, 'enrollment_approved', 80, '2025-09-30 17:44:33', 'Approved 1 enrollments', '2025-09-30 17:44:33'),
(30, 80, 'enrollment_approved', 80, '2025-10-03 18:16:36', 'Approved 1 enrollments', '2025-10-03 18:16:36'),
(31, 80, 'enrollment_approved', 80, '2025-10-03 18:21:40', 'Approved 1 enrollments', '2025-10-03 18:21:40');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_profiles`
--

CREATE TABLE `faculty_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` enum('Professor','Associate Professor','Assistant Professor','Instructor','Lecturer') DEFAULT 'Instructor',
  `employment_type` enum('Full-time','Part-time','Contractual') DEFAULT 'Full-time',
  `specialization` varchar(255) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `consultation_hours` text DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_profiles`
--

INSERT INTO `faculty_profiles` (`id`, `user_id`, `department_id`, `position`, `employment_type`, `specialization`, `education`, `phone`, `office_location`, `consultation_hours`, `hire_date`, `biography`, `created_at`, `updated_at`) VALUES
(7, 70, NULL, 'Instructor', 'Full-time', '', NULL, '+63 091 657 8908', NULL, '', '2025-09-26', NULL, '2025-09-25 16:30:48', '2025-09-25 16:30:48'),
(8, 81, 12, 'Instructor', 'Full-time', '', NULL, '09123456789', NULL, '', '2025-09-30', NULL, '2025-09-30 12:40:00', '2025-09-30 12:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `midterm_grade` varchar(10) DEFAULT NULL COMMENT 'Can be numeric (1.00-5.00), INC (Incomplete), or DRP (Dropped)',
  `final_grade` varchar(10) DEFAULT NULL COMMENT 'Can be numeric (1.00-5.00), INC (Incomplete), or DRP (Dropped)',
  `overall_grade` decimal(3,2) DEFAULT NULL,
  `letter_grade` varchar(10) DEFAULT NULL COMMENT 'Letter grade: A, B, C, D, E, F, INC, or DRP',
  `remarks` varchar(50) DEFAULT NULL COMMENT 'Remarks: Excellent, Very Good, Good, Satisfactory, Passed, Failed, Incomplete, or Dropped',
  `graded_by` int(11) NOT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `enrollment_id`, `midterm_grade`, `final_grade`, `overall_grade`, `letter_grade`, `remarks`, `graded_by`, `graded_at`, `updated_at`) VALUES
(29, 67, '1', '1.5', 1.25, 'A', 'Excellent', 70, '2025-10-06 14:22:47', '2025-10-06 14:33:41'),
(30, 69, 'INC', 'INC', NULL, 'INC', 'Incomplete', 70, '2025-10-06 14:22:47', '2025-10-06 14:33:41'),
(31, 71, NULL, NULL, NULL, NULL, NULL, 70, '2025-10-06 14:22:47', '2025-10-06 14:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `grade_appeals`
--

CREATE TABLE `grade_appeals` (
  `id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_appeals`
--

INSERT INTO `grade_appeals` (`id`, `grade_id`, `student_id`, `reason`, `status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `admin_remarks`) VALUES
(6, 29, 80, 'Done', 'rejected', '2025-10-06 15:30:29', NULL, '2025-10-06 15:38:50', '');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `degree_type` enum('bachelor','master','doctorate','certificate') DEFAULT 'bachelor',
  `duration_years` int(11) DEFAULT 4,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `program_code`, `program_name`, `department_id`, `degree_type`, `duration_years`, `description`, `status`, `created_at`) VALUES
(13, '001', 'BSCS', 12, 'bachelor', 4, '', 'active', '2025-09-26 07:23:33'),
(14, '002', 'BSIS', 12, 'bachelor', 4, '', 'active', '2025-09-26 07:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `report_type` enum('grades','enrollment','faculty_performance','student_ranking') NOT NULL,
  `title` varchar(200) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `file_path` varchar(500) DEFAULT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `year_level` enum('1st','2nd','3rd','4th','5th') NOT NULL,
  `program_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `max_students` int(11) DEFAULT 40,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `year_level`, `program_id`, `academic_year_id`, `adviser_id`, `max_students`, `status`, `created_at`) VALUES
(65, 'B', '4th', 13, 3, 70, 40, 'active', '2025-09-26 08:33:02'),
(66, 'A', '4th', 13, 3, NULL, 40, 'active', '2025-09-26 08:37:18');

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `year_level` enum('1st','2nd','3rd','4th','5th') NOT NULL,
  `program_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) NOT NULL,
  `admission_date` date NOT NULL,
  `student_type` enum('regular','irregular','transferee','returning') DEFAULT 'regular',
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `student_status` enum('regular','irregular','probation','suspended','graduated','dropped','transferred') DEFAULT 'regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `user_id`, `year_level`, `program_id`, `section_id`, `academic_year_id`, `admission_date`, `student_type`, `guardian_name`, `guardian_contact`, `created_at`, `updated_at`, `student_status`) VALUES
(16, 80, '4th', 13, 65, 3, '2025-09-27', 'regular', '', '', '2025-09-27 17:43:56', '2025-09-27 17:43:56', 'regular');

-- --------------------------------------------------------

--
-- Table structure for table `student_rankings`
--

CREATE TABLE `student_rankings` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `gpa` decimal(3,2) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `total_students` int(11) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `credits` int(11) NOT NULL DEFAULT 3,
  `department_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `course_code`, `subject_name`, `description`, `credits`, `department_id`, `created_at`, `status`) VALUES
(32, 'CS101', 'Introduction of Programming', '', 3, 12, '2025-09-26 07:24:06', 'active'),
(33, 'CS102', 'Industrial Safety', '', 3, 12, '2025-09-26 07:25:28', 'active'),
(36, 'CS103', 'Parallel Programming', '', 3, 12, '2025-09-30 12:40:59', 'active'),
(37, 'CS105', 'gvdhfvw er', '', 3, 12, '2025-09-30 17:18:00', 'active'),
(38, 'CS106', 'fgfffffffffffffff', '', 3, 12, '2025-09-30 17:43:44', 'active'),
(39, 'CS108', 'fsdfsdfsd', '', 3, 12, '2025-10-03 18:15:50', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('administrator','faculty','student') NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `student_id` varchar(20) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `student_id`, `employee_id`, `birth_date`, `gender`, `address`, `contact_number`, `emergency_contact`, `emergency_phone`, `profile_image`, `created_at`, `updated_at`) VALUES
(70, 'jdelacruz', 'larrydenverbiaco@gmail.com', '$2y$10$GHhfCVOP2YlxRYmCy9rBBOpnkmwn/iCC4TzTooUMdmWB3L9.B4j0S', 'juan', 'dela cruz', 'faculty', 'active', NULL, '2021-1863-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-25 16:30:48', '2025-09-25 16:30:48'),
(80, 'ldenver', 'larrydenver.biaco@students.isatu.edu.ph', '$2y$10$fRPX2qj412rJMLA4JNnGaOlbqXOhFiZoNOkESfGr1pY1EirJYnrMq', 'Larry ', 'Denver', 'student', 'active', '2021-1234-A', NULL, '2000-09-07', 'male', '', '09165789087', '', '', NULL, '2025-09-27 17:43:56', '2025-09-27 17:43:56'),
(81, 'rmarie', 'rosemarie@gmail.com', '$2y$10$7d2CXJwFuIu/u.hyZUwEKOAqkKYsc.irLnkDQ..IVCNx8AfS7UEpW', 'Rose', 'Marie', 'faculty', 'active', NULL, '2021-1235-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-30 12:40:00', '2025-09-30 12:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(13, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 07:31:38'),
(15, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 17:59:51'),
(16, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 18:23:57'),
(17, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:12:39'),
(18, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:21:08'),
(19, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:22:40'),
(20, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:24:31'),
(21, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:30:54'),
(22, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:33:44'),
(23, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 19:36:07'),
(24, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 16:33:09'),
(25, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-26 07:42:56'),
(26, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-26 08:35:35'),
(27, NULL, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 16:01:06'),
(28, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 17:44:14'),
(29, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:38:18'),
(30, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:41:31'),
(31, 81, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-09-30 12:42:45'),
(32, 81, 'grade_update', 'Updated grades for student ID 80, subject CS103, enrollment ID 68', '::1', NULL, '2025-09-30 12:43:14'),
(33, 81, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-09-30 12:43:16'),
(34, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 14:06:39'),
(35, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-09-30 17:19:15'),
(36, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-09-30 17:19:15'),
(37, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-09-30 17:19:17'),
(38, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 17:19:29'),
(39, 81, 'grade_update', 'Updated grades for student ID 80, subject CS103, enrollment ID 68', '::1', NULL, '2025-09-30 17:45:53'),
(40, 81, 'grade_update', 'Updated grades for student ID 80, subject CS106, enrollment ID 70', '::1', NULL, '2025-09-30 17:45:53'),
(41, 81, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-09-30 17:45:56'),
(42, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 17:46:02'),
(43, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-03 18:15:31'),
(46, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:14:10'),
(47, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:14:16'),
(48, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:14:21'),
(49, 80, 'login', 'Student logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-06 14:14:32'),
(50, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:14:42'),
(53, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:14:58'),
(54, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:15:02'),
(57, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:16:06'),
(58, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:16:06'),
(59, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:16:06'),
(60, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:16:11'),
(61, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:16:11'),
(62, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:16:11'),
(63, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:16:24'),
(64, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:16:24'),
(65, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:16:24'),
(66, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:16:31'),
(67, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:21:29'),
(68, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:21:38'),
(69, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:21:47'),
(70, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:22:06'),
(71, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:22:42'),
(72, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:22:47'),
(73, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:22:47'),
(74, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:22:47'),
(75, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:22:53'),
(76, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:33:08'),
(77, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:33:26'),
(78, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:33:26'),
(79, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:33:26'),
(80, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:33:27'),
(81, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:33:37'),
(82, 70, 'grade_update', 'Updated grades for student ID 80, subject CS101, enrollment ID 67', '::1', NULL, '2025-10-06 14:33:41'),
(83, 70, 'grade_update', 'Updated grades for student ID 80, subject CS105, enrollment ID 69', '::1', NULL, '2025-10-06 14:33:41'),
(84, 70, 'grade_update', 'Updated grades for student ID 80, subject CS108, enrollment ID 71', '::1', NULL, '2025-10-06 14:33:41'),
(85, 70, 'final_grades_submit', 'Submitted final grades for student ID 80', '::1', NULL, '2025-10-06 14:33:44'),
(86, 80, 'grade_appeal_submitted', 'Submitted grade appeal for grade ID 29', '::1', NULL, '2025-10-06 15:20:27'),
(87, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:20:43'),
(88, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:20:50'),
(89, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:20:55'),
(90, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:21:55'),
(91, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:21:58'),
(92, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:22:04'),
(93, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:22:06'),
(94, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:23:32'),
(95, 80, 'grade_appeal_submitted', 'Submitted grade appeal for grade ID 29', '::1', NULL, '2025-10-06 15:25:10'),
(96, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:25:15'),
(97, NULL, 'appeal_review', 'Updated appeal ID 4 status to approved', '::1', NULL, '2025-10-06 15:26:34'),
(98, NULL, 'appeal_review', 'Updated appeal ID 5 status to approved', '::1', NULL, '2025-10-06 15:28:17'),
(99, 80, 'grade_appeal_submitted', 'Submitted grade appeal for grade ID 29', '::1', NULL, '2025-10-06 15:30:29'),
(100, NULL, 'appeal_review', 'Updated appeal ID 6 status to approved', '::1', NULL, '2025-10-06 15:31:02'),
(101, NULL, 'appeal_review', 'Updated appeal ID 6 status to rejected', '::1', NULL, '2025-10-06 15:31:30'),
(102, NULL, 'appeal_review', 'Updated appeal ID 6 status to approved', '::1', NULL, '2025-10-06 15:34:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_sections`
--
ALTER TABLE `class_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `section_id_fk` (`section_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `head_faculty_id` (`head_faculty_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_recipient` (`recipient_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`class_section_id`),
  ADD KEY `class_section_id` (`class_section_id`);

--
-- Indexes for table `enrollment_logs`
--
ALTER TABLE `enrollment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_confirmed_by` (`confirmed_by`);

--
-- Indexes for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `graded_by` (`graded_by`);

--
-- Indexes for table `grade_appeals`
--
ALTER TABLE `grade_appeals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `adviser_id` (`adviser_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `student_rankings`
--
ALTER TABLE `student_rankings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_year` (`student_id`,`academic_year_id`),
  ADD KEY `academic_year_id` (`academic_year_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `class_sections`
--
ALTER TABLE `class_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `enrollment_logs`
--
ALTER TABLE `enrollment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `grade_appeals`
--
ALTER TABLE `grade_appeals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `student_rankings`
--
ALTER TABLE `student_rankings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `class_sections`
--
ALTER TABLE `class_sections`
  ADD CONSTRAINT `class_sections_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_sections_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_sections_ibfk_3` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_sections_ibfk_4` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`head_faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`class_section_id`) REFERENCES `class_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollment_logs`
--
ALTER TABLE `enrollment_logs`
  ADD CONSTRAINT `enrollment_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_logs_ibfk_2` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_profiles`
--
ALTER TABLE `faculty_profiles`
  ADD CONSTRAINT `faculty_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `faculty_profiles_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grade_appeals`
--
ALTER TABLE `grade_appeals`
  ADD CONSTRAINT `grade_appeals_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grade_appeals_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grade_appeals_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sections_ibfk_3` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_profiles_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_profiles_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_profiles_ibfk_4` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_rankings`
--
ALTER TABLE `student_rankings`
  ADD CONSTRAINT `student_rankings_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_rankings_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
