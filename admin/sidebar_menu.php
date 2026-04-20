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
</style>