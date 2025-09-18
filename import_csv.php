<?php
require_once 'config.php';

// ตรวจสอบว่าเป็นแอดมินหรือไม่
if (!isLoggedIn() || !isAdmin()) {
    alert("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
    redirect("index.php");
    exit;
}

$success_message = '';
$error_message = '';

// ตรวจสอบการอัปโหลดไฟล์
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    $file = $_FILES["csv_file"];
    
    // ตรวจสอบว่าเป็นไฟล์ CSV หรือไม่
    $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if ($file_ext != "csv") {
        $error_message = "กรุณาอัปโหลดไฟล์ CSV เท่านั้น";
    } else if ($file["error"] > 0) {
        $error_message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์: " . $file["error"];
    } else {
        // อ่านไฟล์ CSV
        $file_handle = fopen($file["tmp_name"], "r");
        
        if ($file_handle !== FALSE) {
            $row = 0;
            $imported = 0;
            $errors = 0;
            $error_details = [];
            
            // เลือกประเภทข้อมูลที่จะนำเข้า
            $import_type = $_POST["import_type"];
            
            // ทดลองใช้ delimiter ต่างๆ (comma, semicolon, tab)
            $delimiters = [',', ';', "\t"];
            $found_delimiter = null;
            
            // ตรวจสอบว่า delimiter ไหนเหมาะสมกับไฟล์
            foreach ($delimiters as $delimiter) {
                // ตั้ง file pointer กลับไปที่จุดเริ่มต้น
                rewind($file_handle);
                $test_data = fgetcsv($file_handle, 1000, $delimiter);
                
                // ตรวจสอบจำนวนคอลัมน์ขั้นต่ำตามประเภทข้อมูล
                $min_columns = ($import_type == "students") ? 7 : (($import_type == "clubs") ? 11 : 6);
                
                if ($test_data !== FALSE && count($test_data) >= $min_columns) {
                    $found_delimiter = $delimiter;
                    break;
                }
            }
            
            if ($found_delimiter === null) {
                $error_message = "ไม่สามารถอ่านรูปแบบไฟล์ CSV ได้ กรุณาตรวจสอบรูปแบบไฟล์";
                fclose($file_handle);
            } else {
                // ตั้ง file pointer กลับไปที่จุดเริ่มต้น
                rewind($file_handle);
                
                // ปิดการตรวจสอบ Foreign Key ชั่วคราว
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
                
                while (($data = fgetcsv($file_handle, 1000, $found_delimiter)) !== FALSE) {
                    // ข้ามแถวแรก (Header)
                    if ($row == 0) {
                        $row++;
                        continue;
                    }
                    
                    // บันทึกข้อมูลแถวปัจจุบันเพื่อใช้ในการแสดงข้อผิดพลาด
                    $current_row = $row + 1; // +1 เพราะเริ่มนับจาก 0
                    
                    // นำเข้าข้อมูลตามประเภท
                    if ($import_type == "students") {
                        // สำหรับไฟล์นักเรียน: student_id, id_card, firstname, lastname, grade_level, class_room, class_number
                        if (count($data) >= 7) {
                            try {
                                $student_id = mysqli_real_escape_string($conn, trim($data[0]));
                                $id_card = mysqli_real_escape_string($conn, trim($data[1]));
                                $firstname = mysqli_real_escape_string($conn, trim($data[2]));
                                $lastname = mysqli_real_escape_string($conn, trim($data[3]));
                                $grade_level = mysqli_real_escape_string($conn, trim($data[4]));
                                $class_room = intval(trim($data[5]));
                                $class_number = intval(trim($data[6]));
                                
                                // ค่า selection_status (ถ้ามี)
                                $selection_status = (isset($data[7]) && trim($data[7]) != "") ? intval(trim($data[7])) : 0;
                                
                                // ค่า club_id (ถ้ามี)
                                $club_id = "NULL"; // ค่าเริ่มต้นเป็น NULL
                                if (isset($data[8]) && trim($data[8]) != "" && strtoupper(trim($data[8])) != "NULL") {
                                    $club_id_val = intval(trim($data[8]));
                                    // ตรวจสอบว่า club_id มีอยู่จริงหรือไม่
                                    $check_club_sql = "SELECT club_id FROM clubs WHERE club_id = $club_id_val";
                                    $check_club_result = mysqli_query($conn, $check_club_sql);
                                    
                                    if (mysqli_num_rows($check_club_result) > 0) {
                                        $club_id = $club_id_val;
                                    }
                                }
                                
                                // ตรวจสอบข้อมูลว่าดี
                                if (empty($student_id) || empty($firstname) || empty($lastname) || empty($grade_level)) {
                                    throw new Exception("ข้อมูลไม่ครบถ้วน");
                                }
                                
                                // ตรวจสอบว่ามีนักเรียนคนนี้ในระบบแล้วหรือไม่
                                $check_sql = "SELECT student_id FROM students WHERE student_id = '$student_id'";
                                $check_result = mysqli_query($conn, $check_sql);
                                
                                if (mysqli_num_rows($check_result) > 0) {
                                    // อัพเดตข้อมูลนักเรียน
                                    $sql = "UPDATE students SET 
                                            id_card = '$id_card',
                                            firstname = '$firstname',
                                            lastname = '$lastname',
                                            grade_level = '$grade_level',
                                            class_room = $class_room,
                                            class_number = $class_number,
                                            selection_status = $selection_status,
                                            club_id = " . (is_numeric($club_id) ? $club_id : "NULL") . "
                                            WHERE student_id = '$student_id'";
                                } else {
                                    // เพิ่มข้อมูลนักเรียนใหม่
                                    $sql = "INSERT INTO students 
                                            (student_id, id_card, firstname, lastname, grade_level, class_room, class_number, selection_status, club_id) 
                                            VALUES 
                                            ('$student_id', '$id_card', '$firstname', '$lastname', '$grade_level', $class_room, $class_number, 
                                            $selection_status, " . (is_numeric($club_id) ? $club_id : "NULL") . ")";
                                }
                                
                                if (mysqli_query($conn, $sql)) {
                                    $imported++;
                                } else {
                                    $errors++;
                                    $error_details[] = "แถวที่ $current_row: " . mysqli_error($conn);
                                }
                            } catch (Exception $e) {
                                $errors++;
                                $error_details[] = "แถวที่ $current_row: " . $e->getMessage();
                            }
                        } else {
                            $errors++;
                            $error_details[] = "แถวที่ $current_row: จำนวนคอลัมน์ไม่ถูกต้อง (ต้องการอย่างน้อย 7 คอลัมน์ พบ " . count($data) . " คอลัมน์)";
                        }
                    } else if ($import_type == "clubs") {
                        // สำหรับไฟล์ชุมนุม: club_name, description, location, max_members, teacher_id, allow_m1-6
                        if (count($data) >= 11) {
                            try {
                                $club_name = mysqli_real_escape_string($conn, trim($data[0]));
                                $description = mysqli_real_escape_string($conn, trim($data[1]));
                                $location = mysqli_real_escape_string($conn, trim($data[2]));
                                $max_members = intval(trim($data[3]));
                                $teacher_id = mysqli_real_escape_string($conn, trim($data[4]));
                                $allow_m1 = intval(trim($data[5]));
                                $allow_m2 = intval(trim($data[6]));
                                $allow_m3 = intval(trim($data[7]));
                                $allow_m4 = intval(trim($data[8]));
                                $allow_m5 = intval(trim($data[9]));
                                $allow_m6 = intval(trim($data[10]));
                                
                                // ตรวจสอบข้อมูลว่าดี
                                if (empty($club_name) || empty($description) || $max_members <= 0) {
                                    throw new Exception("ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง");
                                }
                                
                                // ตรวจสอบว่า teacher_id มีอยู่จริงหรือไม่
                                $valid_teacher = true;
                                if (!empty($teacher_id)) {
                                    $check_teacher_sql = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
                                    $check_teacher_result = mysqli_query($conn, $check_teacher_sql);
                                    
                                    if (mysqli_num_rows($check_teacher_result) === 0) {
                                        $valid_teacher = false;
                                        // สร้างครูอัตโนมัติเพื่อรองรับ Foreign Key
                                        $insert_teacher_sql = "INSERT INTO teachers (teacher_id, firstname, lastname, teacher_code, telephon, department) 
                                                               VALUES ('$teacher_id', 'อาจารย์', 'ผู้สอน', 'AUTO_$teacher_id', '', 'ทั่วไป')";
                                        if (mysqli_query($conn, $insert_teacher_sql)) {
                                            $valid_teacher = true;
                                        }
                                    }
                                }
                                
                                if (!$valid_teacher) {
                                    throw new Exception("รหัสครูที่ปรึกษา '$teacher_id' ไม่พบในระบบและไม่สามารถสร้างอัตโนมัติได้");
                                }
                                
                                // ตรวจสอบว่ามีชุมนุมนี้ในระบบแล้วหรือไม่ (ตรวจสอบตามชื่อชุมนุม)
                                $check_sql = "SELECT club_id FROM clubs WHERE club_name = '$club_name'";
                                $check_result = mysqli_query($conn, $check_sql);
                                
                                if (mysqli_num_rows($check_result) > 0) {
                                    $club_data = mysqli_fetch_assoc($check_result);
                                    $club_id = $club_data['club_id'];
                                    // อัพเดตข้อมูลชุมนุม
                                    $sql = "UPDATE clubs SET 
                                            description = '$description',
                                            location = '$location',
                                            max_members = $max_members,
                                            teacher_id = '$teacher_id',
                                            allow_m1 = $allow_m1,
                                            allow_m2 = $allow_m2,
                                            allow_m3 = $allow_m3,
                                            allow_m4 = $allow_m4,
                                            allow_m5 = $allow_m5,
                                            allow_m6 = $allow_m6
                                            WHERE club_id = $club_id";
                                } else {
                                    // เพิ่มข้อมูลชุมนุมใหม่
                                    $sql = "INSERT INTO clubs 
                                            (club_name, description, location, max_members, teacher_id, allow_m1, allow_m2, allow_m3, allow_m4, allow_m5, allow_m6) 
                                            VALUES 
                                            ('$club_name', '$description', '$location', $max_members, '$teacher_id', $allow_m1, $allow_m2, $allow_m3, $allow_m4, $allow_m5, $allow_m6)";
                                }
                                
                                if (mysqli_query($conn, $sql)) {
                                    $imported++;
                                } else {
                                    $errors++;
                                    $error_details[] = "แถวที่ $current_row: " . mysqli_error($conn);
                                }
                            } catch (Exception $e) {
                                $errors++;
                                $error_details[] = "แถวที่ $current_row: " . $e->getMessage();
                            }
                        } else {
                            $errors++;
                            $error_details[] = "แถวที่ $current_row: จำนวนคอลัมน์ไม่ถูกต้อง (ต้องการ 11 คอลัมน์ พบ " . count($data) . " คอลัมน์)";
                        }
                    } else if ($import_type == "teachers") {
                        // สำหรับไฟล์ครู: teacher_id, firstname, lastname, teacher_code, telephon, department
                            if (count($data) >= 6) {
                                try {
                                    $teacher_id = mysqli_real_escape_string($conn, trim($data[0]));
                                    $firstname = mysqli_real_escape_string($conn, trim($data[1]));
                                    $lastname = mysqli_real_escape_string($conn, trim($data[2]));
                                    $teacher_code = mysqli_real_escape_string($conn, trim($data[3]));
                                    $telephon = mysqli_real_escape_string($conn, trim($data[4]));
                                    $department = mysqli_real_escape_string($conn, trim($data[5]));
                                    
                                    // ตรวจสอบข้อมูลว่าดี
                                    if (empty($teacher_id) || empty($firstname) || empty($lastname)) {
                                        throw new Exception("ข้อมูลไม่ครบถ้วน");
                                    }
                                    
                                    // ตรวจสอบว่ามีครูคนนี้ในระบบแล้วหรือไม่
                                    $check_sql = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
                                    $check_result = mysqli_query($conn, $check_sql);
                                    
                                    if (mysqli_num_rows($check_result) > 0) {
                                        // อัพเดตข้อมูลครู
                                        $sql = "UPDATE teachers SET 
                                                firstname = '$firstname',
                                                lastname = '$lastname',
                                                teacher_code = '$teacher_code',
                                                telephon = '$telephon',
                                                department = '$department'
                                                WHERE teacher_id = '$teacher_id'";
                                    } else {
                                        // เพิ่มข้อมูลครูใหม่
                                        $sql = "INSERT INTO teachers 
                                                (teacher_id, firstname, lastname, teacher_code, telephon, department) 
                                                VALUES 
                                                ('$teacher_id', '$firstname', '$lastname', '$teacher_code', '$telephon', '$department')";
                                    }
                                    
                                    if (mysqli_query($conn, $sql)) {
                                        $imported++;
                                    } else {
                                        $errors++;
                                        $error_details[] = "แถวที่ $current_row: " . mysqli_error($conn);
                                    }
                                } catch (Exception $e) {
                                    $errors++;
                                    $error_details[] = "แถวที่ $current_row: " . $e->getMessage();
                                }
                            } else {
                                $errors++;
                                $error_details[] = "แถวที่ $current_row: จำนวนคอลัมน์ไม่ถูกต้อง (ต้องการ 6 คอลัมน์ พบ " . count($data) . " คอลัมน์)";
                            }
                    }
                    
                    $row++;
                }
                
                fclose($file_handle);
                
                // เปิดการตรวจสอบ Foreign Key กลับมา
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                
                if ($imported > 0) {
                                $success_message = "นำเข้าข้อมูลสำเร็จ $imported รายการ";
                                
                                // ถ้ามีข้อผิดพลาด ให้แสดงรายละเอียดด้วย
                                if ($errors > 0) {
                                    $success_message .= " (มีข้อผิดพลาด $errors รายการ)";
                                    
                                    // แสดงรายละเอียดข้อผิดพลาด (สูงสุด 20 รายการ)
                                    if (!empty($error_details)) {
                                        $success_message .= "<br><br>รายละเอียดข้อผิดพลาด (แสดง " . min(count($error_details), 20) . " รายการ):<br>";
                                        $success_message .= "<ul>";
                                        for ($i = 0; $i < min(count($error_details), 20); $i++) {
                                            $success_message .= "<li>" . $error_details[$i] . "</li>";
                                        }
                                        $success_message .= "</ul>";
                                        if (count($error_details) > 20) {
                                            $success_message .= "และข้อผิดพลาดอื่นๆ อีก " . (count($error_details) - 20) . " รายการ";
                                        }
                                    }
                                }
                                
                                // บันทึกประวัติการนำเข้าข้อมูล
                                logActivity($_SESSION['user_id'], 'admin', 'import_csv', "นำเข้าข้อมูล $import_type: $imported รายการ" . ($errors > 0 ? " (ผิดพลาด $errors รายการ)" : ""));
                            } else {
                                $error_message = "ไม่สามารถนำเข้าข้อมูลได้ เกิดข้อผิดพลาด $errors รายการ";
                                // แสดงรายละเอียดข้อผิดพลาด (สูงสุด 20 รายการ)
                                if (!empty($error_details)) {
                                    $error_message .= "<br><br>รายละเอียดข้อผิดพลาด (แสดง " . min(count($error_details), 20) . " รายการ):<br>";
                                    $error_message .= "<ul>";
                                    for ($i = 0; $i < min(count($error_details), 20); $i++) {
                                        $error_message .= "<li>" . $error_details[$i] . "</li>";
                                    }
                                    $error_message .= "</ul>";
                                    if (count($error_details) > 20) {
                                        $error_message .= "และข้อผิดพลาดอื่นๆ อีก " . (count($error_details) - 20) . " รายการ";
                                    }
                                }
                            }
            }
        } else {
            $error_message = "ไม่สามารถอ่านไฟล์ CSV ได้";
        }
    }
}

