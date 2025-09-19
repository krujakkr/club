# ระบบเลือกกิจกรรมชุมนุมออนไลน์
โรงเรียนแก่นนครวิทยาลัย

## 📋 รายละเอียดระบบ

ระบบเลือกกิจกรรมชุมนุมออนไลน์ที่พัฒนาเพื่อใช้ในโรงเรียน ช่วยให้นักเรียนสามารถเลือกชุมนุมที่สนใจได้อย่างสะดวก และผู้ดูแลระบบสามารถจัดการข้อมูลได้อย่างมีประสิทธิภาพ

## ✨ คุณสมบัติหลัก

### สำหรับนักเรียน
- 🔐 เข้าสู่ระบบด้วยเลขบัตรประชาชนและรหัสนักเรียน
- 🔍 ค้นหาและดูข้อมูลชุมนุมต่างๆ
- 🎯 เลือกชุมนุมตามระดับชั้นที่เปิดรับ
- 👥 ดูรายชื่อสมาชิกในชุมนุม
- 📊 ตรวจสอบจำนวนที่ว่างในแต่ละชุมนุม

### สำหรับผู้ดูแลระบบ
- 👨‍💼 จัดการข้อมูลนักเรียน ครู และชุมนุม
- 📁 นำเข้าข้อมูลจากไฟล์ CSV
- 🔒 เปิด/ปิดระบบลงทะเบียน
- 🔐 ล็อก/ปลดล็อกชุมนุม
- 📈 ออกรายงานและสถิติ
- 💾 ส่งออกข้อมูลเป็นไฟล์ CSV

## 🛠️ เทคโนโลยีที่ใช้

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3.2
- **Icons**: Font Awesome 6.4.2
- **Charts**: DataTables

## 📂 โครงสร้างโปรเจค

```
club-system/
├── config.php              # ไฟล์กำหนดค่าระบบ
├── index.php               # หน้าหลักแสดงรายการชุมนุม
├── admin_login.php         # หน้าเข้าสู่ระบบผู้ดูแลระบบ
├── admin.php              # หน้าจัดการนักเรียน
├── admin_clubs.php        # หน้าจัดการชุมนุม
├── admin_import.php       # หน้านำเข้าข้อมูล CSV
├── student_login.php      # ประมวลผลการเข้าสู่ระบบนักเรียน
├── select_club.php        # ประมวลผลการเลือกชุมนุม
├── sample_files/          # ไฟล์ตัวอย่างสำหรับนำเข้าข้อมูล
│   ├── import_student.csv
│   ├── import_teacher.csv
│   └── import_club.csv
├── export_csv.php         # ส่งออกรายชื่อสมาชิก
├── get_members.php        # API ดึงรายชื่อสมาชิก
└── README.md
```

## 🚀 การติดตั้งและใช้งาน

### 1. ความต้องการของระบบ
- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า
- Web Server (Apache/Nginx)
- เปิดการใช้งาน PHP Extensions: mysqli, session

### 2. การติดตั้ง

1. **Clone หรือ Download โปรเจค**
   ```bash
   git clone https://github.com/yourusername/club-system.git
   ```

2. **นำเข้าไฟล์ลงเว็บเซิร์ฟเวอร์**
   - อัพโหลดไฟล์ทั้งหมดไปยัง document root ของเว็บเซิร์ฟเวอร์

3. **สร้างฐานข้อมูล**
   ```sql
   CREATE DATABASE club_system CHARACTER SET utf8 COLLATE utf8_general_ci;
   ```

