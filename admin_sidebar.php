<?php
/**
 * admin_sidebar.php
 * Include ไฟล์นี้ในทุกหน้า admin แทน sidebar เดิม
 * ใช้งาน: <?php include 'admin_sidebar.php'; ?>
 *
 * ก่อน include ให้กำหนด $active_menu ด้วยค่าใดค่าหนึ่ง:
 *   students | clubs | teachers | import | reports | settings
 */
$active_menu = $active_menu ?? '';
?>

<!-- ===== Mobile Top Bar ===== -->
<div class="d-md-none bg-light border-bottom px-3 py-2 d-flex align-items-center justify-content-between sticky-top" style="z-index:1030">
    <span class="fw-semibold text-primary">
        <i class="fas fa-cogs me-2"></i>เมนูจัดการระบบ
    </span>
    <button class="btn btn-sm btn-outline-primary" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#adminSidebarOffcanvas">
        <i class="fas fa-bars"></i> เมนู
    </button>
</div>

<!-- ===== Desktop Sidebar ===== -->
<nav class="col-md-3 col-lg-2 d-none d-md-block bg-light sidebar" style="min-height: calc(100vh - 56px);">
    <div class="position-sticky pt-3">
        <?php include __DIR__ . '/admin_sidebar_menu.php'; ?>
    </div>
</nav>

<!-- ===== Mobile Offcanvas Sidebar ===== -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminSidebarOffcanvas"
     style="width: 260px;">
    <div class="offcanvas-header bg-primary text-white">
        <h6 class="offcanvas-title">
            <i class="fas fa-cogs me-2"></i>ระบบจัดการชุมนุม
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 bg-light">
        <?php include __DIR__ . '/admin_sidebar_menu.php'; ?>
    </div>
</div>