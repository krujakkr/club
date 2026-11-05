<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\Teacher\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::post('/enroll', [HomeController::class, 'enroll'])->middleware('auth:student')->name('enroll');

Route::middleware('auth:teacher')->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/clubs', [DashboardController::class, 'store'])->name('clubs.store');
    Route::put('/clubs/{club}', [DashboardController::class, 'update'])->name('clubs.update');
    Route::get('/clubs/{club}/members', [DashboardController::class, 'members'])->name('clubs.members');
    Route::get('/clubs/{club}/candidates', [DashboardController::class, 'candidates'])->name('clubs.candidates');
    Route::post('/clubs/{club}/members', [DashboardController::class, 'addMembers'])->name('clubs.members.add');
    Route::delete('/clubs/{club}/members/{student}', [DashboardController::class, 'removeMember'])->name('clubs.members.remove');
});

Route::middleware('auth:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/students');

    Route::get('/students', [Admin\StudentController::class, 'index'])->name('students');
    Route::post('/students', [Admin\StudentController::class, 'store']);
    Route::put('/students/{student}', [Admin\StudentController::class, 'update']);
    Route::delete('/students/{student}/enrollment', [Admin\StudentController::class, 'resetSelection']);

    Route::get('/clubs', [Admin\ClubController::class, 'index'])->name('clubs');
    Route::post('/clubs', [Admin\ClubController::class, 'store']);
    Route::put('/clubs/{club}', [Admin\ClubController::class, 'update']);
    Route::delete('/clubs/{club}', [Admin\ClubController::class, 'destroy']);
    Route::post('/clubs/{club}/lock', [Admin\ClubController::class, 'toggleLock']);
    Route::get('/clubs/{club}/members', [Admin\ClubController::class, 'members']);
    Route::delete('/clubs/{club}/members/{student}', [Admin\ClubController::class, 'removeMember']);

    Route::get('/teachers', [Admin\TeacherController::class, 'index'])->name('teachers');
    Route::post('/teachers', [Admin\TeacherController::class, 'store']);
    Route::post('/teachers/import', [Admin\TeacherController::class, 'import'])->name('teachers.import');
    Route::put('/teachers/{teacher}', [Admin\TeacherController::class, 'update']);
    Route::delete('/teachers/{teacher}', [Admin\TeacherController::class, 'destroy']);

    Route::get('/import', [Admin\ImportController::class, 'index'])->name('import');
    Route::post('/import', [Admin\ImportController::class, 'store']);

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports');
    Route::get('/export/{type}', [Admin\ReportController::class, 'export'])
        ->whereIn('type', ['students', 'clubs', 'teachers'])->name('export');

    Route::get('/settings', [Admin\SettingController::class, 'index'])->name('settings');
    Route::put('/settings', [Admin\SettingController::class, 'update']);
    Route::put('/settings/password', [Admin\SettingController::class, 'password']);
    Route::post('/settings/registration', [Admin\SettingController::class, 'toggleRegistration']);
    Route::post('/settings/clear', [Admin\SettingController::class, 'clear']);
});

// พิมพ์รายชื่อสมาชิก: ผู้ดูแลระบบพิมพ์ได้ทุกชุมนุม ครูพิมพ์ได้เฉพาะชุมนุมของตน
Route::middleware('auth:admin,teacher')->group(function () {
    Route::get('/print/clubs', [PrintController::class, 'all'])->name('print.all');
    Route::get('/print/clubs/{club}', [PrintController::class, 'club'])->name('print.club');
});
