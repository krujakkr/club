<?php
require_once 'config.php';

// เพิ่มการแสดงข้อผิดพลาดเพื่อการดีบัก
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ดึงข้อมูลการตั้งค่าระบบ
$registration_open = getSystemSetting('registration_open') === 'true';
$academic_year = getSystemSetting('academic_year');
$semester = getSystemSetting('semester');

// สร้างคำสั่ง SQL สำหรับดึงข้อมูลชุมนุมทั้งหมด
$sql = "SELECT c.*, t.firstname as teacher_firstname, t.lastname as teacher_lastname 
        FROM clubs c 
        LEFT JOIN teachers t ON c.teacher_id = t.teacher_id 
        ORDER BY c.club_name";
$result = mysqli_query($conn, $sql);

// ดึงข้อมูลครูทั้งหมดสำหรับการเลือกครูที่ปรึกษาชุมนุม
$teachers_sql = "SELECT * FROM teachers ORDER BY firstname, lastname";
$teachers_result = mysqli_query($conn, $teachers_sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการชุมนุม - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
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
            .form-switch .form-check-input {
                width: 3em;
            }
            /* เพิ่ม CSS สำหรับแสดงสถานะล็อก */
            .club-locked {
                opacity: 0.7;
                background-color: #f8f9fa;
            }
            /* เพิ่ม CSS เพื่อแสดงข้อความแจ้งเตือน */
            .alert {
                margin-bottom: 20px;
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
                            <a class="nav-link active" href="admin_clubs.php">
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
                    <h1 class="h2">จัดการข้อมูลชุมนุม</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClubModal">
                                <i class="fas fa-plus"></i> เพิ่มชุมนุมใหม่
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportClubs()">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>สถานะระบบ</h5>
                                        <p>
                                            <strong>ปีการศึกษา:</strong> <?php echo $academic_year; ?> 
                                            <strong>ภาคเรียนที่:</strong> <?php echo $semester; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <form method="post" action="toggle_registration.php" class="d-inline">
                                            <?php if ($registration_open): ?>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-lock"></i> ปิดระบบลงทะเบียน
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-lock-open"></i> เปิดระบบลงทะเบียน
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clubs Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered" id="clubsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">ลำดับ</th>
                                        <th width="5%">รหัส</th>  <!-- เพิ่มคอลัมภ์ club_id ตรงนี้ -->
                                        <th width="15%">ชื่อชุมนุม</th>
                                        <th width="15%">ครูที่ปรึกษา</th>
                                        <th width="10%">สถานที่เรียน</th>
                                        <th width="5%">จำนวนที่รับ</th>
                                        <th width="5%">ลงทะเบียนแล้ว</th>
                                        <th width="10%">ระดับชั้นที่รับ</th>
                                        <th width="5%">สถานะ</th>
                                        <th width="15%">คำอธิบาย</th>
                                        <th width="10%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                        <?php 
                                        $i = 1;
                                        if (mysqli_num_rows($result) > 0): 
                                            while($row = mysqli_fetch_assoc($result)): 
                                                $club_id = $row['club_id'];
                                                $registered = countClubMembers($club_id);
                                                $teacher_name = $row['teacher_firstname'] ? $row['teacher_firstname'] . ' ' . $row['teacher_lastname'] : 'ไม่มีครูที่ปรึกษา';
                                                $is_locked = $row['is_locked']; // ดึงค่าสถานะการล็อก
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $i++; ?></td>
                                            <td class="text-center"><?php echo $club_id; ?></td>  <!-- เพิ่มการแสดง club_id ตรงนี้ -->
                                            <td><?php echo $row['club_name']; ?><?php echo $is_locked ? ' <i class="fas fa-lock text-danger" title="ชุมนุมนี้ถูกล็อก"></i>' : ''; ?></td>
                                            <td><?php echo $teacher_name; ?></td>
                                            <td><?php echo $row['location']; ?></td>
                                            <td class="text-center"><?php echo $row['max_members']; ?></td>
                                            <td class="text-center"><?php echo $registered; ?></td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $row['allow_m1'] ? 'bg-success' : 'bg-secondary'; ?>">ม.1</span>
                                                <span class="badge <?php echo $row['allow_m2'] ? 'bg-success' : 'bg-secondary'; ?>">ม.2</span>
                                                <span class="badge <?php echo $row['allow_m3'] ? 'bg-success' : 'bg-secondary'; ?>">ม.3</span><br>
                                                <span class="badge <?php echo $row['allow_m4'] ? 'bg-success' : 'bg-secondary'; ?>">ม.4</span>
                                                <span class="badge <?php echo $row['allow_m5'] ? 'bg-success' : 'bg-secondary'; ?>">ม.5</span>
                                                <span class="badge <?php echo $row['allow_m6'] ? 'bg-success' : 'bg-secondary'; ?>">ม.6</span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($is_locked): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-lock"></i> ล็อก</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-lock-open"></i> เปิด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo nl2br(substr($row['description'], 0, 100)) . (strlen($row['description']) > 100 ? '...' : ''); ?></td>
                                            <td class="text-center">
                                                <a href="toggle_club_lock.php?club_id=<?php echo $club_id; ?>" class="btn <?php echo $is_locked ? 'btn-warning' : 'btn-dark'; ?> btn-sm mb-1" title="<?php echo $is_locked ? 'ปลดล็อกชุมนุม' : 'ล็อกชุมนุม'; ?>">
                                                    <i class="fas <?php echo $is_locked ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                                </a>
                                                <button type="button" class="btn btn-info btn-sm mb-1" onclick="viewMembers(<?php echo $club_id; ?>)" title="ดูรายชื่อสมาชิก">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <button type="button" class="btn btn-primary btn-sm mb-1" onclick="editClub(<?php echo $club_id; ?>)" title="แก้ไข">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm mb-1" onclick="deleteClub(<?php echo $club_id; ?>, '<?php echo addslashes($row['club_name']); ?>', <?php echo $registered; ?>)" title="ลบ">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php 
                                            endwhile; 
                                        else: 
                                        ?>
                                        <tr>
                                            <td colspan="11" class="text-center">ไม่พบข้อมูลชุมนุม</td>
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

    <!-- Add Club Modal -->
    <div class="modal fade" id="addClubModal" tabindex="-1" aria-labelledby="addClubModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addClubModalLabel">เพิ่มชุมนุมใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addClubForm" method="post" action="add_club.php">
                        <div class="mb-3">
                            <label for="club_name" class="form-label">ชื่อชุมนุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="club_name" name="club_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">คำอธิบายชุมนุม</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="location" class="form-label">สถานที่เรียน</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                            <div class="col-md-6">
                                <label for="max_members" class="form-label">จำนวนสมาชิกสูงสุดที่รับ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="max_members" name="max_members" min="1" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">ครูที่ปรึกษา</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">-- เลือกครูที่ปรึกษา --</option>
                                <?php 
                                mysqli_data_seek($teachers_result, 0);
                                while ($teacher = mysqli_fetch_assoc($teachers_result)): 
                                ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>">
                                        <?php echo $teacher['firstname'] . ' ' . $teacher['lastname']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ระดับชั้นที่เปิดรับ:</label>
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m1" name="allow_m1" value="1" checked>
                                        <label class="form-check-label" for="allow_m1">ม.1</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m2" name="allow_m2" value="1" checked>
                                        <label class="form-check-label" for="allow_m2">ม.2</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m3" name="allow_m3" value="1" checked>
                                        <label class="form-check-label" for="allow_m3">ม.3</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m4" name="allow_m4" value="1" checked>
                                        <label class="form-check-label" for="allow_m4">ม.4</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m5" name="allow_m5" value="1" checked>
                                        <label class="form-check-label" for="allow_m5">ม.5</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_m6" name="allow_m6" value="1" checked>
                                        <label class="form-check-label" for="allow_m6">ม.6</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="addClubForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Club Modal -->
    <div class="modal fade" id="editClubModal" tabindex="-1" aria-labelledby="editClubModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editClubModalLabel">แก้ไขข้อมูลชุมนุม</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editClubForm" method="post" action="update_club.php">
                        <input type="hidden" id="edit_club_id" name="club_id">
                        
                        <div class="mb-3">
                            <label for="edit_club_name" class="form-label">ชื่อชุมนุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_club_name" name="club_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">คำอธิบายชุมนุม</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_location" class="form-label">สถานที่เรียน</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_max_members" class="form-label">จำนวนสมาชิกสูงสุดที่รับ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_max_members" name="max_members" min="1" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_teacher_id" class="form-label">ครูที่ปรึกษา</label>
                            <select class="form-select" id="edit_teacher_id" name="teacher_id">
                                <option value="">-- เลือกครูที่ปรึกษา --</option>
                                <?php 
                                mysqli_data_seek($teachers_result, 0);
                                while ($teacher = mysqli_fetch_assoc($teachers_result)): 
                                ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>">
                                        <?php echo $teacher['firstname'] . ' ' . $teacher['lastname']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ระดับชั้นที่เปิดรับ:</label>
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m1" name="allow_m1" value="1">
                                        <label class="form-check-label" for="edit_allow_m1">ม.1</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m2" name="allow_m2" value="1">
                                        <label class="form-check-label" for="edit_allow_m2">ม.2</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m3" name="allow_m3" value="1">
                                        <label class="form-check-label" for="edit_allow_m3">ม.3</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m4" name="allow_m4" value="1">
                                        <label class="form-check-label" for="edit_allow_m4">ม.4</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m5" name="allow_m5" value="1">
                                        <label class="form-check-label" for="edit_allow_m5">ม.5</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="edit_allow_m6" name="allow_m6" value="1">
                                        <label class="form-check-label" for="edit_allow_m6">ม.6</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="alert alert-warning">
                                <strong>คำเตือน:</strong> หากลดจำนวนรับ หรือจำกัดระดับชั้นที่รับใหม่ อาจส่งผลกระทบต่อนักเรียนที่ลงทะเบียนแล้ว โปรดตรวจสอบให้แน่ใจก่อนบันทึก
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" form="editClubForm" class="btn btn-primary">บันทึก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Members Modal -->
    <div class="modal fade" id="viewMembersModal" tabindex="-1" aria-labelledby="viewMembersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewMembersModalLabel">รายชื่อสมาชิกชุมนุม</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="members-list">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">กำลังโหลด...</span>
                        </div>
                        <p>กำลังโหลดข้อมูล...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <a href="#" id="export-csv" class="btn btn-success" target="_blank">
                        <i class="fas fa-file-csv"></i> ส่งออก CSV
                    </a>
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
            $('#clubsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                }
            });
        });
        
        // ฟังก์ชันดูรายชื่อสมาชิก
        function viewMembers(clubId) {
            // อัพเดทลิงก์ส่งออก CSV
            document.getElementById('export-csv').href = 'export_csv.php?club_id=' + clubId;
            
            // โหลดข้อมูลสมาชิก
            $.ajax({
                url: 'get_members.php',
                type: 'GET',
                data: { club_id: clubId },
                success: function(response) {
                    document.getElementById('members-list').innerHTML = response;
                    var viewMembersModal = new bootstrap.Modal(document.getElementById('viewMembersModal'));
                    viewMembersModal.show();
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                }
            });
        }
        
        // ฟังก์ชันแก้ไขข้อมูลชุมนุม
        function editClub(clubId) {
            // ส่ง AJAX request เพื่อดึงข้อมูลชุมนุม
            $.ajax({
                url: 'get_club.php',
                type: 'GET',
                data: { club_id: clubId },
                dataType: 'json',
                success: function(data) {
                    // ใส่ข้อมูลลงในฟอร์ม
                    $('#edit_club_id').val(data.club_id);
                    $('#edit_club_name').val(data.club_name);
                    $('#edit_description').val(data.description);
                    $('#edit_location').val(data.location);
                    $('#edit_max_members').val(data.max_members);
                    $('#edit_teacher_id').val(data.teacher_id || '');
                    
                    // เซ็ตค่าชั้นเรียนที่เปิดรับ
                    $('#edit_allow_m1').prop('checked', data.allow_m1 == 1);
                    $('#edit_allow_m2').prop('checked', data.allow_m2 == 1);
                    $('#edit_allow_m3').prop('checked', data.allow_m3 == 1);
                    $('#edit_allow_m4').prop('checked', data.allow_m4 == 1);
                    $('#edit_allow_m5').prop('checked', data.allow_m5 == 1);
                    $('#edit_allow_m6').prop('checked', data.allow_m6 == 1);
                    
                    // แสดง modal
                    var editModal = new bootstrap.Modal(document.getElementById('editClubModal'));
                    editModal.show();
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                }
            });
        }
        
        // ฟังก์ชันลบข้อมูลชุมนุม
        function deleteClub(clubId, clubName, membersCount) {
            if (membersCount > 0) {
                alert('ไม่สามารถลบชุมนุมนี้ได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว ' + membersCount + ' คน\nกรุณาย้ายนักเรียนออกจากชุมนุมนี้ก่อน');
                return;
            }
            
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบชุมนุม "' + clubName + '"?')) {
                window.location.href = 'delete_club.php?club_id=' + clubId;
            }
        }

        // ฟังก์ชัน Export Clubs
        function exportClubs() {
            // เปิดหน้าต่างใหม่สำหรับ download
            window.open('export_clubs.php', '_blank');
        }

         $(document).ready(function() {
        // สามารถเพิ่มโค้ดนี้ก่อน DataTable initialization
        
        // จัดรูปแบบแถวที่ถูกล็อกให้มีสีที่แตกต่าง
        $('tr').each(function() {
            // ตรวจสอบว่ามี badge bg-danger ที่มีข้อความว่า "ล็อก" หรือไม่
            if ($(this).find('.badge.bg-danger').length > 0) {
                $(this).addClass('club-locked');
            }
        });
        
        // แสดงข้อความแจ้งเตือนถ้ามี
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('status') && urlParams.has('message')) {
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            
            let alertClass = 'alert-info';
            if (status === 'success') {
                alertClass = 'alert-success';
            } else if (status === 'error') {
                alertClass = 'alert-danger';
            }
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // เพิ่มข้อความแจ้งเตือนเหนือตาราง
            $('.card:contains("จัดการข้อมูลชุมนุม")').after(alertHtml);
        }
    });


    </script>
</body>
</html>