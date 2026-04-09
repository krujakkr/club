<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ตรวจสอบว่าส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $club_id = (int)$_POST['club_id'];
    $club_name = clean($conn, $_POST['club_name']);
    $description = clean($conn, $_POST['description']);
    $location = clean($conn, $_POST['location']);
    $max_members = (int)$_POST['max_members'];
    $teacher_id = isset($_POST['teacher_id']) && !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : "NULL";
    
    // รับค่าระดับชั้นที่เปิดรับ
    $allow_m1 = isset($_POST['allow_m1']) ? 1 : 0;
    $allow_m2 = isset($_POST['allow_m2']) ? 1 : 0;
    $allow_m3 = isset($_POST['allow_m3']) ? 1 : 0;
    $allow_m4 = isset($_POST['allow_m4']) ? 1 : 0;
    $allow_m5 = isset($_POST['allow_m5']) ? 1 : 0;
    $allow_m6 = isset($_POST['allow_m6']) ? 1 : 0;
    
    // ตรวจสอบว่าชื่อชุมนุมซ้ำหรือไม่
    $check_sql = "SELECT * FROM clubs WHERE club_name = '$club_name' AND club_id != $club_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        alert("ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น");
        redirect("admin_clubs.php");
        exit;
    }
    
    // ตรวจสอบว่าจำนวนที่รับน้อยกว่าจำนวนสมาชิกที่มีอยู่หรือไม่
    $current_members = countClubMembers($club_id);
    if ($max_members < $current_members) {
        alert("ไม่สามารถลดจำนวนรับได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว $current_members คน");
        redirect("admin_clubs.php");
        exit;
    }
    
    // ตรวจสอบว่ามีนักเรียนที่ได้รับผลกระทบจากการเปลี่ยนแปลงระดับชั้นที่รับหรือไม่
    $grade_check_sql = "SELECT COUNT(*) as affected FROM students WHERE club_id = $club_id AND (
                            (grade_level = 'ม.1' AND $allow_m1 = 0) OR
                            (grade_level = 'ม.2' AND $allow_m2 = 0) OR
                            (grade_level = 'ม.3' AND $allow_m3 = 0) OR
                            (grade_level = 'ม.4' AND $allow_m4 = 0) OR
                            (grade_level = 'ม.5' AND $allow_m5 = 0) OR
                            (grade_level = 'ม.6' AND $allow_m6 = 0)
                        )";
    $grade_check_result = mysqli_query($conn, $grade_check_sql);
    $grade_check_data = mysqli_fetch_assoc($grade_check_result);
    
    if ($grade_check_data['affected'] > 0) {
        alert("ไม่สามารถเปลี่ยนแปลงระดับชั้นที่รับได้ เนื่องจากมีนักเรียนในระดับชั้นที่จะปิดรับลงทะเบียนแล้ว {$grade_check_data['affected']} คน");
        redirect("admin_clubs.php");
        exit;
    }
    
    // สร้างคำสั่ง SQL สำหรับอัพเดทข้อมูล
    $sql = "UPDATE clubs SET 
            club_name = '$club_name', 
            description = '$description', 
            location = '$location', 
            max_members = $max_members, 
            teacher_id = " . ($teacher_id === "NULL" ? "NULL" : $teacher_id) . ", 
            allow_m1 = $allow_m1, 
            allow_m2 = $allow_m2, 
            allow_m3 = $allow_m3, 
            allow_m4 = $allow_m4, 
            allow_m5 = $allow_m5, 
            allow_m6 = $allow_m6 
            WHERE club_id = $club_id";
    
    // ทำการอัพเดทข้อมูล
    if (mysqli_query($conn, $sql)) {
        // บันทึกประวัติการอัพเดทชุมนุม
        logActivity($_SESSION['user_id'], 'admin', 'update_club', "อัพเดทชุมนุม: $club_name");
        
        alert("อัพเดทชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการอัพเดทข้อมูล: " . mysqli_error($conn));
    }
    
    redirect("admin_clubs.php");
} else {
    // ถ้าไม่ได้ส่งข้อมูลมาจากฟอร์ม ให้กลับไปที่หน้าจัดการชุมนุม
    redirect("admin_clubs.php");
}
?>