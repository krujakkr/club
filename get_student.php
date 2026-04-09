<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล'], JSON_UNESCAPED_UNICODE);
    exit;
}

// รับค่า student_id
$student_id = isset($_GET['student_id']) ? mysqli_real_escape_string($conn, $_GET['student_id']) : '';

if (empty($student_id)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่พบรหัสนักเรียน'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ดึงข้อมูลนักเรียนพร้อมข้อมูลชุมนุม
$sql = "SELECT s.*, c.club_name 
        FROM students s 
        LEFT JOIN clubs c ON s.club_id = c.club_id 
        WHERE s.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    
    // ส่งข้อมูลกลับในรูปแบบ JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($student, JSON_UNESCAPED_UNICODE);
} else {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่พบข้อมูลนักเรียน'], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>