<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$student_id = isset($_POST['student_id']) ? clean($conn, $_POST['student_id']) : '';
$club_id    = isset($_POST['club_id'])    ? (int)$_POST['club_id']            : 0;

if (empty($student_id) || $club_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$sql = "UPDATE students
        SET club_id = NULL, selection_status = 0
        WHERE student_id = '$student_id' AND club_id = $club_id AND selection_status = 1";

if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
    logActivity($_SESSION['user_id'], 'admin', 'remove_member',
        "ถอนการเลือกชุมนุม club_id=$club_id ของนักเรียน student_id=$student_id");
    echo json_encode(['success' => true, 'message' => 'ถอนการเลือกชุมนุมสำเร็จ']);
} else {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลหรือไม่สามารถดำเนินการได้']);
}
?>
