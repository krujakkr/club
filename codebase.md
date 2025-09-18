# add_club.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ตรวจสอบว่าส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $club_name = clean($conn, $_POST['club_name']);
    $description = clean($conn, $_POST['description']);
    $location = clean($conn, $_POST['location']);
    $max_members = (int)$_POST['max_members'];
    $teacher_id = isset($_POST['teacher_id']) && !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : "NULL";
    
    // รับค่าระดับชั้นที่เปิดรับ
    $allow_m1 = isset($_POST['allow_m1']) ? 1 : 0;
    $allow_m2 = isset($_POST['allow_m2']) ? 1 : 0;
    $allow_m3 = isset($_POST['allow_m3']) ? 1 : 0;
    $allow_m4 = isset($_POST['allow_m4']) ? 1 : 0;
    $allow_m5 = isset($_POST['allow_m5']) ? 1 : 0;
    $allow_m6 = isset($_POST['allow_m6']) ? 1 : 0;
    
    // ตรวจสอบว่าชื่อชุมนุมซ้ำหรือไม่
    $check_sql = "SELECT * FROM clubs WHERE club_name = '$club_name'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        alert("ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น");
        redirect("admin_clubs.php");
        exit;
    }
    
    // สร้างคำสั่ง SQL สำหรับเพิ่มข้อมูล
    $sql = "INSERT INTO clubs (club_name, description, location, max_members, teacher_id, 
                                allow_m1, allow_m2, allow_m3, allow_m4, allow_m5, allow_m6) 
            VALUES ('$club_name', '$description', '$location', $max_members, " . 
            ($teacher_id === "NULL" ? "NULL" : $teacher_id) . ", 
            $allow_m1, $allow_m2, $allow_m3, $allow_m4, $allow_m5, $allow_m6)";
    
    // ทำการเพิ่มข้อมูล
    if (mysqli_query($conn, $sql)) {
        $club_id = mysqli_insert_id($conn);
        
        // บันทึกประวัติการเพิ่มชุมนุม
        logActivity($_SESSION['user_id'], 'admin', 'add_club', "เพิ่มชุมนุมใหม่: $club_name");
        
        alert("เพิ่มชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการเพิ่มข้อมูล: " . mysqli_error($conn));
    }
    
    redirect("admin_clubs.php");
} else {
    // ถ้าไม่ได้ส่งข้อมูลมาจากฟอร์ม ให้กลับไปที่หน้าจัดการชุมนุม
    redirect("admin_clubs.php");
}
?>
```

# admin_clubs.php

```php
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
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClubModal">
                            <i class="fas fa-plus"></i> เพิ่มชุมนุมใหม่
                        </button>
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
                                            <td colspan="10" class="text-center">ไม่พบข้อมูลชุมนุม</td>
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
```

# admin_import.php

```php
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
```

# admin_login.php

```php
<?php
require_once 'config.php';

// ถ้าล็อกอินแล้วให้ไปที่หน้าแอดมิน
if (isLoggedIn() && isAdmin()) {
    redirect('admin.php');
}

