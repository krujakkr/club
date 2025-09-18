<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// จัดการการอัพเดทการตั้งค่าระบบ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $success_messages = [];
    $error_messages = [];
    
    // อัพเดทการตั้งค่าสถานะการลงทะเบียน
    if (isset($_POST['registration_open'])) {
        $registration_status = $_POST['registration_open'] === '1' ? 'true' : 'false';
        if (updateSystemSetting('registration_open', $registration_status)) {
            $action_text = ($registration_status === 'true') ? 'เปิดระบบลงทะเบียน' : 'ปิดระบบลงทะเบียน';
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', $action_text);
            $success_messages[] = $action_text . 'เรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทสถานะการลงทะเบียน';
        }
    }
    
    // อัพเดทปีการศึกษา
    if (isset($_POST['academic_year']) && !empty($_POST['academic_year'])) {
        $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
        if (updateSystemSetting('academic_year', $academic_year)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทปีการศึกษา: $academic_year");
            $success_messages[] = 'อัพเดทปีการศึกษาเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทปีการศึกษา';
        }
    }
    
    // อัพเดทภาคเรียน
    if (isset($_POST['semester']) && !empty($_POST['semester'])) {
        $semester = mysqli_real_escape_string($conn, $_POST['semester']);
        if (updateSystemSetting('semester', $semester)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทภาคเรียน: $semester");
            $success_messages[] = 'อัพเดทภาคเรียนเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทภาคเรียน';
        }
    }
    
    // อัพเดทชื่อโรงเรียน
    if (isset($_POST['school_name']) && !empty($_POST['school_name'])) {
        $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
        if (updateSystemSetting('school_name', $school_name)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทชื่อโรงเรียน: $school_name");
            $success_messages[] = 'อัพเดทชื่อโรงเรียนเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทชื่อโรงเรียน';
        }
    }
    
    // จัดการการล้างข้อมูล
    if (isset($_POST['clear_data_type'])) {
        $clear_type = $_POST['clear_data_type'];
        
        switch ($clear_type) {
            case 'reset_selections':
                // ล้างการเลือกชุมนุมของนักเรียนทั้งหมด
                $reset_sql = "UPDATE students SET selection_status = 0, club_id = NULL";
                if (mysqli_query($conn, $reset_sql)) {
                    $affected_rows = mysqli_affected_rows($conn);
                    logActivity($_SESSION['user_id'], 'admin', 'reset_selections', "รีเซ็ตการเลือกชุมนุม: $affected_rows รายการ");
                    $success_messages[] = "รีเซ็ตการเลือกชุมนุมเรียบร้อยแล้ว ($affected_rows รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาดในการรีเซ็ตการเลือกชุมนุม: ' . mysqli_error($conn);
                }
                break;
                
            case 'clear_students':
                // ลบข้อมูลนักเรียนทั้งหมด
                $count_sql = "SELECT COUNT(*) as total FROM students";
                $count_result = mysqli_query($conn, $count_sql);
                $total_students = mysqli_fetch_assoc($count_result)['total'];
                
                $clear_sql = "DELETE FROM students";
                if (mysqli_query($conn, $clear_sql)) {
                    logActivity($_SESSION['user_id'], 'admin', 'clear_students', "ลบข้อมูลนักเรียน: $total_students รายการ");
                    $success_messages[] = "ลบข้อมูลนักเรียนทั้งหมดเรียบร้อยแล้ว ($total_students รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาดในการลบข้อมูลนักเรียน: ' . mysqli_error($conn);
                }
                break;
                
            case 'clear_clubs':
                // ลบข้อมูลชุมนุมทั้งหมด
                $count_sql = "SELECT COUNT(*) as total FROM clubs";
                $count_result = mysqli_query($conn, $count_sql);
                $total_clubs = mysqli_fetch_assoc($count_result)['total'];
                
                // รีเซ็ตการเลือกชุมนุมของนักเรียนก่อน
                mysqli_query($conn, "UPDATE students SET selection_status = 0, club_id = NULL");
                
                $clear_sql = "DELETE FROM clubs";
                if (mysqli_query($conn, $clear_sql)) {
                    logActivity($_SESSION['user_id'], 'admin', 'clear_clubs', "ลบข้อมูลชุมนุม: $total_clubs รายการ");
                    $success_messages[] = "ลบข้อมูลชุมนุมทั้งหมดเรียบร้อยแล้ว ($total_clubs รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาดในการลบข้อมูลชุมนุม: ' . mysqli_error($conn);
                }
                break;
        }
    }
    
    // เก็บข้อความแจ้งเตือนใน session
    if (!empty($success_messages)) {
        $_SESSION['success_messages'] = $success_messages;
    }
    if (!empty($error_messages)) {
        $_SESSION['error_messages'] = $error_messages;
    }
    
    // Redirect เพื่อป้องกัน form resubmission
    header("Location: admin_settings.php");
    exit;
}

