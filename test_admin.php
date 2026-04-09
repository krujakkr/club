<?php
// ไฟล์ test_admin.php - สำหรับตรวจสอบปัญหา admin.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>การตรวจสอบปัญหา admin.php</h2>";

// 1. ตรวจสอบไฟล์ config.php
echo "<h3>1. ตรวจสอบไฟล์ config.php</h3>";
if (file_exists('config.php')) {
    echo "✅ ไฟล์ config.php พบแล้ว<br>";
    require_once 'config.php';
    echo "✅ โหลด config.php สำเร็จ<br>";
} else {
    echo "❌ ไม่พบไฟล์ config.php<br>";
    die();
}

// 2. ตรวจสอบการเชื่อมต่อฐานข้อมูล
echo "<h3>2. ตรวจสอบการเชื่อมต่อฐานข้อมูล</h3>";
if (isset($conn)) {
    if ($conn instanceof mysqli) {
        echo "✅ เชื่อมต่อฐานข้อมูล MySQLi สำเร็จ<br>";
    } else {
        echo "❌ ตัวแปร \$conn ไม่ใช่ mysqli object<br>";
    }
} else {
    echo "❌ ไม่พบตัวแปร \$conn<br>";
}

// 3. ตรวจสอบ Session
echo "<h3>3. ตรวจสอบ Session</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ เริ่ม session ใหม่<br>";
} else {
    echo "✅ session ทำงานอยู่แล้ว<br>";
}

if (isset($_SESSION)) {
    echo "✅ \$_SESSION ใช้งานได้<br>";
    echo "Session data: " . print_r($_SESSION, true) . "<br>";
} else {
    echo "❌ ปัญหากับ \$_SESSION<br>";
}

// 4. ตรวจสอบฟังก์ชันที่ใช้
echo "<h3>4. ตรวจสอบฟังก์ชัน</h3>";
if (function_exists('isLoggedIn')) {
    echo "✅ ฟังก์ชัน isLoggedIn() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน isLoggedIn()<br>";
}

if (function_exists('isAdmin')) {
    echo "✅ ฟังก์ชัน isAdmin() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน isAdmin()<br>";
}

if (function_exists('getSystemSetting')) {
    echo "✅ ฟังก์ชัน getSystemSetting() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน getSystemSetting()<br>";
}

if (function_exists('clean')) {
    echo "✅ ฟังก์ชัน clean() พบแล้ว<br>";
} else {
    echo "❌ ไม่พบฟังก์ชัน clean()<br>";
}

// 5. ตรวจสอบตาราง
echo "<h3>5. ตรวจสอบตาราง</h3>";
if (isset($conn)) {
    $tables = ['students', 'clubs'];
    foreach ($tables as $table) {
        $check_sql = "SHOW TABLES LIKE '$table'";
        $result = mysqli_query($conn, $check_sql);
        if ($result && mysqli_num_rows($result) > 0) {
            echo "✅ ตาราง $table พบแล้ว<br>";
        } else {
            echo "❌ ไม่พบตาราง $table<br>";
        }
    }
}

// 6. ตรวจสอบ PHP Version
echo "<h3>6. ข้อมูลระบบ</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "MySQLi Extension: " . (extension_loaded('mysqli') ? '✅ โหลดแล้ว' : '❌ ไม่พบ') . "<br>";
echo "Session Extension: " . (extension_loaded('session') ? '✅ โหลดแล้ว' : '❌ ไม่พบ') . "<br>";

// 7. ทดสอบ Query พื้นฐาน
echo "<h3>7. ทดสอบ Query</h3>";
if (isset($conn)) {
    $test_sql = "SELECT COUNT(*) as count FROM students";
    $test_result = mysqli_query($conn, $test_sql);
    if ($test_result) {
        $row = mysqli_fetch_assoc($test_result);
        echo "✅ Query students สำเร็จ - พบ " . $row['count'] . " รายการ<br>";
    } else {
        echo "❌ Query ล้มเหลว: " . mysqli_error($conn) . "<br>";
    }
}

echo "<hr>";
echo "<p><strong>หากทุกอย่างเป็น ✅ ให้ลองเข้า admin.php ใหม่</strong></p>";
echo "<p><strong>หากมี ❌ ให้แก้ไขปัญหานั้นๆ ก่อน</strong></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h2, h3 { color: #333; }
</style>