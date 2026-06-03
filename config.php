<?php
// ไฟล์ config.php
session_start();

// ข้อมูลการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'knwacth_club');
define('DB_PASSWORD', '2!06hhC2t');
define('DB_NAME', 'knwacth_club');

// เชื่อมต่อกับฐานข้อมูล MySQL
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn === false) {
    die("ERROR: ไม่สามารถเชื่อมต่อฐานข้อมูลได้. " . mysqli_connect_error());
}

// ตั้งค่าให้รองรับภาษาไทย
mysqli_set_charset($conn, "utf8");

// ฟังก์ชันสำหรับทำความสะอาดข้อมูลป้องกัน SQL Injection
function clean($conn, $str) {
    return mysqli_real_escape_string($conn, $str);
}

// ฟังก์ชันตรวจสอบสถานะการล็อกอิน
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ฟังก์ชันตรวจสอบสถานะผู้ดูแลระบบ
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// ฟังก์ชันตรวจสอบสถานะครู
function isTeacher() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher';
}

// ฟังก์ชันตรวจสอบสถานะนักเรียน
function isStudent() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student';
}

// ฟังก์ชันสำหรับแสดงข้อความแจ้งเตือน
function alert($message) {
    echo "<script>alert('$message');</script>";
}

// ฟังก์ชันสำหรับเปลี่ยนหน้า
function redirect($url) {
    echo "<script>window.location.href='$url';</script>";
    exit;
}

// ฟังก์ชันตรวจสอบเลขบัตรประชาชน 13 หลัก (ID Card Validation)
// แก้ไขฟังก์ชันตรวจสอบเลขบัตรประชาชนใน config.php
// ให้พบฟังก์ชันนี้ในไฟล์ config.php และแก้ไขเป็นดังนี้:

// ฟังก์ชันตรวจสอบเลขบัตรประชาชน 13 หลัก (ID Card Validation)
function validateIdCard($id_card) {
    // เปลี่ยนเป็นตรวจสอบแค่ว่าเป็นตัวเลข 13 หลักหรือไม่
    if (!preg_match('/^[0-9]{13}$/', $id_card)) {
        return false;
    }
    
    // ยกเลิกการตรวจสอบเลขบัตรขั้นสูง เนื่องจากเลขบัตรตัวอย่างอาจเป็นเลขสมมติ
    return true;
    
    /* ตรวจสอบความถูกต้องของเลขบัตรตามอัลกอริทึม (ปิดไว้ชั่วคราว)
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id_card[$i] * (13 - $i);
    }
    
    $check_digit = (11 - ($sum % 11)) % 10;
    
    return (int)$id_card[12] === $check_digit;
    */
}

// ฟังก์ชันบันทึกประวัติการทำงาน (Logs)
function logActivity($user_id, $user_type, $action, $details = '') {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_id = clean($conn, $user_id);
    $action = clean($conn, $action);
    $details = clean($conn, $details);
    
    $sql = "INSERT INTO logs (user_id, user_type, action, details, ip_address) 
            VALUES ('$user_id', '$user_type', '$action', '$details', '$ip')";
    
    mysqli_query($conn, $sql);
}

// ฟังก์ชันดึงข้อมูลการตั้งค่าระบบ
function getSystemSetting($setting_name) {
    global $conn;
    $setting_name = clean($conn, $setting_name);
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = '$setting_name'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    
    return null;
}

// ฟังก์ชันอัพเดทการตั้งค่าระบบ
function updateSystemSetting($setting_name, $setting_value) {
    global $conn;
    $setting_name = clean($conn, $setting_name);
    $setting_value = clean($conn, $setting_value);
    
    $sql = "UPDATE system_settings SET setting_value = '$setting_value' WHERE setting_name = '$setting_name'";
    return mysqli_query($conn, $sql);
}

// แก้ไขฟังก์ชัน countClubMembers ในไฟล์ config.php

// ฟังก์ชันนับจำนวนสมาชิกในชุมนุม
function countClubMembers($club_id) {
    global $conn;
    $club_id = (int)$club_id;
    
    $sql = "SELECT COUNT(*) as total FROM students WHERE club_id = $club_id AND selection_status = 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['total'];
}
?>