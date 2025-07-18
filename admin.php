<?php
require_once 'config.php';

// เปิดการแสดงข้อผิดพลาดเพื่อ debug
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// จัดการการยกเลิกการเลือกชุมนุม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_selection') {
    $student_id = $_POST['student_id'] ?? '';
    
    if (!empty($student_id)) {
        // ตรวจสอบข้อมูลนักเรียน
        $check_sql = "SELECT * FROM students WHERE student_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $student_id);
        $check_stmt->execute();
        $student_data = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($student_data) {
            // Reset การเลือกชุมนุม
            $reset_sql = "UPDATE students SET selection_status = 0, club_id = NULL WHERE student_id = ?";
            $reset_stmt = $conn->prepare($reset_sql);
            $reset_stmt->bind_param('s', $student_id);
            
            if ($reset_stmt->execute()) {
                $_SESSION['success_message'] = "ยกเลิกการเลือกชุมนุมของ " . $student_data['firstname'] . " " . $student_data['lastname'] . " เรียบร้อยแล้ว";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการยกเลิกการเลือกชุมนุม";
            }
            $reset_stmt->close();
        } else {
            $_SESSION['error_message'] = "ไม่พบข้อมูลนักเรียน";
        }
    }
    
    // Redirect กลับมาหน้าเดิมพร้อมกับ query parameters
    $redirect_url = 'admin.php';
    $query_params = [];
    if (!empty($_GET['search'])) $query_params['search'] = $_GET['search'];
    if (!empty($_GET['grade'])) $query_params['grade'] = $_GET['grade'];
    if (!empty($_GET['status'])) $query_params['status'] = $_GET['status'];
    if (!empty($_GET['page'])) $query_params['page'] = $_GET['page'];
    
    if (!empty($query_params)) {
        $redirect_url .= '?' . http_build_query($query_params);
    }
    
    header("Location: $redirect_url");
    exit;
}

// ตัวแปรสำหรับการค้นหาและแบ่งหน้า
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// สร้าง SQL query สำหรับค้นหาและกรอง
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(s.student_id LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($grade_filter)) {
    $where_conditions[] = "s.grade_level = ?";
    $params[] = $grade_filter;
    $param_types .= 's';
}

