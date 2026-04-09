<?php
require_once 'config.php';
$active_menu = 'teachers'; // เปลี่ยนตามตารางด้านล่าง
// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// จัดการการลบครู
if (isset($_GET['delete']) && isset($_GET['teacher_id'])) {
    $teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id']);
    
    $check_clubs_sql = "SELECT COUNT(*) as club_count FROM clubs WHERE teacher_id = '$teacher_id'";
    $check_clubs_result = mysqli_query($conn, $check_clubs_sql);
    $club_count = mysqli_fetch_assoc($check_clubs_result)['club_count'];
    
    if ($club_count > 0) {
        $_SESSION['error_message'] = "ไม่สามารถลบครูได้ เนื่องจากครูท่านนี้เป็นที่ปรึกษาชุมนุมอยู่ $club_count ชุมนุม";
    } else {
        $delete_sql = "DELETE FROM teachers WHERE teacher_id = '$teacher_id'";
        if (mysqli_query($conn, $delete_sql)) {
            logActivity($_SESSION['user_id'], 'admin', 'delete_teacher', "ลบข้อมูลครู ID: $teacher_id");
            $_SESSION['success_message'] = "ลบข้อมูลครูเรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . mysqli_error($conn);
        }
    }
    header("Location: admin_teachers.php");
    exit;
}

// จัดการการเพิ่มครูใหม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_teacher') {
    $teacher_id   = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $firstname    = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname     = mysqli_real_escape_string($conn, $_POST['lastname']);
    $teacher_code = mysqli_real_escape_string($conn, $_POST['teacher_code']);
    $telephon     = mysqli_real_escape_string($conn, $_POST['telephon']);
    $department   = mysqli_real_escape_string($conn, $_POST['department']);
    
    $check_sql    = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error_message'] = "รหัสครู $teacher_id มีในระบบแล้ว กรุณาใช้รหัสอื่น";
    } else {
        $insert_sql = "INSERT INTO teachers (teacher_id, firstname, lastname, teacher_code, telephon, department) 
                       VALUES ('$teacher_id', '$firstname', '$lastname', '$teacher_code', '$telephon', '$department')";
        if (mysqli_query($conn, $insert_sql)) {
            logActivity($_SESSION['user_id'], 'admin', 'add_teacher', "เพิ่มครูใหม่: $firstname $lastname");
            $_SESSION['success_message'] = "เพิ่มข้อมูลครูเรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . mysqli_error($conn);
        }
    }
    header("Location: admin_teachers.php");
    exit;
}

// จัดการการแก้ไขครู
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_teacher') {
    $original_teacher_id = mysqli_real_escape_string($conn, $_POST['original_teacher_id']);
    $teacher_id          = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $firstname           = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname            = mysqli_real_escape_string($conn, $_POST['lastname']);
    $teacher_code        = mysqli_real_escape_string($conn, $_POST['teacher_code']);
    $telephon            = mysqli_real_escape_string($conn, $_POST['telephon']);
    $department          = mysqli_real_escape_string($conn, $_POST['department']);
    
    if ($teacher_id != $original_teacher_id) {
        $check_sql    = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error_message'] = "รหัสครู $teacher_id มีในระบบแล้ว กรุณาใช้รหัสอื่น";
            header("Location: admin_teachers.php");
            exit;
        }
    }
    
    $update_sql = "UPDATE teachers SET 
                   teacher_id    = '$teacher_id',
                   firstname     = '$firstname', 
                   lastname      = '$lastname', 
                   teacher_code  = '$teacher_code', 
                   telephon      = '$telephon', 
                   department    = '$department'
                   WHERE teacher_id = '$original_teacher_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        logActivity($_SESSION['user_id'], 'admin', 'edit_teacher', "แก้ไขข้อมูลครู: $firstname $lastname");
        $_SESSION['success_message'] = "แก้ไขข้อมูลครูเรียบร้อยแล้ว";
    } else {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . mysqli_error($conn);
    }
    header("Location: admin_teachers.php");
    exit;
}

