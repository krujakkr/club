<?php
// เปิดแสดง error เพื่อดีบัก (ปิดเมื่อใช้งานจริง)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'config.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isLoggedIn()) {
    redirect('admin_login.php');
}

// รับค่า club_id
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

if ($club_id <= 0) {
    die('ไม่พบข้อมูลชุมนุม');
}

// ดึงข้อมูลชุมนุมพร้อมข้อมูลครู
$sql_club = "SELECT c.*, t.teacher_id as t_id, t.firstname as teacher_firstname, t.lastname as teacher_lastname 
             FROM clubs c 
             LEFT JOIN teachers t ON c.teacher_id = t.teacher_id 
             WHERE c.club_id = ?";
$stmt = mysqli_prepare($conn, $sql_club);
mysqli_stmt_bind_param($stmt, "i", $club_id);
mysqli_stmt_execute($stmt);
$result_club = mysqli_stmt_get_result($stmt);
$club = mysqli_fetch_assoc($result_club);

if (!$club) {
    die('ไม่พบข้อมูลชุมนุม รหัส: ' . $club_id);
}

// ดึงข้อมูลการตั้งค่าระบบ
if (function_exists('getSystemSetting')) {
    $academic_year = getSystemSetting('academic_year');
    $semester = getSystemSetting('semester');
} else {
    // ดึงจากตาราง system_settings
    $academic_year = '';
    $semester = '';
    
    $settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('academic_year', 'semester')";
    $settings_result = mysqli_query($conn, $settings_sql);
    if ($settings_result) {
        while ($setting = mysqli_fetch_assoc($settings_result)) {
            if ($setting['setting_key'] == 'academic_year') {
                $academic_year = $setting['setting_value'];
            } elseif ($setting['setting_key'] == 'semester') {
                $semester = $setting['setting_value'];
            }
        }
    }
}

// ดึงรายชื่อสมาชิกจากตาราง students (ใช้ชื่อคอลัมน์ที่ถูกต้อง)
$sql_members = "SELECT student_id, firstname, lastname, grade_level, class_room, class_number
                FROM students
                WHERE club_id = ?
                ORDER BY grade_level, class_room, class_number, student_id";
$stmt_members = mysqli_prepare($conn, $sql_members);
mysqli_stmt_bind_param($stmt_members, "i", $club_id);
mysqli_stmt_execute($stmt_members);
$result_members = mysqli_stmt_get_result($stmt_members);

// จัดกลุ่มตามระดับชั้น
$members_by_level = [];
while ($member = mysqli_fetch_assoc($result_members)) {
    $level_key = $member['grade_level'] . '/' . $member['class_room'];
    if (!isset($members_by_level[$level_key])) {
        $members_by_level[$level_key] = [];
    }
    $members_by_level[$level_key][] = $member;
}

// เรียงลำดับ key ตามระดับชั้น
ksort($members_by_level);

