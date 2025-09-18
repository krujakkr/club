<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// รับค่า club_id จาก GET parameter
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($club_id <= 0) {
    alert("ไม่พบรหัสชุมนุม");
    redirect("admin_clubs.php");
    exit;
}

// ตรวจสอบว่ามีนักเรียนในชุมนุมนี้หรือไม่
$count = countClubMembers($club_id);
if ($count > 0) {
    alert("ไม่สามารถลบชุมนุมนี้ได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว $count คน");
    redirect("admin_clubs.php");
    exit;
}

// ดึงข้อมูลชุมนุมเพื่อบันทึกประวัติ
$sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $club = mysqli_fetch_assoc($result);
    $club_name = $club['club_name'];
    
    // สร้างคำสั่ง SQL สำหรับลบข้อมูล
    $delete_sql = "DELETE FROM clubs WHERE club_id = $club_id";
    
    // ทำการลบข้อมูล
    if (mysqli_query($conn, $delete_sql)) {
        // บันทึกประวัติการลบ
        logActivity($_SESSION['user_id'], 'admin', 'delete_club', "ลบชุมนุม: $club_name");
        
        alert("ลบชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการลบข้อมูล: " . mysqli_error($conn));
    }
} else {
    alert("ไม่พบข้อมูลชุมนุม");
}

// กลับไปที่หน้าจัดการชุมนุม
redirect("admin_clubs.php");
?>