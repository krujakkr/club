<!-- 
=======================================================
  PATCH สำหรับ admin_settings.php
  วิธีใช้: คัดลอกโค้ดส่วนนี้แทรกใน admin_settings.php

  1) ส่วน PHP (ด้านบน ก่อน <!DOCTYPE html>) — แทรกหลัง
     บล็อก "อัพเดทชื่อโรงเรียน" และก่อน "จัดการการล้างข้อมูล"

  2) ส่วน HTML — แทรกหลัง </form> แรก (ฟอร์มตั้งค่าหลัก)
=======================================================
-->

<!-- ========== ส่วนที่ 1: PHP Logic (แทรกใน POST handler) ========== -->
<?php
/*
วางโค้ดด้านล่างนี้ต่อจาก:
    if (isset($_POST['school_name']) && !empty($_POST['school_name'])) { ... }

และก่อน:
    if (isset($_POST['clear_data_type'])) { ... }
*/

// อัพเดทช่วงเวลา login ครู
if (isset($_POST['teacher_edit_start']) && isset($_POST['teacher_edit_end'])) {
    $t_start = mysqli_real_escape_string($conn, $_POST['teacher_edit_start']);
    $t_end   = mysqli_real_escape_string($conn, $_POST['teacher_edit_end']);

    // ล้างค่า (ปิดช่วงเวลา)
    if (empty($t_start) && empty($t_end)) {
        updateSystemSetting('teacher_edit_start', '');
        updateSystemSetting('teacher_edit_end',   '');
        logActivity($_SESSION['user_id'], 'admin', 'update_settings', 'ล้างช่วงเวลา login ครู');
        $success_messages[] = 'ล้างช่วงเวลา login ครูเรียบร้อยแล้ว';
    } elseif (!empty($t_start) && !empty($t_end)) {
        // แปลง datetime-local เป็น Y-m-d H:i:s
        $t_start_db = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $t_start)));
        $t_end_db   = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $t_end)));

        if ($t_end_db <= $t_start_db) {
            $error_messages[] = 'วันสิ้นสุดต้องหลังวันเริ่มต้น';
        } else {
            updateSystemSetting('teacher_edit_start', $t_start_db);
            updateSystemSetting('teacher_edit_end',   $t_end_db);
            logActivity($_SESSION['user_id'], 'admin', 'update_settings',
                "ตั้งช่วงเวลา login ครู: $t_start_db ถึง $t_end_db");
            $success_messages[] = 'บันทึกช่วงเวลา login ครูเรียบร้อยแล้ว';
        }
    } else {
        $error_messages[] = 'กรุณากรอกทั้งวันเริ่มต้นและวันสิ้นสุด';
    }
}
?>

<!-- ========== ส่วนที่ 2: ดึงค่าตั้งค่าปัจจุบัน (ด้านบน, นอก POST) ========== -->
<?php
/*
วางต่อจาก:
    $school_name = getSystemSetting('school_name') ?: 'โรงเรียนแก่นนครวิทยาลัย';
*/
$teacher_edit_start = getSystemSetting('teacher_edit_start') ?: '';
$teacher_edit_end   = getSystemSetting('teacher_edit_end')   ?: '';

// แปลงเป็น datetime-local format สำหรับ HTML input
$teacher_start_input = !empty($teacher_edit_start)
    ? date('Y-m-d\TH:i', strtotime($teacher_edit_start)) : '';
$teacher_end_input   = !empty($teacher_edit_end)
    ? date('Y-m-d\TH:i', strtotime($teacher_edit_end))   : '';

$teacher_period_active = false;
if (!empty($teacher_edit_start) && !empty($teacher_edit_end)) {
    $now = date('Y-m-d H:i:s');
    $teacher_period_active = ($now >= $teacher_edit_start && $now <= $teacher_edit_end);
}
?>

<!-- ========== ส่วนที่ 3: HTML Card (แทรกหลัง </div> ของ card ตั้งค่าหลัก) ========== -->

<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-chalkboard-teacher me-2"></i>
            ช่วงเวลาที่ครูสามารถแก้ไขข้อมูลชุมนุมได้
        </h5>
    </div>
    <div class="card-body">

        <!-- สถานะปัจจุบัน -->
        <div class="alert <?php echo $teacher_period_active ? 'alert-success' : 'alert-secondary'; ?> d-flex align-items-center mb-4">
            <i class="fas fa-<?php echo $teacher_period_active ? 'check-circle' : 'clock'; ?> me-3 fa-lg"></i>
            <div>
                <?php if ($teacher_period_active): ?>
                    <strong>ช่วงเวลา Login ครู: เปิดอยู่</strong><br>
                    <small>ถึง <?php echo date('d/m/Y H:i', strtotime($teacher_edit_end)); ?> น.</small>
                <?php elseif (!empty($teacher_edit_start)): ?>
                    <strong>ช่วงเวลา Login ครู: ปิด (นอกช่วงเวลาที่กำหนด)</strong><br>
                    <small>
                        <?php echo date('d/m/Y H:i', strtotime($teacher_edit_start)); ?>
                        &ndash;
                        <?php echo date('d/m/Y H:i', strtotime($teacher_edit_end)); ?> น.
                    </small>
                <?php else: ?>
                    <strong>ไม่ได้ตั้งช่วงเวลา</strong> — ครูสามารถ Login ได้ตลอดเวลา
                <?php endif; ?>
            </div>
        </div>

        <form method="post" id="teacherPeriodForm">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-calendar-plus text-success me-1"></i>วันที่/เวลาเริ่มต้น
                    </label>
                    <input type="datetime-local" class="form-control" name="teacher_edit_start"
                           value="<?php echo $teacher_start_input; ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-calendar-minus text-danger me-1"></i>วันที่/เวลาสิ้นสุด
                    </label>
                    <input type="datetime-local" class="form-control" name="teacher_edit_end"
                           value="<?php echo $teacher_end_input; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save me-1"></i>บันทึก
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        หากไม่ต้องการจำกัดช่วงเวลา ให้ล้างค่าทั้งสองช่องแล้วบันทึก
                    </div>
                </div>
                <div class="col-12 mt-2">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="clearTeacherPeriod">
                        <i class="fas fa-times me-1"></i>ล้างช่วงเวลา (ครู Login ได้ตลอด)
                    </button>
                    <a href="teacher_login.php" target="_blank" class="btn btn-outline-success btn-sm ms-2">
                        <i class="fas fa-external-link-alt me-1"></i>ทดสอบหน้า Login ครู
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========== ส่วนที่ 4: JavaScript (เพิ่มใน <script> ด้านล่างหน้า) ========== -->
<script>
// ล้างช่วงเวลาครู
document.getElementById('clearTeacherPeriod').addEventListener('click', function() {
    if (confirm('ต้องการล้างช่วงเวลา? ครูจะสามารถ Login ได้ตลอดเวลา')) {
        const form = document.getElementById('teacherPeriodForm');
        form.querySelector('[name="teacher_edit_start"]').value = '';
        form.querySelector('[name="teacher_edit_end"]').value   = '';
        form.submit();
    }
});
</script>