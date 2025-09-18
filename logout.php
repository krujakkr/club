<?php
require_once 'config.php';

// บันทึกประวัติการออกจากระบบ
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    logActivity($user_id, $user_type, 'logout', 'ออกจากระบบ');
}

// ล้างข้อมูล session ทั้งหมด
session_unset();
session_destroy();

// เปลี่ยนเส้นทางไปยังหน้าหลัก
header("Location: index.php");
exit;
?>