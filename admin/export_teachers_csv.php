<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$sql = "
    SELECT
        t.teacher_id,
        t.firstname,
        t.lastname,
        t.teacher_code,
        t.telephon,
        t.department,
        COUNT(DISTINCT c.club_id) AS club_count,
        GROUP_CONCAT(DISTINCT c.club_name ORDER BY c.club_name SEPARATOR ', ') AS club_names,
        (SELECT created_at FROM logs
         WHERE user_id = t.teacher_id AND user_type = 'teacher' AND action = 'update_club'
         ORDER BY created_at DESC LIMIT 1) AS last_update,
        (SELECT created_at FROM logs
         WHERE user_id = t.teacher_id AND user_type = 'teacher' AND action = 'login'
         ORDER BY created_at DESC LIMIT 1) AS last_login
    FROM teachers t
    LEFT JOIN clubs c ON t.teacher_id = c.teacher_id
    GROUP BY t.teacher_id, t.firstname, t.lastname, t.teacher_code, t.telephon, t.department
    ORDER BY t.teacher_id
";
$result = mysqli_query($conn, $sql);

$filename = 'teachers_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'รหัสครู',
    'ชื่อ',
    'นามสกุล',
    'รหัสประจำตัว',
    'เบอร์โทรศัพท์',
    'แผนก/กลุ่มสาระ',
    'ชุมนุม',
    'สถานะการแก้ไขชุมนุม',
    'แก้ไขชุมนุมล่าสุด',
]);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $has_club   = $row['club_count'] > 0;
        $has_update = !empty($row['last_update']);

        if (!$has_club) {
            $status = 'ไม่มีชุมนุม';
        } elseif ($has_update) {
            $status = 'แก้ไขแล้ว';
        } else {
            $status = 'ยังไม่แก้ไข';
        }

        $last_update = $has_update
            ? date('d/m/Y H:i', strtotime($row['last_update']))
            : '';

        fputcsv($output, [
            $row['teacher_id'],
            $row['firstname'],
            $row['lastname'],
            $row['teacher_code'] ?? '',
            $row['telephon'] ?? '',
            $row['department'] ?? '',
            $row['club_names'] ?? '',
            $status,
            $last_update,
        ]);
    }
}

fclose($output);
logActivity($_SESSION['user_id'], 'admin', 'export_csv', 'Export ข้อมูลครูเป็น CSV');
exit;