// นับจำนวนสมาชิกทั้งหมด
$total_members = 0;
foreach ($members_by_level as $members) {
    $total_members += count($members);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์รายชื่อสมาชิก - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
            }
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'TH Sarabun New', 'Sarabun', sans-serif;
            font-size: 14pt;
            line-height: 1.3;
            padding: 10px;
            background-color: #f5f5f5;
        }
        
        .print-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 10mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 8px;
        }
        
        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .header h2 {
            font-size: 14pt;
            font-weight: normal;
        }
        
        .club-info {
            border: 1px solid #999;
            padding: 8px 12px;
            margin-bottom: 10px;
            font-size: 13pt;
        }
        
        .club-info-row {
            display: flex;
            margin-bottom: 3px;
        }
        
        .club-info-label {
            font-weight: bold;
            width: 130px;
            flex-shrink: 0;
        }
        
        .club-info-value {
            flex: 1;
        }
        
        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 13pt;
        }
        
        .members-table th,
        .members-table td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: left;
        }
        
        .members-table th {
            background-color: transparent;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid #333;
        }
        
        .members-table td.center {
            text-align: center;
        }
        
        .level-header {
            background-color: #eee !important;
            font-weight: bold;
        }
        
        .level-header td {
            text-align: left !important;
            font-size: 12pt;
            padding: 3px 6px;
        }
        
        .summary {
            margin-top: 10px;
            text-align: right;
            font-size: 13pt;
        }
        
        .signature-section {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
            font-size: 13pt;
        }
        
        .signature-line {
            border-top: 1px dotted #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        
        .print-date {
            margin-top: 15px;
            text-align: right;
            font-size: 11pt;
            color: #666;
        }
        
        .btn-toolbar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14pt;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .empty-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- ปุ่มควบคุม -->
    <div class="btn-toolbar no-print">
        <button class="btn btn-primary" onclick="window.print()">
            🖨️ พิมพ์
        </button>
        <a href="admin_clubs.php" class="btn btn-secondary">
            ← กลับ
        </a>
    </div>
    
    <div class="print-container">
        <!-- หัวกระดาษ -->
        <div class="header">
            <h1>รายชื่อนักเรียนชุมนุม</h1>
            <?php if ($academic_year || $semester): ?>
            <h2>ปีการศึกษา <?php echo $academic_year; ?> ภาคเรียนที่ <?php echo $semester; ?></h2>
            <?php endif; ?>
        </div>
        
        <!-- ข้อมูลชุมนุม -->
        <div class="club-info">
            <div class="club-info-row">
                <span class="club-info-label">รหัสวิชา:</span>
                <span class="club-info-value"><?php echo $club_id; ?></span>
            </div>
            <div class="club-info-row">
                <span class="club-info-label">ชื่อวิชา:</span>
                <span class="club-info-value"><?php echo htmlspecialchars($club['club_name']); ?></span>
            </div>
            <div class="club-info-row">
                <span class="club-info-label">รหัสครูที่ปรึกษา:</span>
                <span class="club-info-value"><?php echo $club['t_id'] ? $club['t_id'] : '-'; ?></span>
            </div>
            <div class="club-info-row">
                <span class="club-info-label">ชื่อครูที่ปรึกษา:</span>
                <span class="club-info-value">
                    <?php 
                    if ($club['teacher_firstname']) {
                        echo htmlspecialchars($club['teacher_firstname'] . ' ' . $club['teacher_lastname']);
                    } else {
                        echo '-';
                    }
                    ?>
                </span>
            </div>
            <div class="club-info-row">
                <span class="club-info-label">สถานที่เรียน:</span>
                <span class="club-info-value"><?php echo $club['location'] ? htmlspecialchars($club['location']) : '-'; ?></span>
            </div>
            <div class="club-info-row">
                <span class="club-info-label">จำนวนที่รับ/ลงทะเบียน:</span>
                <span class="club-info-value"><strong><?php echo $total_members; ?></strong> / <?php echo $club['max_members']; ?> คน</span>
            </div>
        </div>
        
        <!-- ตารางรายชื่อสมาชิก -->
        <?php if ($total_members > 0): ?>
        <table class="members-table">
            <thead>
                <tr>
                    <th width="6%">ลำดับ</th>
                    <th width="8%">เลขที่</th>
                    <th width="16%">รหัสนักเรียน</th>
                    <th width="38%">ชื่อ - นามสกุล</th>
                    <th width="12%">ชั้น/ห้อง</th>
                    <th width="20%">ลายเซ็น</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $overall_index = 1;
                foreach ($members_by_level as $level => $members): 
                ?>
                <tr class="level-header">
                    <td colspan="6"><?php echo $level; ?> (<?php echo count($members); ?> คน)</td>
                </tr>
                <?php foreach ($members as $member): ?>
                <tr>
                    <td class="center"><?php echo $overall_index++; ?></td>
                    <td class="center"><?php echo $member['class_number']; ?></td>
                    <td class="center"><?php echo htmlspecialchars($member['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($member['firstname'] . ' ' . $member['lastname']); ?></td>
                    <td class="center"><?php echo $member['grade_level'] . '/' . $member['class_room']; ?></td>
                    <td></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <strong>รวมทั้งสิ้น <?php echo $total_members; ?> คน</strong>
        </div>
        
        <?php else: ?>
        <div class="empty-message">
            <p>ยังไม่มีนักเรียนลงทะเบียนในชุมนุมนี้</p>
        </div>
        <?php endif; ?>
        
        <!-- ส่วนลงนาม -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    (............................................)<br>
                    ครูที่ปรึกษาชุมนุม
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    (............................................)<br>
                    ผู้บริหาร/หัวหน้างาน
                </div>
            </div>
        </div>
        
        <!-- วันที่พิมพ์ -->
        <div class="print-date">
            พิมพ์เมื่อ: <?php 
            $thai_months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                           'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
            $now = new DateTime();
            echo $now->format('j') . ' ' . $thai_months[(int)$now->format('n')] . ' ' . ($now->format('Y') + 543) . ' เวลา ' . $now->format('H:i') . ' น.';
            ?>
        </div>
    </div>
</body>
</html>