// ดึงข้อมูลการตั้งค่าปัจจุบัน
$registration_open = getSystemSetting('registration_open') === 'true';
$academic_year = getSystemSetting('academic_year') ?: '2568';
$semester = getSystemSetting('semester') ?: '1';
$school_name = getSystemSetting('school_name') ?: 'โรงเรียนแก่นนครวิทยาลัย';

// ดึงสถิติระบบ
$stats = [];

// จำนวนนักเรียนทั้งหมด
$students_sql = "SELECT COUNT(*) as total, 
                 SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) as selected,
                 SUM(CASE WHEN selection_status = 0 THEN 1 ELSE 0 END) as not_selected
                 FROM students";
$students_result = mysqli_query($conn, $students_sql);
$students_stats = mysqli_fetch_assoc($students_result);
$stats['students'] = $students_stats;

// จำนวนชุมนุมทั้งหมด
$clubs_sql = "SELECT COUNT(*) as total,
              SUM(max_members) as total_capacity,
              SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked_count
              FROM clubs";
$clubs_result = mysqli_query($conn, $clubs_sql);
$clubs_stats = mysqli_fetch_assoc($clubs_result);
$stats['clubs'] = $clubs_stats;

// จำนวนครูทั้งหมด
$teachers_sql = "SELECT COUNT(*) as total FROM teachers";
$teachers_result = mysqli_query($conn, $teachers_sql);
$teachers_stats = mysqli_fetch_assoc($teachers_result);
$stats['teachers'] = $teachers_stats;