4. **สร้างตารางในฐานข้อมูล**
   ```sql
   -- ตารางผู้ดูแลระบบ
   CREATE TABLE admins (
       admin_id INT PRIMARY KEY AUTO_INCREMENT,
       username VARCHAR(50) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- ตารางครู
   CREATE TABLE teachers (
       teacher_id VARCHAR(10) PRIMARY KEY,
       firstname VARCHAR(100) NOT NULL,
       lastname VARCHAR(100) NOT NULL,
       teacher_code VARCHAR(20),
       telephon VARCHAR(20),
       department VARCHAR(100)
   );

   -- ตารางชุมนุม
   CREATE TABLE clubs (
       club_id INT PRIMARY KEY AUTO_INCREMENT,
       club_name VARCHAR(200) NOT NULL,
       description TEXT,
       location VARCHAR(100),
       max_members INT NOT NULL,
       teacher_id VARCHAR(10),
       allow_m1 TINYINT DEFAULT 1,
       allow_m2 TINYINT DEFAULT 1,
       allow_m3 TINYINT DEFAULT 1,
       allow_m4 TINYINT DEFAULT 1,
       allow_m5 TINYINT DEFAULT 1,
       allow_m6 TINYINT DEFAULT 1,
       is_locked TINYINT DEFAULT 0,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id)
   );

   -- ตารางนักเรียน
   CREATE TABLE students (
       student_id VARCHAR(20) PRIMARY KEY,
       id_card VARCHAR(13),
       firstname VARCHAR(100) NOT NULL,
       lastname VARCHAR(100) NOT NULL,
       grade_level VARCHAR(10) NOT NULL,
       class_room INT,
       class_number INT,
       selection_status TINYINT DEFAULT 0,
       club_id INT,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       FOREIGN KEY (club_id) REFERENCES clubs(club_id)
   );

   -- ตารางการตั้งค่าระบบ
   CREATE TABLE system_settings (
       setting_name VARCHAR(50) PRIMARY KEY,
       setting_value VARCHAR(255),
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );

   -- ตารางบันทึกกิจกรรม
   CREATE TABLE logs (
       log_id INT PRIMARY KEY AUTO_INCREMENT,
       user_id VARCHAR(50),
       user_type VARCHAR(20),
       action VARCHAR(100),
       details TEXT,
       ip_address VARCHAR(45),
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

5. **เพิ่มข้อมูลเริ่มต้น**
   ```sql
   -- สร้างบัญชีผู้ดูแลระบบ (รหัสผ่าน: admin123)
   INSERT INTO admins (username, password) 
   VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

   -- ตั้งค่าระบบเริ่มต้น
   INSERT INTO system_settings (setting_name, setting_value) VALUES 
   ('registration_open', 'true'),
   ('academic_year', '2568'),
   ('semester', '1');
   ```

6. **แก้ไขไฟล์ config.php**
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'your_db_username');
   define('DB_PASSWORD', 'your_db_password');
   define('DB_NAME', 'club_system');
   ```

### 3. การเข้าใช้งาน

- **หน้าหลัก**: `http://yourdomain.com/`
- **ผู้ดูแลระบบ**: `http://yourdomain.com/admin_login.php`
  - Username: `admin`
  - Password: `admin123`

## 📊 การใช้งานระบบ

### การนำเข้าข้อมูล
1. เตรียมไฟล์ CSV ตามรูปแบบตัวอย่าง
2. เข้าสู่ระบบผู้ดูแล
3. เลือกเมนู "นำเข้าข้อมูล"
4. อัพโหลดไฟล์ตามลำดับ: ครู → ชุมนุม → นักเรียน

### การจัดการชุมนุม
- เพิ่ม/แก้ไข/ลบชุมนุม
- กำหนดจำนวนรับและระดับชั้นที่เปิดรับ
- ล็อก/ปลดล็อกชุมนุม
- ดูรายชื่อสมาชิกและส่งออก CSV

### การจัดการนักเรียน
- ค้นหาและกรองข้อมูลนักเรียน
- ยกเลิกการเลือกชุมนุมของนักเรียน
- ส่งออกรายงาน

## 🔒 ความปลอดภัย

- เข้ารหัสรหัสผ่านด้วย `password_hash()`
- ป้องกัน SQL Injection ด้วย `mysqli_real_escape_string()`
- ตรวจสอบสิทธิ์การเข้าถึงในทุกหน้า
- บันทึกกิจกรรมของผู้ใช้

## 🐛 การแก้ไขปัญหา

### ปัญหาเข้าสู่ระบบไม่ได้
1. ตรวจสอบการเชื่อมต่อฐานข้อมูล
2. ตรวจสอบข้อมูลในตาราง `admins`
3. รีเซ็ตรหัสผ่านด้วย `fix_admin.php`

### ปัญหาการแสดงผลภาษาไทย
1. ตรวจสอบ charset ของฐานข้อมูลเป็น `utf8`
2. ตั้งค่า `mysqli_set_charset($conn, "utf8")`

### ปัญหาการนำเข้า CSV
1. ตรวจสอบรูปแบบไฟล์ให้ตรงตามตัวอย่าง
2. บันทึกไฟล์เป็น UTF-8
3. ใช้ comma (,) เป็นตัวคั่น

## 🤝 การสนับสนุน

หากพบปัญหาหรือต้องการความช่วยเหลือ:

1. เปิด Issue ใน GitHub Repository
2. ติดต่อผู้พัฒนา
3. ดูเอกสารเพิ่มเติมในโฟลเดอร์ `docs/`

## 📝 การพัฒนาต่อ

### ฟีเจอร์ที่อาจเพิ่มในอนาคต
- ระบบแจ้งเตือนทาง Email/SMS
- API สำหรับแอพมือถือ
- ระบบรายงานแบบ Real-time
- การสำรองข้อมูลอัตโนมัติ
- ระบบการอนุมัติจากครูที่ปรึกษา

## 📄 License

MIT License - ดู [LICENSE](LICENSE) สำหรับรายละเอียด

## 👥 ผู้พัฒนา

- **ครูจักรพงษ์** - t246-math@knw.ac.th
- **โรงเรียนแก่นนครวิทยาลัย**

---

⭐ หากโปรเจคนี้มีประโยชน์ โปรดกด Star ให้ด้วยครับ!
