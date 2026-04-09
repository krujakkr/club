<?php
require_once 'config.php';

// เพิ่มการแสดงข้อผิดพลาดเพื่อการดีบัก (สามารถลบออกหลังจากแก้ไขแล้ว)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// รับ club_id จาก GET parameter
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// ตรวจสอบว่า club_id ถูกส่งมาหรือไม่
if ($club_id <= 0) {
    echo '<div class="alert alert-danger">ไม่พบข้อมูลชุมนุม</div>';
    exit;
}

// ดึงข้อมูลชุมนุม
$club_sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$club_result = mysqli_query($conn, $club_sql);

if (mysqli_num_rows($club_result) > 0) {
    $club_data = mysqli_fetch_assoc($club_result);
    
    // ดึงข้อมูลสมาชิกในชุมนุม
    $members_sql = "SELECT * FROM students 
                  WHERE club_id = $club_id AND selection_status = 1
                  ORDER BY grade_level, class_room, class_number";
    $members_result = mysqli_query($conn, $members_sql);
    
    // แสดงข้อมูลชุมนุม
    echo '<div class="mb-4">';
    echo '<h5>' . $club_data['club_name'] . '</h5>';
    echo '<p><strong>สถานที่เรียน:</strong> ' . $club_data['location'] . '</p>';
    
    // ดึงข้อมูลครูที่ปรึกษา
    $teacher_id = $club_data['teacher_id'];
    if ($teacher_id) {
        $teacher_sql = "SELECT * FROM teachers WHERE teacher_id = $teacher_id";
        $teacher_result = mysqli_query($conn, $teacher_sql);
        
        if (mysqli_num_rows($teacher_result) > 0) {
            $teacher_data = mysqli_fetch_assoc($teacher_result);
            echo '<p><strong>ครูที่ปรึกษา:</strong> ' . $teacher_data['firstname'] . ' ' . $teacher_data['lastname'] . '</p>';
        }
    }
    
    echo '</div>';
    
    // ตรวจสอบว่ามีสมาชิกหรือไม่
    if (mysqli_num_rows($members_result) > 0) {
        // แสดงตารางรายชื่อสมาชิก
        echo '<table class="table table-striped table-bordered">';
        echo '<thead class="table-dark">';
        echo '<tr>';
        echo '<th width="5%">ลำดับ</th>';
        echo '<th width="15%">รหัสนักเรียน</th>';
        echo '<th width="30%">ชื่อ-นามสกุล</th>';
        echo '<th width="10%">ระดับชั้น</th>';
        echo '<th width="10%">ห้อง</th>';
        echo '<th width="5%">เลขที่</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $i = 1;
        while ($member = mysqli_fetch_assoc($members_result)) {
            echo '<tr>';
            echo '<td class="text-center">' . $i++ . '</td>';
            echo '<td>' . $member['student_id'] . '</td>';
            echo '<td>' . $member['firstname'] . ' ' . $member['lastname'] . '</td>';
            echo '<td class="text-center">' . $member['grade_level'] . '</td>';
            echo '<td class="text-center">' . $member['class_room'] . '</td>';
            echo '<td class="text-center">' . $member['class_number'] . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // แสดงสรุปจำนวนสมาชิก
        $total_members = mysqli_num_rows($members_result);
        $max_members = $club_data['max_members'];
        $available_slots = $max_members - $total_members;
        
        echo '<div class="alert alert-info">';
        echo '<p><strong>จำนวนสมาชิกทั้งหมด:</strong> ' . $total_members . ' คน</p>';
        echo '<p><strong>จำนวนที่รับสูงสุด:</strong> ' . $max_members . ' คน</p>';
        echo '<p><strong>จำนวนที่ว่าง:</strong> ' . $available_slots . ' คน</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">ยังไม่มีสมาชิกในชุมนุมนี้</div>';
    }
} else {
    echo '<div class="alert alert-danger">ไม่พบข้อมูลชุมนุม</div>';
}
?>