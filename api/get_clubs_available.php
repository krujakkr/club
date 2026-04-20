<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล'], JSON_UNESCAPED_UNICODE);
    exit;
}

$grade_level    = isset($_GET['grade_level'])    ? trim($_GET['grade_level'])    : '';
$current_club_id = isset($_GET['current_club_id']) ? (int)$_GET['current_club_id'] : 0;

if (empty($grade_level)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ไม่พบระดับชั้น'], JSON_UNESCAPED_UNICODE);
    exit;
}

preg_match('/[0-9]+/', $grade_level, $matches);
$grade_number = !empty($matches) ? (int)$matches[0] : 0;

if ($grade_number < 1 || $grade_number > 6) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ระดับชั้นไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allow_field = "allow_m" . $grade_number;

// ดึงชุมนุมที่เปิดสำหรับชั้นนี้และยังไม่เต็ม
$sql = "SELECT
            c.club_id,
            c.club_name,
            c.max_members,
            COUNT(s.student_id) AS current_members
        FROM clubs c
        LEFT JOIN students s ON c.club_id = s.club_id AND s.selection_status = 1
        WHERE c.$allow_field = 1
        GROUP BY c.club_id, c.club_name, c.max_members
        HAVING current_members < c.max_members
        ORDER BY c.club_name";

$result = mysqli_query($conn, $sql);
$clubs = [];
$found_current = false;

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['club_id'] == $current_club_id) {
        $found_current = true;
    }
    $clubs[] = $row;
}

// ถ้า club ปัจจุบันของนักเรียนไม่อยู่ในลิสต์ (เต็ม/ไม่เปิดชั้นนี้) ให้ดึงมาใส่ไว้ด้วย
if ($current_club_id > 0 && !$found_current) {
    $sql2 = "SELECT
                 c.club_id,
                 c.club_name,
                 c.max_members,
                 COUNT(s.student_id) AS current_members
             FROM clubs c
             LEFT JOIN students s ON c.club_id = s.club_id AND s.selection_status = 1
             WHERE c.club_id = ?
             GROUP BY c.club_id, c.club_name, c.max_members";
    $stmt = $conn->prepare($sql2);
    $stmt->bind_param('i', $current_club_id);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows > 0) {
        $clubs[] = $r->fetch_assoc();
    }
    $stmt->close();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($clubs, JSON_UNESCAPED_UNICODE);
