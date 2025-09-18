<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
    exit; // เพิ่ม exit หลัง redirect เพื่อหยุดการทำงานของสคริปต์
}

// ตรวจสอบว่ามีการส่ง club_id มาหรือไม่
if (isset($_GET['club_id'])) {
    $club_id = mysqli_real_escape_string($conn, $_GET['club_id']);
    
    // ดึงข้อมูลสถานะการล็อกชุมนุมปัจจุบัน
    $check_sql = "SELECT is_locked FROM clubs WHERE club_id = '$club_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $club = mysqli_fetch_assoc($check_result);
        $current_status = $club['is_locked'];
        
        // สลับค่าสถานะการล็อก
        $new_status = $current_status ? 0 : 1;
        
        // อัพเดทสถานะการล็อก
        $update_sql = "UPDATE clubs SET is_locked = '$new_status' WHERE club_id = '$club_id'";
        if (mysqli_query($conn, $update_sql)) {
            // บันทึกประวัติการอัพเดท (optional)
            // ตรวจสอบว่ามีตาราง activity_logs หรือไม่ก่อนบันทึก
            $table_exists_query = "SHOW TABLES LIKE 'activity_logs'";
            $table_exists_result = mysqli_query($conn, $table_exists_query);
            
            if ($table_exists_result && mysqli_num_rows($table_exists_result) > 0) {
                $admin_username = $_SESSION['username'] ?? 'admin';
                $action = $new_status ? "ล็อกชุมนุม" : "ปลดล็อกชุมนุม";
                $log_sql = "INSERT INTO activity_logs (user, action, target_id, details) 
                            VALUES ('$admin_username', '$action', '$club_id', 'club_id: $club_id')";
                mysqli_query($conn, $log_sql);
            }
            
            // กลับไปที่หน้าจัดการชุมนุม
            redirect('admin_clubs.php?status=success&message=' . urlencode(($new_status ? 'ล็อกชุมนุมเรียบร้อยแล้ว' : 'ปลดล็อกชุมนุมเรียบร้อยแล้ว')));
            exit; // เพิ่ม exit หลัง redirect
        } else {
            // แสดงข้อผิดพลาด MySQL เพื่อการแก้ไขปัญหา (ลบออกในการใช้งานจริง)
            // echo "MySQL Error: " . mysqli_error($conn);
            redirect('admin_clubs.php?status=error&message=' . urlencode('เกิดข้อผิดพลาดในการอัพเดทสถานะชุมนุม'));
            exit;
        }
    } else {
        redirect('admin_clubs.php?status=error&message=' . urlencode('ไม่พบข้อมูลชุมนุม'));
        exit;
    }
} else {
    redirect('admin_clubs.php?status=error&message=' . urlencode('ไม่ได้ระบุรหัสชุมนุม'));
    exit;
}
?>