// ดึงข้อมูลครูทั้งหมด พร้อม log การแก้ไขชุมนุมล่าสุด
$teachers_sql = "
    SELECT 
        t.*,
        COUNT(DISTINCT c.club_id) AS club_count,
        -- หาว่าครูเคย login แล้วหรือยัง
        (SELECT created_at FROM logs 
         WHERE user_id = t.teacher_id AND user_type = 'teacher' AND action = 'login'
         ORDER BY created_at DESC LIMIT 1) AS last_login,
        -- หาว่าครูเคยแก้ไขชุมนุมแล้วหรือยัง
        (SELECT created_at FROM logs 
         WHERE user_id = t.teacher_id AND user_type = 'teacher' AND action = 'update_club'
         ORDER BY created_at DESC LIMIT 1) AS last_update,
        -- นับจำนวนครั้งที่แก้ไข
        (SELECT COUNT(*) FROM logs 
         WHERE user_id = t.teacher_id AND user_type = 'teacher' AND action = 'update_club') AS update_count
    FROM teachers t
    LEFT JOIN clubs c ON t.teacher_id = c.teacher_id
    GROUP BY t.teacher_id
    ORDER BY t.teacher_id
";
$teachers_result = mysqli_query($conn, $teachers_sql);

// ดึงรายการแผนกที่มีอยู่
$departments_sql    = "SELECT DISTINCT department FROM teachers WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_sql);
$departments        = [];
if ($departments_result) {
    while ($row = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $row['department'];
    }
}

