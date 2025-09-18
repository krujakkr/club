<?php
require_once 'config.php';

// รับ club_id จาก GET parameter
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// ตรวจสอบว่า club_id ถูกส่งมาหรือไม่
if ($club_id <= 0) {
    echo "ไม่พบข้อมูลชุมนุม";
    exit;
}

// ดึงข้อมูลชุมนุม
$club_sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$club_result = mysqli_query($conn, $club_sql);

if (mysqli_num_rows($club_result) > 0) {
    $club_data = mysqli_fetch_assoc($club_result);
    $club_name = $club_data['club_name'];
    
    // ดึงข้อมูลครูที่ปรึกษา (ข้อมูลเพิ่มเติม)
    $teacher_id = $club_data['teacher_id'];
    $teacher_info = [
        'teacher_id' => 'ไม่มีข้อมูล',
        'firstname' => 'ไม่มีข้อมูล',
        'lastname' => 'ไม่มีข้อมูล',
        'telephon' => 'ไม่มีข้อมูล'
    ];
    
    if ($teacher_id) {
        $teacher_sql = "SELECT teacher_id, firstname, lastname, telephon FROM teachers WHERE teacher_id = $teacher_id";
        $teacher_result = mysqli_query($conn, $teacher_sql);
        if (mysqli_num_rows($teacher_result) > 0) {
            $teacher_data = mysqli_fetch_assoc($teacher_result);
            $teacher_info = $teacher_data;
            
            // แก้ไขเบอร์โทรให้มีเครื่องหมาย ' นำหน้าเพื่อรักษาเลข 0 ในเบอร์โทร
            if (!empty($teacher_info['telephon'])) {
                $teacher_info['telephon'] = "'" . $teacher_info['telephon'];
            }
        }
    }
    
    // ดึงข้อมูลสมาชิกในชุมนุม
    $members_sql = "SELECT s.* FROM students s 
                  WHERE s.club_id = $club_id 
                  ORDER BY s.class_room, s.class_number";
    $members_result = mysqli_query($conn, $members_sql);
    
    // ตั้งค่า header สำหรับไฟล์ CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="รายชื่อสมาชิกชุมนุม_' . $club_name . '.csv"');
    
    // สร้าง output stream
    $output = fopen('php://output', 'w');
    
    // เพิ่ม BOM เพื่อให้รองรับภาษาไทยใน Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // เขียนข้อมูลชุมนุม
    fputcsv($output, ['รายชื่อสมาชิกชุมนุม', $club_name]);
    
    // แสดงข้อมูลครูที่ปรึกษาแบบละเอียด
    fputcsv($output, ['ข้อมูลครูที่ปรึกษา']);
    fputcsv($output, ['รหัสครู', $teacher_info['teacher_id']]);
    fputcsv($output, ['ชื่อ', $teacher_info['firstname']]);
    fputcsv($output, ['นามสกุล', $teacher_info['lastname']]);
    fputcsv($output, ['เบอร์โทร', $teacher_info['telephon']]);
    
    fputcsv($output, ['สถานที่เรียน', $club_data['location']]);
    fputcsv($output, []); // บรรทัดว่าง
    
    // หัวข้อตาราง - ลบคอลัมน์เลขบัตรประชาชน
    fputcsv($output, ['ลำดับ', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ระดับชั้น', 'ห้อง', 'เลขที่']);
    
    // ข้อมูลสมาชิก
    $i = 1;
    if (mysqli_num_rows($members_result) > 0) {
        while ($member = mysqli_fetch_assoc($members_result)) {
            fputcsv($output, [
                $i++,
                $member['student_id'],
                $member['firstname'] . ' ' . $member['lastname'],
                $member['grade_level'],
                $member['class_room'],
                $member['class_number']
            ]);
        }
    }
    
    // ปิด output stream
    fclose($output);
} else {
    echo "ไม่พบข้อมูลชุมนุม";
}
exit;
?>