// รับข้อมูลจากฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // ตรวจสอบข้อมูลในฐานข้อมูล
    $sql = "SELECT * FROM admins WHERE username = '$username'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $admin = mysqli_fetch_assoc($result);
        
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $admin['password'])) {
            // เก็บข้อมูลเซสชัน
            $_SESSION['user_id'] = $admin['admin_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_type'] = 'admin';
            
            // บันทึกประวัติการเข้าระบบ
            logActivity($admin['admin_id'], 'admin', 'login', 'แอดมินเข้าสู่ระบบ');
            
            redirect('admin.php');
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแล - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f5f5f5;
        }
        .login-form {
            max-width: 450px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h2 class="text-center mb-4">เข้าสู่ระบบผู้ดูแล</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">เข้าสู่ระบบ</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> กลับไปยังหน้าหลัก
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

# admin.php

```php
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

// ดึงข้อมูลนักเรียน
$sql = "SELECT s.*, c.club_name 
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
```

# config.php

```php
<?php
// ไฟล์ config.php
session_start();

// ข้อมูลการเชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'knwacth_club');
define('DB_PASSWORD', '2!06hhC2t');
define('DB_NAME', 'knwacth_club');

// เชื่อมต่อกับฐานข้อมูล MySQL
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn === false) {
    die("ERROR: ไม่สามารถเชื่อมต่อฐานข้อมูลได้. " . mysqli_connect_error());
}

// ตั้งค่าให้รองรับภาษาไทย
mysqli_set_charset($conn, "utf8");

// ฟังก์ชันสำหรับทำความสะอาดข้อมูลป้องกัน SQL Injection
function clean($conn, $str) {
    return mysqli_real_escape_string($conn, $str);
}

// ฟังก์ชันตรวจสอบสถานะการล็อกอิน
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ฟังก์ชันตรวจสอบสถานะผู้ดูแลระบบ
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// ฟังก์ชันตรวจสอบสถานะครู
function isTeacher() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher';
}

// ฟังก์ชันตรวจสอบสถานะนักเรียน
function isStudent() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student';
}

// ฟังก์ชันสำหรับแสดงข้อความแจ้งเตือน
function alert($message) {
    echo "<script>alert('$message');</script>";
}

// ฟังก์ชันสำหรับเปลี่ยนหน้า
function redirect($url) {
    echo "<script>window.location.href='$url';</script>";
    exit;
}

// ฟังก์ชันตรวจสอบเลขบัตรประชาชน 13 หลัก (ID Card Validation)
// แก้ไขฟังก์ชันตรวจสอบเลขบัตรประชาชนใน config.php
// ให้พบฟังก์ชันนี้ในไฟล์ config.php และแก้ไขเป็นดังนี้:

// ฟังก์ชันตรวจสอบเลขบัตรประชาชน 13 หลัก (ID Card Validation)
function validateIdCard($id_card) {
    // เปลี่ยนเป็นตรวจสอบแค่ว่าเป็นตัวเลข 13 หลักหรือไม่
    if (!preg_match('/^[0-9]{13}$/', $id_card)) {
        return false;
    }
    
    // ยกเลิกการตรวจสอบเลขบัตรขั้นสูง เนื่องจากเลขบัตรตัวอย่างอาจเป็นเลขสมมติ
    return true;
    
    /* ตรวจสอบความถูกต้องของเลขบัตรตามอัลกอริทึม (ปิดไว้ชั่วคราว)
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id_card[$i] * (13 - $i);
    }
    
    $check_digit = (11 - ($sum % 11)) % 10;
    
    return (int)$id_card[12] === $check_digit;
    */
}

// ฟังก์ชันบันทึกประวัติการทำงาน (Logs)
function logActivity($user_id, $user_type, $action, $details = '') {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_id = clean($conn, $user_id);
    $action = clean($conn, $action);
    $details = clean($conn, $details);
    
    $sql = "INSERT INTO logs (user_id, user_type, action, details, ip_address) 
            VALUES ('$user_id', '$user_type', '$action', '$details', '$ip')";
    
    mysqli_query($conn, $sql);
}

// ฟังก์ชันดึงข้อมูลการตั้งค่าระบบ
function getSystemSetting($setting_name) {
    global $conn;
    $setting_name = clean($conn, $setting_name);
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_name = '$setting_name'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['setting_value'];
    }
    
    return null;
}

// ฟังก์ชันอัพเดทการตั้งค่าระบบ
function updateSystemSetting($setting_name, $setting_value) {
    global $conn;
    $setting_name = clean($conn, $setting_name);
    $setting_value = clean($conn, $setting_value);
    
    $sql = "UPDATE system_settings SET setting_value = '$setting_value' WHERE setting_name = '$setting_name'";
    return mysqli_query($conn, $sql);
}

// แก้ไขฟังก์ชัน countClubMembers ในไฟล์ config.php

// ฟังก์ชันนับจำนวนสมาชิกในชุมนุม
function countClubMembers($club_id) {
    global $conn;
    $club_id = (int)$club_id;
    
    $sql = "SELECT COUNT(*) as total FROM students WHERE club_id = $club_id AND selection_status = 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['total'];
}
?>
```

# debug_csv.php

```php
<?php
// ไฟล์ debug_csv.php - สำหรับตรวจสอบไฟล์ CSV
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $uploadedFile = $_FILES['csvFile'];
    
    echo "<h3>การตรวจสอบไฟล์ CSV</h3>";
    
    // อ่านไฟล์ CSV
    $content = file_get_contents($uploadedFile['tmp_name']);
    
    echo "<h4>ข้อมูลไฟล์:</h4>";
    echo "ขนาดไฟล์: " . strlen($content) . " bytes<br>";
    echo "บรรทัดแรก (raw): " . htmlspecialchars(substr($content, 0, 200)) . "<br><br>";
    
    // ลบ BOM
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    
    // แยกบรรทัด
    $lines = explode("\n", $content);
    
    echo "<h4>บรรทัดแรก (หลังลบ BOM):</h4>";
    echo htmlspecialchars($lines[0]) . "<br><br>";
    
    // ใช้ str_getcsv
    $headers = str_getcsv($lines[0]);
    
    echo "<h4>Headers ที่อ่านได้:</h4>";
    echo "<pre>";
    foreach ($headers as $i => $header) {
        $clean_header = trim(str_replace(["\r", "\n"], '', $header));
        echo "$i: '$header' -> ล้างแล้ว: '$clean_header'\n";
    }
    echo "</pre>";
    
    // ตรวจสอบการจับคู่
    $expected_headers = ['student_id', 'id_card', 'firstname', 'lastname', 'grade_level', 'class_level', 'class_number', 'selection_status', 'club_id'];
    echo "<h4>การจับคู่ headers:</h4>";
    
    foreach ($expected_headers as $expected) {
        $found = false;
        foreach ($headers as $i => $header) {
            $clean_header = trim(str_replace(["\r", "\n"], '', $header));
            if (strtolower($clean_header) === $expected) {
                echo "✅ '$expected' พบที่ตำแหน่ง $i<br>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ '$expected' ไม่พบ<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Debug CSV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h2>ตรวจสอบไฟล์ CSV</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csvFile" accept=".csv" required>
        <button type="submit">ตรวจสอบ</button>
    </form>
</body>
</html>
```

# delete_club.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// รับค่า club_id จาก GET parameter
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($club_id <= 0) {
    alert("ไม่พบรหัสชุมนุม");
    redirect("admin_clubs.php");
    exit;
}

// ตรวจสอบว่ามีนักเรียนในชุมนุมนี้หรือไม่
$count = countClubMembers($club_id);
if ($count > 0) {
    alert("ไม่สามารถลบชุมนุมนี้ได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว $count คน");
    redirect("admin_clubs.php");
    exit;
}

// ดึงข้อมูลชุมนุมเพื่อบันทึกประวัติ
$sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $club = mysqli_fetch_assoc($result);
    $club_name = $club['club_name'];
    
    // สร้างคำสั่ง SQL สำหรับลบข้อมูล
    $delete_sql = "DELETE FROM clubs WHERE club_id = $club_id";
    
    // ทำการลบข้อมูล
    if (mysqli_query($conn, $delete_sql)) {
        // บันทึกประวัติการลบ
        logActivity($_SESSION['user_id'], 'admin', 'delete_club', "ลบชุมนุม: $club_name");
        
        alert("ลบชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการลบข้อมูล: " . mysqli_error($conn));
    }
} else {
    alert("ไม่พบข้อมูลชุมนุม");
}

// กลับไปที่หน้าจัดการชุมนุม
redirect("admin_clubs.php");
?>
```

# export_csv.php

```php
<?php
require_once 'config.php';

// รับ club_id จาก GET parameter
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

// ตรวจสอบว่า club_id ถูกส่งมาหรือไม่
if ($club_id <= 0) {
    echo "ไม่พบข้อมูลชุมนุม";
    exit;
}

// ดึงข้อมูลชุมนุม
$club_sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$club_result = mysqli_query($conn, $club_sql);

