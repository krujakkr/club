<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied');
}

// รับค่า filter จาก URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// สร้าง SQL query เหมือนกับหน้า admin.php
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(s.student_id LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($grade_filter)) {
    $where_conditions[] = "s.grade_level = ?";
    $params[] = $grade_filter;
    $param_types .= 's';
}

if ($status_filter !== '') {
    if ($status_filter == '1') {
        $where_conditions[] = "s.selection_status = 1";
    } else {
        $where_conditions[] = "s.selection_status = 0";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ดึงข้อมูลนักเรียนทั้งหมด (ไม่มี LIMIT) - เพิ่ม s.club_id เข้าไปใน SELECT
$sql = "SELECT s.*, s.club_id, c.club_name 
        FROM students s 
        LEFT JOIN clubs c ON s.club_id = c.club_id 
        $where_clause 
        ORDER BY s.grade_level, s.class_room, s.class_number, s.lastname, s.firstname";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// สร้างชื่อไฟล์ CSV
$filename = 'students_' . date('Y-m-d_H-i-s') . '.csv';

// ตั้งค่า headers สำหรับ download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// เพิ่ม BOM สำหรับ UTF-8
echo "\xEF\xBB\xBF";

// สร้าง CSV content
$output = fopen('php://output', 'w');

// Header ของ CSV - เพิ่มคอลัมน์รหัสชุมนุม
$headers = [
    'รหัสนักเรียน',
    'เลขบัตรประชาชน',
    'ชื่อ',
    'นามสกุล',
    'ระดับชั้น',
    'ห้อง',
    'เลขที่',
    'ชั้น',
    'สถานะการเลือก',
    'รหัสชุมนุม',
    'ชุมนุม',
    'วันที่สร้าง',
    'วันที่อัพเดต'
];
fputcsv($output, $headers);

// เขียนข้อมูลลงไฟล์ CSV
while ($row = $result->fetch_assoc()) {
    $class_display = '';
    if (!empty($row['class'])) {
        $class_display = $row['class'];
    } else {
        $class_display = $row['grade_level'];
        if ($row['class_room']) {
            $class_display .= '/' . $row['class_room'];
        }
    }
    
    $status_text = $row['selection_status'] == 1 ? 'เลือกแล้ว' : 'ยังไม่เลือก';
    
    $data = [
        $row['student_id'],
        $row['id_card'],
        $row['firstname'],
        $row['lastname'],
        $row['grade_level'],
        $row['class_room'],
        $row['class_number'],
        $class_display,
        $status_text,
        $row['club_id'] ?? '',  // เพิ่มรหัสชุมนุม
        $row['club_name'] ?? '',
        $row['created_at'],
        $row['updated_at']
    ];
    
    fputcsv($output, $data);
}

fclose($output);
exit;
?>