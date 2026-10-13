<?php

use App\Models\Admin;
use App\Models\Student;
use App\Models\Teacher;

return [

    'defaults' => [
        'guard' => 'admin',
        'passwords' => null,
    ],

    // ผู้ใช้สามประเภทแยก guard กัน แต่ล็อกอินจากฟอร์มเดียวกันที่หน้าแรก
    'guards' => [
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
        'teacher' => ['driver' => 'session', 'provider' => 'teachers'],
        'student' => ['driver' => 'session', 'provider' => 'students'],
    ],

    'providers' => [
        'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        'teachers' => ['driver' => 'eloquent', 'model' => Teacher::class],
        'students' => ['driver' => 'eloquent', 'model' => Student::class],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
