<?php
require_once __DIR__ . '/../config.php';

// ตรวจสอบว่า login เป็นครูแล้ว
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    redirect('login.php');
}

$teacher_id = $_SESSION['user_id'];

// ตรวจสอบช่วงเวลาอีกครั้ง (กันกรณีหมดเวลาระหว่าง session)
$period_start = getSystemSetting('teacher_edit_start');
$period_end   = getSystemSetting('teacher_edit_end');
$now          = date('Y-m-d H:i:s');
$period_active = true;

if (!empty($period_start) && !empty($period_end)) {
    if ($now < $period_start || $now > $period_end) {
        $period_active = false;
    }
}

$success = '';
$error   = '';

// ===== AJAX: ดึงนักเรียนตามระดับชั้นและห้อง =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_students') {
    header('Content-Type: application/json');
    $club_id_filter = (int)$_GET['club_id'];
    $grade  = mysqli_real_escape_string($conn, $_GET['grade'] ?? '');
    $room   = (int)($_GET['room'] ?? 0);

    // ตรวจสอบว่าชุมนุมนี้เป็นของครูคนนี้
    $chk = mysqli_query($conn, "SELECT club_id FROM clubs WHERE club_id = $club_id_filter AND teacher_id = '$teacher_id'");
    if (mysqli_num_rows($chk) === 0) {
        echo json_encode(['error' => 'ไม่มีสิทธิ์']);
        exit;
    }

    $where = "WHERE 1=1";
    if (!empty($grade)) $where .= " AND grade_level = '$grade'";
    if ($room > 0)      $where .= " AND class_room = $room";

    $sql = "SELECT student_id, id_card, firstname, lastname, grade_level, class_room, class_number,
                   selection_status, club_id
            FROM students
            $where
            ORDER BY grade_level, class_room, class_number";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    echo json_encode($rows);
    exit;
}

// ===== AJAX: ดึงรายชื่อห้องตามระดับชั้น =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_rooms') {
    header('Content-Type: application/json');
    $grade = mysqli_real_escape_string($conn, $_GET['grade'] ?? '');
    $sql = "SELECT DISTINCT class_room FROM students WHERE grade_level = '$grade' ORDER BY class_room";
    $res = mysqli_query($conn, $sql);
    $rooms = [];
    while ($r = mysqli_fetch_assoc($res)) $rooms[] = $r['class_room'];
    echo json_encode($rooms);
    exit;
}

// ===== POST: นำเข้านักเรียนเข้าชุมนุม =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_students') {
    if (!$period_active) {
        $error = "ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไข";
    } else {
        $club_id_import = (int)$_POST['club_id'];
        $student_ids    = $_POST['student_ids'] ?? [];

        // ตรวจสอบสิทธิ์
        $chk = mysqli_query($conn, "SELECT club_id, max_members FROM clubs WHERE club_id = $club_id_import AND teacher_id = '$teacher_id'");
        if (mysqli_num_rows($chk) === 0) {
            $error = "ไม่มีสิทธิ์จัดการชุมนุมนี้";
        } else {
            $club_row   = mysqli_fetch_assoc($chk);
            $max_mem    = (int)$club_row['max_members'];

            // นับสมาชิกปัจจุบัน
            $cur_res    = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM students WHERE club_id = $club_id_import AND selection_status = 1");
            $cur_row    = mysqli_fetch_assoc($cur_res);
            $current    = (int)$cur_row['cnt'];

            $imported   = 0;
            $skipped    = 0;
            $full       = false;

            foreach ($student_ids as $sid) {
                $sid = mysqli_real_escape_string($conn, $sid);

                // ตรวจสอบว่าเต็มหรือยัง
                if ($current >= $max_mem) {
                    $full = true;
                    $skipped++;
                    continue;
                }

                // ดึงข้อมูลนักเรียน
                $s_res = mysqli_query($conn, "SELECT * FROM students WHERE student_id = '$sid'");
                if (mysqli_num_rows($s_res) === 0) { $skipped++; continue; }
                $s = mysqli_fetch_assoc($s_res);

                // ถ้าเลือกชุมนุมไปแล้ว ข้าม
                if ($s['selection_status'] == 1) { $skipped++; continue; }

                // อัพเดท
                $upd = "UPDATE students SET club_id = $club_id_import, selection_status = 1 WHERE student_id = '$sid'";
                if (mysqli_query($conn, $upd)) {
                    logActivity($teacher_id, 'teacher', 'update_club', "นำเข้านักเรียน student_id=$sid เข้า club_id=$club_id_import");
                    $imported++;
                    $current++;
                } else {
                    $skipped++;
                }
            }

            if ($imported > 0) {
                $success = "นำเข้านักเรียนสำเร็จ $imported คน";
                if ($skipped > 0) $success .= " (ข้าม $skipped คน)";
                if ($full) $success .= " — ชุมนุมเต็มแล้ว";
            } else {
                $error = "ไม่มีนักเรียนที่นำเข้าได้" . ($skipped > 0 ? " (ข้าม $skipped คน เพราะเลือกชุมนุมแล้วหรือชุมนุมเต็ม)" : "");
            }
        }
    }
}

