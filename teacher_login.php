<?php
require_once 'config.php';

// ถ้าล็อกอินเป็นครูแล้วให้ไปที่หน้า dashboard
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher') {
    redirect('teacher_dashboard.php');
}

$error = '';

// รับข้อมูลจากฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = clean($conn, $_POST['teacher_id']);
    $password   = $_POST['password'];

    // ตรวจสอบว่า teacher_id เป็นตัวเลขเท่านั้น
    if (!preg_match('/^\d+$/', $teacher_id)) {
        $error = "รหัสครูต้องเป็นตัวเลขเท่านั้น";
    } elseif (strlen($password) !== 10 || !preg_match('/^\d{10}$/', $password)) {
        $error = "รหัสผ่านต้องเป็นเบอร์โทรศัพท์ 10 หลัก";
    } else {
        // ตรวจสอบช่วงเวลาที่อนุญาตให้ครู login
        $period_start = getSystemSetting('teacher_edit_start');
        $period_end   = getSystemSetting('teacher_edit_end');
        $now          = date('Y-m-d H:i:s');

        if (!empty($period_start) && !empty($period_end)) {
            if ($now < $period_start || $now > $period_end) {
                $start_fmt = date('d/m/Y H:i', strtotime($period_start));
                $end_fmt   = date('d/m/Y H:i', strtotime($period_end));
                $error = "ขณะนี้ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไขข้อมูลชุมนุม<br>
                          <small>ช่วงเวลาที่อนุญาต: $start_fmt &ndash; $end_fmt</small>";
            }
        }

        if (empty($error)) {
            // ค้นหาครูในฐานข้อมูล
            $sql    = "SELECT * FROM teachers WHERE teacher_id = '$teacher_id'";
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                $teacher = mysqli_fetch_assoc($result);

                // เปรียบเทียบเบอร์โทรศัพท์
                if ($teacher['telephon'] === $password) {
                    $_SESSION['user_id']      = $teacher['teacher_id'];
                    $_SESSION['username']     = $teacher['firstname'] . ' ' . $teacher['lastname'];
                    $_SESSION['user_type']    = 'teacher';
                    $_SESSION['teacher_data'] = $teacher;

                    logActivity($teacher['teacher_id'], 'teacher', 'login', 'ครูเข้าสู่ระบบ');
                    redirect('teacher_dashboard.php');
                } else {
                    $error = "รหัสผ่าน (เบอร์โทรศัพท์) ไม่ถูกต้อง";
                }
            } else {
                $error = "ไม่พบรหัสครูนี้ในระบบ";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบครู - ระบบเลือกกิจกรรมชุมนุมออนไลน์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #1a6b3c 0%, #2d9b63 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 460px;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1a6b3c, #2d9b63);
            color: #fff;
            padding: 32px 24px 24px;
            text-align: center;
        }
        .login-header .icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .login-body {
            padding: 32px;
        }
        .form-control:focus {
            border-color: #2d9b63;
            box-shadow: 0 0 0 0.2rem rgba(45,155,99,0.25);
        }
        .btn-teacher {
            background: linear-gradient(135deg, #1a6b3c, #2d9b63);
            border: none;
            color: #fff;
            padding: 12px;
            font-size: 1rem;
            border-radius: 8px;
            transition: opacity .2s;
        }
        .btn-teacher:hover { opacity: 0.9; color: #fff; }
        .input-group-text {
            background: #f0faf5;
            border-color: #ced4da;
            color: #2d9b63;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="login-card">
        <div class="login-header">
            <div class="icon-wrap">
                <i class="fas fa-chalkboard-teacher fa-2x"></i>
            </div>
            <h4 class="mb-1 fw-bold">เข้าสู่ระบบครู</h4>
            <p class="mb-0 opacity-75 small">ระบบเลือกกิจกรรมชุมนุมออนไลน์</p>
        </div>

        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                <div class="mb-3">
                    <label for="teacher_id" class="form-label fw-semibold">
                        <i class="fas fa-id-badge me-1 text-success"></i> รหัสครู
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="teacher_id" name="teacher_id"
                               placeholder="รหัสครู (ตัวเลข 3 หลัก)"
                               value="<?php echo isset($_POST['teacher_id']) ? htmlspecialchars($_POST['teacher_id']) : ''; ?>"
                               inputmode="numeric" pattern="\d+" maxlength="10" required>
                    </div>
                    <div class="form-text">ใช้รหัสครู (teacher_id) ตัวเลข เช่น 108, 203</div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="fas fa-phone me-1 text-success"></i> รหัสผ่าน (เบอร์โทรศัพท์)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="เบอร์โทรศัพท์ 10 หลัก"
                               inputmode="numeric" maxlength="10" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="form-text">ใช้เบอร์โทรศัพท์ที่ลงทะเบียนไว้ในระบบ</div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-teacher">
                        <i class="fas fa-sign-in-alt me-2"></i> เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <hr class="my-4">
            <div class="text-center">
                <a href="index.php" class="text-decoration-none text-secondary">
                    <i class="fas fa-arrow-left me-1"></i> กลับไปยังหน้าหลัก
                </a>
                <span class="mx-2 text-muted">|</span>
                <a href="admin_login.php" class="text-decoration-none text-secondary">
                    <i class="fas fa-user-shield me-1"></i> เข้าสู่ระบบผู้ดูแล
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
        const pw   = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pw.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });

    // Allow digits only in teacher_id and password
    ['teacher_id', 'password'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });
</script>
</body>
</html>