// นับจำนวนสมาชิกในแต่ละชุมนุม
$club_members_sql = "SELECT COUNT(*) as total_registered FROM students WHERE selection_status = 1 AND club_id IS NOT NULL";
$club_members_result = mysqli_query($conn, $club_members_sql);
$club_members_stats = mysqli_fetch_assoc($club_members_result);
$stats['registered_members'] = $club_members_stats['total_registered'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
        }
        .nav-link {
            color: #333;
        }
        .nav-link:hover {
            background-color: #f8f9fa;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 8px;
            background-color: #f8d7da;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
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

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">
                                <i class="fas fa-tachometer-alt me-2"></i>จัดการนักเรียน
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_clubs.php">
                                <i class="fas fa-users me-2"></i>จัดการชุมนุม
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_teachers.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>จัดการครู
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_import.php">
                                <i class="fas fa-file-import me-2"></i>นำเข้าข้อมูล
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_reports.php">
                                <i class="fas fa-chart-bar me-2"></i>รายงาน
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_settings.php">
                                <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ตั้งค่าระบบ</h1>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_messages'])): ?>
                    <?php foreach ($_SESSION['success_messages'] as $message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['success_messages']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_messages'])): ?>
                    <?php foreach ($_SESSION['error_messages'] as $message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['error_messages']); ?>
                <?php endif; ?>

                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3"><i class="fas fa-chart-bar text-primary"></i> สถิติระบบ</h4>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-2x text-primary mb-3"></i>
                                <h5>จำนวนนักเรียน</h5>
                                <h3 class="text-primary"><?php echo number_format($stats['students']['total']); ?></h3>
                                <small class="text-muted">
                                    เลือกแล้ว: <?php echo number_format($stats['students']['selected']); ?> | 
                                    ยังไม่เลือก: <?php echo number_format($stats['students']['not_selected']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-info mb-3"></i>
                                <h5>จำนวนชุมนุม</h5>
                                <h3 class="text-info"><?php echo number_format($stats['clubs']['total']); ?></h3>
                                <small class="text-muted">
                                    รับได้: <?php echo number_format($stats['clubs']['total_capacity']); ?> คน |
                                    ล็อก: <?php echo number_format($stats['clubs']['locked_count']); ?> ชุมนุม
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard-teacher fa-2x text-success mb-3"></i>
                                <h5>จำนวนครู</h5>
                                <h3 class="text-success"><?php echo number_format($stats['teachers']['total']); ?></h3>
                                <small class="text-muted">ที่ปรึกษาชุมนุม</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x text-warning mb-3"></i>
                                <h5>ลงทะเบียนแล้ว</h5>
                                <h3 class="text-warning"><?php echo number_format($stats['registered_members']); ?></h3>
                                <small class="text-muted">
                                    <?php 
                                    $percentage = $stats['students']['total'] > 0 ? 
                                        round(($stats['registered_members'] / $stats['students']['total']) * 100, 1) : 0;
                                    echo $percentage . '%';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings Form -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-cog"></i> การตั้งค่าระบบ</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <!-- Registration Status -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-toggle-on text-primary"></i> สถานะระบบลงทะเบียน
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php if ($registration_open): ?>
                                                            ขณะนี้นักเรียนสามารถลงทะเบียนเลือกชุมนุมได้
                                                        <?php else: ?>
                                                            ขณะนี้ระบบปิดการลงทะเบียนชั่วคราว
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <label class="switch">
                                                    <input type="hidden" name="registration_open" value="0">
                                                    <input type="checkbox" name="registration_open" value="1" 
                                                           <?php echo $registration_open ? 'checked' : ''; ?>
                                                           onchange="this.form.submit()">
                                                    <span class="slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Academic Settings -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="academic_year" class="form-label">
                                                <i class="fas fa-calendar-alt"></i> ปีการศึกษา
                                            </label>
                                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                                   value="<?php echo htmlspecialchars($academic_year); ?>" 
                                                   placeholder="เช่น 2568">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="semester" class="form-label">
                                                <i class="fas fa-book"></i> ภาคเรียนที่
                                            </label>
                                            <select class="form-select" id="semester" name="semester">
                                                <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>ภาคเรียนที่ 1</option>
                                                <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>ภาคเรียนที่ 2</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- School Name -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <label for="school_name" class="form-label">
                                                <i class="fas fa-school"></i> ชื่อโรงเรียน
                                            </label>
                                            <input type="text" class="form-control" id="school_name" name="school_name" 
                                                   value="<?php echo htmlspecialchars($school_name); ?>" 
                                                   placeholder="ชื่อโรงเรียน">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> บันทึกการตั้งค่า
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-bolt"></i> การดำเนินการด่วน</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin_import.php" class="btn btn-outline-primary">
                                        <i class="fas fa-file-import"></i> นำเข้าข้อมูล CSV
                                    </a>
                                    <a href="admin_reports.php" class="btn btn-outline-info">
                                        <i class="fas fa-chart-line"></i> ดูรายงานระบบ
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetSelectionsModal">
                                        <i class="fas fa-undo"></i> รีเซ็ตการเลือกชุมนุม
                                    </button>
                                </div>
                                
                                <hr>
                                
                                <h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> พื้นที่อันตราย</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearDataModal">
                                        <i class="fas fa-trash-alt"></i> ลบข้อมูลทั้งหมด
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Selections Modal -->
    <div class="modal fade" id="resetSelectionsModal" tabindex="-1" aria-labelledby="resetSelectionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="resetSelectionsModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> ยืนยันการรีเซ็ตการเลือกชุมนุม
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>คำเตือน:</strong> การดำเนินการนี้จะลบการเลือกชุมนุมของนักเรียนทั้งหมด
                    </div>
                    <p>นักเรียนจะต้องเลือกชุมนุมใหม่อีกครั้ง</p>
                    <p><strong>จำนวนนักเรียนที่เลือกชุมนุมแล้ว:</strong> <?php echo number_format($stats['students']['selected']); ?> คน</p>
                    <p class="text-danger">คุณแน่ใจหรือไม่ที่จะรีเซ็ตการเลือกชุมนุมทั้งหมด?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="clear_data_type" value="reset_selections">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo"></i> ยืนยันการรีเซ็ต
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Data Modal -->
    <div class="modal fade" id="clearDataModal" tabindex="-1" aria-labelledby="clearDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="clearDataModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> ลบข้อมูลทั้งหมด
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>อันตราย!</strong> การดำเนินการนี้จะลบข้อมูลอย่างถาวร และไม่สามารถกู้คืนได้
                    </div>
                    
                    <form method="post" id="clearDataForm">
                        <div class="mb-3">
                            <label for="clear_data_type" class="form-label">เลือกประเภทข้อมูลที่ต้องการลบ:</label>
                            <select class="form-select" id="clear_data_type" name="clear_data_type" required>
                                <option value="">-- เลือกประเภทข้อมูล --</option>
                                <option value="reset_selections">รีเซ็ตการเลือกชุมนุมเท่านั้น (<?php echo number_format($stats['students']['selected']); ?> รายการ)</option>
                                <option value="clear_students">ลบข้อมูลนักเรียนทั้งหมด (<?php echo number_format($stats['students']['total']); ?> รายการ)</option>
                                <option value="clear_clubs">ลบข้อมูลชุมนุมทั้งหมด (<?php echo number_format($stats['clubs']['total']); ?> รายการ)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                                <label class="form-check-label text-danger" for="confirmDelete">
                                    ฉันเข้าใจว่าการดำเนินการนี้ไม่สามารถยกเลิกได้
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmText" class="form-label">พิมพ์ "DELETE" เพื่อยืนยัน:</label>
                            <input type="text" class="form-control" id="confirmText" placeholder="DELETE" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="clearDataForm" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="fas fa-trash-alt"></i> ยืนยันการลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ตรวจสอบการยืนยันการลบข้อมูล
        document.getElementById('confirmText').addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const confirmCheckbox = document.getElementById('confirmDelete');
            const confirmText = this.value;
            
            if (confirmText === 'DELETE' && confirmCheckbox.checked) {
                confirmBtn.disabled = false;
            } else {
                confirmBtn.disabled = true;
            }
        });
        
        document.getElementById('confirmDelete').addEventListener('change', function() {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const confirmText = document.getElementById('confirmText').value;
            
            if (confirmText === 'DELETE' && this.checked) {
                confirmBtn.disabled = false;
            } else {
                confirmBtn.disabled = true;
            }
        });
        
        // รีเซ็ตฟอร์มเมื่อปิด modal
        document.getElementById('clearDataModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('clearDataForm').reset();
            document.getElementById('confirmDeleteBtn').disabled = true;
        });
        
        // แสดงรายละเอียดการลบข้อมูลตามที่เลือก
        document.getElementById('clear_data_type').addEventListener('change', function() {
            const selectedValue = this.value;
            let warningText = '';
            
            switch(selectedValue) {
                case 'reset_selections':
                    warningText = 'จะรีเซ็ตการเลือกชุมนุมของนักเรียนทั้งหมด นักเรียนจะต้องเลือกชุมนุมใหม่';
                    break;
                case 'clear_students':
                    warningText = 'จะลบข้อมูลนักเรียนทั้งหมดออกจากระบบ รวมถึงการเลือกชุมนุมด้วย';
                    break;
                case 'clear_clubs':
                    warningText = 'จะลบข้อมูลชุมนุมทั้งหมด และรีเซ็ตการเลือกชุมนุมของนักเรียน';
                    break;
            }
            
            // อัพเดทข้อความเตือน (ถ้าต้องการ)
            console.log(warningText);
        });
        
        // ป้องกันการส่งฟอร์มโดยไม่ตั้งใจ
        document.getElementById('clearDataForm').addEventListener('submit', function(e) {
            const confirmText = document.getElementById('confirmText').value;
            const confirmCheckbox = document.getElementById('confirmDelete').checked;
            const dataType = document.getElementById('clear_data_type').value;
            
            if (confirmText !== 'DELETE' || !confirmCheckbox || !dataType) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลให้ครบถ้วนและยืนยันการลบ');
                return false;
            }
            
            // ยืนยันอีกครั้ง
            if (!confirm('คุณแน่ใจหรือไม่ที่จะดำเนินการนี้? การกระทำนี้ไม่สามารถยกเลิกได้')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-submit เมื่อเปลี่ยนสถานะการลงทะเบียน
        document.querySelector('input[name="registration_open"]').addEventListener('change', function() {
            // แสดง loading หรือ disabled state
            this.disabled = true;
            
            // ส่งฟอร์มหลังจาก delay เล็กน้อย
            setTimeout(() => {
                this.form.submit();
            }, 100);
        });
        
        // เพิ่มการแสดงสถานะปัจจุบัน
        window.addEventListener('load', function() {
            const registrationStatus = document.querySelector('input[name="registration_open"]').checked;
            console.log('Registration status:', registrationStatus ? 'เปิด' : 'ปิด');
        });
    </script>
</body>
</html>