if (mysqli_num_rows($club_result) > 0) {
    $club_data = mysqli_fetch_assoc($club_result);
    $club_name = $club_data['club_name'];
    
    // ดึงข้อมูลครูที่ปรึกษา (ข้อมูลเพิ่มเติม)
    $teacher_id = $club_data['teacher_id'];
    $teacher_info = [
        'teacher_id' => 'ไม่มีข้อมูล',
        'firstname' => 'ไม่มีข้อมูล',
        'lastname' => 'ไม่มีข้อมูล',
        'telephon' => 'ไม่มีข้อมูล'
    ];
    
    if ($teacher_id) {
        $teacher_sql = "SELECT teacher_id, firstname, lastname, telephon FROM teachers WHERE teacher_id = $teacher_id";
        $teacher_result = mysqli_query($conn, $teacher_sql);
        if (mysqli_num_rows($teacher_result) > 0) {
            $teacher_data = mysqli_fetch_assoc($teacher_result);
            $teacher_info = $teacher_data;
            
            // แก้ไขเบอร์โทรให้มีเครื่องหมาย ' นำหน้าเพื่อรักษาเลข 0 ในเบอร์โทร
            if (!empty($teacher_info['telephon'])) {
                $teacher_info['telephon'] = "'" . $teacher_info['telephon'];
            }
        }
    }
    
    // ดึงข้อมูลสมาชิกในชุมนุม
    $members_sql = "SELECT s.* FROM students s 
                  WHERE s.club_id = $club_id 
                  ORDER BY s.class_room, s.class_number";
    $members_result = mysqli_query($conn, $members_sql);
    
    // ตั้งค่า header สำหรับไฟล์ CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="รายชื่อสมาชิกชุมนุม_' . $club_name . '.csv"');
    
    // สร้าง output stream
    $output = fopen('php://output', 'w');
    
    // เพิ่ม BOM เพื่อให้รองรับภาษาไทยใน Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // เขียนข้อมูลชุมนุม
    fputcsv($output, ['รายชื่อสมาชิกชุมนุม', $club_name]);
    
    // แสดงข้อมูลครูที่ปรึกษาแบบละเอียด
    fputcsv($output, ['ข้อมูลครูที่ปรึกษา']);
    fputcsv($output, ['รหัสครู', $teacher_info['teacher_id']]);
    fputcsv($output, ['ชื่อ', $teacher_info['firstname']]);
    fputcsv($output, ['นามสกุล', $teacher_info['lastname']]);
    fputcsv($output, ['เบอร์โทร', $teacher_info['telephon']]);
    
    fputcsv($output, ['สถานที่เรียน', $club_data['location']]);
    fputcsv($output, []); // บรรทัดว่าง
    
    // หัวข้อตาราง - ลบคอลัมน์เลขบัตรประชาชน
    fputcsv($output, ['ลำดับ', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ระดับชั้น', 'ห้อง', 'เลขที่']);
    
    // ข้อมูลสมาชิก
    $i = 1;
    if (mysqli_num_rows($members_result) > 0) {
        while ($member = mysqli_fetch_assoc($members_result)) {
            fputcsv($output, [
                $i++,
                $member['student_id'],
                $member['firstname'] . ' ' . $member['lastname'],
                $member['grade_level'],
                $member['class_room'],
                $member['class_number']
            ]);
        }
    }
    
    // ปิด output stream
    fclose($output);
} else {
    echo "ไม่พบข้อมูลชุมนุม";
}
exit;
?>
```

# export_students.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied');
}

// รับค่า filter จาก URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// สร้าง SQL query เหมือนกับหน้า admin.php
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

// ดึงข้อมูลนักเรียนทั้งหมด (ไม่มี LIMIT)
$sql = "SELECT s.*, c.club_name 
        FROM students s 
        LEFT JOIN clubs c ON s.club_id = c.club_id 
        $where_clause 
        ORDER BY s.grade_level, s.class_room, s.class_number, s.lastname, s.firstname";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// สร้างชื่อไฟล์ CSV
$filename = 'students_' . date('Y-m-d_H-i-s') . '.csv';

// ตั้งค่า headers สำหรับ download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// เพิ่ม BOM สำหรับ UTF-8
echo "\xEF\xBB\xBF";

// สร้าง CSV content
$output = fopen('php://output', 'w');

// Header ของ CSV
$headers = [
    'รหัสนักเรียน',
    'เลขบัตรประชาชน',
    'ชื่อ',
    'นามสกุล',
    'ระดับชั้น',
    'ห้อง',
    'เลขที่',
    'ชั้น',
    'สถานะการเลือก',
    'ชุมนุม',
    'วันที่สร้าง',
    'วันที่อัพเดต'
];
fputcsv($output, $headers);

// เขียนข้อมูลลงไฟล์ CSV
while ($row = $result->fetch_assoc()) {
    $class_display = '';
    if (!empty($row['class'])) {
        $class_display = $row['class'];
    } else {
        $class_display = $row['grade_level'];
        if ($row['class_room']) {
            $class_display .= '/' . $row['class_room'];
        }
    }
    
    $status_text = $row['selection_status'] == 1 ? 'เลือกแล้ว' : 'ยังไม่เลือก';
    
    $data = [
        $row['student_id'],
        $row['id_card'],
        $row['firstname'],
        $row['lastname'],
        $row['grade_level'],
        $row['class_room'],
        $row['class_number'],
        $class_display,
        $status_text,
        $row['club_name'] ?? '',
        $row['created_at'],
        $row['updated_at']
    ];
    
    fputcsv($output, $data);
}

fclose($output);
exit;
?>
```

# fix_admin.php

```php
<?php
// สคริปต์แก้ไขรหัสผ่านผู้ดูแลระบบ
// ใช้รันเพื่อรีเซ็ตรหัสผ่านของผู้ดูแลระบบ

// เชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // เปลี่ยนตามข้อมูลของคุณ
define('DB_PASSWORD', 'root1234');    // เปลี่ยนตามข้อมูลของคุณ
define('DB_NAME', 'club_system');

// เชื่อมต่อกับฐานข้อมูล MySQL
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn === false) {
    die("ERROR: ไม่สามารถเชื่อมต่อฐานข้อมูลได้. " . mysqli_connect_error());
}

// ข้อมูลผู้ดูแลระบบ
$username = "admin";
$new_password = "admin123"; // รหัสผ่านใหม่

// เข้ารหัสรหัสผ่านด้วย password_hash() ซึ่งเป็นฟังก์ชันมาตรฐานของ PHP
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// ตรวจสอบว่ามีผู้ดูแลระบบในฐานข้อมูลแล้วหรือไม่
$check_sql = "SELECT * FROM admins WHERE username = '$username'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    // ถ้ามีบัญชีผู้ดูแลระบบอยู่แล้ว ให้อัพเดทรหัสผ่าน
    $update_sql = "UPDATE admins SET password = '$hashed_password' WHERE username = '$username'";
    
    if (mysqli_query($conn, $update_sql)) {
        echo "รีเซ็ตรหัสผ่านผู้ดูแลระบบเรียบร้อยแล้ว<br>";
        echo "ชื่อผู้ใช้: $username<br>";
        echo "รหัสผ่านใหม่: $new_password<br>";
        
        // แสดงรหัสผ่านที่เข้ารหัสแล้วเพื่อการตรวจสอบ
        echo "<hr>";
        echo "รหัสผ่านที่เข้ารหัสแล้ว: $hashed_password<br>";
        
        // ทดสอบว่าการตรวจสอบรหัสผ่านทำงานถูกต้องหรือไม่
        echo "<hr>";
        echo "ทดสอบการตรวจสอบรหัสผ่าน:<br>";
        $verify_result = password_verify($new_password, $hashed_password);
        echo "ผลการตรวจสอบ: " . ($verify_result ? "ถูกต้อง" : "ไม่ถูกต้อง") . "<br>";
        
        echo "<hr>";
        echo "กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
    } else {
        echo "เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน: " . mysqli_error($conn);
    }
} else {
    // ถ้ายังไม่มีบัญชีผู้ดูแลระบบ ให้สร้างใหม่
    $insert_sql = "INSERT INTO admins (username, password) VALUES ('$username', '$hashed_password')";
    
    if (mysqli_query($conn, $insert_sql)) {
        echo "สร้างบัญชีผู้ดูแลระบบใหม่เรียบร้อยแล้ว<br>";
        echo "ชื่อผู้ใช้: $username<br>";
        echo "รหัสผ่าน: $new_password<br>";
        
        // แสดงรหัสผ่านที่เข้ารหัสแล้วเพื่อการตรวจสอบ
        echo "<hr>";
        echo "รหัสผ่านที่เข้ารหัสแล้ว: $hashed_password<br>";
        
        // ทดสอบว่าการตรวจสอบรหัสผ่านทำงานถูกต้องหรือไม่
        echo "<hr>";
        echo "ทดสอบการตรวจสอบรหัสผ่าน:<br>";
        $verify_result = password_verify($new_password, $hashed_password);
        echo "ผลการตรวจสอบ: " . ($verify_result ? "ถูกต้อง" : "ไม่ถูกต้อง") . "<br>";
        
        echo "<hr>";
        echo "กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
    } else {
        echo "เกิดข้อผิดพลาดในการสร้างบัญชีผู้ดูแลระบบ: " . mysqli_error($conn);
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
mysqli_close($conn);
?>
```

# get_club.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล']);
    exit;
}

// รับค่า club_id
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : 0;

if ($club_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ไม่พบรหัสชุมนุม']);
    exit;
}

// ดึงข้อมูลชุมนุม
$sql = "SELECT * FROM clubs WHERE club_id = $club_id";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $club = mysqli_fetch_assoc($result);
    
    // ส่งข้อมูลกลับในรูปแบบ JSON
    header('Content-Type: application/json');
    echo json_encode($club);
} else {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'ไม่พบข้อมูลชุมนุม']);
}
?>
```

# get_members.php

```php
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
```

# import_csv.php

```php
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
```

# index.php

```php
<?php
require_once 'config.php';

