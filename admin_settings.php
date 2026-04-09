<?php
require_once 'config.php';
$active_menu = 'settings';

if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $success_messages = [];
    $error_messages   = [];

    // ===== อัพเดทช่วงเวลาเปิด-ปิดระบบลงทะเบียน =====
    if (isset($_POST['reg_start']) && isset($_POST['reg_end'])) {
        $r_start = mysqli_real_escape_string($conn, $_POST['reg_start']);
        $r_end   = mysqli_real_escape_string($conn, $_POST['reg_end']);

        if (empty($r_start) && empty($r_end)) {
            updateSystemSetting('registration_start', '');
            updateSystemSetting('registration_end',   '');
            // ปิดระบบด้วยเมื่อล้างช่วงเวลา
            updateSystemSetting('registration_open', 'false');
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', 'ล้างช่วงเวลาเปิด-ปิดระบบลงทะเบียน');
            $success_messages[] = 'ล้างช่วงเวลาลงทะเบียนเรียบร้อยแล้ว (ระบบปิด)';
        } elseif (!empty($r_start) && !empty($r_end)) {
            $r_start_db = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $r_start)));
            $r_end_db   = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $r_end)));
            if ($r_end_db <= $r_start_db) {
                $error_messages[] = 'วันสิ้นสุดต้องหลังวันเริ่มต้น';
            } else {
                updateSystemSetting('registration_start', $r_start_db);
                updateSystemSetting('registration_end',   $r_end_db);
                // คำนวณสถานะ registration_open อัตโนมัติ
                $now = date('Y-m-d H:i:s');
                $is_open = ($now >= $r_start_db && $now <= $r_end_db) ? 'true' : 'false';
                updateSystemSetting('registration_open', $is_open);
                logActivity($_SESSION['user_id'], 'admin', 'update_settings',
                    "ตั้งช่วงเวลาลงทะเบียน: $r_start_db ถึง $r_end_db");
                $success_messages[] = 'บันทึกช่วงเวลาลงทะเบียนเรียบร้อยแล้ว';
            }
        } else {
            $error_messages[] = 'กรุณากรอกทั้งวันเริ่มต้นและวันสิ้นสุด';
        }
    }

    // ===== อัพเดทปีการศึกษา =====
    if (isset($_POST['academic_year']) && !empty($_POST['academic_year'])) {
        $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
        if (updateSystemSetting('academic_year', $academic_year)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทปีการศึกษา: $academic_year");
            $success_messages[] = 'อัพเดทปีการศึกษาเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทปีการศึกษา';
        }
    }

    // ===== อัพเดทภาคเรียน =====
    if (isset($_POST['semester']) && !empty($_POST['semester'])) {
        $semester = mysqli_real_escape_string($conn, $_POST['semester']);
        if (updateSystemSetting('semester', $semester)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทภาคเรียน: $semester");
            $success_messages[] = 'อัพเดทภาคเรียนเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทภาคเรียน';
        }
    }

    // ===== อัพเดทชื่อโรงเรียน =====
    if (isset($_POST['school_name']) && !empty($_POST['school_name'])) {
        $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
        if (updateSystemSetting('school_name', $school_name)) {
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', "อัพเดทชื่อโรงเรียน: $school_name");
            $success_messages[] = 'อัพเดทชื่อโรงเรียนเรียบร้อยแล้ว';
        } else {
            $error_messages[] = 'เกิดข้อผิดพลาดในการอัพเดทชื่อโรงเรียน';
        }
    }

    // ===== อัพเดทช่วงเวลา login ครู =====
    if (isset($_POST['teacher_edit_start']) && isset($_POST['teacher_edit_end'])) {
        $t_start = mysqli_real_escape_string($conn, $_POST['teacher_edit_start']);
        $t_end   = mysqli_real_escape_string($conn, $_POST['teacher_edit_end']);

        if (empty($t_start) && empty($t_end)) {
            updateSystemSetting('teacher_edit_start', '');
            updateSystemSetting('teacher_edit_end',   '');
            logActivity($_SESSION['user_id'], 'admin', 'update_settings', 'ล้างช่วงเวลา login ครู');
            $success_messages[] = 'ล้างช่วงเวลา login ครูเรียบร้อยแล้ว';
        } elseif (!empty($t_start) && !empty($t_end)) {
            $t_start_db = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $t_start)));
            $t_end_db   = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $t_end)));
            if ($t_end_db <= $t_start_db) {
                $error_messages[] = 'วันสิ้นสุดต้องหลังวันเริ่มต้น';
            } else {
                updateSystemSetting('teacher_edit_start', $t_start_db);
                updateSystemSetting('teacher_edit_end',   $t_end_db);
                logActivity($_SESSION['user_id'], 'admin', 'update_settings',
                    "ตั้งช่วงเวลา login ครู: $t_start_db ถึง $t_end_db");
                $success_messages[] = 'บันทึกช่วงเวลา login ครูเรียบร้อยแล้ว';
            }
        } else {
            $error_messages[] = 'กรุณากรอกทั้งวันเริ่มต้นและวันสิ้นสุด';
        }
    }

    // ===== จัดการการล้างข้อมูล =====
    if (isset($_POST['clear_data_type'])) {
        $clear_type = $_POST['clear_data_type'];
        switch ($clear_type) {
            case 'reset_selections':
                $reset_sql = "UPDATE students SET selection_status = 0, club_id = NULL";
                if (mysqli_query($conn, $reset_sql)) {
                    $affected_rows = mysqli_affected_rows($conn);
                    logActivity($_SESSION['user_id'], 'admin', 'reset_selections', "รีเซ็ตการเลือกชุมนุม: $affected_rows รายการ");
                    $success_messages[] = "รีเซ็ตการเลือกชุมนุมเรียบร้อยแล้ว ($affected_rows รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาดในการรีเซ็ต: ' . mysqli_error($conn);
                }
                break;
            case 'clear_students':
                $count_result   = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
                $total_students = mysqli_fetch_assoc($count_result)['total'];
                if (mysqli_query($conn, "DELETE FROM students")) {
                    logActivity($_SESSION['user_id'], 'admin', 'clear_students', "ลบข้อมูลนักเรียน: $total_students รายการ");
                    $success_messages[] = "ลบข้อมูลนักเรียนทั้งหมดเรียบร้อยแล้ว ($total_students รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
                }
                break;
            case 'clear_clubs':
                $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM clubs");
                $total_clubs  = mysqli_fetch_assoc($count_result)['total'];
                mysqli_query($conn, "UPDATE students SET selection_status = 0, club_id = NULL");
                if (mysqli_query($conn, "DELETE FROM clubs")) {
                    logActivity($_SESSION['user_id'], 'admin', 'clear_clubs', "ลบข้อมูลชุมนุม: $total_clubs รายการ");
                    $success_messages[] = "ลบข้อมูลชุมนุมทั้งหมดเรียบร้อยแล้ว ($total_clubs รายการ)";
                } else {
                    $error_messages[] = 'เกิดข้อผิดพลาด: ' . mysqli_error($conn);
                }
                break;
        }
    }

    if (!empty($success_messages)) $_SESSION['success_messages'] = $success_messages;
    if (!empty($error_messages))   $_SESSION['error_messages']   = $error_messages;

    header("Location: admin_settings.php");
    exit;
}

