<?php
require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn() || !isAdmin()) {
    redirect('admin_login.php');
}

// ดึงข้อมูลสำหรับรายงาน
$academic_year = getSystemSetting('academic_year') ?: '2568';
$semester = getSystemSetting('semester') ?: '1';
$registration_open = getSystemSetting('registration_open') === 'true';

// 1. สถิติทั่วไป
$general_stats = [];

// จำนวนนักเรียนทั้งหมด
$students_sql = "SELECT 
    COUNT(*) as total_students,
    SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) as selected_count,
    SUM(CASE WHEN selection_status = 0 THEN 1 ELSE 0 END) as not_selected_count
    FROM students";
$students_result = mysqli_query($conn, $students_sql);
$general_stats['students'] = mysqli_fetch_assoc($students_result);

// จำนวนชุมนุมทั้งหมด
$clubs_sql = "SELECT 
    COUNT(*) as total_clubs,
    SUM(max_members) as total_capacity,
    SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked_clubs
    FROM clubs";
$clubs_result = mysqli_query($conn, $clubs_sql);
$general_stats['clubs'] = mysqli_fetch_assoc($clubs_result);

// จำนวนครู
$teachers_sql = "SELECT COUNT(*) as total_teachers FROM teachers";
$teachers_result = mysqli_query($conn, $teachers_sql);
$general_stats['teachers'] = mysqli_fetch_assoc($teachers_result);