// ตรวจสอบว่าระบบเปิดให้ลงทะเบียนหรือไม่
$registration_open = getSystemSetting('registration_open') === 'true';
$academic_year = getSystemSetting('academic_year');
$semester = getSystemSetting('semester');

// รับค่าจากการค้นหาและ pagination
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 30;

// ตรวจสอบค่า per_page ที่ถูกต้อง
if (!in_array($per_page, [30, 100, 0])) {
    $per_page = 30;
}

// สร้างคำสั่ง SQL สำหรับดึงข้อมูลชุมนุมทั้งหมด
$where_conditions = [];
$sql_base = "FROM clubs c LEFT JOIN teachers t ON c.teacher_id = t.teacher_id";

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $where_conditions[] = "(c.club_name LIKE '%$search%' OR c.description LIKE '%$search%' OR c.location LIKE '%$search%' OR CONCAT(t.firstname, ' ', t.lastname) LIKE '%$search%')";
}

// ถ้ามีการล็อกอินและเป็นนักเรียน ให้กรองชุมนุมตามระดับชั้น
if (isLoggedIn() && isStudent()) {
    $student_id = $_SESSION['user_id'];
    $student_sql = "SELECT * FROM students WHERE student_id = '$student_id'";
    $student_result = mysqli_query($conn, $student_sql);
    $student_data = mysqli_fetch_assoc($student_result);
    
    // สร้างชื่อฟิลด์ allow_mx ตามระดับชั้น
    $grade_level = $student_data['grade_level'];
    // ดึงเลขระดับชั้นจากข้อความ โดยใช้ regex เพื่อรองรับรูปแบบต่างๆ เช่น "ม.5", "ม. 5", "ม5"
    preg_match('/[0-9]+/', $grade_level, $matches);
    $grade_number = !empty($matches) ? $matches[0] : null;
    
    if (!$grade_number) {
        // หากไม่สามารถดึงเลขระดับชั้นได้ ให้กำหนดค่าเริ่มต้นเป็น 1
        $grade_number = 1;
        echo "<!-- ไม่สามารถอ่านเลขระดับชั้นได้ กำหนดเป็น 1 -->";
    }
    
    $allow_field = "allow_m" . $grade_number;
    
    // แสดงค่าเพื่อดีบัก (สามารถลบออกเมื่อแก้ไขเสร็จ)
    echo "<!-- ระดับชั้น: " . $grade_level . " | ค่า grade_number: " . $grade_number . " | ฟิลด์ที่ใช้กรอง: " . $allow_field . " -->";
    
    // กรองชุมนุมตามระดับชั้นที่เปิดสอน (allow_mx = 1)
    $where_conditions[] = "c.$allow_field = 1";
}

// รวม WHERE conditions
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// นับจำนวนข้อมูลทั้งหมด
$count_sql = "SELECT COUNT(*) as total $sql_base $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// คำนวณ pagination
$total_pages = ($per_page > 0) ? ceil($total_records / $per_page) : 1;
$offset = ($per_page > 0) ? ($page - 1) * $per_page : 0;

// สร้าง SQL สำหรับดึงข้อมูล
$sql = "SELECT c.*, t.teacher_id, t.firstname as teacher_firstname, t.lastname as teacher_lastname, 
        t.telephon as teacher_phone, c.allow_m1, c.allow_m2, c.allow_m3, c.allow_m4, c.allow_m5, c.allow_m6,
        c.is_locked
        $sql_base 
        $where_clause 
        ORDER BY c.club_name";