// สร้างและตรวจสอบโฟลเดอร์สำหรับไฟล์ตัวอย่าง
$sample_dir = "sample_files";
if (!file_exists($sample_dir)) {
    mkdir($sample_dir, 0755, true);
}

// ตรวจสอบและสร้างไฟล์ตัวอย่างถ้ายังไม่มี
$student_sample = "$sample_dir/import_student.csv";
if (!file_exists($student_sample)) {
    $student_content = "student_id,id_card,firstname,lastname,grade_level,class_room,class_number,selection_status,club_id\n";
    $student_content .= "60001,1100301111111,โชคชัย,มีชัย,ม.6,1,1,0,\n";
    $student_content .= "60002,1100301222222,โชติกา,แสงสว่าง,ม.6,1,2,0,\n";
    $student_content .= "60003,1100301333333,ไชยา,ยิ่งใหญ่,ม.6,1,3,0,\n";
    file_put_contents($student_sample, $student_content);
}

$club_sample = "$sample_dir/import_club.csv";
if (!file_exists($club_sample)) {
    $club_content = "club_name,description,location,max_members,teacher_id,allow_m1,allow_m2,allow_m3,allow_m4,allow_m5,allow_m6\n";
    $club_content .= "ชุมนุมคอมพิวเตอร์,เรียนรู้การเขียนโปรแกรมและการใช้คอมพิวเตอร์ขั้นสูง,ห้องคอมพิวเตอร์ 1,30,T001,0,0,0,1,1,1\n";
    $club_content .= "ชุมนุมภาษาอังกฤษ,พัฒนาทักษะการสื่อสารภาษาอังกฤษและเรียนรู้วัฒนธรรมต่างประเทศ,ห้อง 506,35,T002,1,1,1,1,1,1\n";
    file_put_contents($club_sample, $club_content);
}