// 2. รายงานตามระดับชั้น
$grade_stats_sql = "SELECT 
    grade_level,
    COUNT(*) as total_students,
    SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) as selected_students,
    ROUND((SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as selection_percentage
    FROM students 
    GROUP BY grade_level 
    ORDER BY grade_level";
$grade_stats_result = mysqli_query($conn, $grade_stats_sql);

// 3. รายงานชุมนุม (เรียงตามความนิยม)
$club_stats_sql = "SELECT 
    c.club_id,
    c.club_name,
    c.max_members,
    c.location,
    CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
    COUNT(s.student_id) as current_members,
    ROUND((COUNT(s.student_id) / c.max_members) * 100, 2) as occupancy_rate,
    c.is_locked
    FROM clubs c
    LEFT JOIN students s ON c.club_id = s.club_id AND s.selection_status = 1
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    GROUP BY c.club_id, c.club_name, c.max_members, c.location, c.is_locked, t.firstname, t.lastname
    ORDER BY current_members DESC, c.club_name";
$club_stats_result = mysqli_query($conn, $club_stats_sql);

// 4. รายงานครู
$teacher_stats_sql = "SELECT 
    t.teacher_id,
    CONCAT(t.firstname, ' ', t.lastname) as teacher_name,
    t.department,
    t.telephon,
    COUNT(c.club_id) as clubs_managed,
    GROUP_CONCAT(c.club_name SEPARATOR ', ') as club_names,
    COALESCE(SUM(club_members.member_count), 0) as total_students_supervised
    FROM teachers t
    LEFT JOIN clubs c ON t.teacher_id = c.teacher_id
    LEFT JOIN (
        SELECT club_id, COUNT(*) as member_count 
        FROM students 
        WHERE selection_status = 1 
        GROUP BY club_id
    ) club_members ON c.club_id = club_members.club_id
    GROUP BY t.teacher_id, t.firstname, t.lastname, t.department, t.telephon
    ORDER BY clubs_managed DESC, teacher_name";
$teacher_stats_result = mysqli_query($conn, $teacher_stats_sql);

// 5. รายงานห้องเรียน/ชั้นเรียน
$class_stats_sql = "SELECT 
    grade_level,
    class_room,
    COUNT(*) as total_students,
    SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) as selected_students,
    ROUND((SUM(CASE WHEN selection_status = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as selection_percentage
    FROM students 
    GROUP BY grade_level, class_room 
    ORDER BY grade_level, class_room";
$class_stats_result = mysqli_query($conn, $class_stats_sql);

// 6. ชุมนุมที่เต็ม/ว่าง/ล็อก
$club_status_sql = "SELECT 
    SUM(CASE WHEN current_members >= max_members THEN 1 ELSE 0 END) as full_clubs,
    SUM(CASE WHEN current_members < max_members AND is_locked = 0 THEN 1 ELSE 0 END) as available_clubs,
    SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked_clubs
    FROM (
        SELECT 
            c.club_id,
            c.max_members,
            c.is_locked,
            COUNT(s.student_id) as current_members
        FROM clubs c
        LEFT JOIN students s ON c.club_id = s.club_id AND s.selection_status = 1
        GROUP BY c.club_id, c.max_members, c.is_locked
    ) as club_summary";
$club_status_result = mysqli_query($conn, $club_status_sql);
$club_status_stats = mysqli_fetch_assoc($club_status_result);

// คำนวณเปอร์เซ็นต์การเลือกโดยรวม
$overall_percentage = $general_stats['students']['total_students'] > 0 ? 
    round(($general_stats['students']['selected_count'] / $general_stats['students']['total_students']) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานระบบ - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-card.primary { border-left-color: #0d6efd; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
        .stat-card.danger { border-left-color: #dc3545; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .progress-custom {
            height: 25px;
        }
        
        .table-sm th, .table-sm td {
            padding: 0.3rem;
            font-size: 0.875rem;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            .sidebar {
                display: none;
            }
            .container-fluid {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
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
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse no-print">
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
                            <a class="nav-link active" href="admin_reports.php">
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
                    <div>
                        <h1 class="h2">รายงานระบบ</h1>
                        <small class="text-muted">ปีการศึกษา <?php echo $academic_year; ?> ภาคเรียนที่ <?php echo $semester; ?></small>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0 no-print">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-success" onclick="exportReport('excel')">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print"></i> พิมพ์รายงาน
                            </button>
                        </div>
                    </div>
                </div>

                <!-- รายงานห้องเรียน -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-door-open"></i> รายงานการเลือกชุมนุมตามห้องเรียน</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm" id="classTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ระดับชั้น</th>
                                        <th class="text-center">ห้อง</th>
                                        <th class="text-center">นักเรียนทั้งหมด</th>
                                        <th class="text-center">เลือกแล้ว</th>
                                        <th class="text-center">ยังไม่เลือก</th>
                                        <th class="text-center">เปอร์เซ็นต์</th>
                                        <th class="text-center">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($class = mysqli_fetch_assoc($class_stats_result)): ?>
                                        <?php $not_selected = $class['total_students'] - $class['selected_students']; ?>
                                        <tr>
                                            <td><?php echo $class['grade_level']; ?></td>
                                            <td class="text-center"><?php echo $class['class_room']; ?></td>
                                            <td class="text-center"><?php echo $class['total_students']; ?></td>
                                            <td class="text-center text-success"><?php echo $class['selected_students']; ?></td>
                                            <td class="text-center text-warning"><?php echo $not_selected; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $class['selection_percentage'] >= 80 ? 'success' : ($class['selection_percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $class['selection_percentage']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress progress-custom">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $class['selection_percentage']; ?>%" 
                                                         aria-valuenow="<?php echo $class['selection_percentage']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $class['selection_percentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลระบบ -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> ข้อมูลระบบ</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>ปีการศึกษา:</strong></td>
                                        <td><?php echo $academic_year; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>ภาคเรียน:</strong></td>
                                        <td><?php echo $semester; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>สถานะการลงทะเบียน:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $registration_open ? 'success' : 'danger'; ?>">
                                                <?php echo $registration_open ? 'เปิด' : 'ปิด'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>วันที่สร้างรายงาน:</strong></td>
                                        <td><?php echo date('d/m/Y H:i:s'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>จำนวนครูทั้งหมด:</strong></td>
                                        <td><?php echo number_format($general_stats['teachers']['total_teachers']); ?> คน</td>
                                    </tr>
                                    <tr>
                                        <td><strong>ความจุชุมนุมรวม:</strong></td>
                                        <td><?php echo number_format($general_stats['clubs']['total_capacity']); ?> คน</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- jQuery, Bootstrap, and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // เริ่มต้น DataTables
            $('#clubsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                "pageLength": 25,
                "order": [[5, "desc"]] // เรียงตามจำนวนที่ลงทะเบียนแล้ว
            });
            
            $('#teachersTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                "pageLength": 25,
                "order": [[4, "desc"]] // เรียงตามจำนวนชุมนุมที่ดูแล
            });
            
            $('#classTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                "pageLength": 25,
                "order": [[5, "desc"]] // เรียงตามเปอร์เซ็นต์
            });
        });

        // สร้างกราฟแสดงสัดส่วนการเลือกชุมนุม
        const selectionCtx = document.getElementById('selectionChart').getContext('2d');
        const selectionChart = new Chart(selectionCtx, {
            type: 'doughnut',
            data: {
                labels: ['เลือกแล้ว', 'ยังไม่เลือก'],
                datasets: [{
                    data: [
                        <?php echo $general_stats['students']['selected_count']; ?>, 
                        <?php echo $general_stats['students']['not_selected_count']; ?>
                    ],
                    backgroundColor: ['#28a745', '#ffc107'],
                    borderColor: ['#1e7e34', '#e0a800'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'สัดส่วนการเลือกชุมนุม'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // สร้างกราฟแสดงการเลือกชุมนุมตามระดับชั้น
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        
        <?php
        // รีเซ็ต pointer สำหรับ grade_stats_result
        mysqli_data_seek($grade_stats_result, 0);
        $grade_labels = [];
        $grade_selected = [];
        $grade_not_selected = [];
        while($grade = mysqli_fetch_assoc($grade_stats_result)) {
            $grade_labels[] = $grade['grade_level'];
            $grade_selected[] = $grade['selected_students'];
            $grade_not_selected[] = $grade['total_students'] - $grade['selected_students'];
        }
        ?>
        
        const gradeChart = new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($grade_labels); ?>,
                datasets: [
                    {
                        label: 'เลือกแล้ว',
                        data: <?php echo json_encode($grade_selected); ?>,
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    },
                    {
                        label: 'ยังไม่เลือก',
                        data: <?php echo json_encode($grade_not_selected); ?>,
                        backgroundColor: '#ffc107',
                        borderColor: '#e0a800',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'การเลือกชุมนุมตามระดับชั้น'
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });

        // ฟังก์ชัน Export รายงาน
        function exportReport(format) {
            if (format === 'excel') {
                // สร้าง form สำหรับส่งข้อมูลไป export
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export_report.php';
                form.target = '_blank';
                
                const formatInput = document.createElement('input');
                formatInput.type = 'hidden';
                formatInput.name = 'format';
                formatInput.value = 'excel';
                form.appendChild(formatInput);
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }
        
        // ปรับแต่งการพิมพ์
        window.addEventListener('beforeprint', function() {
            // ซ่อน DataTables controls
            $('.dataTables_wrapper .dataTables_length').hide();
            $('.dataTables_wrapper .dataTables_filter').hide();
            $('.dataTables_wrapper .dataTables_info').hide();
            $('.dataTables_wrapper .dataTables_paginate').hide();
        });
        
        window.addEventListener('afterprint', function() {
            // แสดง DataTables controls กลับมา
            $('.dataTables_wrapper .dataTables_length').show();
            $('.dataTables_wrapper .dataTables_filter').show();
            $('.dataTables_wrapper .dataTables_info').show();
            $('.dataTables_wrapper .dataTables_paginate').show();
        });
    </script>
</body>
</html></div>
                </div>

                <!-- สถิติภาพรวม -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4><i class="fas fa-chart-pie text-primary"></i> สถิติภาพรวม</h4>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-primary">นักเรียนทั้งหมด</h6>
                                        <h3 class="mb-0"><?php echo number_format($general_stats['students']['total_students']); ?></h3>
                                        <small class="text-muted">คน</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-success">เลือกชุมนุมแล้ว</h6>
                                        <h3 class="mb-0"><?php echo number_format($general_stats['students']['selected_count']); ?></h3>
                                        <small class="text-muted"><?php echo $overall_percentage; ?>%</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-warning">ยังไม่เลือกชุมนุม</h6>
                                        <h3 class="mb-0"><?php echo number_format($general_stats['students']['not_selected_count']); ?></h3>
                                        <small class="text-muted"><?php echo 100 - $overall_percentage; ?>%</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-info">ชุมนุมทั้งหมด</h6>
                                        <h3 class="mb-0"><?php echo number_format($general_stats['clubs']['total_clubs']); ?></h3>
                                        <small class="text-muted">รับได้ <?php echo number_format($general_stats['clubs']['total_capacity']); ?> คน</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- กราฟสถิติ -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-pie"></i> สัดส่วนการเลือกชุมนุม</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="selectionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> การเลือกชุมนุมตามระดับชั้น</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="gradeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- รายงานตามระดับชั้น -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-layer-group"></i> รายงานการเลือกชุมนุมตามระดับชั้น</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ระดับชั้น</th>
                                        <th class="text-center">นักเรียนทั้งหมด</th>
                                        <th class="text-center">เลือกแล้ว</th>
                                        <th class="text-center">ยังไม่เลือก</th>
                                        <th class="text-center">เปอร์เซ็นต์</th>
                                        <th class="text-center">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($grade = mysqli_fetch_assoc($grade_stats_result)): ?>
                                        <?php $not_selected = $grade['total_students'] - $grade['selected_students']; ?>
                                        <tr>
                                            <td><strong><?php echo $grade['grade_level']; ?></strong></td>
                                            <td class="text-center"><?php echo number_format($grade['total_students']); ?></td>
                                            <td class="text-center text-success"><?php echo number_format($grade['selected_students']); ?></td>
                                            <td class="text-center text-warning"><?php echo number_format($not_selected); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $grade['selection_percentage'] >= 80 ? 'success' : ($grade['selection_percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $grade['selection_percentage']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress progress-custom">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $grade['selection_percentage']; ?>%" 
                                                         aria-valuenow="<?php echo $grade['selection_percentage']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $grade['selection_percentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- รายงานชุมนุม -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-users"></i> รายงานชุมนุม (เรียงตามความนิยม)</h6>
                        <div class="no-print">
                            <small class="text-muted">
                                เต็ม: <?php echo $club_status_stats['full_clubs']; ?> | 
                                ว่าง: <?php echo $club_status_stats['available_clubs']; ?> | 
                                ล็อก: <?php echo $club_status_stats['locked_clubs']; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm" id="clubsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="3%">#</th>
                                        <th width="20%">ชื่อชุมนุม</th>
                                        <th width="15%">ครูที่ปรึกษา</th>
                                        <th width="10%">สถานที่</th>
                                        <th width="8%">รับได้</th>
                                        <th width="8%">ลงทะเบียน</th>
                                        <th width="8%">เหลือ</th>
                                        <th width="10%">อัตราเต็ม</th>
                                        <th width="8%">สถานะ</th>
                                        <th width="10%">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    while($club = mysqli_fetch_assoc($club_stats_result)): 
                                        $remaining = $club['max_members'] - $club['current_members'];
                                        $is_full = $remaining <= 0;
                                        $is_locked = $club['is_locked'];
                                    ?>
                                        <tr class="<?php echo $is_locked ? 'table-secondary' : ($is_full ? 'table-warning' : ''); ?>">
                                            <td class="text-center"><?php echo $rank++; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($club['club_name']); ?>
                                                <?php if ($is_locked): ?>
                                                    <i class="fas fa-lock text-danger ms-1"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($club['teacher_name']); ?></td>
                                            <td><?php echo htmlspecialchars($club['location']); ?></td>
                                            <td class="text-center"><?php echo $club['max_members']; ?></td>
                                            <td class="text-center text-primary"><?php echo $club['current_members']; ?></td>
                                            <td class="text-center">
                                                <span class="<?php echo $remaining <= 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo max(0, $remaining); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $club['occupancy_rate'] >= 100 ? 'danger' : ($club['occupancy_rate'] >= 80 ? 'warning' : 'success'); ?>">
                                                    <?php echo $club['occupancy_rate']; ?>%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($is_locked): ?>
                                                    <span class="badge bg-secondary">ล็อก</span>
                                                <?php elseif ($is_full): ?>
                                                    <span class="badge bg-danger">เต็ม</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">ว่าง</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="progress progress-custom">
                                                    <div class="progress-bar bg-<?php echo $club['occupancy_rate'] >= 100 ? 'danger' : ($club['occupancy_rate'] >= 80 ? 'warning' : 'info'); ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min($club['occupancy_rate'], 100); ?>%" 
                                                         aria-valuenow="<?php echo $club['occupancy_rate']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $club['occupancy_rate']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- รายงานครู -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> รายงานครูที่ปรึกษาชุมนุม</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm" id="teachersTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>รหัสครู</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>แผนก</th>
                                        <th>เบอร์โทร</th>
                                        <th class="text-center">จำนวนชุมนุม</th>
                                        <th class="text-center">นักเรียนที่ดูแล</th>
                                        <th>ชุมนุมที่ดูแล</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($teacher = mysqli_fetch_assoc($teacher_stats_result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['teacher_name']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['department'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['telephon'] ?: '-'); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?php echo $teacher['clubs_managed']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $teacher['total_students_supervised']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($teacher['club_names'] ?: 'ไม่มี'); ?></small>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>