// เพิ่ม LIMIT หากไม่ใช่แสดงทั้งหมด
if ($per_page > 0) {
    $sql .= " LIMIT $offset, $per_page";
}

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <style>
    body {
        font-family: 'Sarabun', sans-serif;
    }
    .club-available {
        background-color: rgba(40, 167, 69, 0.2);
        color: #155724;
    }
    .club-full {
        background-color: rgba(220, 53, 69, 0.2);
        color: #721c24;
    }
    /* เพิ่ม CSS สำหรับแถวที่ชุมนุมเต็ม */
    .row-full {
        background-color: rgba(255, 151, 45, 0.2) !important;
    }
    .row-full td {
        background-color: rgba(255, 151, 45, 0.2) !important;
    }
    .row-full:hover {
        background-color: rgba(255, 151, 45, 0.3) !important;
    }
    .row-full:hover td {
        background-color: rgba(255, 151, 45, 0.3) !important;
    }
    
    /* เพิ่ม CSS สำหรับแถวที่ชุมนุมถูกล็อก */
    .row-locked {
        background-color: rgba(108, 117, 125, 0.2) !important;
        opacity: 0.8;
    }
    .row-locked td {
        background-color: rgba(108, 117, 125, 0.2) !important;
    }
    .row-locked:hover {
        background-color: rgba(108, 117, 125, 0.3) !important;
    }
    .row-locked:hover td {
        background-color: rgba(108, 117, 125, 0.3) !important;
    }
    
    /* CSS สำหรับชุมนุมที่ถูกล็อก */
    .club-locked {
        background-color: rgba(108, 117, 125, 0.2);
        color: #343a40;
    }
    
    /* Override Bootstrap table striped */
    .table-striped > tbody > tr.row-full:nth-of-type(odd) > td,
    .table-striped > tbody > tr.row-full:nth-of-type(even) > td {
        background-color: rgba(255, 151, 45, 0.2) !important;
    }
    
    .table-striped > tbody > tr.row-locked:nth-of-type(odd) > td,
    .table-striped > tbody > tr.row-locked:nth-of-type(even) > td {
        background-color: rgba(108, 117, 125, 0.2) !important;
    }
    
    .btn-select {
        min-width: 80px;
    }
    
    /* ไอคอนลูกกุญแจ */
    .lock-icon {
        color: #6c757d;
        margin-left: 5px;
    }
    
    /* ส่วนที่เหลือของ CSS คงเดิม */
    .search-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .per-page-selector {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* CSS สำหรับแสดงระดับชั้นที่รับ */
    .grade-badge {
        display: inline-block;
        padding: 2px 6px;
        margin: 1px;
        font-size: 11px;
        font-weight: bold;
        border-radius: 3px;
        color: white;
    }
    
    .grade-accepted {
        background-color: #28a745;
    }
    
    .grade-not-accepted {
        background-color: #6c757d;
    }
    
    .grade-levels {
        line-height: 1.8;
    }
    
    @media (max-width: 768px) {
        .results-info {
            flex-direction: column;
            align-items: stretch;
        }
        .per-page-selector {
            justify-content: center;
        }
    }
</style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-school me-2"></i> โรงเรียนแก่นนครวิทยาลัย
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (!isLoggedIn()): ?>
                        <li class="nav-item">
                            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                            </button>
                        </li>
                    <?php elseif (isStudent()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['firstname'] . ' ' . $_SESSION['lastname']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php elseif (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin.php">
                                <i class="fas fa-cogs"></i> หน้าผู้ดูแลระบบ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">ระบบเลือกกิจกรรมชุมนุมออนไลน์</h4>
                        <h6 class="mb-0">ปีการศึกษา 2568 ภาคเรียนที่ <?php echo $semester; ?></h6>
                    </div>
                    <div class="card-body">
                        <?php if (isLoggedIn() && isStudent()): ?>
                            <?php
                            // ดึงข้อมูลนักเรียนที่ล็อกอินเข้าสู่ระบบ
                            $student_id = $_SESSION['user_id'];
                            $student_sql = "SELECT s.*, c.club_name 
                                        FROM students s 
                                        LEFT JOIN clubs c ON s.club_id = c.club_id 
                                        WHERE s.student_id = '$student_id'";
                            $student_result = mysqli_query($conn, $student_sql);
                            $student_data = mysqli_fetch_assoc($student_result);
                            ?>
                            <div class="alert alert-info">
                                <h5>ข้อมูลนักเรียน</h5>
                                <p>
                                    รหัสนักเรียน: <?php echo $student_data['student_id']; ?><br>
                                    ชื่อ-นามสกุล: <?php echo $student_data['firstname'] . ' ' . $student_data['lastname']; ?><br>
                                    ระดับชั้น: <?php echo $student_data['grade_level']; ?> ห้อง: <?php echo $student_data['class_room']; ?> เลขที่: <?php echo $student_data['class_number']; ?>
                                </p>
                                
                                <?php if ($student_data['selection_status']): ?>
                                    <div class="alert alert-success">
                                        <h6>คุณได้เลือกชุมนุม <strong><?php echo $student_data['club_name']; ?></strong> เรียบร้อยแล้ว</h6>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <h6>คุณยังไม่ได้เลือกชุมนุม กรุณาเลือกชุมนุมที่สนใจด้านล่าง</h6>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <h5>ยินดีต้อนรับสู่ระบบเลือกกิจกรรมชุมนุมออนไลน์</h5>
                                <p>กรุณาเข้าสู่ระบบเพื่อเลือกชุมนุมที่คุณสนใจ</p>
                                <div class="d-grid gap-2 col-md-4 mx-auto mt-3">
                                    <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                                        <i class="fas fa-sign-in-alt me-2"></i> เข้าสู่ระบบ
                                    </button>
                                </div>
                                <?php if (!$registration_open): ?>
                                    <div class="alert alert-danger mt-3">
                                        <h6>ขณะนี้ระบบปิดรับลงทะเบียนชั่วคราว กรุณาติดต่อผู้ดูแลระบบ</h6>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ช่องค้นหา -->
                        <div class="search-container">
                            <form method="GET" action="index.php" class="d-flex gap-3 align-items-end">
                                <div class="flex-grow-1">
                                    <label for="search" class="form-label"><i class="fas fa-search"></i> ค้นหาชุมนุม</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="ป้อนชื่อชุมนุม คำอธิบาย สถานที่ หรือชื่อครู">
                                </div>
                                <div>
                                    <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> ค้นหา
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="index.php?per_page=<?php echo $per_page; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i> ล้าง
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        
                        <!-- ข้อมูลผลลัพธ์และตัวเลือกจำนวนรายการ -->
                        <div class="results-info">
                            <div>
                                <span class="text-muted">
                                    <?php if (!empty($search)): ?>
                                        ผลการค้นหา "<?php echo htmlspecialchars($search); ?>" 
                                    <?php endif; ?>
                                    แสดง <?php echo min($per_page > 0 ? $per_page : $total_records, $total_records); ?> 
                                    จาก <?php echo number_format($total_records); ?> รายการ
                                    <?php if ($per_page > 0): ?>
                                        (หน้า <?php echo $page; ?> จาก <?php echo number_format($total_pages); ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="per-page-selector">
                                <span class="text-muted">แสดง:</span>
                                <div class="btn-group" role="group">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['per_page' => 30, 'page' => 1])); ?>" 
                                       class="btn btn-outline-primary <?php echo $per_page == 30 ? 'active' : ''; ?>">30</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['per_page' => 100, 'page' => 1])); ?>" 
                                       class="btn btn-outline-primary <?php echo $per_page == 100 ? 'active' : ''; ?>">100</a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['per_page' => 0, 'page' => 1])); ?>" 
                                       class="btn btn-outline-primary <?php echo $per_page == 0 ? 'active' : ''; ?>">ทั้งหมด</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr class="text-center">
                                        <th width="5%">ลำดับ</th>
                                        <th width="15%">ชื่อชุมนุม</th>
                                        <th width="20%">คำอธิบาย</th>
                                        <th width="10%">สถานที่เรียน</th>
                                        <th width="12%">ครูที่ปรึกษา</th>
                                        <th width="5%">จำนวนที่รับ</th>
                                        <th width="8%">ระดับชั้นที่รับ</th>
                                        <th width="5%">ลงทะเบียนแล้ว</th>
                                        <th width="5%">รายชื่อสมาชิก</th>
                                        <th width="10%">สถานะ</th>
                                        <?php if (isLoggedIn() && isStudent() && !$student_data['selection_status'] && $registration_open): ?>
                                        <th width="5%">เลือก</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $i = ($per_page > 0) ? ($page - 1) * $per_page + 1 : 1;
                                        if (mysqli_num_rows($result) > 0): 
                                            while($row = mysqli_fetch_assoc($result)): 
                                                $club_id = $row['club_id'];
                                                $registered = countClubMembers($club_id);
                                                $available = $row['max_members'] - $registered;
                                                $is_full = $available <= 0;
                                                $is_locked = isset($row['is_locked']) && $row['is_locked'] == 1;
                                                
                                                // กำหนด class และสถานะตามเงื่อนไข
                                                if ($is_locked) {
                                                    $row_class = 'row-locked';
                                                    $status_class = 'club-locked';
                                                } else if ($is_full) {
                                                    $row_class = 'row-full';
                                                    $status_class = 'club-full';
                                                } else {
                                                    $row_class = '';
                                                    $status_class = 'club-available';
                                                }
                                                
                                                $teacher_name = $row['teacher_firstname'] . ' ' . $row['teacher_lastname'];
                                                
                                                // สร้างการแสดงระดับชั้นที่รับ
                                                $grade_badges = '';
                                                for ($grade = 1; $grade <= 6; $grade++) {
                                                    $allow_field = 'allow_m' . $grade;
                                                    $is_allowed = isset($row[$allow_field]) && $row[$allow_field] == 1;
                                                    $badge_class = $is_allowed ? 'grade-accepted' : 'grade-not-accepted';
                                                    $grade_badges .= '<span class="grade-badge ' . $badge_class . '">ม.' . $grade . '</span>';
                                                }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td class="text-center"><?php echo $i++; ?></td>
                                            <td>
                                                <?php echo $row['club_name']; ?>
                                                <?php if ($is_locked): ?>
                                                    <i class="fas fa-lock lock-icon" title="ชุมนุมนี้ถูกล็อก ไม่สามารถลงทะเบียนได้"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $row['description']; ?></td>
                                            <td><?php echo $row['location']; ?></td>
                                            <td>
                                                <?php echo $teacher_name; ?> 
                                                <?php if (!empty($row['teacher_phone'])): ?>
                                                    <br>โทร. <?php echo $row['teacher_phone']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $row['max_members']; ?></td>
                                            <td class="text-center grade-levels">
                                                <?php echo $grade_badges; ?>
                                            </td>
                                            <td class="text-center"><?php echo $registered; ?></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-info btn-sm" onclick="viewMembers(<?php echo $club_id; ?>)">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                            </td>
                                            <td class="text-center <?php echo $status_class; ?>">
                                                <?php if ($is_locked): ?>
                                                    <strong><i class="fas fa-lock me-1"></i> ถูกล็อก</strong>
                                                <?php elseif ($is_full): ?>
                                                    <strong>เต็ม</strong>
                                                <?php else: ?>
                                                    <strong>ว่าง <?php echo $available; ?> ที่</strong>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (isLoggedIn() && isStudent() && !$student_data['selection_status'] && $registration_open): ?>
                                            <td class="text-center">
                                                <?php if ($is_locked): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm btn-select" disabled>
                                                        <i class="fas fa-lock"></i> ถูกล็อก
                                                    </button>
                                                <?php elseif (!$is_full): ?>
                                                    <button type="button" class="btn btn-success btn-sm btn-select" onclick="confirmSelectClub(<?php echo $club_id; ?>, '<?php echo htmlspecialchars($row['club_name'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-check-circle"></i> เลือก
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary btn-sm btn-select" disabled>
                                                        <i class="fas fa-times"></i> เต็มแล้ว
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php 
                                            endwhile; 
                                        else: 
                                        ?>
                                        <tr>
                                            <td colspan="<?php echo (isLoggedIn() && isStudent() && !$student_data['selection_status'] && $registration_open) ? '11' : '10'; ?>" class="text-center">
                                                <?php if (!empty($search)): ?>
                                                    ไม่พบข้อมูลชุมนุมที่ตรงกับการค้นหา "<?php echo htmlspecialchars($search); ?>"
                                                <?php else: ?>
                                                    ไม่พบข้อมูลชุมนุม
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($per_page > 0 && $total_pages > 1): ?>
                        <nav aria-label="การนำทาง" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <!-- ปุ่มก่อนหน้า -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- หมายเลขหน้า -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- ปุ่มถัดไป -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-0">ติดต่อผู้ดูแลระบบ: ครูจักรพงษ์ t246-math@knw.ac.th</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if (!isLoggedIn()): ?>
                                    <a href="admin_login.php" class="btn btn-outline-primary btn-sm">สำหรับผู้ดูแลระบบ</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="loginModalLabel">เข้าสู่ระบบเพื่อเลือกชุมนุม</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="loginForm" method="post" action="student_login.php">
                        <div class="mb-3">
                            <label for="id_card" class="form-label">เลขบัตรประชาชน 13 หลัก</label>
                            <input type="text" class="form-control" id="id_card" name="id_card" maxlength="13" required>
                            <div class="form-text">กรุณากรอกเลขบัตรประชาชน 13 หลัก ไม่ต้องมีขีดคั่น</div>
                        </div>
                        <div class="mb-3">
                            <label for="student_id" class="form-label">รหัสนักเรียน</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" required>
                            <div class="form-text">กรุณากรอกรหัสนักเรียนของคุณ</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">เข้าสู่ระบบ</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Club Members Modal -->
    <div class="modal fade" id="membersModal" tabindex="-1" aria-labelledby="membersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="membersModalLabel">รายชื่อสมาชิกชุมนุม</h5>
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

    <!-- Confirm Selection Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="confirmModalLabel">ยืนยันการเลือกชุมนุม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>คุณกำลังเลือกชุมนุม <strong id="confirm-club-name"></strong></p>
                    <p class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> โปรดตรวจสอบให้แน่ใจก่อนยืนยัน เนื่องจากไม่สามารถเปลี่ยนแปลงได้ในภายหลัง
                    </p>
                    <form id="selectClubForm" method="post" action="select_club.php">
                        <input type="hidden" name="club_id" id="confirm_club_id" value="">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('selectClubForm').submit();">
                        <i class="fas fa-check-circle"></i> ยืนยันการเลือกชุมนุม
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ฟังก์ชันเมื่อกดปุ่มเลือกชุมนุม
        function selectClub(clubId) {
            // ใช้สำหรับกรณีที่ยังไม่ได้ล็อกอิน
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        }
        
        // ฟังก์ชันยืนยันการเลือกชุมนุม
        function confirmSelectClub(clubId, clubName) {
            document.getElementById('confirm-club-name').textContent = clubName;
            document.getElementById('confirm_club_id').value = clubId;
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        }
        
        // ฟังก์ชันเมื่อกดปุ่มดูรายชื่อสมาชิก
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
                    var membersModal = new bootstrap.Modal(document.getElementById('membersModal'));
                    membersModal.show();
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                }
            });
        }
        
        // ตรวจสอบความถูกต้องของเลขบัตรประชาชน
        document.getElementById('id_card').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
        
        // ฟังก์ชันสำหรับ clear search เมื่อกด Enter ในช่องค้นหา
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
        
        // เพิ่ม responsive behavior สำหรับ pagination
        window.addEventListener('resize', function() {
            // ปรับขนาด pagination สำหรับ mobile
            const pagination = document.querySelector('.pagination');
            if (pagination && window.innerWidth < 576) {
                pagination.classList.add('pagination-sm');
            } else if (pagination) {
                pagination.classList.remove('pagination-sm');
            }
        });
        
        // เรียกใช้เมื่อหน้าโหลดเสร็จ
        window.addEventListener('load', function() {
            // ปรับขนาด pagination สำหรับ mobile
            const pagination = document.querySelector('.pagination');
            if (pagination && window.innerWidth < 576) {
                pagination.classList.add('pagination-sm');
            }
        });
    </script>
