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
        $grade_number = 1;
        echo "<!-- ไม่สามารถอ่านเลขระดับชั้นได้ กำหนดเป็น 1 -->";
    }
    
    $allow_field = "allow_m" . $grade_number;
    
    echo "<!-- ระดับชั้น: " . $grade_level . " | ค่า grade_number: " . $grade_number . " | ฟิลด์ที่ใช้กรอง: " . $allow_field . " -->";
    
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

    /* ===== สถานะชุมนุม ===== */
    .club-available {
        background-color: rgba(40, 167, 69, 0.2);
        color: #155724;
    }

    /* ชุมนุมเต็ม - สีส้มเข้ม (สีประจำโรงเรียน) ตัวหนังสือขาว */
    .club-full {
        background-color: #a84020 !important;
        color: #ffffff !important;
        font-weight: bold;
    }

    /* ===== แถวชุมนุมเต็ม ===== */
    .row-full td {
        background-color: #cb532d !important;
        color: #ffffff !important;
    }
    .row-full:hover td {
        background-color: #a84020 !important;
        color: #ffffff !important;
    }
    /* ปุ่มดูรายชื่อในแถวเต็ม */
    .row-full td .btn-info {
        background-color: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.6);
        color: #ffffff;
    }
    .row-full td .btn-info:hover {
        background-color: rgba(255, 255, 255, 0.4);
    }
    /* badge ระดับชั้นในแถวเต็ม */
    .row-full td .grade-not-accepted {
        background-color: rgba(0, 0, 0, 0.3);
    }
    .row-full td .grade-accepted {
        background-color: rgba(0, 120, 0, 0.55);
    }
    /* Override Bootstrap striped ในแถวเต็ม */
    .table-striped > tbody > tr.row-full:nth-of-type(odd) > td,
    .table-striped > tbody > tr.row-full:nth-of-type(even) > td {
        background-color: #cb532d !important;
        color: #ffffff !important;
    }

    /* ===== แถวชุมนุมถูกล็อก ===== */
    .row-locked td {
        background-color: rgba(108, 117, 125, 0.18) !important;
        color: #495057;
    }
    .row-locked:hover td {
        background-color: rgba(108, 117, 125, 0.28) !important;
    }
    .club-locked {
        background-color: rgba(108, 117, 125, 0.2);
        color: #343a40;
    }
    /* Override Bootstrap striped ในแถวล็อก */
    .table-striped > tbody > tr.row-locked:nth-of-type(odd) > td,
    .table-striped > tbody > tr.row-locked:nth-of-type(even) > td {
        background-color: rgba(108, 117, 125, 0.18) !important;
    }

    /* ===== ไอคอนลูกกุญแจ ===== */
    .lock-icon {
        color: #6c757d;
        margin-left: 5px;
    }

    /* ===== ปุ่มเลือก ===== */
    .btn-select {
        min-width: 80px;
    }

    /* ===== ช่องค้นหา ===== */
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

    /* ===== badge ระดับชั้น ===== */
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

    /* ===== RESPONSIVE TABLE บนมือถือ ===== */
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    /* ===== ปุ่มเลือกบนมือถือ ===== */
    @media (max-width: 575px) {
        .table th, .table td {
            font-size: 13px;
            padding: 8px 6px;
        }
        /* ปุ่มเลือกบนมือถือ - ใหญ่กว่าและกดง่ายขึ้น */
        .btn-select {
            min-width: unset;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 8px;
        }
        /* ซ่อนข้อความในปุ่ม เหลือแค่ icon */
        .btn-select .btn-text {
            display: none;
        }
        .results-info {
            flex-direction: column;
            align-items: stretch;
        }
        .per-page-selector {
            justify-content: center;
        }
        /* ชื่อชุมนุมบนมือถือ */
        .club-name-cell {
            font-weight: 600;
        }
        /* สถานะบนมือถือ - แสดงแบบกระทัดรัด */
        .status-cell-mobile {
            font-size: 12px;
            white-space: nowrap;
        }
    }

    @media (min-width: 576px) and (max-width: 991px) {
        .table th, .table td {
            font-size: 13px;
            padding: 6px 8px;
        }
        .grade-badge {
            font-size: 10px;
            padding: 1px 4px;
        }
        .btn-select {
            min-width: 60px;
            font-size: 12px;
            padding: 4px 8px;
        }
        .btn-select .btn-text {
            display: none;
        }
    }

    @media (min-width: 768px) {
        .results-info {
            flex-direction: row;
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
                            <a class="nav-link text-white" href="admin/index.php">
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
                                        <th width="5%" class="d-none d-lg-table-cell">ลำดับ</th>
                                        <th width="20%">ชื่อชุมนุม</th>
                                        <th width="18%" class="d-none d-lg-table-cell">คำอธิบาย</th>
                                        <th width="10%" class="d-none d-sm-table-cell">สถานที่เรียน</th>
                                        <th width="12%" class="d-none d-sm-table-cell">ครูที่ปรึกษา</th>
                                        <th width="5%" class="d-none d-sm-table-cell">จำนวนที่รับ</th>
                                        <th width="8%" class="d-none d-lg-table-cell">ระดับชั้นที่รับ</th>
                                        <th width="5%" class="d-none d-sm-table-cell">ลงทะเบียนแล้ว</th>
                                        <th width="5%" class="d-none d-lg-table-cell">รายชื่อสมาชิก</th>
                                        <th width="12%">สถานะ</th>
                                        <?php if (isLoggedIn() && isStudent() && !$student_data['selection_status'] && $registration_open): ?>
                                        <th width="8%">เลือก</th>
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
                                            <td class="text-center d-none d-lg-table-cell"><?php echo $i++; ?></td>
                                            <td class="club-name-cell">
                                                <?php echo $row['club_name']; ?>
                                                <?php if ($is_locked): ?>
                                                    <i class="fas fa-lock lock-icon" title="ชุมนุมนี้ถูกล็อก ไม่สามารถลงทะเบียนได้"></i>
                                                <?php endif; ?>
                                                <!-- ปุ่มดูสมาชิก แสดงเฉพาะมือถือ (ซ่อนบน desktop) -->
                                                <button type="button" class="btn btn-info btn-sm d-lg-none ms-1 py-0 px-1" onclick="viewMembers(<?php echo $club_id; ?>)" title="ดูรายชื่อสมาชิก">
                                                    <i class="fas fa-users" style="font-size:11px;"></i>
                                                </button>
                                            </td>
                                            <td class="d-none d-lg-table-cell"><?php echo $row['description']; ?></td>
                                            <td class="d-none d-sm-table-cell"><?php echo $row['location']; ?></td>
                                            <td class="d-none d-sm-table-cell">
                                                <?php echo $teacher_name; ?>
                                                <?php if (!empty($row['teacher_phone'])): ?>
                                                    <br>โทร. <?php echo $row['teacher_phone']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center d-none d-sm-table-cell"><?php echo $row['max_members']; ?></td>
                                            <td class="text-center grade-levels d-none d-lg-table-cell">
                                                <?php echo $grade_badges; ?>
                                            </td>
                                            <td class="text-center d-none d-sm-table-cell"><?php echo $registered; ?></td>
                                            <td class="text-center d-none d-lg-table-cell">
                                                <button type="button" class="btn btn-info btn-sm" onclick="viewMembers(<?php echo $club_id; ?>)">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                            </td>
                                            <td class="text-center <?php echo $status_class; ?> status-cell-mobile">
                                                <?php if ($is_locked): ?>
                                                    <strong><i class="fas fa-lock me-1"></i> ถูกล็อก</strong>
                                                <?php elseif ($is_full): ?>
                                                    <strong>เต็ม</strong>
                                                <?php else: ?>
                                                    <strong>ว่าง <?php echo $available; ?> ที่</strong>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (isLoggedIn() && isStudent() && !$student_data['selection_status'] && $registration_open): ?>
                                            <td class="text-center align-middle">
                                                <?php if ($is_locked): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm btn-select" disabled title="ถูกล็อก">
                                                        <i class="fas fa-lock"></i> <span class="btn-text">ถูกล็อก</span>
                                                    </button>
                                                <?php elseif (!$is_full): ?>
                                                    <button type="button" class="btn btn-success btn-sm btn-select" onclick="confirmSelectClub(<?php echo $club_id; ?>, '<?php echo htmlspecialchars($row['club_name'], ENT_QUOTES); ?>')" title="เลือกชุมนุมนี้">
                                                        <i class="fas fa-check-circle"></i> <span class="btn-text">เลือก</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary btn-sm btn-select" disabled title="เต็มแล้ว">
                                                        <i class="fas fa-times"></i> <span class="btn-text">เต็มแล้ว</span>
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
                            <div class="col-md-6 text-end d-flex gap-2 justify-content-end">
                                <a href="teacher/login.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>สำหรับครู
                                </a>
                                <?php if (!isLoggedIn()): ?>
                                    <a href="admin/login.php" class="btn btn-outline-primary btn-sm">สำหรับผู้ดูแลระบบ</a>
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
                    <form id="loginForm" method="post" action="student/login.php">
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
                    <form id="selectClubForm" method="post" action="student/select_club.php">
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
        function selectClub(clubId) {
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        }
        
        function confirmSelectClub(clubId, clubName) {
            document.getElementById('confirm-club-name').textContent = clubName;
            document.getElementById('confirm_club_id').value = clubId;
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        }
        
        function viewMembers(clubId) {
            document.getElementById('export-csv').href = 'api/export_csv.php?club_id=' + clubId;
            
            $.ajax({
                url: 'api/get_members.php',
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
        
        document.getElementById('id_card').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 13) {
                this.value = this.value.slice(0, 13);
            }
        });
        
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.form.submit();
            }
        });
        
        window.addEventListener('resize', function() {
            const pagination = document.querySelector('.pagination');
            if (pagination && window.innerWidth < 576) {
                pagination.classList.add('pagination-sm');
            } else if (pagination) {
                pagination.classList.remove('pagination-sm');
            }
        });
        
        window.addEventListener('load', function() {
            const pagination = document.querySelector('.pagination');
            if (pagination && window.innerWidth < 576) {
                pagination.classList.add('pagination-sm');
            }
        });
    </script>
</body>
</html>