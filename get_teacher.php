<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id'] ?? '');

if (empty($teacher_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing teacher_id']);
    exit;
}

$sql    = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found']);
    exit;
}

$teacher = mysqli_fetch_assoc($result);

// ถ้าขอรายชื่อชุมนุมด้วย
if (isset($_GET['clubs']) && $_GET['clubs'] == '1') {
    $clubs_sql    = "SELECT club_name FROM clubs WHERE teacher_id = '$teacher_id' ORDER BY club_name";
    $clubs_result = mysqli_query($conn, $clubs_sql);
    $clubs        = [];
    while ($row = mysqli_fetch_assoc($clubs_result)) {
        $clubs[] = $row['club_name'];
    }
    $teacher['clubs'] = $clubs;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($teacher, JSON_UNESCAPED_UNICODE);