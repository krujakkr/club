-- ผู้ใช้ฐานข้อมูลแบบอ่านอย่างเดียว สำหรับให้ระบบ ปพ.5 เชื่อมเข้ามาดึงข้อมูล
-- แยกจากผู้ใช้ที่ระบบชุมนุมใช้เขียนข้อมูล (ข้อ 6 ใน plan_club.md)
-- แก้รหัสผ่านและ host ให้ตรงกับเซิร์ฟเวอร์จริงก่อนรัน

CREATE USER IF NOT EXISTS 'por5_reader'@'localhost' IDENTIFIED BY 'เปลี่ยนรหัสผ่านนี้';

-- ให้สิทธิ์เฉพาะตารางที่ ปพ.5 ต้องใช้ ไม่รวม admins, logs และ system_settings
GRANT SELECT ON knwacth_club.teachers TO 'por5_reader'@'localhost';
GRANT SELECT ON knwacth_club.students TO 'por5_reader'@'localhost';
GRANT SELECT ON knwacth_club.clubs TO 'por5_reader'@'localhost';
GRANT SELECT ON knwacth_club.enrollments TO 'por5_reader'@'localhost';

FLUSH PRIVILEGES;
