<?php
require_once __DIR__ . '/../config.php';
$active_menu = '';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$changelog_file = __DIR__ . '/../changelog.json';
$changelog = [];
if (file_exists($changelog_file)) {
    $changelog = json_decode(file_get_contents($changelog_file), true) ?? [];
}

$version_file = __DIR__ . '/../version.json';
$version_info = [];
if (file_exists($version_file)) {
    $version_info = json_decode(file_get_contents($version_file), true) ?? [];
}
$current_version = $version_info['version'] ?? '-';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเปลี่ยนแปลง - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .version-badge {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        .changelog-card { border-left: 4px solid #0d6efd; }
        .changelog-card.current { border-left-color: #198754; }
        .change-list li { padding: 4px 0; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-cogs me-2"></i> ระบบจัดการชุมนุม
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php" target="_blank">
                            <i class="fas fa-home"></i> หน้าหลัก
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> ผู้ดูแลระบบ: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 mb-0">
                        <i class="fas fa-code-branch me-2 text-primary"></i>ประวัติการเปลี่ยนแปลง
                    </h1>
                    <span class="badge bg-success ms-3 version-badge">เวอร์ชันปัจจุบัน v<?php echo htmlspecialchars($current_version); ?></span>
                </div>

                <?php if (empty($changelog)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                        <p>ไม่พบข้อมูล changelog</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($changelog as $index => $entry): ?>
                        <?php
                        $is_current = ($entry['version'] === $current_version);
                        ?>
                        <div class="card mb-4 shadow-sm changelog-card <?php echo $is_current ? 'current' : ''; ?>">
                            <div class="card-header d-flex align-items-center gap-3 <?php echo $is_current ? 'bg-success bg-opacity-10' : 'bg-light'; ?>">
                                <span class="badge <?php echo $is_current ? 'bg-success' : 'bg-secondary'; ?> version-badge">
                                    v<?php echo htmlspecialchars($entry['version']); ?>
                                </span>
                                <?php if (!empty($entry['date'])): ?>
                                    <span class="text-muted small">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php
                                        $d = DateTime::createFromFormat('Y-m-d', $entry['date']);
                                        echo $d ? $d->format('d/m/Y') : htmlspecialchars($entry['date']);
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($is_current): ?>
                                    <span class="badge bg-success ms-auto">
                                        <i class="fas fa-check me-1"></i>เวอร์ชันปัจจุบัน
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($entry['changes'])): ?>
                                    <ul class="change-list mb-0 ps-3">
                                        <?php foreach ($entry['changes'] as $change): ?>
                                            <li><?php echo htmlspecialchars($change); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">ไม่มีรายละเอียดการเปลี่ยนแปลง</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
