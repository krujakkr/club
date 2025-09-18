<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// จัดการการลบครู
if (isset($_GET['delete']) && isset($_GET['teacher_id'])) {
    $teacher_id = mysqli_real_escape_string($conn, $_GET['teacher_id']);
    
    // ตรวจสอบว่าครูนี้เป็นที่ปรึกษาชุมนุมหรือไม่
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
    
    // Redirect เพื่อป้องกัน resubmission
    header("Location: admin_teachers.php");
    exit;
}

// จัดการการเพิ่มครูใหม่
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_teacher') {
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $teacher_code = mysqli_real_escape_string($conn, $_POST['teacher_code']);
    $telephon = mysqli_real_escape_string($conn, $_POST['telephon']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    
    // ตรวจสอบว่ารหัสครูซ้ำหรือไม่
    $check_sql = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
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
    $teacher_id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $teacher_code = mysqli_real_escape_string($conn, $_POST['teacher_code']);
    $telephon = mysqli_real_escape_string($conn, $_POST['telephon']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    
    // ตรวจสอบว่ารหัสครูใหม่ซ้ำกับคนอื่นหรือไม่ (ถ้าเปลี่ยนรหัส)
    if ($teacher_id != $original_teacher_id) {
        $check_sql = "SELECT teacher_id FROM teachers WHERE teacher_id = '$teacher_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error_message'] = "รหัสครู $teacher_id มีในระบบแล้ว กรุณาใช้รหัสอื่น";
            header("Location: admin_teachers.php");
            exit;
        }
    }
    
    $update_sql = "UPDATE teachers SET 
                   teacher_id = '$teacher_id',
                   firstname = '$firstname', 
                   lastname = '$lastname', 
                   teacher_code = '$teacher_code', 
                   telephon = '$telephon', 
                   department = '$department'
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

// ดึงข้อมูลครูทั้งหมด
$teachers_sql = "SELECT t.*, 
                 COUNT(c.club_id) as club_count 
                 FROM teachers t 
                 LEFT JOIN clubs c ON t.teacher_id = c.teacher_id 
                 GROUP BY t.teacher_id 
                 ORDER BY t.teacher_id";
$teachers_result = mysqli_query($conn, $teachers_sql);

// ดึงรายการแผนกที่มีอยู่
$departments_sql = "SELECT DISTINCT department FROM teachers WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_sql);
$departments = [];
if ($departments_result) {
    while ($row = mysqli_fetch_assoc($departments_result)) {
        $departments[] = $row['department'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลครู - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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
                            <a class="nav-link active" href="admin_teachers.php">
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
                            <a class="nav-link" href="admin_settings.php">
                                <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">จัดการข้อมูลครู</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                            <i class="fas fa-plus"></i> เพิ่มครูใหม่
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Teachers Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="teachersTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="8%">รหัสครู</th>
                                        <th width="15%">ชื่อ-นามสกุล</th>
                                        <th width="10%">รหัสประจำตัว</th>
                                        <th width="12%">เบอร์โทรศัพท์</th>
                                        <th width="15%">แผนก/กลุ่มสาระ</th>
                                        <th width="10%">จำนวนชุมนุมที่ดูแล</th>
                                        <th width="20%">ชุมนุมที่ดูแล</th>
                                        <th width="10%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($teachers_result && mysqli_num_rows($teachers_result) > 0): ?>
                                        <?php while($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                            <?php
                                            // ดึงรายชื่อชุมนุมที่ครูคนนี้ดูแล
                                            $clubs_sql = "SELECT club_name FROM clubs WHERE teacher_id = '{$teacher['teacher_id']}'";
                                            $clubs_result = mysqli_query($conn, $clubs_sql);
                                            $club_names = [];
                                            if ($clubs_result) {
                                                while ($club = mysqli_fetch_assoc($clubs_result)) {
                                                    $club_names[] = $club['club_name'];
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['teacher_code'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['telephon'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($teacher['department'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-info"><?php echo $teacher['club_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($club_names)): ?>
                                                        <small><?php echo implode('<br>', array_map('htmlspecialchars', $club_names)); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
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
                                        <tr>
                                            <td colspan="8" class="text-center">ไม่พบข้อมูลครู</td>
                                        </tr>
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
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTeacherModalLabel">เพิ่มครูใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addTeacherForm" method="post">
                        <input type="hidden" name="action" value="add_teacher">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_teacher_id" class="form-label">รหัสครู <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_teacher_id" name="teacher_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_teacher_code" class="form-label">รหัสประจำตัวครู</label>
                                <input type="text" class="form-control" id="add_teacher_code" name="teacher_code">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_firstname" name="firstname" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_lastname" name="lastname" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="add_telephon" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control" id="add_telephon" name="telephon">
                            </div>
                            <div class="col-md-6">
                                <label for="add_department" class="form-label">แผนก/กลุ่มสาระ</label>
                                <input type="text" class="form-control" id="add_department" name="department" 
                                       list="departmentsList">
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
    <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editTeacherModalLabel">แก้ไขข้อมูลครู</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editTeacherForm" method="post">
                        <input type="hidden" name="action" value="edit_teacher">
                        <input type="hidden" name="original_teacher_id" id="edit_original_teacher_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_teacher_id" class="form-label">รหัสครู <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_teacher_id" name="teacher_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_teacher_code" class="form-label">รหัสประจำตัวครู</label>
                                <input type="text" class="form-control" id="edit_teacher_code" name="teacher_code">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_telephon" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control" id="edit_telephon" name="telephon">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_department" class="form-label">แผนก/กลุ่มสาระ</label>
                                <input type="text" class="form-control" id="edit_department" name="department" 
                                       list="departmentsList2">
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

    <!-- jQuery, Bootstrap, and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // เริ่มต้น DataTable
            $('#teachersTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                "pageLength": 25,
                "order": [[0, "asc"]]
            });
        });
        
        // ฟังก์ชันแก้ไขข้อมูลครู
        function editTeacher(teacherId) {
            // ส่ง AJAX request เพื่อดึงข้อมูลครู
            $.ajax({
                url: 'get_teacher.php',
                type: 'GET',
                data: { teacher_id: teacherId },
                dataType: 'json',
                success: function(data) {
                    // ใส่ข้อมูลลงในฟอร์ม
                    $('#edit_original_teacher_id').val(data.teacher_id);
                    $('#edit_teacher_id').val(data.teacher_id);
                    $('#edit_firstname').val(data.firstname);
                    $('#edit_lastname').val(data.lastname);
                    $('#edit_teacher_code').val(data.teacher_code || '');
                    $('#edit_telephon').val(data.telephon || '');
                    $('#edit_department').val(data.department || '');
                    
                    // แสดง modal
                    var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                    editModal.show();
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                }
            });
        }
        
        // ฟังก์ชันลบข้อมูลครู
        function deleteTeacher(teacherId, teacherName, clubCount) {
            if (clubCount > 0) {
                alert('ไม่สามารถลบครูได้ เนื่องจากครูท่านนี้เป็นที่ปรึกษาชุมนุมอยู่ ' + clubCount + ' ชุมนุม\nกรุณาเปลี่ยนครูที่ปรึกษาชุมนุมเหล่านั้นก่อน');
                return;
            }
            
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลครู "' + teacherName + '"?\nการกระทำนี้ไม่สามารถยกเลิกได้')) {
                window.location.href = 'admin_teachers.php?delete=1&teacher_id=' + encodeURIComponent(teacherId);
            }
        }
        
        // ตรวจสอบเบอร์โทรศัพท์ (รับเฉพาะตัวเลข)
        $('#add_telephon, #edit_telephon').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>