</body>
</html>
```

# logout.php

```php
<?php
require_once 'config.php';

// บันทึกประวัติการออกจากระบบ
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    logActivity($user_id, $user_type, 'logout', 'ออกจากระบบ');
}

// ล้างข้อมูล session ทั้งหมด
session_unset();
session_destroy();

// เปลี่ยนเส้นทางไปยังหน้าหลัก
header("Location: index.php");
exit;
?>
```

# sample_files\import_club.csv

```csv
club_name,description,location,max_members,teacher_id,allow_m1,allow_m2,allow_m3,allow_m4,allow_m5,allow_m6
ชุมนุมคอมพิวเตอร์,เรียนรู้การเขียนโปรแกรมและการใช้คอมพิวเตอร์ขั้นสูง,ห้องคอมพิวเตอร์ 1,20,246,0,0,0,1,1,1
ชุมนุมภาษาอังกฤษ,พัฒนาทักษะการสื่อสารภาษาอังกฤษและเรียนรู้วัฒนธรรมต่างประเทศ,ห้อง 506,35,247,1,1,1,1,1,1
ชุมนุมวิทยาศาสตร์,ทำการทดลองและโครงงานวิทยาศาสตร์,ห้องปฏิบัติการวิทย์ 2,20,248,0,0,1,1,1,0
ชุมนุมดนตรีไทย,เรียนรู้และฝึกปฏิบัติเครื่องดนตรีไทย,ห้องดนตรีไทย,20,249,1,1,1,1,1,1
ชุมนุมดนตรีสากล,เล่นดนตรีและร้องเพลงสมัยนิยม,ห้องดนตรีสากล,20,250,0,0,0,1,1,1

