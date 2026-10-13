# ระบบเลือกกิจกรรมชุมนุม โรงเรียนแก่นนครวิทยาลัย

Laravel 13 + Inertia + React 19 + Tailwind CSS 4 ฐานข้อมูล MySQL/MariaDB (utf8mb4)

## นำขึ้น host (Plesk + Git)

ไฟล์ที่ build แล้ว (`public/build`) อยู่ใน repo จึงไม่ต้องมี Node บน host ก่อน push ทุกครั้งที่แก้ไฟล์ใน `resources/` ให้รัน `npm run build` แล้ว commit ผลลัพธ์ไปด้วย

ตั้งค่าครั้งแรก

1. Plesk → Git → Remote repository ใส่ URL ของ repo, Deployment directory เป็นโฟลเดอร์ของโดเมน
2. Hosting Settings → Document root ชี้ไปที่ `<โฟลเดอร์ของโดเมน>/public` และเลือก PHP 8.3 ขึ้นไป
3. สร้างไฟล์ `.env` ในโฟลเดอร์ของโดเมน (คัดลอกจาก `.env.example`) ตั้ง `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, ค่า `DB_*` และ `APP_KEY` (สร้างด้วย `php artisan key:generate --show`)
4. นำเข้าฐานข้อมูลผ่าน phpMyAdmin (สำรองของเดิมก่อนทุกครั้ง)
5. เปิด Enable post deployment actions แล้วใส่คำสั่งด้านล่าง (แก้ path ของ PHP/Composer ให้ตรงกับเซิร์ฟเวอร์)

```sh
/opt/plesk/php/8.4/bin/php /usr/lib/plesk-9.0/composer.phar install --no-dev --optimize-autoloader --no-interaction
/opt/plesk/php/8.4/bin/php artisan migrate --force
/opt/plesk/php/8.4/bin/php artisan optimize
```

โฟลเดอร์ `storage/` และ `bootstrap/cache/` ต้องเขียนได้โดยผู้ใช้ของเว็บ

## ติดตั้ง

```sh
composer install
npm install
cp .env.example .env        # แล้วแก้ค่า DB_*
php artisan key:generate
php artisan migrate
npm run build               # หรือ npm run dev ระหว่างพัฒนา
php artisan serve
```

ชี้ DocumentRoot ของเว็บเซิร์ฟเวอร์ไปที่โฟลเดอร์ `public/` (ถ้าชี้มาที่โฟลเดอร์โปรเจกต์ ไฟล์ `.htaccess` ที่ root จะส่งต่อเข้า `public/` ให้)

## นำเข้าข้อมูลจากระบบเดิม

1. สร้างฐานข้อมูลชั่วคราวแล้วนำไฟล์ dump ของระบบเดิมเข้า

   ```sh
   mysql -uroot -e "CREATE DATABASE knwacth_club_legacy CHARACTER SET utf8mb4"
   mysql -uroot --default-character-set=utf8mb4 knwacth_club_legacy < "knwacth_club (1).sql"
   ```

2. ตั้ง `LEGACY_DB_DATABASE` ใน `.env` แล้วรัน

   ```sh
   php artisan club:import-legacy
   ```

คำสั่งนี้ล้างข้อมูลในฐานข้อมูลใหม่ก่อนนำเข้า และพิมพ์รายการที่ต้องแก้ด้วยตนเอง (รหัสครูที่ไม่ใช่ 3 หลัก ห้อง/เลขที่ซ้ำ เลขบัตรไม่ครบ)

ถ้าติดตั้งใหม่โดยไม่มีข้อมูลเดิม ให้รัน `php artisan db:seed` เพื่อสร้างค่าตั้งต้นและผู้ดูแลระบบ `admin` (รหัสผ่านแสดงครั้งเดียวตอนรัน)

## โครงสร้างข้อมูลที่ใช้ร่วมกับ ปพ.5

ปรับตาม `plan_club.md`

| ตาราง | สาระสำคัญ |
| --- | --- |
| `teachers` | `teacher_id` (รหัสครู 3 หลัก) เป็นคีย์หลัก, `department` สะกดตรงกับ ปพ.5 |
| `students` | `student_id` เป็นคีย์หลัก, แยก `prefix`, `id_card` 13 หลัก, unique (`grade_level`, `class_room`, `class_number`) |
| `clubs` | มี `academic_year`, unique (`academic_year`, `club_name`), `teacher_id` ชนิดเดียวกับตารางครู |
| `enrollments` | การเลือกชุมนุม: `student_id`, `club_id`, `academic_year`, `enrolled_at`, unique (`student_id`, `academic_year`) |

ผู้ใช้ฐานข้อมูลแบบอ่านอย่างเดียวสำหรับ ปพ.5: `database/sql/readonly_user_for_por5.sql`

## การเข้าสู่ระบบ

ทุกประเภทผู้ใช้เข้าสู่ระบบจากฟอร์มเดียวที่หน้าแรก

| ผู้ใช้ | ข้อมูลที่ใช้ |
| --- | --- |
| นักเรียน | เลขประจำตัวนักเรียน + เลขบัตรประชาชน |
| ครู | รหัสครู + เบอร์โทรศัพท์ 10 หลัก (เฉพาะช่วงเวลาที่ผู้ดูแลกำหนด) |
| ผู้ดูแลระบบ | ชื่อผู้ใช้ + รหัสผ่าน |
