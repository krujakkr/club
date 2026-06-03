<?php
/**
 * admin_sidebar_menu.php
 * ไฟล์นี้ถูก include จาก admin_sidebar.php เท่านั้น
 * ใช้ตัวแปร $active_menu จาก parent
 */
$menu_items = [
    'students' => ['href' => 'index.php',     'icon' => 'fa-tachometer-alt',     'label' => 'จัดการนักเรียน'],
    'clubs'    => ['href' => 'clubs.php',     'icon' => 'fa-users',              'label' => 'จัดการชุมนุม'],
    'teachers' => ['href' => 'teachers.php',  'icon' => 'fa-chalkboard-teacher', 'label' => 'จัดการครู'],
    'import'   => ['href' => 'import.php',    'icon' => 'fa-file-import',        'label' => 'นำเข้าข้อมูล'],
    'reports'  => ['href' => 'reports.php',   'icon' => 'fa-chart-bar',          'label' => 'รายงาน'],
    'settings' => ['href' => 'settings.php',  'icon' => 'fa-cog',                'label' => 'ตั้งค่าระบบ'],
];
?>
<?php
$_ver = json_decode(file_get_contents(__DIR__ . '/../version.json'), true);
?>
<ul class="nav flex-column pt-2 pb-3">
    <?php foreach ($menu_items as $key => $item): ?>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2 px-3 py-2
                  <?php echo $active_menu === $key ? 'active fw-semibold' : ''; ?>"
           href="<?php echo $item['href']; ?>">
            <i class="fas <?php echo $item['icon']; ?> fa-fw"></i>
            <?php echo $item['label']; ?>
        </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item mt-2 border-top pt-2 mx-2">
        <a class="nav-link d-flex align-items-center gap-2 px-3 py-2 text-danger"
           href="../logout.php">
            <i class="fas fa-sign-out-alt fa-fw"></i>
            ออกจากระบบ
        </a>
    </li>
</ul>

<style>
    /* Sidebar menu styles (ใช้ได้ทั้ง desktop และ offcanvas) */
    .nav-link {
        color: #333;
        border-radius: 6px;
        margin: 0 8px;
        transition: background .15s;
    }
    .nav-link:hover  { background-color: #e9ecef; color: #0d6efd; }
    .nav-link.active { background-color: #0d6efd; color: #fff !important; }
    .nav-link.text-danger:hover { background-color: #f8d7da; color: #b02a37 !important; }
    .sidebar-version { font-size: 0.75rem; color: #adb5bd; padding: 8px 16px 4px; }
</style>
<div class="sidebar-version">
    <a href="changelog.php" class="text-decoration-none" style="color: inherit;" title="ดูประวัติการเปลี่ยนแปลง">
        <i class="fas fa-code-branch me-1"></i>v<?php echo htmlspecialchars($_ver['version'] ?? '-'); ?>
    </a><br>
    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_ver['author'] ?? ''); ?>
</div>