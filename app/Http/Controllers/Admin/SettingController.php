<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const PERIODS = ['registration' => 'ลงทะเบียน', 'teacher_edit' => 'แก้ไขข้อมูลของครู'];

    public function index(): Response
    {
        $year = Setting::academicYear();

        return Inertia::render('Admin/Settings', [
            'settings' => [
                'academic_year' => Setting::value('academic_year'),
                'semester' => Setting::value('semester'),
                'school_name' => Setting::value('school_name'),
            ],
            'registration' => ['open' => Setting::registrationOpen()] + Setting::period('registration'),
            'teacherPeriod' => Setting::period('teacher_edit'),
            'stats' => [
                'students' => Student::count(),
                'selected' => Enrollment::where('academic_year', $year)->count(),
                'clubs' => Club::current()->count(),
                'teachers' => Teacher::count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $request->validate(['section' => 'required|in:general,registration,teacher_edit'])['section'];

        if ($section === 'general') {
            $data = $request->validate([
                'academic_year' => 'required|integer|min:2500|max:2700',
                'semester' => 'required|in:1,2',
                'school_name' => 'required|string|max:150',
            ], [], ['academic_year' => 'ปีการศึกษา', 'semester' => 'ภาคเรียน', 'school_name' => 'ชื่อโรงเรียน']);

            foreach ($data as $name => $value) {
                Setting::put($name, (string) $value);
            }
            ActivityLog::record('update_settings', "อัพเดทปีการศึกษา {$data['academic_year']} ภาคเรียน {$data['semester']}");

            return back()->with('success', 'บันทึกการตั้งค่าทั่วไปเรียบร้อยแล้ว');
        }

        $label = self::PERIODS[$section];
        $data = $request->validate([
            'start' => 'nullable|date|required_with:end',
            'end' => 'nullable|date|required_with:start|after:start',
        ], [
            'required_with' => 'กรุณากรอกทั้งวันเริ่มต้นและวันสิ้นสุด',
            'end.after' => 'วันสิ้นสุดต้องหลังวันเริ่มต้น',
        ]);

        $format = fn (?string $value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : '';
        Setting::put("{$section}_start", $format($data['start'] ?? null));
        Setting::put("{$section}_end", $format($data['end'] ?? null));

        if ($section === 'registration' && empty($data['start'])) {
            // ล้างช่วงเวลาแล้วให้ระบบปิดไว้ก่อน ผู้ดูแลต้องเปิดเองด้วยสวิตช์
            Setting::put('registration_open', 'false');
        }

        ActivityLog::record('update_settings', empty($data['start'])
            ? "ล้างช่วงเวลา{$label}"
            : "ตั้งช่วงเวลา{$label}: {$format($data['start'])} ถึง {$format($data['end'])}");

        return back()->with('success', empty($data['start']) ? "ล้างช่วงเวลา{$label}เรียบร้อยแล้ว" : "บันทึกช่วงเวลา{$label}เรียบร้อยแล้ว");
    }

    /** เปิด-ปิดการลงทะเบียนด้วยมือ ใช้ได้เมื่อไม่ได้ตั้งช่วงเวลา */
    public function toggleRegistration(): RedirectResponse
    {
        if (Setting::period('registration')['state'] !== 'none') {
            return back()->with('error', 'ระบบเปิด-ปิดตามช่วงเวลาที่ตั้งไว้ หากต้องการสลับด้วยมือให้ล้างช่วงเวลาก่อน');
        }

        $open = ! Setting::registrationOpen();
        Setting::put('registration_open', $open ? 'true' : 'false');
        ActivityLog::record('toggle_system', $open ? 'เปิดระบบลงทะเบียน' : 'ปิดระบบลงทะเบียน');

        return back()->with('success', $open ? 'เปิดระบบลงทะเบียนแล้ว' : 'ปิดระบบลงทะเบียนแล้ว');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], ['password.confirmed' => 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน', 'password.min' => 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร']);

        $admin = Auth::guard('admin')->user();
        if (! Hash::check($data['current_password'], $admin->password)) {
            throw ValidationException::withMessages(['current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
        }

        $admin->update(['password' => $data['password']]);
        ActivityLog::record('change_password', 'เปลี่ยนรหัสผ่านผู้ดูแลระบบ');

        return back()->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }

    public function clear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:reset_selections,clear_students,clear_clubs,clear_teachers',
            'confirm' => 'required|in:ยืนยัน',
        ], ['confirm.in' => 'กรุณาพิมพ์คำว่า ยืนยัน เพื่อดำเนินการ']);

        $year = Setting::academicYear();

        [$count, $message] = DB::transaction(fn () => match ($data['type']) {
            'reset_selections' => [Enrollment::where('academic_year', $year)->delete(), "รีเซ็ตการเลือกชุมนุมปีการศึกษา {$year}"],
            // การเลือกชุมนุมของนักเรียน (ทุกปี) ถูกลบตามด้วย foreign key
            'clear_students' => [Student::query()->delete(), 'ลบข้อมูลนักเรียนทั้งหมด'],
            'clear_clubs' => [Club::current()->delete(), "ลบข้อมูลชุมนุมปีการศึกษา {$year}"],
            // ชุมนุมยังอยู่ แต่ครูที่ปรึกษาจะว่าง (ON DELETE SET NULL)
            'clear_teachers' => [Teacher::query()->delete(), 'ลบข้อมูลครูทั้งหมด'],
        });

        ActivityLog::record($data['type'], "{$message}: {$count} รายการ");

        return back()->with('success', "{$message}เรียบร้อยแล้ว ({$count} รายการ)");
    }
}
