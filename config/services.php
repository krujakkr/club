<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ตารางครูในฐานข้อมูลหลัก knwacth_tel (การเชื่อมต่ออยู่ที่ config/database.php → tel)
    // ใส่ชื่อตาราง/คอลัมน์ให้ตรงกับของจริงใน .env ถ้าไม่มีคอลัมน์คำนำหน้าหรือกลุ่มสาระ ให้เว้นว่าง
    'tel' => [
        'table' => env('TEL_TEACHER_TABLE', 'teachers'),
        'status_value' => env('TEL_STATUS_TEACHER', 'ครู'),
        'columns' => [
            'id' => env('TEL_COL_ID', 'teacher_id'),
            'prefix' => env('TEL_COL_PREFIX', 'prefix'),
            'firstname' => env('TEL_COL_FIRSTNAME', 'firstname'),
            'lastname' => env('TEL_COL_LASTNAME', 'lastname'),
            'status' => env('TEL_COL_STATUS', 'status'),
            'id_card' => env('TEL_COL_ID_CARD', 'id_card'),
            'department' => env('TEL_COL_DEPARTMENT', ''),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
