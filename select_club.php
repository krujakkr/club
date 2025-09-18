<?php
require_once 'config.php';

// เพิ่มการแสดงข้อผิดพลาดเพื่อการดีบัก (สามารถลบออกในเวอร์ชันใช้งานจริง)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isStudent()) {
    // ถ้าไม่ได้ล็อกอินหรือไม่ใช่นักเรียน ให้ redirect ไปที่หน้าหลัก
    redirect('index.php');
    exit; // เพิ่ม exit หลัง redirect
}

// ตรวจสอบว่าระบบเปิดให้ลงทะเบียนหรือไม่
$registration_open = getSystemSetting('registration_open') === 'true';
if (!$registration_open) {
    // ถ้าระบบปิดการลงทะเบียน ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
    redirect('index.php?error=closed');
    exit; // เพิ่ม exit หลัง redirect
}

// ดึงข้อมูลนักเรียน
$student_id = $_SESSION['user_id'];

// ตรวจสอบว่า user_id มีค่าหรือไม่
if (empty($student_id)) {
    redirect('index.php?error=invalid_session');
    exit;
}

try {
    // ใช้ try-catch เพื่อจับข้อผิดพลาดที่อาจเกิดขึ้น
    $student_sql = "SELECT * FROM students WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $student_sql);
    
    if (!$stmt) {
        // หากการเตรียม query ไม่สำเร็จ
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $student_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        // หากการ execute ไม่สำเร็จ
        throw new Exception("Error executing statement: " . mysqli_stmt_error($stmt));
    }
    
    $student_result = mysqli_stmt_get_result($stmt);
    
    if (!$student_result) {
        // หากไม่สามารถรับผลลัพธ์ได้
        throw new Exception("Error getting result: " . mysqli_error($conn));
    }
    
    if (mysqli_num_rows($student_result) == 0) {
        // หากไม่พบข้อมูลนักเรียน
        redirect('index.php?error=student_not_found');
        exit;
    }
    
    $student_data = mysqli_fetch_assoc($student_result);
    
    // ตรวจสอบว่านักเรียนได้เลือกชุมนุมไปแล้วหรือไม่
    if ($student_data['selection_status']) {
        // ถ้าเลือกแล้ว ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
        redirect('index.php?error=already_selected');
        exit;
    }
    
    // ตรวจสอบว่ามีการส่ง club_id มาหรือไม่
    if (isset($_POST['club_id'])) {
        $club_id = mysqli_real_escape_string($conn, $_POST['club_id']);
        
        // ตรวจสอบว่า club_id มีค่าหรือไม่
        if (empty($club_id)) {
            redirect('index.php?error=invalid_club');
            exit;
        }
        
        // ตรวจสอบว่าชุมนุมที่เลือกมีอยู่จริงหรือไม่
        $club_sql = "SELECT c.*, 
                    c.allow_m1, c.allow_m2, c.allow_m3, c.allow_m4, c.allow_m5, c.allow_m6";
                    
        // ตรวจสอบว่ามีคอลัมน์ is_locked ในตาราง clubs หรือไม่
        $column_check_sql = "SHOW COLUMNS FROM clubs LIKE 'is_locked'";
        $column_check_result = mysqli_query($conn, $column_check_sql);
        if (mysqli_num_rows($column_check_result) > 0) {
            $club_sql .= ", c.is_locked";
        }
        
        $club_sql .= " FROM clubs c WHERE c.club_id = ?";
        
        $stmt = mysqli_prepare($conn, $club_sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing club statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $club_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error executing club statement: " . mysqli_stmt_error($stmt));
        }
        
        $club_result = mysqli_stmt_get_result($stmt);
        
        if (!$club_result) {
            throw new Exception("Error getting club result: " . mysqli_error($conn));
        }
        
        if (mysqli_num_rows($club_result) > 0) {
            $club_data = mysqli_fetch_assoc($club_result);
            
            // ตรวจสอบว่าชุมนุมถูกล็อกหรือไม่ (ถ้ามีคอลัมน์ is_locked)
            if (isset($club_data['is_locked']) && $club_data['is_locked']) {
                // ถ้าชุมนุมถูกล็อก ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
                redirect('index.php?error=club_locked');
                exit;
            }
            
            // ตรวจสอบว่าชุมนุมนั้นยังมีที่ว่างหรือไม่
            $registered_count = countClubMembers($club_id);
            if ($registered_count >= $club_data['max_members']) {
                // ถ้าเต็มแล้ว ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
                redirect('index.php?error=club_full');
                exit;
            }
            
            // ตรวจสอบว่านักเรียนอยู่ในระดับชั้นที่ชุมนุมรับหรือไม่
            $grade_level = $student_data['grade_level'];
            // ดึงเลขระดับชั้นจากข้อความ โดยใช้ regex
            preg_match('/[0-9]+/', $grade_level, $matches);
            $grade_number = !empty($matches) ? $matches[0] : 1;
            
            $allow_field = 'allow_m' . $grade_number;
            if (!isset($club_data[$allow_field]) || $club_data[$allow_field] != 1) {
                // ถ้าไม่ได้รับระดับชั้นของนักเรียน ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
                redirect('index.php?error=grade_not_allowed');
                exit;
            }

            // ตรวจสอบว่ามีคอลัมน์ selection_timestamp ในตาราง students หรือไม่
            $column_check_sql = "SHOW COLUMNS FROM students LIKE 'selection_timestamp'";
            $column_check_result = mysqli_query($conn, $column_check_sql);
            
            // ทุกอย่างผ่านการตรวจสอบแล้ว ดำเนินการลงทะเบียน
            if (mysqli_num_rows($column_check_result) > 0) {
                // ถ้ามีคอลัมน์ selection_timestamp
                $update_sql = "UPDATE students SET club_id = ?, selection_status = 1, selection_timestamp = NOW() WHERE student_id = ?";
            } else {
                // ถ้าไม่มีคอลัมน์ selection_timestamp
                $update_sql = "UPDATE students SET club_id = ?, selection_status = 1 WHERE student_id = ?";
            }
            
            $stmt = mysqli_prepare($conn, $update_sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing update statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "is", $club_id, $student_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // ตรวจสอบว่ามีตาราง registrations หรือไม่
                $table_check_sql = "SHOW TABLES LIKE 'registrations'";
                $table_check_result = mysqli_query($conn, $table_check_sql);
                
                if (mysqli_num_rows($table_check_result) > 0) {
                    // บันทึกประวัติการลงทะเบียน
                    $log_sql = "INSERT INTO registrations (student_id, club_id, registration_time) VALUES (?, ?, NOW())";
                    $stmt = mysqli_prepare($conn, $log_sql);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "si", $student_id, $club_id);
                        mysqli_stmt_execute($stmt);
                    }
                }
                
                // ลงทะเบียนสำเร็จ ให้ redirect ไปที่หน้าหลักพร้อมข้อความ
                redirect('index.php?success=registration_complete&club=' . urlencode($club_data['club_name']));
                exit;
            } else {
                // เกิดข้อผิดพลาดในการบันทึกข้อมูล
                throw new Exception("Error updating student record: " . mysqli_stmt_error($stmt));
            }
        } else {
            // ไม่พบชุมนุมที่เลือก
            redirect('index.php?error=club_not_found');
            exit;
        }
    } else {
        // ไม่ได้ระบุ club_id
        redirect('index.php?error=no_club_selected');
        exit;
    }
} catch (Exception $e) {
    // บันทึกข้อผิดพลาดลงไฟล์ log
    error_log("Error in select_club.php: " . $e->getMessage());
    
    // Redirect กลับไปหน้าหลักพร้อมข้อความ
    redirect('index.php?error=database_error');
    exit;
}