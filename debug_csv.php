<?php
// ไฟล์ debug_csv.php - สำหรับตรวจสอบไฟล์ CSV
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $uploadedFile = $_FILES['csvFile'];
    
    echo "<h3>การตรวจสอบไฟล์ CSV</h3>";
    
    // อ่านไฟล์ CSV
    $content = file_get_contents($uploadedFile['tmp_name']);
    
    echo "<h4>ข้อมูลไฟล์:</h4>";
    echo "ขนาดไฟล์: " . strlen($content) . " bytes<br>";
    echo "บรรทัดแรก (raw): " . htmlspecialchars(substr($content, 0, 200)) . "<br><br>";
    
    // ลบ BOM
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    
    // แยกบรรทัด
    $lines = explode("\n", $content);
    
    echo "<h4>บรรทัดแรก (หลังลบ BOM):</h4>";
    echo htmlspecialchars($lines[0]) . "<br><br>";
    
    // ใช้ str_getcsv
    $headers = str_getcsv($lines[0]);
    
    echo "<h4>Headers ที่อ่านได้:</h4>";
    echo "<pre>";
    foreach ($headers as $i => $header) {
        $clean_header = trim(str_replace(["\r", "\n"], '', $header));
        echo "$i: '$header' -> ล้างแล้ว: '$clean_header'\n";
    }
    echo "</pre>";
    
    // ตรวจสอบการจับคู่
    $expected_headers = ['student_id', 'id_card', 'firstname', 'lastname', 'grade_level', 'class_level', 'class_number', 'selection_status', 'club_id'];
    echo "<h4>การจับคู่ headers:</h4>";
    
    foreach ($expected_headers as $expected) {
        $found = false;
        foreach ($headers as $i => $header) {
            $clean_header = trim(str_replace(["\r", "\n"], '', $header));
            if (strtolower($clean_header) === $expected) {
                echo "✅ '$expected' พบที่ตำแหน่ง $i<br>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ '$expected' ไม่พบ<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Debug CSV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h2>ตรวจสอบไฟล์ CSV</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csvFile" accept=".csv" required>
        <button type="submit">ตรวจสอบ</button>
    </form>
</body>
</html>