```

# sample_files\import_student.csv

```csv
student_id,id_card,firstname,lastname,grade_level,class_level,class_number,selection_status,club_id
60001,1100301111111,โชคชัย,มีชัย,ม.6,1,1,0,
60002,1100301222222,โชติกา,แสงสว่าง,ม.6,1,2,0,
60003,1100301333333,ไชยา,ยิ่งใหญ่,ม.6,1,3,0,
60004,1100301444444,กนกพร,พรกนก,ม.6,1,4,0,
60005,1100301555555,กมลชนก,ชนกกมล,ม.6,1,5,0,
61001,1100401111111,อนันต์,ไม่สิ้นสุด,ม.5,1,1,0,
61002,1100401222222,อนิษา,สดใส,ม.5,1,2,0,
61003,1100401333333,อภิชาติ,ชาติอภิ,ม.5,1,3,0,
61004,1100401444444,อมรรัตน์,รัตน์อมร,ม.5,1,4,0,
61005,1100401555555,อรุณ,แสงทอง,ม.5,1,5,0,
62001,1100501111111,สุชาติ,มีชัย,ม.4,1,1,0,

```

# sample_files\import_teacher.csv

```csv
teacher_id,firstname,lastname,teacher_code,telephon,department
246,สมชาย,รักดี,246,0811234567,คณิตศาสตร์
247,นภา,สมใจ,247,0822345678,วิทยาศาสตร์
248,วิชัย,สุขสันต์,248,0833456789,สุขศึกษาและพละศึกษา
249,ประภา,มีสุข,249,0844567890,ภาษาไทย
250,อนันต์,สมบูรณ์,250,0855678901,การงานอาชีพ
```

# select_club.php

```php
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
```

# student_login.php

```php
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
```

# test_admin.php

```php
<?php
// ไฟล์ test_admin.php - สำหรับตรวจสอบปัญหา admin.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>การตรวจสอบปัญหา admin.php</h2>";

// 1. ตรวจสอบไฟล์ config.php
echo "<h3>1. ตรวจสอบไฟล์ config.php</h3>";
if (file_exists('config.php')) {
    echo "✅ ไฟล์ config.php พบแล้ว<br>";
    require_once 'config.php';
    echo "✅ โหลด config.php สำเร็จ<br>";
} else {
    echo "❌ ไม่พบไฟล์ config.php<br>";
    die();
}

// 2. ตรวจสอบการเชื่อมต่อฐานข้อมูล
echo "<h3>2. ตรวจสอบการเชื่อมต่อฐานข้อมูล</h3>";
if (isset($conn)) {
    if ($conn instanceof mysqli) {
        echo "✅ เชื่อมต่อฐานข้อมูล MySQLi สำเร็จ<br>";
    } else {
        echo "❌ ตัวแปร \$conn ไม่ใช่ mysqli object<br>";
    }
} else {
    echo "❌ ไม่พบตัวแปร \$conn<br>";
}

// 3. ตรวจสอบ Session
echo "<h3>3. ตรวจสอบ Session</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ เริ่ม session ใหม่<br>";
} else {
    echo "✅ session ทำงานอยู่แล้ว<br>";
}

if (isset($_SESSION)) {
    echo "✅ \$_SESSION ใช้งานได้<br>";
    echo "Session data: " . print_r($_SESSION, true) . "<br>";
} else {
    echo "❌ ปัญหากับ \$_SESSION<br>";
}

// 4. ตรวจสอบฟังก์ชันที่ใช้
echo "<h3>4. ตรวจสอบฟังก์ชัน</h3>";
if (function_exists('isLoggedIn')) {
    echo "✅ ฟังก์ชัน isLoggedIn() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน isLoggedIn()<br>";
}

if (function_exists('isAdmin')) {
    echo "✅ ฟังก์ชัน isAdmin() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน isAdmin()<br>";
}

if (function_exists('getSystemSetting')) {
    echo "✅ ฟังก์ชัน getSystemSetting() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน getSystemSetting()<br>";
}

if (function_exists('clean')) {
    echo "✅ ฟังก์ชัน clean() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน clean()<br>";
}

// 5. ตรวจสอบตาราง
echo "<h3>5. ตรวจสอบตาราง</h3>";
if (isset($conn)) {
    $tables = ['students', 'clubs'];
    foreach ($tables as $table) {
        $check_sql = "SHOW TABLES LIKE '$table'";
        $result = mysqli_query($conn, $check_sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo "✅ ตาราง $table พบแล้ว<br>";
        } else {
            echo "❌ ไม่พบตาราง $table<br>";
        }
    }
}

// 6. ตรวจสอบ PHP Version
echo "<h3>6. ข้อมูลระบบ</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "MySQLi Extension: " . (extension_loaded('mysqli') ? '✅ โหลดแล้ว' : '❌ ไม่พบ') . "<br>";
echo "Session Extension: " . (extension_loaded('session') ? '✅ โหลดแล้ว' : '❌ ไม่พบ') . "<br>";

// 7. ทดสอบ Query พื้นฐาน
echo "<h3>7. ทดสอบ Query</h3>";
if (isset($conn)) {
    $test_sql = "SELECT COUNT(*) as count FROM students";
    $test_result = mysqli_query($conn, $test_sql);
    if ($test_result) {
        $row = mysqli_fetch_assoc($test_result);
        echo "✅ Query students สำเร็จ - พบ " . $row['count'] . " รายการ<br>";
    } else {
        echo "❌ Query ล้มเหลว: " . mysqli_error($conn) . "<br>";
    }
}

echo "<hr>";
echo "<p><strong>หากทุกอย่างเป็น ✅ ให้ลองเข้า admin.php ใหม่</strong></p>";
echo "<p><strong>หากมี ❌ ให้แก้ไขปัญหานั้นๆ ก่อน</strong></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h2, h3 { color: #333; }
</style>
```

# test.html

```html

