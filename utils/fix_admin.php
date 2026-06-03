<?php
// สคริปต์แก้ไขรหัสผ่านผู้ดูแลระบบ
// ใช้รันเพื่อรีเซ็ตรหัสผ่านของผู้ดูแลระบบ

// เชื่อมต่อฐานข้อมูล
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // เปลี่ยนตามข้อมูลของคุณ
define('DB_PASSWORD', 'root1234');    // เปลี่ยนตามข้อมูลของคุณ
define('DB_NAME', 'club_system');

// เชื่อมต่อกับฐานข้อมูล MySQL
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn === false) {
    die("ERROR: ไม่สามารถเชื่อมต่อฐานข้อมูลได้. " . mysqli_connect_error());
}

// ข้อมูลผู้ดูแลระบบ
$username = "admin";
$new_password = "admin123"; // รหัสผ่านใหม่

// เข้ารหัสรหัสผ่านด้วย password_hash() ซึ่งเป็นฟังก์ชันมาตรฐานของ PHP
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// ตรวจสอบว่ามีผู้ดูแลระบบในฐานข้อมูลแล้วหรือไม่
$check_sql = "SELECT * FROM admins WHERE username = '$username'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    // ถ้ามีบัญชีผู้ดูแลระบบอยู่แล้ว ให้อัพเดทรหัสผ่าน
    $update_sql = "UPDATE admins SET password = '$hashed_password' WHERE username = '$username'";
    
    if (mysqli_query($conn, $update_sql)) {
        echo "รีเซ็ตรหัสผ่านผู้ดูแลระบบเรียบร้อยแล้ว<br>";
        echo "ชื่อผู้ใช้: $username<br>";
        echo "รหัสผ่านใหม่: $new_password<br>";
        
        // แสดงรหัสผ่านที่เข้ารหัสแล้วเพื่อการตรวจสอบ
        echo "<hr>";
        echo "รหัสผ่านที่เข้ารหัสแล้ว: $hashed_password<br>";
        
        // ทดสอบว่าการตรวจสอบรหัสผ่านทำงานถูกต้องหรือไม่
        echo "<hr>";
        echo "ทดสอบการตรวจสอบรหัสผ่าน:<br>";
        $verify_result = password_verify($new_password, $hashed_password);
        echo "ผลการตรวจสอบ: " . ($verify_result ? "ถูกต้อง" : "ไม่ถูกต้อง") . "<br>";
        
        echo "<hr>";
        echo "กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
    } else {
        echo "เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน: " . mysqli_error($conn);
    }
} else {
    // ถ้ายังไม่มีบัญชีผู้ดูแลระบบ ให้สร้างใหม่
    $insert_sql = "INSERT INTO admins (username, password) VALUES ('$username', '$hashed_password')";
    
    if (mysqli_query($conn, $insert_sql)) {
        echo "สร้างบัญชีผู้ดูแลระบบใหม่เรียบร้อยแล้ว<br>";
        echo "ชื่อผู้ใช้: $username<br>";
        echo "รหัสผ่าน: $new_password<br>";
        
        // แสดงรหัสผ่านที่เข้ารหัสแล้วเพื่อการตรวจสอบ
        echo "<hr>";
        echo "รหัสผ่านที่เข้ารหัสแล้ว: $hashed_password<br>";
        
        // ทดสอบว่าการตรวจสอบรหัสผ่านทำงานถูกต้องหรือไม่
        echo "<hr>";
        echo "ทดสอบการตรวจสอบรหัสผ่าน:<br>";
        $verify_result = password_verify($new_password, $hashed_password);
        echo "ผลการตรวจสอบ: " . ($verify_result ? "ถูกต้อง" : "ไม่ถูกต้อง") . "<br>";
        
        echo "<hr>";
        echo "กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
    } else {
        echo "เกิดข้อผิดพลาดในการสร้างบัญชีผู้ดูแลระบบ: " . mysqli_error($conn);
    }
}

// ปิดการเชื่อมต่อฐานข้อมูล
mysqli_close($conn);
?>