// ===== ดึงค่าตั้งค่าปัจจุบัน =====
$academic_year = getSystemSetting('academic_year') ?: '2568';
$semester      = getSystemSetting('semester')      ?: '1';
$school_name   = getSystemSetting('school_name')   ?: 'โรงเรียนแก่นนครวิทยาลัย';

// Registration period
$reg_start       = getSystemSetting('registration_start') ?: '';
$reg_end         = getSystemSetting('registration_end')   ?: '';
$reg_start_input = !empty($reg_start) ? date('Y-m-d\TH:i', strtotime($reg_start)) : '';
$reg_end_input   = !empty($reg_end)   ? date('Y-m-d\TH:i', strtotime($reg_end))   : '';
$now             = date('Y-m-d H:i:s');
$reg_active      = !empty($reg_start) && !empty($reg_end) && $now >= $reg_start && $now <= $reg_end;
// sync registration_open อัตโนมัติ
if (!empty($reg_start) && !empty($reg_end)) {
    updateSystemSetting('registration_open', $reg_active ? 'true' : 'false');
}

// Teacher edit period
$teacher_edit_start   = getSystemSetting('teacher_edit_start') ?: '';
$teacher_edit_end     = getSystemSetting('teacher_edit_end')   ?: '';
$teacher_start_input  = !empty($teacher_edit_start) ? date('Y-m-d\TH:i', strtotime($teacher_edit_start)) : '';
$teacher_end_input    = !empty($teacher_edit_end)   ? date('Y-m-d\TH:i', strtotime($teacher_edit_end))   : '';
$teacher_period_active = !empty($teacher_edit_start) && !empty($teacher_edit_end)
    && $now >= $teacher_edit_start && $now <= $teacher_edit_end;