// สถิติสรุปสถานะการแก้ไข
$stats_sql    = "
    SELECT
        COUNT(DISTINCT t.teacher_id) AS total_teachers,
        COUNT(DISTINCT CASE WHEN l.user_id IS NOT NULL THEN t.teacher_id END) AS updated_teachers
    FROM teachers t
    LEFT JOIN logs l ON l.user_id = t.teacher_id AND l.user_type = 'teacher' AND l.action = 'update_club'
    WHERE EXISTS (SELECT 1 FROM clubs c WHERE c.teacher_id = t.teacher_id)
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats        = mysqli_fetch_assoc($stats_result);
$total        = (int)$stats['total_teachers'];
$updated      = (int)$stats['updated_teachers'];
$not_updated  = $total - $updated;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลครู - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .sidebar { min-height: calc(100vh - 56px); }
        .nav-link { color: #333; }
        .nav-link:hover { background-color: #f8f9fa; }
        .nav-link.active { background-color: #0d6efd; color: white; }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-done      { background: #d1e7dd; color: #0a3622; }
        .status-pending   { background: #fff3cd; color: #664d03; }
        .status-no-club   { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }

        /* Progress bar summary */
        .summary-bar { background: #f8f9fa; border-radius: 12px; padding: 16px 20px; }
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
                    <h1 class="h2">จัดการข้อมูลครู</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
                        <!-- Filter Buttons -->
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm active" id="filterAll">
                                ทั้งหมด
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="filterDone">
                                <i class="fas fa-check me-1"></i>แก้ไขแล้ว
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" id="filterPending">
                                <i class="fas fa-clock me-1"></i>ยังไม่แก้ไข
                            </button>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                            <i class="fas fa-plus"></i> เพิ่มครูใหม่
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- ====== Summary Progress Bar ====== -->
                <div class="summary-bar mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><i class="fas fa-tasks text-primary me-2"></i>สถานะการแก้ไขข้อมูลชุมนุมของครู</strong>
                            <small class="text-muted ms-2">(เฉพาะครูที่มีชุมนุม)</small>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="status-badge status-done">
                                <i class="fas fa-check-circle"></i> แก้ไขแล้ว <?php echo $updated; ?> คน
                            </span>
                            <span class="status-badge status-pending">
                                <i class="fas fa-clock"></i> ยังไม่แก้ไข <?php echo $not_updated; ?> คน
                            </span>
                        </div>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 6px;">
                        <?php $pct = $total > 0 ? round($updated / $total * 100) : 0; ?>
                        <div class="progress-bar bg-success" style="width: <?php echo $pct; ?>%"></div>
                        <div class="progress-bar bg-warning" style="width: <?php echo (100 - $pct); ?>%"></div>
                    </div>
                    <div class="text-end mt-1">
                        <small class="text-muted"><?php echo $pct; ?>% เสร็จสิ้น (<?php echo $updated; ?>/<?php echo $total; ?> คน)</small>
                    </div>
                </div>
                <!-- ====== End Summary ====== -->

                <!-- Teachers Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="teachersTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>รหัสครู</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>เบอร์โทรศัพท์</th>
                                        <th>แผนก/กลุ่มสาระ</th>
                                        <th class="text-center">ชุมนุม</th>
                                        <th class="text-center">สถานะการแก้ไขชุมนุม</th>
                                        <th>แก้ไขล่าสุด</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($teachers_result && mysqli_num_rows($teachers_result) > 0): ?>
                                        <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                            <?php
                                            $has_club   = $teacher['club_count'] > 0;
                                            $has_update = !empty($teacher['last_update']);

                                            if (!$has_club) {
                                                $status_class = 'status-no-club';
                                                $status_icon  = 'fas fa-minus';
                                                $status_text  = 'ไม่มีชุมนุม';
                                                $row_filter   = 'no-club';
                                            } elseif ($has_update) {
                                                $status_class = 'status-done';
                                                $status_icon  = 'fas fa-check-circle';
                                                $status_text  = 'แก้ไขแล้ว';
                                                $row_filter   = 'done';
                                            } else {
                                                $status_class = 'status-pending';
                                                $status_icon  = 'fas fa-clock';
                                                $status_text  = 'ยังไม่แก้ไข';
                                                $row_filter   = 'pending';
                                            }
                                            ?>
                                            <tr data-filter="<?php echo $row_filter; ?>">
                                                <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['telephon'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['department'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <?php if ($has_club): ?>
                                                        <span class="badge bg-info" title="ดูชุมนุม" 
                                                              style="cursor:pointer"
                                                              onclick="showClubs('<?php echo $teacher['teacher_id']; ?>')">
                                                            <?php echo $teacher['club_count']; ?> ชุมนุม
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <i class="<?php echo $status_icon; ?>"></i>
                                                        <?php echo $status_text; ?>
                                                        <?php if ($has_update && $teacher['update_count'] > 1): ?>
                                                            <small class="opacity-75">(<?php echo $teacher['update_count']; ?> ครั้ง)</small>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($has_update): ?>
                                                        <span class="text-success small">
                                                            <i class="fas fa-edit me-1"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($teacher['last_update'])); ?>
                                                        </span>
                                                    <?php elseif (!empty($teacher['last_login'])): ?>
                                                        <span class="text-warning small">
                                                            <i class="fas fa-sign-in-alt me-1"></i>
                                                            Login: <?php echo date('d/m/Y H:i', strtotime($teacher['last_login'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">ยังไม่เคย Login</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-primary btn-sm mb-1"
                                                            onclick="editTeacher('<?php echo $teacher['teacher_id']; ?>')"
                                                            title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm mb-1"
                                                            onclick="deleteTeacher('<?php echo $teacher['teacher_id']; ?>', '<?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname'], ENT_QUOTES); ?>', <?php echo $teacher['club_count']; ?>)"
                                                            title="ลบ"
                                                            <?php echo $teacher['club_count'] > 0 ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8" class="text-center">ไม่พบข้อมูลครู</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">เพิ่มครูใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addTeacherForm" method="post">
                        <input type="hidden" name="action" value="add_teacher">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">รหัสครู <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="teacher_id" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รหัสประจำตัวครู</label>
                                <input type="text" class="form-control" name="teacher_code">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="firstname" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="lastname" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control add-phone" name="telephon">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">แผนก/กลุ่มสาระ</label>
                                <input type="text" class="form-control" name="department" list="departmentsList">
                                <datalist id="departmentsList">
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="addTeacherForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">แก้ไขข้อมูลครู</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTeacherForm" method="post">
                        <input type="hidden" name="action" value="edit_teacher">
                        <input type="hidden" name="original_teacher_id" id="edit_original_teacher_id">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">รหัสครู <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_teacher_id" name="teacher_id" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รหัสประจำตัวครู</label>
                                <input type="text" class="form-control" id="edit_teacher_code" name="teacher_code">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control edit-phone" id="edit_telephon" name="telephon">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">แผนก/กลุ่มสาระ</label>
                                <input type="text" class="form-control" id="edit_department" name="department" list="departmentsList2">
                                <datalist id="departmentsList2">
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="editTeacherForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clubs Modal (popup รายชื่อชุมนุม) -->
    <div class="modal fade" id="clubsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>ชุมนุมที่รับผิดชอบ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="clubsModalBody">กำลังโหลด...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // DataTable
        const table = $('#teachersTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json' },
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [7] }]
        });

        // Filter buttons
        const filters = { all: '', done: 'done', pending: 'pending' };
        $('#filterAll').on('click', function () { applyFilter('all', this); });
        $('#filterDone').on('click', function () { applyFilter('done', this); });
        $('#filterPending').on('click', function () { applyFilter('pending', this); });

        function applyFilter(type, btn) {
            $('.btn-group .btn').removeClass('active');
            $(btn).addClass('active');
            if (type === 'all') {
                $.fn.dataTable.ext.search = [];
            } else {
                $.fn.dataTable.ext.search = [function (settings, data, dataIndex) {
                    const row = table.row(dataIndex).node();
                    return $(row).data('filter') === filters[type];
                }];
            }
            table.draw();
        }

        // แก้ไขครู (AJAX)
        function editTeacher(teacherId) {
            $.ajax({
                url: 'get_teacher.php',
                type: 'GET',
                data: { teacher_id: teacherId },
                dataType: 'json',
                success: function (data) {
                    $('#edit_original_teacher_id').val(data.teacher_id);
                    $('#edit_teacher_id').val(data.teacher_id);
                    $('#edit_firstname').val(data.firstname);
                    $('#edit_lastname').val(data.lastname);
                    $('#edit_teacher_code').val(data.teacher_code || '');
                    $('#edit_telephon').val(data.telephon || '');
                    $('#edit_department').val(data.department || '');
                    new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
                },
                error: function () { alert('เกิดข้อผิดพลาดในการโหลดข้อมูล'); }
            });
        }

        // ลบครู
        function deleteTeacher(teacherId, teacherName, clubCount) {
            if (clubCount > 0) {
                alert('ไม่สามารถลบครูได้ เนื่องจากครูท่านนี้เป็นที่ปรึกษาชุมนุมอยู่ ' + clubCount + ' ชุมนุม\nกรุณาเปลี่ยนครูที่ปรึกษาชุมนุมเหล่านั้นก่อน');
                return;
            }
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลครู "' + teacherName + '"?\nการกระทำนี้ไม่สามารถยกเลิกได้')) {
                window.location.href = 'admin_teachers.php?delete=1&teacher_id=' + encodeURIComponent(teacherId);
            }
        }

        // แสดงรายชื่อชุมนุม (popup)
        function showClubs(teacherId) {
            $.ajax({
                url: 'get_teacher.php',
                type: 'GET',
                data: { teacher_id: teacherId, clubs: 1 },
                dataType: 'json',
                success: function (data) {
                    let html = '<ul class="list-group list-group-flush">';
                    if (data.clubs && data.clubs.length > 0) {
                        data.clubs.forEach(function (c) {
                            html += '<li class="list-group-item"><i class="fas fa-users text-info me-2"></i>' + c + '</li>';
                        });
                    } else {
                        html += '<li class="list-group-item text-muted">ไม่มีชุมนุม</li>';
                    }
                    html += '</ul>';
                    $('#clubsModalBody').html(html);
                    new bootstrap.Modal(document.getElementById('clubsModal')).show();
                },
                error: function () {
                    $('#clubsModalBody').html('<p class="text-danger">โหลดข้อมูลไม่สำเร็จ</p>');
                    new bootstrap.Modal(document.getElementById('clubsModal')).show();
                }
            });
        }

        // รับเฉพาะตัวเลขในช่องเบอร์โทร
        $(document).on('input', '.add-phone, .edit-phone', function () {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>