if ($status_filter !== '') {
    if ($status_filter == '1') {
        $where_conditions[] = "s.selection_status = 1";
    } else {
        $where_conditions[] = "s.selection_status = 0";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// นับจำนวนรายการทั้งหมด
$count_sql = "SELECT COUNT(*) as total FROM students s $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// ดึงข้อมูลนักเรียน (เพิ่ม s.club_id เข้าไปใน SELECT)
$sql = "SELECT s.*, s.club_id, c.club_name 
        FROM students s 
        LEFT JOIN clubs c ON s.club_id = c.club_id 
        $where_clause 
        ORDER BY s.grade_level, s.class_room, s.class_number, s.lastname, s.firstname 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $records_per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param('ii', $records_per_page, $offset);
}
$stmt->execute();
$students = $stmt->get_result();

// ดึงรายการระดับชั้น
$grades_sql = "SELECT DISTINCT grade_level FROM students ORDER BY grade_level";
$grades_result = $conn->query($grades_sql);
$grades = [];
if ($grades_result && $grades_result->num_rows > 0) {
    while ($row = $grades_result->fetch_assoc()) {
        $grades[] = $row['grade_level'];
    }
}

// สถิติการเลือกชุมนุม
$stats_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) as selected_count,
    SUM(CASE WHEN selection_status = 0 THEN 1 ELSE 0 END) as not_selected_count
    FROM students";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_students' => 0,
    'selected_count' => 0,
    'not_selected_count' => 0
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการนักเรียน - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
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
        .badge-status {
            font-size: 0.875em;
        }
        .table th {
            background-color: #f8f9fa;
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
                            <i class="fas fa-user-circle"></i> ผู้ดูแลระบบ: <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?>
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
                            <a class="nav-link active" href="admin.php">
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
                            <a class="nav-link" href="import_csv.php">
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
                    <h1 class="h2">จัดการนักเรียน</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="import_csv.php" class="btn btn-success">
                                <i class="fas fa-file-import me-1"></i> นำเข้าข้อมูล
                            </a>
                            <button type="button" class="btn btn-primary" onclick="exportStudents()">
                                <i class="fas fa-download me-1"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- สถิติ -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-3"></i>
                                <h5 class="card-title">จำนวนนักเรียนทั้งหมด</h5>
                                <h3 class="text-primary"><?php echo number_format($stats['total_students']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                                <h5 class="card-title">เลือกชุมนุมแล้ว</h5>
                                <h3 class="text-success"><?php echo number_format($stats['selected_count']); ?></h3>
                                <small class="text-muted">
                                    <?php echo $stats['total_students'] > 0 ? round(($stats['selected_count'] / $stats['total_students']) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                                <h5 class="card-title">ยังไม่เลือกชุมนุม</h5>
                                <h3 class="text-warning"><?php echo number_format($stats['not_selected_count']); ?></h3>
                                <small class="text-muted">
                                    <?php echo $stats['total_students'] > 0 ? round(($stats['not_selected_count'] / $stats['total_students']) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ฟอร์มค้นหาและกรอง -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">ค้นหา</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="รหัสนักเรียน, ชื่อ, หรือนามสกุล">
                            </div>
                            <div class="col-md-3">
                                <label for="grade" class="form-label">ระดับชั้น</label>
                                <select class="form-select" id="grade" name="grade">
                                    <option value="">ทุกระดับชั้น</option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo htmlspecialchars($grade); ?>" 
                                                <?php echo $grade_filter === $grade ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">สถานะการเลือก</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>เลือกแล้ว</option>
                                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>ยังไม่เลือก</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ตารางข้อมูลนักเรียน -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            รายชื่อนักเรียน 
                            <span class="badge bg-primary"><?php echo number_format($total_records); ?> คน</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if ($students && $students->num_rows > 0): ?>
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

                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>รหัสนักเรียน</th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>ชั้น</th>
                                            <th>สถานะการเลือก</th>
                                            <th>รหัสชุมนุม</th>
                                            <th>ชุมนุม</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($student = $students->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>
                                                    <?php if (!empty($student['id_card'])): ?>
                                                        <br><small class="text-muted">บัตรประชาชน: <?php echo htmlspecialchars($student['id_card']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $class_display = '';
                                                    if (!empty($student['class'])) {
                                                        $class_display = $student['class'];
                                                    } else {
                                                        $class_display = $student['grade_level'];
                                                        if ($student['class_room']) {
                                                            $class_display .= '/' . $student['class_room'];
                                                        }
                                                    }
                                                    echo htmlspecialchars($class_display); 
                                                    ?>
                                                    <?php if ($student['class_number']): ?>
                                                        <br><small class="text-muted">เลขที่ <?php echo $student['class_number']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['selection_status'] == 1): ?>
                                                        <span class="badge bg-success badge-status">
                                                            <i class="fas fa-check"></i> เลือกแล้ว
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning badge-status">
                                                            <i class="fas fa-clock"></i> ยังไม่เลือก
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!empty($student['club_id'])): ?>
                                                        <span class="badge bg-primary">
                                                            <?php echo htmlspecialchars($student['club_id']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($student['club_name'])): ?>
                                                        <span class="badge bg-info text-dark">
                                                            <?php echo htmlspecialchars($student['club_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['selection_status'] == 1): ?>
                                                        <button type="button" class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#resetConfirmModal"
                                                                data-student-id="<?php echo htmlspecialchars($student['student_id']); ?>"
                                                                data-student-name="<?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>"
                                                                data-club-name="<?php echo htmlspecialchars($student['club_name']); ?>">
                                                            <i class="fas fa-undo"></i> ยกเลิกการเลือก
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($grade_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = max(1, $page - 5); $i <= min($total_pages, $page + 5); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($grade_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($grade_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">ไม่พบข้อมูลนักเรียน</h5>
                                <p class="text-muted">ลองค้นหาด้วยคำค้นอื่น หรือ <a href="import_csv.php">นำเข้าข้อมูลใหม่</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div class="modal fade" id="resetConfirmModal" tabindex="-1" aria-labelledby="resetConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="resetConfirmModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> ยืนยันการยกเลิกการเลือกชุมนุม
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="resetForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_selection">
                        <input type="hidden" name="student_id" id="resetStudentId" value="">
                        <!-- Pass current filter parameters -->
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="grade" value="<?php echo htmlspecialchars($grade_filter); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                        
                        <div class="alert alert-warning">
                            <strong>คำเตือน:</strong> การดำเนินการนี้จะยกเลิกการเลือกชุมนุมของนักเรียน
                        </div>
                        
                        <p><strong>นักเรียน:</strong> <span id="resetStudentName"></span></p>
                        <p><strong>ชุมนุมปัจจุบัน:</strong> <span id="resetClubName"></span></p>
                        <p class="text-danger">คุณแน่ใจหรือไม่ที่จะยกเลิกการเลือกชุมนุมของนักเรียนคนนี้?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo"></i> ยืนยันการยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle reset confirmation modal
        document.addEventListener('DOMContentLoaded', function() {
            const resetModal = document.getElementById('resetConfirmModal');
            resetModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const studentId = button.getAttribute('data-student-id');
                const studentName = button.getAttribute('data-student-name');
                const clubName = button.getAttribute('data-club-name');
                
                // Update modal content
                document.getElementById('resetStudentId').value = studentId;
                document.getElementById('resetStudentName').textContent = studentName;
                document.getElementById('resetClubName').textContent = clubName;
            });
        });
        
        function exportStudents() {
            // สร้าง URL สำหรับ export โดยใช้ filter ปัจจุบัน
            const search = document.getElementById('search').value;
            const grade = document.getElementById('grade').value;
            const status = document.getElementById('status').value;
            
            let exportUrl = 'export_students.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (grade) params.push('grade=' + encodeURIComponent(grade));
            if (status !== '') params.push('status=' + encodeURIComponent(status));
            
            exportUrl += params.join('&');
            
            // เปิดหน้าต่างใหม่สำหรับ download
            window.open(exportUrl, '_blank');
        }
    </script>
</body>
</html>

<?php
// ปิด statements และ connection
if (isset($stmt) && !$stmt->close()) {
    // Statement already closed or error
}
if (isset($conn)) $conn->close();
?>