<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ตรวจสอบว่าส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
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
    $check_sql = "SELECT * FROM clubs WHERE club_name = '$club_name'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        alert("ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น");
        redirect("admin_clubs.php");
        exit;
    }
    
    // สร้างคำสั่ง SQL สำหรับเพิ่มข้อมูล
    $sql = "INSERT INTO clubs (club_name, description, location, max_members, teacher_id, 
                                allow_m1, allow_m2, allow_m3, allow_m4, allow_m5, allow_m6) 
            VALUES ('$club_name', '$description', '$location', $max_members, " . 
            ($teacher_id === "NULL" ? "NULL" : $teacher_id) . ", 
            $allow_m1, $allow_m2, $allow_m3, $allow_m4, $allow_m5, $allow_m6)";
    
    // ทำการเพิ่มข้อมูล
    if (mysqli_query($conn, $sql)) {
        $club_id = mysqli_insert_id($conn);
        
        // บันทึกประวัติการเพิ่มชุมนุม
        logActivity($_SESSION['user_id'], 'admin', 'add_club', "เพิ่มชุมนุมใหม่: $club_name");
        
        alert("เพิ่มชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . mysqli_error($conn));
    }
    
    redirect("admin_clubs.php");
} else {
    // ถ้าไม่ได้ส่งข้อมูลมาจากฟอร์ม ให้กลับไปที่หน้าจัดการชุมนุม
    redirect("admin_clubs.php");
}
?>