```

# toggle_club_lock.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
    exit; // เพิ่ม exit หลัง redirect เพื่อหยุดการทำงานของสคริปต์
}

// ตรวจสอบว่ามีการส่ง club_id มาหรือไม่
if (isset($_GET['club_id'])) {
    $club_id = mysqli_real_escape_string($conn, $_GET['club_id']);
    
    // ดึงข้อมูลสถานะการล็อกชุมนุมปัจจุบัน
    $check_sql = "SELECT is_locked FROM clubs WHERE club_id = '$club_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $club = mysqli_fetch_assoc($check_result);
        $current_status = $club['is_locked'];
        
        // สลับค่าสถานะการล็อก
        $new_status = $current_status ? 0 : 1;
        
        // อัพเดทสถานะการล็อก
        $update_sql = "UPDATE clubs SET is_locked = '$new_status' WHERE club_id = '$club_id'";
        if (mysqli_query($conn, $update_sql)) {
            // บันทึกประวัติการอัพเดท (optional)
            // ตรวจสอบว่ามีตาราง activity_logs หรือไม่ก่อนบันทึก
            $table_exists_query = "SHOW TABLES LIKE 'activity_logs'";
            $table_exists_result = mysqli_query($conn, $table_exists_query);
            
            if ($table_exists_result && mysqli_num_rows($table_exists_result) > 0) {
                $admin_username = $_SESSION['username'] ?? 'admin';
                $action = $new_status ? "ล็อกชุมนุม" : "ปลดล็อกชุมนุม";
                $log_sql = "INSERT INTO activity_logs (user, action, target_id, details) 
                            VALUES ('$admin_username', '$action', '$club_id', 'club_id: $club_id')";
                mysqli_query($conn, $log_sql);
            }
            
            // กลับไปที่หน้าจัดการชุมนุม
            redirect('admin_clubs.php?status=success&message=' . urlencode(($new_status ? 'ล็อกชุมนุมเรียบร้อยแล้ว' : 'ปลดล็อกชุมนุมเรียบร้อยแล้ว')));
            exit; // เพิ่ม exit หลัง redirect
        } else {
            // แสดงข้อผิดพลาด MySQL เพื่อการแก้ไขปัญหา (ลบออกในการใช้งานจริง)
            // echo "MySQL Error: " . mysqli_error($conn);
            redirect('admin_clubs.php?status=error&message=' . urlencode('เกิดข้อผิดพลาดในการอัพเดทสถานะชุมนุม'));
            exit;
        }
    } else {
        redirect('admin_clubs.php?status=error&message=' . urlencode('ไม่พบข้อมูลชุมนุม'));
        exit;
    }
} else {
    redirect('admin_clubs.php?status=error&message=' . urlencode('ไม่ได้ระบุรหัสชุมนุม'));
    exit;
}
?>
```

# toggle_registration.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ดึงสถานะปัจจุบันของระบบ
$current_status = getSystemSetting('registration_open');

// สลับสถานะ
$new_status = ($current_status === 'true') ? 'false' : 'true';

// อัพเดทสถานะในฐานข้อมูล
if (updateSystemSetting('registration_open', $new_status)) {
    // บันทึกประวัติการเปิด/ปิดระบบ
    $action_text = ($new_status === 'true') ? 'เปิดระบบลงทะเบียน' : 'ปิดระบบลงทะเบียน';
    logActivity($_SESSION['user_id'], 'admin', 'toggle_system', $action_text);
    
    alert($action_text . "เรียบร้อยแล้ว");
} else {
    alert("เกิดข้อผิดพลาดในการอัพเดทสถานะระบบ");
}

// กลับไปที่หน้าแอดมิน
redirect("admin.php");
?>
```

# update_club.php

```php
<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ตรวจสอบว่าส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $club_id = (int)$_POST['club_id'];
    $club_name = clean($conn, $_POST['club_name']);
    $description = clean($conn, $_POST['description']);
    $location = clean($conn, $_POST['location']);
    $max_members = (int)$_POST['max_members'];
    $teacher_id = isset($_POST['teacher_id']) && !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : "NULL";
    
    // รับค่าระดับชั้นที่เปิดรับ
    $allow_m1 = isset($_POST['allow_m1']) ? 1 : 0;
    $allow_m2 = isset($_POST['allow_m2']) ? 1 : 0;
    $allow_m3 = isset($_POST['allow_m3']) ? 1 : 0;
    $allow_m4 = isset($_POST['allow_m4']) ? 1 : 0;
    $allow_m5 = isset($_POST['allow_m5']) ? 1 : 0;
    $allow_m6 = isset($_POST['allow_m6']) ? 1 : 0;
    
    // ตรวจสอบว่าชื่อชุมนุมซ้ำหรือไม่
    $check_sql = "SELECT * FROM clubs WHERE club_name = '$club_name' AND club_id != $club_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        alert("ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น");
        redirect("admin_clubs.php");
        exit;
    }
    
    // ตรวจสอบว่าจำนวนที่รับน้อยกว่าจำนวนสมาชิกที่มีอยู่หรือไม่
    $current_members = countClubMembers($club_id);
    if ($max_members < $current_members) {
        alert("ไม่สามารถลดจำนวนรับได้ เนื่องจากมีนักเรียนลงทะเบียนแล้ว $current_members คน");
        redirect("admin_clubs.php");
        exit;
    }
    
    // ตรวจสอบว่ามีนักเรียนที่ได้รับผลกระทบจากการเปลี่ยนแปลงระดับชั้นที่รับหรือไม่
    $grade_check_sql = "SELECT COUNT(*) as affected FROM students WHERE club_id = $club_id AND (
                            (grade_level = 'ม.1' AND $allow_m1 = 0) OR
                            (grade_level = 'ม.2' AND $allow_m2 = 0) OR
                            (grade_level = 'ม.3' AND $allow_m3 = 0) OR
                            (grade_level = 'ม.4' AND $allow_m4 = 0) OR
                            (grade_level = 'ม.5' AND $allow_m5 = 0) OR
                            (grade_level = 'ม.6' AND $allow_m6 = 0)
                        )";
    $grade_check_result = mysqli_query($conn, $grade_check_sql);
    $grade_check_data = mysqli_fetch_assoc($grade_check_result);
    
    if ($grade_check_data['affected'] > 0) {
        alert("ไม่สามารถเปลี่ยนแปลงระดับชั้นที่รับได้ เนื่องจากมีนักเรียนในระดับชั้นที่จะปิดรับลงทะเบียนแล้ว {$grade_check_data['affected']} คน");
        redirect("admin_clubs.php");
        exit;
    }
    
    // สร้างคำสั่ง SQL สำหรับอัพเดทข้อมูล
    $sql = "UPDATE clubs SET 
            club_name = '$club_name', 
            description = '$description', 
            location = '$location', 
            max_members = $max_members, 
            teacher_id = " . ($teacher_id === "NULL" ? "NULL" : $teacher_id) . ", 
            allow_m1 = $allow_m1, 
            allow_m2 = $allow_m2, 
            allow_m3 = $allow_m3, 
            allow_m4 = $allow_m4, 
            allow_m5 = $allow_m5, 
            allow_m6 = $allow_m6 
            WHERE club_id = $club_id";
    
    // ทำการอัพเดทข้อมูล
    if (mysqli_query($conn, $sql)) {
        // บันทึกประวัติการอัพเดทชุมนุม
        logActivity($_SESSION['user_id'], 'admin', 'update_club', "อัพเดทชุมนุม: $club_name");
        
        alert("อัพเดทชุมนุมเรียบร้อยแล้ว");
    } else {
        alert("เกิดข้อผิดพลาดในการอัพเดทข้อมูล: " . mysqli_error($conn));
    }
    
    redirect("admin_clubs.php");
} else {
    // ถ้าไม่ได้ส่งข้อมูลมาจากฟอร์ม ให้กลับไปที่หน้าจัดการชุมนุม
    redirect("admin_clubs.php");
}
?>
```