// ===== POST: สร้างชุมนุมใหม่ (ครูที่ยังไม่มีชุมนุม) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_club') {
    if (!$period_active) {
        $error = "ไม่อยู่ในช่วงเวลาที่อนุญาตให้สร้างชุมนุม";
    } else {
        $new_club_name   = mysqli_real_escape_string($conn, trim($_POST['club_name']));
        $new_description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $new_location    = mysqli_real_escape_string($conn, trim($_POST['location']));
        $new_max_members = (int)$_POST['max_members'];

        $allow_m1 = isset($_POST['allow_m1']) ? 1 : 0;
        $allow_m2 = isset($_POST['allow_m2']) ? 1 : 0;
        $allow_m3 = isset($_POST['allow_m3']) ? 1 : 0;
        $allow_m4 = isset($_POST['allow_m4']) ? 1 : 0;
        $allow_m5 = isset($_POST['allow_m5']) ? 1 : 0;
        $allow_m6 = isset($_POST['allow_m6']) ? 1 : 0;

        if (empty($new_club_name)) {
            $error = "กรุณากรอกชื่อชุมนุม";
        } elseif ($new_max_members < 1) {
            $error = "จำนวนที่รับต้องมากกว่า 0";
        } else {
            $dup = mysqli_query($conn, "SELECT club_id FROM clubs WHERE club_name = '$new_club_name'");
            if (mysqli_num_rows($dup) > 0) {
                $error = "ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น";
            } else {
                $ins = "INSERT INTO clubs (club_name, description, location, max_members, teacher_id,
                                          allow_m1, allow_m2, allow_m3, allow_m4, allow_m5, allow_m6)
                        VALUES ('$new_club_name', '$new_description', '$new_location', $new_max_members, '$teacher_id',
                                $allow_m1, $allow_m2, $allow_m3, $allow_m4, $allow_m5, $allow_m6)";
                if (mysqli_query($conn, $ins)) {
                    logActivity($teacher_id, 'teacher', 'create_club', "สร้างชุมนุมใหม่: $new_club_name");
                    $success = "สร้างชุมนุม \"$new_club_name\" เรียบร้อยแล้ว";
                } else {
                    $error = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
                }
            }
        }
    }
}

