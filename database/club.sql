-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2025 at 10:12 AM
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
-- Database: `club`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL COMMENT 'รหัสผู้ดูแลระบบ',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (เข้ารหัสแล้ว)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`) VALUES
(2, 'admin', '$2y$10$tsgrLICaxL2DOlkGptcvM.gObnR1idvB97GJEK3HH4Jums/0.rt5u');

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `club_id` int(11) NOT NULL COMMENT 'รหัสชุมนุม',
  `club_name` varchar(100) NOT NULL COMMENT 'ชื่อชุมนุม',
  `description` longtext DEFAULT NULL COMMENT 'คำอธิบายชุมนุม',
  `location` varchar(100) DEFAULT NULL COMMENT 'สถานที่เรียน',
  `max_members` int(11) NOT NULL COMMENT 'จำนวนสมาชิกสูงสุดที่รับ',
  `teacher_id` int(11) DEFAULT NULL COMMENT 'รหัสครูที่ดูแลชุมนุม',
  `allow_m1` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.1 เลือก',
  `allow_m2` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.2 เลือก',
  `allow_m3` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.3 เลือก',
  `allow_m4` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.4 เลือก',
  `allow_m5` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.5 เลือก',
  `allow_m6` tinyint(1) DEFAULT 1 COMMENT 'อนุญาตให้ ม.6 เลือก',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `user_type` enum('student','teacher','admin') NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(10) NOT NULL COMMENT 'เลขประจำตัวนักเรียน',
  `id_card` varchar(13) NOT NULL COMMENT 'เลขบัตรประชาชน',
  `firstname` varchar(100) NOT NULL COMMENT 'ชื่อ',
  `lastname` varchar(100) NOT NULL COMMENT 'นามสกุล',
  `grade_level` enum('ม.1','ม.2','ม.3','ม.4','ม.5','ม.6') NOT NULL COMMENT 'ระดับชั้น',
  `class_room` int(11) NOT NULL COMMENT 'ห้อง',
  `class_number` int(11) NOT NULL COMMENT 'เลขที่',
  `selection_status` tinyint(1) DEFAULT 0 COMMENT 'สถานะการเลือกชุมนุม (true = เลือกแล้ว, false = ยังไม่เลือก)',
  `club_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'registration_open', 'false', '2025-03-30 05:15:01', '2025-06-04 06:27:36'),
(2, 'academic_year', '2024', '2025-03-30 05:15:01', '2025-05-16 04:53:07'),
(3, 'semester', '1', '2025-03-30 05:15:01', '2025-05-16 04:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` varchar(20) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `teacher_code` varchar(50) DEFAULT NULL,
  `telephon` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers_backup`
--

CREATE TABLE `teachers_backup` (
  `teacher_id` int(11) NOT NULL COMMENT 'รหัสครู',
  `firstname` varchar(100) NOT NULL COMMENT 'ชื่อครู',
  `lastname` varchar(100) NOT NULL COMMENT 'นามสกุลครู',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้สำหรับเข้าระบบ',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (เข้ารหัสแล้ว)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `teachers_backup`
--

INSERT INTO `teachers_backup` (`teacher_id`, `firstname`, `lastname`, `username`, `password`) VALUES
(1, 'วิชัย', 'รักการสอน', 'teacher1', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(2, 'สมศรี', 'ใจดี', 'teacher2', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(3, 'ประสิทธิ์', 'เก่งกล้า', 'teacher3', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(4, 'มาลี', 'วิชาการ', 'teacher4', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(5, 'สมชาย', 'เทคโนโลยี', 'teacher5', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(6, 'แสงดาว', 'พัฒนา', 'teacher6', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(7, 'วีระ', 'ศิลปะดี', 'teacher7', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO'),
(8, 'นันทนา', 'ดนตรีเลิศ', 'teacher8', '$2y$10$4E4ODzJeXrXiMVOGkRY1HeDbz8FYXLHxjmwPLHMBkLjvcJmNiDJAO');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`club_id`),
  ADD UNIQUE KEY `club_name` (`club_name`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `id_card` (`id_card`),
  ADD KEY `fk_students_clubs` (`club_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`);

--
-- Indexes for table `teachers_backup`
--
ALTER TABLE `teachers_backup`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสผู้ดูแลระบบ', AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `club_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสชุมนุม', AUTO_INCREMENT=814;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teachers_backup`
--
ALTER TABLE `teachers_backup`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสครู', AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_clubs` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`club_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