// สถิติ
$students_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total,
     SUM(CASE WHEN selection_status=1 THEN 1 ELSE 0 END) as selected,
     SUM(CASE WHEN selection_status=0 THEN 1 ELSE 0 END) as not_selected
     FROM students"));
$clubs_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total, SUM(max_members) as total_capacity,
     SUM(CASE WHEN is_locked=1 THEN 1 ELSE 0 END) as locked_count FROM clubs"));
$teachers_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers"));
$registered_members = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as cnt FROM students WHERE selection_status=1 AND club_id IS NOT NULL"))['cnt'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }

        /* Period cards */
        .reg-period-card     { border-left: 4px solid #0d6efd; }
        .teacher-period-card { border-left: 4px solid #198754; }

        .period-status-badge {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 10px 16px; border-radius: 8px; font-size: .9rem;
        }
        .period-status-badge.active   { background: #d1e7dd; color: #0a3622; }
        .period-status-badge.inactive { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .period-status-badge.pending  { background: #fff3cd; color: #664d03; }
        .period-status-badge.expired  { background: #f8d7da; color: #58151c; }

        .timeline-line {
            display: flex; align-items: center; gap: 0;
            margin: 16px 0 8px;
        }
        .timeline-dot {
            width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0;
        }
        .timeline-bar {
            flex: 1; height: 6px; border-radius: 3px;
        }
        .timeline-bar-fill {
            height: 6px; border-radius: 3px; transition: width .4s;
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
            <?php include 'admin_sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">ตั้งค่าระบบ</h1>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_messages'])): ?>
                    <?php foreach ($_SESSION['success_messages'] as $msg): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; unset($_SESSION['success_messages']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_messages'])): ?>
                    <?php foreach ($_SESSION['error_messages'] as $msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; unset($_SESSION['error_messages']); ?>
                <?php endif; ?>

                <!-- System Statistics -->
                <div class="row mb-4">
                    <div class="col-12"><h4 class="mb-3"><i class="fas fa-chart-bar text-primary"></i> สถิติระบบ</h4></div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-2x text-primary mb-3"></i>
                                <h5>จำนวนนักเรียน</h5>
                                <h3 class="text-primary"><?php echo number_format($students_stats['total']); ?></h3>
                                <small class="text-muted">
                                    เลือกแล้ว: <?php echo number_format($students_stats['selected']); ?> |
                                    ยังไม่เลือก: <?php echo number_format($students_stats['not_selected']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-info mb-3"></i>
                                <h5>จำนวนชุมนุม</h5>
                                <h3 class="text-info"><?php echo number_format($clubs_stats['total']); ?></h3>
                                <small class="text-muted">
                                    รับได้: <?php echo number_format($clubs_stats['total_capacity']); ?> คน |
                                    ล็อก: <?php echo number_format($clubs_stats['locked_count']); ?> ชุมนุม
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-chalkboard-teacher fa-2x text-success mb-3"></i>
                                <h5>จำนวนครู</h5>
                                <h3 class="text-success"><?php echo number_format($teachers_stats['total']); ?></h3>
                                <small class="text-muted">ที่ปรึกษาชุมนุม</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card stat-card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-user-check fa-2x text-warning mb-3"></i>
                                <h5>ลงทะเบียนแล้ว</h5>
                                <h3 class="text-warning"><?php echo number_format($registered_members); ?></h3>
                                <small class="text-muted">
                                    <?php
                                    $pct = $students_stats['total'] > 0
                                        ? round($registered_members / $students_stats['total'] * 100, 1) : 0;
                                    echo $pct . '%';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">

                        <!-- ====== การตั้งค่าทั่วไป ====== -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-cog me-2"></i>การตั้งค่าทั่วไป</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-calendar-alt me-1"></i>ปีการศึกษา</label>
                                            <input type="text" class="form-control" name="academic_year"
                                                   value="<?php echo htmlspecialchars($academic_year); ?>" placeholder="เช่น 2568">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-book me-1"></i>ภาคเรียนที่</label>
                                            <select class="form-select" name="semester">
                                                <option value="1" <?php echo $semester=='1'?'selected':''; ?>>ภาคเรียนที่ 1</option>
                                                <option value="2" <?php echo $semester=='2'?'selected':''; ?>>ภาคเรียนที่ 2</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label"><i class="fas fa-school me-1"></i>ชื่อโรงเรียน</label>
                                            <input type="text" class="form-control" name="school_name"
                                                   value="<?php echo htmlspecialchars($school_name); ?>">
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>บันทึกการตั้งค่า
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- ====== ช่วงเวลาเปิด-ปิดระบบลงทะเบียนนักเรียน ====== -->
                        <div class="card mb-4 reg-period-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    ช่วงเวลาเปิด-ปิดระบบลงทะเบียนนักเรียน
                                </h5>
                            </div>
                            <div class="card-body">

                                <!-- สถานะปัจจุบัน -->
                                <div class="mb-4">
                                    <?php if (empty($reg_start)): ?>
                                        <div class="period-status-badge inactive">
                                            <i class="fas fa-lock fa-lg"></i>
                                            <div><strong>ปิดระบบ</strong><br><small>ยังไม่ได้ตั้งช่วงเวลา</small></div>
                                        </div>
                                    <?php elseif ($reg_active): ?>
                                        <div class="period-status-badge active">
                                            <i class="fas fa-check-circle fa-lg"></i>
                                            <div>
                                                <strong>เปิดรับลงทะเบียนอยู่ขณะนี้</strong><br>
                                                <small>
                                                    <?php echo date('d/m/Y H:i', strtotime($reg_start)); ?> น.
                                                    &ndash;
                                                    <?php echo date('d/m/Y H:i', strtotime($reg_end)); ?> น.
                                                </small>
                                            </div>
                                        </div>
                                    <?php elseif ($now < $reg_start): ?>
                                        <div class="period-status-badge pending">
                                            <i class="fas fa-clock fa-lg"></i>
                                            <div>
                                                <strong>ยังไม่ถึงเวลาเปิด</strong><br>
                                                <small>จะเปิด <?php echo date('d/m/Y H:i', strtotime($reg_start)); ?> น.</small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="period-status-badge expired">
                                            <i class="fas fa-times-circle fa-lg"></i>
                                            <div>
                                                <strong>หมดช่วงเวลาแล้ว</strong><br>
                                                <small>สิ้นสุด <?php echo date('d/m/Y H:i', strtotime($reg_end)); ?> น.</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Progress bar ช่วงเวลา -->
                                    <?php if (!empty($reg_start) && !empty($reg_end)): ?>
                                    <?php
                                        $ts_start  = strtotime($reg_start);
                                        $ts_end    = strtotime($reg_end);
                                        $ts_now    = time();
                                        $total_sec = max(1, $ts_end - $ts_start);
                                        $elapsed   = max(0, min($total_sec, $ts_now - $ts_start));
                                        $pct_time  = round($elapsed / $total_sec * 100);
                                        $bar_color = $reg_active ? '#0d6efd' : ($ts_now < $ts_start ? '#ffc107' : '#dc3545');
                                    ?>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span><i class="fas fa-play-circle me-1"></i><?php echo date('d/m/Y H:i', $ts_start); ?></span>
                                            <span><?php echo $pct_time; ?>%</span>
                                            <span><?php echo date('d/m/Y H:i', $ts_end); ?><i class="fas fa-stop-circle ms-1"></i></span>
                                        </div>
                                        <div class="progress" style="height:8px; border-radius:4px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width:<?php echo $pct_time; ?>%; background:<?php echo $bar_color; ?>;">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Form ตั้งช่วงเวลา -->
                                <form method="post" id="regPeriodForm">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-calendar-plus text-primary me-1"></i>วันที่/เวลาเปิดระบบ
                                            </label>
                                            <input type="datetime-local" class="form-control"
                                                   name="reg_start" id="reg_start"
                                                   value="<?php echo $reg_start_input; ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-calendar-minus text-danger me-1"></i>วันที่/เวลาปิดระบบ
                                            </label>
                                            <input type="datetime-local" class="form-control"
                                                   name="reg_end" id="reg_end"
                                                   value="<?php echo $reg_end_input; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-save me-1"></i>บันทึก
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="clearRegPeriod">
                                            <i class="fas fa-times me-1"></i>ล้างช่วงเวลา (ปิดระบบลงทะเบียน)
                                        </button>
                                    </div>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        ระบบจะเปิด/ปิดรับลงทะเบียนอัตโนมัติตามช่วงเวลาที่กำหนด
                                        หากต้องการปิดระบบทันที ให้กด "ล้างช่วงเวลา"
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- ====== ช่วงเวลา Login ครู ====== -->
                        <div class="card mb-4 teacher-period-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>
                                    ช่วงเวลาที่ครูสามารถแก้ไขข้อมูลชุมนุมได้
                                </h5>
                            </div>
                            <div class="card-body">

                                <!-- สถานะปัจจุบัน -->
                                <div class="mb-4">
                                    <?php if (empty($teacher_edit_start)): ?>
                                        <div class="period-status-badge inactive">
                                            <i class="fas fa-infinity fa-lg"></i>
                                            <div><strong>ไม่ได้จำกัดช่วงเวลา</strong><br><small>ครูสามารถ Login ได้ตลอดเวลา</small></div>
                                        </div>
                                    <?php elseif ($teacher_period_active): ?>
                                        <div class="period-status-badge active">
                                            <i class="fas fa-check-circle fa-lg"></i>
                                            <div>
                                                <strong>เปิดอยู่ขณะนี้</strong><br>
                                                <small>ถึง <?php echo date('d/m/Y H:i', strtotime($teacher_edit_end)); ?> น.</small>
                                            </div>
                                        </div>
                                    <?php elseif ($now < $teacher_edit_start): ?>
                                        <div class="period-status-badge pending">
                                            <i class="fas fa-clock fa-lg"></i>
                                            <div>
                                                <strong>ยังไม่ถึงเวลา</strong><br>
                                                <small>จะเปิด <?php echo date('d/m/Y H:i', strtotime($teacher_edit_start)); ?> น.</small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="period-status-badge expired">
                                            <i class="fas fa-lock fa-lg"></i>
                                            <div>
                                                <strong>หมดช่วงเวลาแล้ว</strong><br>
                                                <small>สิ้นสุด <?php echo date('d/m/Y H:i', strtotime($teacher_edit_end)); ?> น.</small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <form method="post" id="teacherPeriodForm">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-calendar-plus text-success me-1"></i>วันที่/เวลาเริ่มต้น
                                            </label>
                                            <input type="datetime-local" class="form-control"
                                                   name="teacher_edit_start"
                                                   value="<?php echo $teacher_start_input; ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">
                                                <i class="fas fa-calendar-minus text-danger me-1"></i>วันที่/เวลาสิ้นสุด
                                            </label>
                                            <input type="datetime-local" class="form-control"
                                                   name="teacher_edit_end"
                                                   value="<?php echo $teacher_end_input; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-save me-1"></i>บันทึก
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="clearTeacherPeriod">
                                            <i class="fas fa-times me-1"></i>ล้างช่วงเวลา (ครู Login ได้ตลอด)
                                        </button>
                                        <a href="teacher_login.php" target="_blank" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-external-link-alt me-1"></i>ทดสอบหน้า Login ครู
                                        </a>
                                    </div>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        หากไม่ต้องการจำกัดช่วงเวลา ให้กดปุ่ม "ล้างช่วงเวลา" ด้านบน
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    <!-- Quick Actions -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>การดำเนินการด่วน</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin_import.php" class="btn btn-outline-primary">
                                        <i class="fas fa-file-import me-1"></i> นำเข้าข้อมูล CSV
                                    </a>
                                    <a href="admin_reports.php" class="btn btn-outline-info">
                                        <i class="fas fa-chart-line me-1"></i> ดูรายงานระบบ
                                    </a>
                                    <button type="button" class="btn btn-outline-warning"
                                            data-bs-toggle="modal" data-bs-target="#resetSelectionsModal">
                                        <i class="fas fa-undo me-1"></i> รีเซ็ตการเลือกชุมนุม
                                    </button>
                                </div>
                                <hr>
                                <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>พื้นที่อันตราย</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#clearDataModal">
                                        <i class="fas fa-trash-alt me-1"></i> ลบข้อมูลทั้งหมด
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
    <div class="modal fade" id="resetSelectionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการรีเซ็ตการเลือกชุมนุม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning"><strong>คำเตือน:</strong> การดำเนินการนี้จะลบการเลือกชุมนุมของนักเรียนทั้งหมด</div>
                    <p>นักเรียนจะต้องเลือกชุมนุมใหม่อีกครั้ง</p>
                    <p><strong>จำนวนนักเรียนที่เลือกชุมนุมแล้ว:</strong> <?php echo number_format($students_stats['selected']); ?> คน</p>
                    <p class="text-danger">คุณแน่ใจหรือไม่ที่จะรีเซ็ตการเลือกชุมนุมทั้งหมด?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="clear_data_type" value="reset_selections">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-undo me-1"></i>ยืนยันการรีเซ็ต</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Data Modal -->
    <div class="modal fade" id="clearDataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>ลบข้อมูลทั้งหมด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger"><strong>อันตราย!</strong> การดำเนินการนี้จะลบข้อมูลอย่างถาวร ไม่สามารถกู้คืนได้</div>
                    <form method="post" id="clearDataForm">
                        <div class="mb-3">
                            <label class="form-label">เลือกประเภทข้อมูลที่ต้องการลบ:</label>
                            <select class="form-select" name="clear_data_type" id="clear_data_type" required>
                                <option value="">-- เลือกประเภทข้อมูล --</option>
                                <option value="reset_selections">รีเซ็ตการเลือกชุมนุมเท่านั้น (<?php echo number_format($students_stats['selected']); ?> รายการ)</option>
                                <option value="clear_students">ลบข้อมูลนักเรียนทั้งหมด (<?php echo number_format($students_stats['total']); ?> รายการ)</option>
                                <option value="clear_clubs">ลบข้อมูลชุมนุมทั้งหมด (<?php echo number_format($clubs_stats['total']); ?> รายการ)</option>
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
                            <label class="form-label">พิมพ์ "DELETE" เพื่อยืนยัน:</label>
                            <input type="text" class="form-control" id="confirmText" placeholder="DELETE" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="clearDataForm" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="fas fa-trash-alt me-1"></i>ยืนยันการลบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ล้างช่วงเวลาลงทะเบียน
        document.getElementById('clearRegPeriod').addEventListener('click', function () {
            if (confirm('ต้องการล้างช่วงเวลา? ระบบจะปิดรับลงทะเบียนทันที')) {
                const form = document.getElementById('regPeriodForm');
                form.querySelector('[name="reg_start"]').value = '';
                form.querySelector('[name="reg_end"]').value   = '';
                form.submit();
            }
        });

        // ล้างช่วงเวลาครู
        document.getElementById('clearTeacherPeriod').addEventListener('click', function () {
            if (confirm('ต้องการล้างช่วงเวลา? ครูจะสามารถ Login ได้ตลอดเวลา')) {
                const form = document.getElementById('teacherPeriodForm');
                form.querySelector('[name="teacher_edit_start"]').value = '';
                form.querySelector('[name="teacher_edit_end"]').value   = '';
                form.submit();
            }
        });

        // ยืนยันการลบข้อมูล
        function checkDeleteConfirm() {
            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = !(
                document.getElementById('confirmText').value === 'DELETE' &&
                document.getElementById('confirmDelete').checked &&
                document.getElementById('clear_data_type').value
            );
        }
        document.getElementById('confirmText').addEventListener('input', checkDeleteConfirm);
        document.getElementById('confirmDelete').addEventListener('change', checkDeleteConfirm);
        document.getElementById('clear_data_type').addEventListener('change', checkDeleteConfirm);

        document.getElementById('clearDataModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('clearDataForm').reset();
            document.getElementById('confirmDeleteBtn').disabled = true;
        });

        document.getElementById('clearDataForm').addEventListener('submit', function (e) {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะดำเนินการนี้? การกระทำนี้ไม่สามารถยกเลิกได้')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>