<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ดึงสถานะปัจจุบันของระบบ
$current_status = getSystemSetting('registration_open');

// สลับสถานะ
$new_status = ($current_status === 'true') ? 'false' : 'true';

// อัพเดทสถานะในฐานข้อมูล
if (updateSystemSetting('registration_open', $new_status)) {
    // บันทึกประวัติการเปิด/ปิดระบบ
    $action_text = ($new_status === 'true') ? 'เปิดระบบลงทะเบียน' : 'ปิดระบบลงทะเบียน';
    logActivity($_SESSION['user_id'], 'admin', 'toggle_system', $action_text);
    
    alert($action_text . "เรียบร้อยแล้ว");
} else {
    alert("เกิดข้อผิดพลาดในการอัพเดทสถานะระบบ");
}

// กลับไปที่หน้าแอดมิน
redirect("admin.php");
?>