// ===== POST: บันทึกข้อมูลชุมนุม (เดิม) =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && $period_active && !isset($_POST['action'])) {
    $club_id     = (int)$_POST['club_id'];
    $club_name   = mysqli_real_escape_string($conn, trim($_POST['club_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $location    = mysqli_real_escape_string($conn, trim($_POST['location']));
    $max_members = (int)$_POST['max_members'];
    $is_locked   = isset($_POST['is_locked']) ? 1 : 0;

    $allow_m1 = isset($_POST['allow_m1']) ? 1 : 0;
    $allow_m2 = isset($_POST['allow_m2']) ? 1 : 0;
    $allow_m3 = isset($_POST['allow_m3']) ? 1 : 0;
    $allow_m4 = isset($_POST['allow_m4']) ? 1 : 0;
    $allow_m5 = isset($_POST['allow_m5']) ? 1 : 0;
    $allow_m6 = isset($_POST['allow_m6']) ? 1 : 0;

    $check_sql = "SELECT club_id FROM clubs WHERE club_id = $club_id AND teacher_id = '$teacher_id'";
    $check     = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check) > 0) {
        if ($max_members < 1) {
            $error = "จำนวนที่รับต้องมากกว่า 0";
        } elseif (empty($club_name)) {
            $error = "กรุณากรอกชื่อชุมนุม";
        } else {
            $update_sql = "UPDATE clubs SET
                club_name   = '$club_name',
                description = '$description',
                location    = '$location',
                max_members = $max_members,
                is_locked   = $is_locked,
                allow_m1    = $allow_m1,
                allow_m2    = $allow_m2,
                allow_m3    = $allow_m3,
                allow_m4    = $allow_m4,
                allow_m5    = $allow_m5,
                allow_m6    = $allow_m6
            WHERE club_id = $club_id AND teacher_id = '$teacher_id'";

            if (mysqli_query($conn, $update_sql)) {
                logActivity($teacher_id, 'teacher', 'update_club', "อัพเดทชุมนุม club_id=$club_id");
                $success = "บันทึกข้อมูลชุมนุมเรียบร้อยแล้ว";
            } else {
                $error = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "ไม่มีสิทธิ์แก้ไขชุมนุมนี้";
    }
}

// ดึงชุมนุมของครูคนนี้ทั้งหมด
$clubs_sql    = "SELECT c.*, 
                        (SELECT COUNT(*) FROM students s WHERE s.club_id = c.club_id AND s.selection_status = 1) AS current_members
                 FROM clubs c
                 WHERE c.teacher_id = '$teacher_id'
                 ORDER BY c.club_name";
$clubs_result = mysqli_query($conn, $clubs_sql);
$clubs        = [];
while ($row = mysqli_fetch_assoc($clubs_result)) {
    $clubs[] = $row;
}

// ดึงระดับชั้นทั้งหมดในระบบ
$grades_res = mysqli_query($conn, "SELECT DISTINCT grade_level FROM students ORDER BY grade_level");
$all_grades = [];
while ($g = mysqli_fetch_assoc($grades_res)) $all_grades[] = $g['grade_level'];

// ดึงข้อมูลครู
$teacher_sql    = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_sql);
$teacher        = mysqli_fetch_assoc($teacher_result);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการชุมนุม - <?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: #f4f7f4; }
        .navbar-teacher { background: linear-gradient(135deg, #1a6b3c, #2d9b63); }
        .club-card { border-left: 4px solid #2d9b63; transition: box-shadow .2s; }
        .club-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .badge-members { background: #e8f5ee; color: #1a6b3c; border: 1px solid #a8d5bc; }
        .period-banner { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; }
        .period-banner.active { background: #d1e7dd; border-color: #2d9b63; }
        .form-check-input:checked { background-color: #2d9b63; border-color: #2d9b63; }
        .btn-save { background: linear-gradient(135deg, #1a6b3c, #2d9b63); border: none; color: #fff; }
        .btn-save:hover { opacity: 0.9; color: #fff; }
        .btn-import-open { background: linear-gradient(135deg, #0d6efd, #3d8bfd); border: none; color: #fff; }
        .btn-import-open:hover { opacity: 0.9; color: #fff; }
        .grade-check { display: inline-flex; align-items: center; gap: 6px;
                        padding: 6px 12px; border-radius: 20px; border: 1px solid #dee2e6;
                        cursor: pointer; user-select: none; transition: all .15s; }
        .grade-check input:checked ~ span { font-weight: 600; }
        .grade-check:has(input:checked) { background: #d1e7dd; border-color: #2d9b63; }

        /* Import Modal Styles */
        #studentTable th, #studentTable td { vertical-align: middle; font-size: 0.875rem; }
        .table-import thead { background: #1a6b3c; color: #fff; position: sticky; top: 0; z-index: 1; }
        .status-badge-selected { background: #d1e7dd; color: #0a3622; }
        .status-badge-none { background: #f8d7da; color: #58151c; }
        .filter-bar { background: #f8f9fa; border-radius: 8px; padding: 12px 16px; }
        .student-scroll { max-height: 380px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; }
        .select-count-badge { font-size: 1rem; }
        .capacity-bar { height: 6px; border-radius: 3px; background: #dee2e6; }
        .capacity-fill { height: 6px; border-radius: 3px; background: #2d9b63; transition: width .3s; }
        .capacity-fill.danger { background: #dc3545; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark navbar-teacher navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">
            <i class="fas fa-chalkboard-teacher me-2"></i>ระบบจัดการชุมนุม (ครู)
        </a>
        <div class="navbar-nav ms-auto flex-row gap-3 align-items-center">
            <span class="text-white opacity-75 small">
                <i class="fas fa-user-circle me-1"></i>
                <?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Period Banner -->
    <?php if (!empty($period_start) && !empty($period_end)): ?>
    <div class="period-banner <?php echo $period_active ? 'active' : ''; ?> p-3 mb-4 d-flex align-items-center gap-3">
        <i class="fas fa-<?php echo $period_active ? 'check-circle text-success' : 'clock text-warning'; ?> fa-lg"></i>
        <div>
            <?php if ($period_active): ?>
                <strong class="text-success">ช่วงเวลาแก้ไขข้อมูลกำลังเปิดอยู่</strong>
                <div class="small text-muted">
                    ถึง <?php echo date('d/m/Y H:i', strtotime($period_end)); ?> น.
                </div>
            <?php else: ?>
                <strong class="text-warning">ขณะนี้ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไข</strong>
                <div class="small text-muted">
                    ช่วงเวลาที่อนุญาต: <?php echo date('d/m/Y H:i', strtotime($period_start)); ?>
                    &ndash; <?php echo date('d/m/Y H:i', strtotime($period_end)); ?> น.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alert Messages -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h4 class="mb-4 fw-bold">
        <i class="fas fa-users text-success me-2"></i>
        ชุมนุมที่รับผิดชอบ
        <span class="badge bg-success ms-2"><?php echo count($clubs); ?> ชุมนุม</span>
    </h4>

    <?php if (empty($clubs)): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>ยังไม่มีชุมนุมที่รับผิดชอบในระบบ
            <?php if ($period_active): ?>
            — กรุณากรอกข้อมูลด้านล่างเพื่อสร้างชุมนุมของคุณ
            <?php endif; ?>
        </div>

        <?php if ($period_active): ?>
        <div class="card club-card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold text-success">
                    <i class="fas fa-plus-circle me-2"></i>สร้างชุมนุมใหม่
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_club">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-tag me-1 text-success"></i>ชื่อชุมนุม <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="club_name"
                                   value="<?php echo htmlspecialchars($_POST['club_name'] ?? ''); ?>"
                                   placeholder="กรอกชื่อชุมนุม" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-users me-1 text-success"></i>จำนวนที่รับ (คน) <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" name="max_members"
                                   value="<?php echo htmlspecialchars($_POST['max_members'] ?? '30'); ?>"
                                   min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-map-marker-alt me-1 text-success"></i>สถานที่เรียน
                            </label>
                            <input type="text" class="form-control" name="location"
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                   placeholder="เช่น ห้อง 101">
                        </div>
                        <div class="col-md-6">
                            <!-- spacer -->
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-align-left me-1 text-success"></i>คำอธิบายชุมนุม
                            </label>
                            <textarea class="form-control" name="description" rows="3"
                                      placeholder="รายละเอียดชุมนุม กิจกรรม วัตถุประสงค์ ฯลฯ"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-graduation-cap me-1 text-success"></i>ระดับชั้นที่รับ
                            </label>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <?php
                                $levels = ['m1'=>'ม.1','m2'=>'ม.2','m3'=>'ม.3','m4'=>'ม.4','m5'=>'ม.5','m6'=>'ม.6'];
                                foreach ($levels as $key => $label):
                                    $checked = isset($_POST["allow_$key"]) ? 'checked' : '';
                                ?>
                                <label class="grade-check">
                                    <input type="checkbox" name="allow_<?php echo $key; ?>"
                                           class="form-check-input" <?php echo $checked; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-save px-4">
                                <i class="fas fa-plus me-2"></i>สร้างชุมนุม
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock me-2"></i>ขณะนี้ไม่อยู่ในช่วงเวลาที่อนุญาตให้สร้างชุมนุม กรุณาติดต่อผู้ดูแลระบบ
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php foreach ($clubs as $club): ?>
    <div class="card club-card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-success">
                    <i class="fas fa-users me-2"></i>
                    <?php echo htmlspecialchars($club['club_name']); ?>
                </h5>
                <small class="text-muted">รหัสชุมนุม: <?php echo $club['club_id']; ?></small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge badge-members px-3 py-2">
                    <i class="fas fa-user-check me-1"></i>
                    <?php echo $club['current_members']; ?>/<?php echo $club['max_members']; ?> คน
                </span>
                <?php if ($club['is_locked']): ?>
                    <span class="badge bg-danger">
                        <i class="fas fa-lock me-1"></i>ล็อก
                    </span>
                <?php endif; ?>
                <?php if ($period_active): ?>
                <button type="button" class="btn btn-import-open btn-sm"
                        onclick="openImportModal(<?php echo $club['club_id']; ?>, '<?php echo htmlspecialchars(addslashes($club['club_name'])); ?>', <?php echo $club['current_members']; ?>, <?php echo $club['max_members']; ?>)">
                    <i class="fas fa-user-plus me-1"></i> นำเข้านักเรียน
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <?php if ($period_active): ?>
            <form method="post" action="">
                <input type="hidden" name="club_id" value="<?php echo $club['club_id']; ?>">

                <div class="row g-3">
                    <!-- ชื่อชุมนุม -->
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tag me-1 text-success"></i>ชื่อชุมนุม
                        </label>
                        <input type="text" class="form-control" name="club_name"
                               value="<?php echo htmlspecialchars($club['club_name']); ?>" required>
                    </div>

                    <!-- จำนวนที่รับ -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-users me-1 text-success"></i>จำนวนที่รับ (คน)
                        </label>
                        <input type="number" class="form-control" name="max_members"
                               value="<?php echo $club['max_members']; ?>"
                               min="<?php echo $club['current_members']; ?>" required>
                        <div class="form-text">ลงทะเบียนแล้ว <?php echo $club['current_members']; ?> คน</div>
                    </div>

                    <!-- สถานที่ -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-map-marker-alt me-1 text-success"></i>สถานที่เรียน
                        </label>
                        <input type="text" class="form-control" name="location"
                               value="<?php echo htmlspecialchars($club['location']); ?>">
                    </div>

                    <!-- ล็อกชุมนุม -->
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch ms-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="is_locked" id="lock_<?php echo $club['club_id']; ?>"
                                   <?php echo $club['is_locked'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="lock_<?php echo $club['club_id']; ?>">
                                <i class="fas fa-lock me-1"></i>ล็อกชุมนุม (นักเรียนเลือกไม่ได้)
                            </label>
                        </div>
                    </div>

                    <!-- คำอธิบาย -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-align-left me-1 text-success"></i>คำอธิบายชุมนุม
                        </label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($club['description']); ?></textarea>
                    </div>

                    <!-- ระดับชั้นที่รับ -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-graduation-cap me-1 text-success"></i>ระดับชั้นที่รับ
                        </label>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <?php
                            $levels = ['m1'=>'ม.1','m2'=>'ม.2','m3'=>'ม.3','m4'=>'ม.4','m5'=>'ม.5','m6'=>'ม.6'];
                            foreach ($levels as $key => $label):
                                $checked = $club["allow_$key"] ? 'checked' : '';
                            ?>
                            <label class="grade-check">
                                <input type="checkbox" name="allow_<?php echo $key; ?>"
                                       class="form-check-input" <?php echo $checked; ?>>
                                <span><?php echo $label; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-save px-4">
                            <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <!-- แสดงข้อมูลอ่านอย่างเดียวเมื่อไม่อยู่ในช่วงเวลา -->
            <div class="alert alert-warning mb-3">
                <i class="fas fa-lock me-2"></i>ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไข (แสดงข้อมูลอย่างเดียว)
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-3">สถานที่</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($club['location'] ?: '-'); ?></dd>
                <dt class="col-sm-3">จำนวนที่รับ</dt>
                <dd class="col-sm-9"><?php echo $club['max_members']; ?> คน</dd>
                <dt class="col-sm-3">คำอธิบาย</dt>
                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($club['description'] ?: '-')); ?></dd>
                <dt class="col-sm-3">ระดับชั้น</dt>
                <dd class="col-sm-9">
                    <?php
                    $lvls = [];
                    foreach ($levels as $key => $label) {
                        if ($club["allow_$key"]) $lvls[] = $label;
                    }
                    echo implode(', ', $lvls) ?: '-';
                    ?>
                </dd>
            </dl>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="text-center mt-3">
        <a href="../index.php" class="text-decoration-none text-secondary">
            <i class="fas fa-arrow-left me-1"></i>กลับหน้าหลัก
        </a>
    </div>
</div>

<!-- ===== Modal: นำเข้านักเรียน ===== -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#1a6b3c,#2d9b63); color:#fff;">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="fas fa-user-plus me-2"></i>นำเข้านักเรียนเข้าชุมนุม: <span id="modalClubName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Capacity Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-semibold text-muted">จำนวนสมาชิก</span>
                        <span class="small" id="capacityText">0/0 คน</span>
                    </div>
                    <div class="capacity-bar">
                        <div class="capacity-fill" id="capacityFill" style="width:0%"></div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small fw-semibold mb-1">ระดับชั้น</label>
                            <select class="form-select form-select-sm" id="filterGrade" onchange="onGradeChange()">
                                <option value="">-- เลือกระดับชั้น --</option>
                                <?php foreach ($all_grades as $g): ?>
                                <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small fw-semibold mb-1">ห้อง</label>
                            <select class="form-select form-select-sm" id="filterRoom" onchange="loadStudents()" disabled>
                                <option value="">-- ทุกห้อง --</option>
                            </select>
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <label class="form-label small fw-semibold mb-1">ค้นหาชื่อ</label>
                            <input type="text" class="form-control form-control-sm" id="searchName"
                                   placeholder="พิมพ์ชื่อ-นามสกุล..." oninput="filterTable()">
                        </div>
                        <div class="col-sm-12 col-md-3">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="showOnlyAvail"
                                       onchange="filterTable()" checked>
                                <label class="form-check-label small" for="showOnlyAvail">
                                    แสดงเฉพาะยังไม่มีชุมนุม
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Select All + Count -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="selectAllChk" onchange="toggleSelectAll()">
                        <label class="form-check-label small" for="selectAllChk">เลือกทั้งหมดที่แสดง</label>
                    </div>
                    <span class="badge bg-primary select-count-badge" id="selectedCount">เลือกแล้ว 0 คน</span>
                </div>

                <!-- Table -->
                <div class="student-scroll">
                    <table class="table table-hover table-import mb-0" id="studentTable">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ระดับ</th>
                                <th>ห้อง</th>
                                <th>เลขที่</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="studentTbody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-arrow-up me-2"></i>กรุณาเลือกระดับชั้นก่อน
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Hidden form for submit -->
                <form id="importForm" method="post" action="">
                    <input type="hidden" name="action" value="import_students">
                    <input type="hidden" name="club_id" id="importClubId" value="">
                    <div id="hiddenStudentIds"></div>
                </form>

            </div>
            <div class="modal-footer justify-content-between">
                <div class="text-muted small" id="importNote">
                    <i class="fas fa-info-circle me-1"></i>นักเรียนที่เลือกชุมนุมแล้วจะไม่สามารถนำเข้าซ้ำได้
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-save btn-sm px-4" id="btnImport" onclick="submitImport()" disabled>
                        <i class="fas fa-user-plus me-1"></i> นำเข้า <span id="btnCount"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentClubId   = null;
let currentMaxMem   = 0;
let currentMem      = 0;
let allStudents     = [];   // raw data from server
let visibleStudents = [];   // after filter

function openImportModal(clubId, clubName, curMem, maxMem) {
    currentClubId = clubId;
    currentMaxMem = maxMem;
    currentMem    = curMem;

    document.getElementById('modalClubName').textContent = clubName;
    document.getElementById('importClubId').value = clubId;
    document.getElementById('filterGrade').value  = '';
    document.getElementById('filterRoom').value   = '';
    document.getElementById('filterRoom').disabled = true;
    document.getElementById('searchName').value   = '';
    document.getElementById('showOnlyAvail').checked = true;
    document.getElementById('selectAllChk').checked  = false;

    updateCapacity(curMem, maxMem);
    resetTable('กรุณาเลือกระดับชั้นก่อน');
    updateSelectedCount();

    new bootstrap.Modal(document.getElementById('importModal')).show();
}

function updateCapacity(cur, max) {
    const pct = max > 0 ? Math.min(100, Math.round(cur / max * 100)) : 0;
    document.getElementById('capacityText').textContent = cur + '/' + max + ' คน';
    const fill = document.getElementById('capacityFill');
    fill.style.width = pct + '%';
    fill.className   = 'capacity-fill' + (pct >= 90 ? ' danger' : '');
}

async function onGradeChange() {
    const grade = document.getElementById('filterGrade').value;
    const roomSel = document.getElementById('filterRoom');
    roomSel.innerHTML = '<option value="">-- ทุกห้อง --</option>';
    roomSel.disabled  = !grade;
    allStudents = [];
    if (!grade) { resetTable('กรุณาเลือกระดับชั้นก่อน'); return; }

    // โหลดรายห้อง
    const res   = await fetch(`?ajax=get_rooms&grade=${encodeURIComponent(grade)}`);
    const rooms = await res.json();
    rooms.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r; opt.textContent = 'ห้อง ' + r;
        roomSel.appendChild(opt);
    });

    await loadStudents();
}

async function loadStudents() {
    const grade = document.getElementById('filterGrade').value;
    const room  = document.getElementById('filterRoom').value;
    if (!grade) return;

    resetTable('<i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...');

    const params = new URLSearchParams({ ajax: 'get_students', club_id: currentClubId, grade });
    if (room) params.set('room', room);

    const res = await fetch('?' + params.toString());
    allStudents = await res.json();

    filterTable();
}

function filterTable() {
    const search   = document.getElementById('searchName').value.trim().toLowerCase();
    const onlyAvail = document.getElementById('showOnlyAvail').checked;

    visibleStudents = allStudents.filter(s => {
        if (onlyAvail && s.selection_status == 1) return false;
        if (search) {
            const full = (s.firstname + ' ' + s.lastname).toLowerCase();
            if (!full.includes(search)) return false;
        }
        return true;
    });

    renderTable();
    document.getElementById('selectAllChk').checked = false;
}

function renderTable() {
    const tbody = document.getElementById('studentTbody');
    if (visibleStudents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
            <i class="fas fa-search me-2"></i>ไม่พบนักเรียนที่ตรงเงื่อนไข
        </td></tr>`;
        updateSelectedCount();
        return;
    }

    tbody.innerHTML = visibleStudents.map(s => {
        const selected = s.selection_status == 1;
        const statusBadge = selected
            ? `<span class="badge status-badge-selected">เลือกแล้ว</span>`
            : `<span class="badge status-badge-none">ยังไม่เลือก</span>`;
        const disabled = selected ? 'disabled' : '';
        return `<tr class="${selected ? 'table-light text-muted' : ''}">
            <td><input type="checkbox" class="form-check-input student-chk"
                       value="${s.student_id}" ${disabled} onchange="updateSelectedCount()"></td>
            <td class="small">${escHtml(s.student_id)}</td>
            <td>${escHtml(s.firstname)} ${escHtml(s.lastname)}</td>
            <td>${escHtml(s.grade_level)}</td>
            <td>${s.class_room}</td>
            <td>${s.class_number}</td>
            <td>${statusBadge}</td>
        </tr>`;
    }).join('');

    updateSelectedCount();
}

function resetTable(msg) {
    document.getElementById('studentTbody').innerHTML =
        `<tr><td colspan="7" class="text-center text-muted py-4">${msg}</td></tr>`;
    visibleStudents = [];
    updateSelectedCount();
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAllChk').checked;
    document.querySelectorAll('.student-chk:not(:disabled)').forEach(c => c.checked = checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const chks   = document.querySelectorAll('.student-chk:checked');
    const n      = chks.length;
    document.getElementById('selectedCount').textContent = 'เลือกแล้ว ' + n + ' คน';
    document.getElementById('btnCount').textContent      = n > 0 ? '(' + n + ' คน)' : '';
    document.getElementById('btnImport').disabled        = n === 0;

    // warn if over capacity
    const remaining = currentMaxMem - currentMem;
    const note = document.getElementById('importNote');
    if (n > remaining && remaining >= 0) {
        note.innerHTML = `<i class="fas fa-exclamation-triangle text-warning me-1"></i>
            เลือก ${n} คน แต่รับได้อีก ${remaining} คน — ส่วนที่เกินจะถูกข้าม`;
    } else {
        note.innerHTML = `<i class="fas fa-info-circle me-1"></i>นักเรียนที่เลือกชุมนุมแล้วจะไม่สามารถนำเข้าซ้ำได้`;
    }
}

function submitImport() {
    const chks = document.querySelectorAll('.student-chk:checked');
    if (chks.length === 0) return;

    // ใส่ hidden inputs
    const container = document.getElementById('hiddenStudentIds');
    container.innerHTML = '';
    chks.forEach(c => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'student_ids[]';
        inp.value = c.value;
        container.appendChild(inp);
    });

    document.getElementById('importForm').submit();
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>