<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล']);
    exit;
}

// รับค่า club_id
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($club_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ไม่พบรหัสชุมนุม']);
    exit;
}

// ดึงข้อมูลชุมนุม
$sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $club = mysqli_fetch_assoc($result);
    
    // ส่งข้อมูลกลับในรูปแบบ JSON
    header('Content-Type: application/json');
    echo json_encode($club);
} else {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'ไม่พบข้อมูลชุมนุม']);
}
?>