<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied');
}

// ดึงข้อมูลชุมนุมทั้งหมด
$sql = "SELECT c.*, 
               t.firstname as teacher_firstname, 
               t.lastname as teacher_lastname,
               t.telephon as teacher_phone,
               t.department as teacher_department
        FROM clubs c 
        LEFT JOIN teachers t ON c.teacher_id = t.teacher_id 
        ORDER BY c.club_id";

$result = mysqli_query($conn, $sql);

// สร้างชื่อไฟล์ CSV
$filename = 'clubs_' . date('Y-m-d_H-i-s') . '.csv';

// ตั้งค่า headers สำหรับ download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// เพิ่ม BOM สำหรับ UTF-8
echo "\xEF\xBB\xBF";

// สร้าง CSV content
$output = fopen('php://output', 'w');

// Header ของ CSV
$headers = [
    'รหัสชุมนุม',
    'ชื่อชุมนุม',
    'คำอธิบาย',
    'สถานที่เรียน',
    'จำนวนที่รับสูงสุด',
    'จำนวนที่ลงทะเบียนแล้ว',
    'รหัสครูที่ปรึกษา',
    'ชื่อครูที่ปรึกษา',
    'นามสกุลครูที่ปรึกษา',
    'เบอร์โทรครูที่ปรึกษา',
    'แผนกครูที่ปรึกษา',
    'เปิดรับ ม.1',
    'เปิดรับ ม.2',
    'เปิดรับ ม.3',
    'เปิดรับ ม.4',
    'เปิดรับ ม.5',
    'เปิดรับ ม.6',
    'สถานะการล็อก',
    'วันที่สร้าง',
    'วันที่อัพเดต'
];
fputcsv($output, $headers);

// เขียนข้อมูลลงไฟล์ CSV
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // นับจำนวนสมาชิกที่ลงทะเบียนแล้ว
        $registered_count = countClubMembers($row['club_id']);
        
        // แปลงค่า boolean เป็นข้อความ
        $allow_m1 = $row['allow_m1'] ? 'ใช่' : 'ไม่';
        $allow_m2 = $row['allow_m2'] ? 'ใช่' : 'ไม่';
        $allow_m3 = $row['allow_m3'] ? 'ใช่' : 'ไม่';
        $allow_m4 = $row['allow_m4'] ? 'ใช่' : 'ไม่';
        $allow_m5 = $row['allow_m5'] ? 'ใช่' : 'ไม่';
        $allow_m6 = $row['allow_m6'] ? 'ใช่' : 'ไม่';
        
        // สถานะการล็อก
        $is_locked = isset($row['is_locked']) && $row['is_locked'] ? 'ล็อก' : 'เปิด';
        
        // ข้อมูลครูที่ปรึกษา
        $teacher_firstname = $row['teacher_firstname'] ?? '';
        $teacher_lastname = $row['teacher_lastname'] ?? '';
        $teacher_phone = $row['teacher_phone'] ?? '';
        $teacher_department = $row['teacher_department'] ?? '';
        
        $data = [
            $row['club_id'],
            $row['club_name'],
            $row['description'],
            $row['location'],
            $row['max_members'],
            $registered_count,
            $row['teacher_id'] ?? '',
            $teacher_firstname,
            $teacher_lastname,
            $teacher_phone,
            $teacher_department,
            $allow_m1,
            $allow_m2,
            $allow_m3,
            $allow_m4,
            $allow_m5,
            $allow_m6,
            $is_locked,
            $row['created_at'] ?? '',
            $row['updated_at'] ?? ''
        ];
        
        fputcsv($output, $data);
    }
}

fclose($output);
exit;
?>