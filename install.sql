-- =====================================================
-- ระบบลงทะเบียนชุมนุม โรงเรียนแก่นนครวิทยาลัย
-- install.sql — สร้างฐานข้อมูลและตารางเปล่า + admin เริ่มต้น
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- =====================================================
-- สร้างฐานข้อมูล
-- =====================================================
CREATE DATABASE IF NOT EXISTS `knwacth_club`
  CHARACTER SET utf8mb3
  COLLATE utf8mb3_general_ci;

USE `knwacth_club`;

-- =====================================================
-- ตาราง admins — ผู้ดูแลระบบ
-- =====================================================
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสผู้ดูแลระบบ',
  `username` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้',
  `password` varchar(255) NOT NULL COMMENT 'รหัสผ่าน (เข้ารหัสแล้ว)',
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ตาราง teachers — ข้อมูลครู
-- =====================================================
CREATE TABLE `teachers` (
  `teacher_id` varchar(20) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `teacher_code` varchar(50) DEFAULT NULL,
  `telephon` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ตาราง clubs — ข้อมูลชุมนุม
-- =====================================================
CREATE TABLE `clubs` (
  `club_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'รหัสชุมนุม',
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
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`club_id`),
  UNIQUE KEY `club_name` (`club_name`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ตาราง students — ข้อมูลนักเรียน
-- =====================================================
CREATE TABLE `students` (
  `student_id` varchar(10) NOT NULL COMMENT 'เลขประจำตัวนักเรียน',
  `id_card` varchar(13) NOT NULL COMMENT 'เลขบัตรประชาชน',
  `firstname` varchar(100) NOT NULL COMMENT 'ชื่อ',
  `lastname` varchar(100) NOT NULL COMMENT 'นามสกุล',
  `grade_level` enum('ม.1','ม.2','ม.3','ม.4','ม.5','ม.6') NOT NULL COMMENT 'ระดับชั้น',
  `class_room` int(11) NOT NULL COMMENT 'ห้อง',
  `class_number` int(11) NOT NULL COMMENT 'เลขที่',
  `selection_status` tinyint(1) DEFAULT 0 COMMENT 'สถานะการเลือกชุมนุม (1 = เลือกแล้ว, 0 = ยังไม่เลือก)',
  `club_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `id_card` (`id_card`),
  KEY `fk_students_clubs` (`club_id`),
  CONSTRAINT `fk_students_clubs` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`club_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ตาราง logs — บันทึกการใช้งาน
-- =====================================================
CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `user_type` enum('student','teacher','admin') NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ตาราง system_settings — การตั้งค่าระบบ
-- =====================================================
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- =====================================================
-- ข้อมูลเริ่มต้น: ผู้ดูแลระบบ
-- username : admin
-- password : admin2026  (เข้ารหัสด้วย bcrypt)
-- =====================================================
INSERT INTO `admins` (`username`, `password`) VALUES
('admin', '$2y$10$Li8BXusb1hiE/fUufFemA.iMdAmGtUJQlYAtCgnkUmwW.36UUNgxG');

-- =====================================================
-- ข้อมูลเริ่มต้น: การตั้งค่าระบบ
-- =====================================================
INSERT INTO `system_settings` (`setting_name`, `setting_value`) VALUES
('registration_open', 'false'),
('academic_year',     '2569'),
('semester',          '1'),
('teacher_edit_start', ''),
('teacher_edit_end',   ''),
('registration_start', ''),
('registration_end',   '');

COMMIT;