$teacher_sample = "$sample_dir/import_teacher.csv";
if (!file_exists($teacher_sample)) {
    $teacher_content = "teacher_id,firstname,lastname,teacher_code,telephon,department\n";
    $teacher_content .= "T001,สมชาย,รักดี,TC001,0811234567,คณิตศาสตร์\n";
    $teacher_content .= "T002,นภา,สมใจ,TC002,0822345678,ภาษาไทย\n";
    $teacher_content .= "T003,วิชัย,สุขสันต์,TC003,0833456789,วิทยาศาสตร์\n";
    file_put_contents($teacher_sample, $teacher_content);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูล CSV - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
        .custom-alert {
            border-left: 5px solid;
        }
        .custom-alert.alert-info {
            border-left-color: #0dcaf0;
            background-color: rgba(13, 202, 240, 0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-cogs me-2"></i> ระบบจัดการชุมนุม
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" target="_blank">
                            <i class="fas fa-home"></i> หน้าหลัก
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> ผู้ดูแลระบบ: <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="admin_change_password.php">เปลี่ยนรหัสผ่าน</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-file-import"></i> นำเข้าข้อมูล CSV</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert custom-alert alert-info mb-4">
                            <i class="fas fa-info-circle"></i> <strong>คำแนะนำ:</strong> ควรนำเข้าข้อมูลตามลำดับดังนี้: 1) ข้อมูลครู 2) ข้อมูลชุมนุม 3) ข้อมูลนักเรียน เพื่อหลีกเลี่ยงปัญหาการอ้างอิงข้อมูล
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">อัปโหลดไฟล์ CSV</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="import_type" class="form-label">ประเภทข้อมูลที่นำเข้า</label>
                                                <select class="form-select" id="import_type" name="import_type" required>
                                                    <option value="teachers">ข้อมูลครู</option>
                                                    <option value="clubs">ข้อมูลชุมนุม</option>
                                                    <option value="students">ข้อมูลนักเรียน</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="csv_file" class="form-label">เลือกไฟล์ CSV</label>
                                                <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                                <div class="form-text">เลือกไฟล์ CSV ที่ต้องการนำเข้า</div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-upload"></i> นำเข้าข้อมูล
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0">คำแนะนำการใช้งาน</h5>
                                    </div>
                                    <div class="card-body">
                                       <p>การนำเข้าข้อมูลโดยใช้ไฟล์ CSV ต้องมีรูปแบบตามนี้:</p>
                                       
                                       <div class="accordion" id="accordionExample">
                                           <div class="accordion-item">
                                               <h2 class="accordion-header" id="headingOne">
                                                   <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                                       รูปแบบไฟล์นำเข้าข้อมูลนักเรียน
                                                   </button>
                                               </h2>
                                               <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                                   <div class="accordion-body">
                                                       <p>คอลัมน์ที่ต้องมี:</p>
                                                       <ol>
                                                           <li>student_id - รหัสนักเรียน</li>
                                                           <li>id_card - เลขบัตรประชาชน</li>
                                                           <li>firstname - ชื่อ</li>
                                                           <li>lastname - นามสกุล</li>
                                                           <li>grade_level - ระดับชั้น (ม.1, ม.2, ...)</li>
                                                           <li>class_room - ห้อง</li>
                                                           <li>class_number - เลขที่</li>
                                                           <li>selection_status - สถานะการเลือก (0=ยังไม่เลือก, 1=เลือกแล้ว) (ไม่บังคับ)</li>
                                                           <li>club_id - รหัสชุมนุม (ไม่บังคับ)</li>
                                                       </ol>
                                                       <p class="text-info"><i class="fas fa-info-circle"></i> หมายเหตุ: ควรนำเข้าข้อมูลชุมนุมก่อนถ้าต้องการกำหนด club_id</p>
                                                   </div>
                                               </div>
                                           </div>
                                           <div class="accordion-item">
                                               <h2 class="accordion-header" id="headingTwo">
                                                   <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                                                       รูปแบบไฟล์นำเข้าข้อมูลชุมนุม
                                                   </button>
                                               </h2>
                                               <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
                                                   <div class="accordion-body">
                                                       <p>คอลัมน์ที่ต้องมี:</p>
                                                       <ol>
                                                           <li>club_name - ชื่อชุมนุม</li>
                                                           <li>description - รายละเอียด</li>
                                                           <li>location - สถานที่</li>
                                                           <li>max_members - จำนวนสมาชิกสูงสุด</li>
                                                           <li>teacher_id - รหัสครูที่ปรึกษา</li>
                                                           <li>allow_m1 - อนุญาตระดับชั้น ม.1 (0 หรือ 1)</li>
                                                           <li>allow_m2 - อนุญาตระดับชั้น ม.2 (0 หรือ 1)</li>
                                                           <li>allow_m3 - อนุญาตระดับชั้น ม.3 (0 หรือ 1)</li>
                                                           <li>allow_m4 - อนุญาตระดับชั้น ม.4 (0 หรือ 1)</li>
                                                           <li>allow_m5 - อนุญาตระดับชั้น ม.5 (0 หรือ 1)</li>
                                                           <li>allow_m6 - อนุญาตระดับชั้น ม.6 (0 หรือ 1)</li>
                                                       </ol>
                                                       <p class="text-info"><i class="fas fa-info-circle"></i> หมายเหตุ: ควรนำเข้าข้อมูลครูก่อนเพื่อให้มีรหัสครูที่ปรึกษาอยู่ในระบบ</p>
                                                   </div>
                                               </div>
                                           </div>
                                           <div class="accordion-item">
                                               <h2 class="accordion-header" id="headingThree">
                                                   <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                                       รูปแบบไฟล์นำเข้าข้อมูลครู
                                                   </button>
                                               </h2>
                                               <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                                                   <div class="accordion-body">
                                                       <p>คอลัมน์ที่ต้องมี:</p>
                                                       <ol>
                                                           <li>teacher_id - รหัสครู</li>
                                                           <li>firstname - ชื่อ</li>
                                                           <li>lastname - นามสกุล</li>
                                                           <li>teacher_code - รหัสประจำตัวครู</li>
                                                           <li>telephon - เบอร์โทรศัพท์</li>
                                                           <li>department - แผนกหรือกลุ่มสาระ</li>
                                                       </ol>
                                                   </div>
                                               </div>
                                           </div>
                                       </div>
                                       
                                       <div class="mt-3">
                                           <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> สำคัญ:</p>
                                           <ul>
                                               <li>ไฟล์ CSV ต้องมีแถวแรกเป็น header (ชื่อคอลัมน์)</li>
                                               <li>ตรวจสอบข้อมูลให้ถูกต้องก่อนนำเข้า</li>
                                               <li>ระบบจะอัพเดตข้อมูลหากพบว่ามีรหัสซ้ำกับในระบบ</li>
                                               <li>ระบบรองรับตัวคั่น (delimiter) แบบ comma (,), semicolon (;) และ tab</li>
                                               <li>หากใช้ Microsoft Excel ให้บันทึกเป็น CSV (Comma delimited)</li>
                                               <li>ควรบันทึกไฟล์ในรูปแบบ UTF-8 เพื่อรองรับภาษาไทย</li>
                                           </ul>
                                           
                                           <p>ตัวอย่างการสร้างไฟล์ CSV:</p>
                                           <ol>
                                               <li>สร้างข้อมูลใน Excel และบันทึกเป็น "CSV (Comma delimited)"</li>
                                               <li>หรือสร้างด้วยโปรแกรม Text Editor และใช้ , เป็นตัวคั่นระหว่างข้อมูล</li>
                                           </ol>
                                       </div>

                                       <!-- ส่วนดาวน์โหลดไฟล์ตัวอย่าง -->
                                       <div class="mt-3">
                                           <p class="text-primary"><i class="fas fa-download"></i> ไฟล์ตัวอย่าง:</p>
                                           <ul>
                                               <li class="mb-2">
                                                   <a href="sample_files/import_teacher.csv" download class="btn btn-sm btn-outline-primary">
                                                       <i class="fas fa-file-csv"></i> ไฟล์ตัวอย่างข้อมูลครู
                                                   </a>
                                               </li>
                                               <li class="mb-2">
                                                   <a href="sample_files/import_club.csv" download class="btn btn-sm btn-outline-primary">
                                                       <i class="fas fa-file-csv"></i> ไฟล์ตัวอย่างข้อมูลชุมนุม
                                                   </a>
                                               </li>
                                               <li>
                                                   <a href="sample_files/import_student.csv" download class="btn btn-sm btn-outline-primary">
                                                       <i class="fas fa-file-csv"></i> ไฟล์ตัวอย่างข้อมูลนักเรียน
                                                   </a>
                                               </li>
                                                <li>
                                                   <a href="sample_files/students_club.xlsx" download class="btn btn-sm btn-outline-primary">
                                                       <i class="fas fa-file-csv"></i> ไฟล์จัดกระทำข้อมูลเพื่อนำเข้า
                                                   </a>
                                               </li>
                                           </ul>
                                       </div>
                                   </div>
                               </div>
                           </div>
                       </div>
                       
                       <div class="mt-3">
                           <a href="admin.php" class="btn btn-secondary">
                               <i class="fas fa-arrow-left"></i> กลับไปยังหน้าหลักผู้ดูแลระบบ
                           </a>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>

   <!-- Bootstrap JS -->
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>