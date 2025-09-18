<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล']);
    exit;
}

// รับค่า teacher_id
$teacher_id = isset($_GET['teacher_id']) ? mysqli_real_escape_string($conn, $_GET['teacher_id']) : '';

if (empty($teacher_id)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ไม่พบรหัสครู']);
    exit;
}

// ดึงข้อมูลครู
$sql = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $teacher = mysqli_fetch_assoc($result);
    
    // ส่งข้อมูลกลับในรูปแบบ JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($teacher, JSON_UNESCAPED_UNICODE);
} else {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'ไม่พบข้อมูลครู']);
}
?>