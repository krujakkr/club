<?php
require_once 'config.php';

// เพิ่มการแสดงข้อผิดพลาดเพื่อการดีบัก
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// ตรวจสอบว่าส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $id_card = clean($conn, $_POST['id_card']);
    $student_id = clean($conn, $_POST['student_id']);
    $club_id = isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0;
    
    // ยกเลิกการตรวจสอบเลขบัตรประชาชนชั่วคราว (หรือทำเป็น optional)
    // if (!validateIdCard($id_card)) {
    //     alert("เลขบัตรประชาชนไม่ถูกต้อง");
    //     redirect("index.php");
    //     exit;
    // }
    
    // ตรวจสอบการเข้าสู่ระบบ
    $sql = "SELECT * FROM students WHERE id_card = '$id_card' AND student_id = '$student_id'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
        
        // ตรวจสอบสถานะการเลือกชุมนุม
        if ($student['selection_status']) {
            // ถ้าเลือกชุมนุมแล้ว
            $_SESSION['user_id'] = $student['student_id'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['firstname'] = $student['firstname'];
            $_SESSION['lastname'] = $student['lastname'];
            
            logActivity($student['student_id'], 'student', 'login', 'นักเรียนเข้าสู่ระบบ');
            
            redirect("index.php");
        } else {
            // ยังไม่ได้เลือกชุมนุม และมีการระบุชุมนุมที่ต้องการเลือก
            if ($club_id > 0) {
                // ตรวจสอบว่าชุมนุมยังมีที่ว่างหรือไม่
                $club_sql = "SELECT * FROM clubs WHERE club_id = $club_id";
                $club_result = mysqli_query($conn, $club_sql);
                
                if (mysqli_num_rows($club_result) > 0) {
                    $club_data = mysqli_fetch_assoc($club_result);
                    $max_members = $club_data['max_members'];
                    $current_members = countClubMembers($club_id);
                    
                    // ตรวจสอบว่าระดับชั้นของนักเรียนสามารถเลือกชุมนุมนี้ได้หรือไม่
                    $grade_level = $student['grade_level'];
                    $allow_field = '';
                    
                    // แปลงระดับชั้นเป็นชื่อฟิลด์ในฐานข้อมูล
                    switch ($grade_level) {
                        case 'ม.1':
                            $allow_field = 'allow_m1';
                            break;
                        case 'ม.2':
                            $allow_field = 'allow_m2';
                            break;
                        case 'ม.3':
                            $allow_field = 'allow_m3';
                            break;
                        case 'ม.4':
                            $allow_field = 'allow_m4';
                            break;
                        case 'ม.5':
                            $allow_field = 'allow_m5';
                            break;
                        case 'ม.6':
                            $allow_field = 'allow_m6';
                            break;
                    }
                    
                    // ตรวจสอบว่าชุมนุมเปิดรับระดับชั้นนี้หรือไม่
                    if (!$allow_field || $club_data[$allow_field] != 1) {
                        alert("ขออภัย ชุมนุมนี้ไม่เปิดรับนักเรียนระดับชั้น " . $grade_level);
                        redirect("index.php");
                        exit;
                    }
                    
                    if ($current_members < $max_members) {
                        // อัพเดทสถานะการเลือกชุมนุม
                        $update_sql = "UPDATE students SET 
                                    selection_status = 1, 
                                    club_id = $club_id 
                                    WHERE student_id = '{$student['student_id']}'";
                        
                        if (mysqli_query($conn, $update_sql)) {
                            $_SESSION['user_id'] = $student['student_id'];
                            $_SESSION['user_type'] = 'student';
                            $_SESSION['firstname'] = $student['firstname'];
                            $_SESSION['lastname'] = $student['lastname'];
                            
                            logActivity($student['student_id'], 'student', 'select_club', "เลือกชุมนุม: {$club_data['club_name']}");
                            
                            // แสดงข้อความแจ้งเตือนและเปลี่ยนหน้า
                            alert("เลือกชุมนุม {$club_data['club_name']} เรียบร้อยแล้ว");
                            redirect("index.php");
                        } else {
                            alert("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . mysqli_error($conn));
                            redirect("index.php");
                        }
                    } else {
                        alert("ชุมนุมนี้เต็มแล้ว กรุณาเลือกชุมนุมอื่น");
                        redirect("index.php");
                    }
                } else {
                    alert("ไม่พบข้อมูลชุมนุมที่เลือก");
                    redirect("index.php");
                }
            } else {
                // ไม่ได้ระบุชุมนุมที่ต้องการเลือก
                $_SESSION['user_id'] = $student['student_id'];
                $_SESSION['user_type'] = 'student';
                $_SESSION['firstname'] = $student['firstname'];
                $_SESSION['lastname'] = $student['lastname'];
                
                logActivity($student['student_id'], 'student', 'login', 'นักเรียนเข้าสู่ระบบ');
                
                redirect("index.php");
            }
        }
    } else {
        alert("ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบเลขบัตรประชาชนและรหัสนักเรียนอีกครั้ง");
        redirect("index.php");
    }
} else {
    // ถ้าไม่ได้ส่งข้อมูลมาจากฟอร์ม ให้กลับไปที่หน้าหลัก
    redirect("index.php");
}
?>