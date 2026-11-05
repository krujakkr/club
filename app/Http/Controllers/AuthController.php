<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\Thai;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const GUARDS = ['admin', 'teacher', 'student'];

    /** ล็อกอินจากฟอร์มเดียวที่หน้าแรก แยกตามประเภทผู้ใช้ */
    public function login(Request $request): RedirectResponse
    {
        $role = $request->validate(['role' => 'required|in:student,teacher,admin'])['role'];

        [$user, $redirect] = match ($role) {
            'student' => [$this->student($request), route('home')],
            'teacher' => [$this->teacher($request), route('teacher.dashboard')],
            'admin' => [$this->admin($request), route('admin.students')],
        };

        foreach (self::GUARDS as $guard) {
            Auth::guard($guard)->logout();
        }
        Auth::guard($role)->login($user);
        $request->session()->regenerate();

        ActivityLog::record('login', match ($role) {
            'student' => 'นักเรียนเข้าสู่ระบบ',
            'teacher' => 'ครูเข้าสู่ระบบ',
            'admin' => 'แอดมินเข้าสู่ระบบ',
        });

        return redirect()->to($redirect);
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::record('logout', 'ออกจากระบบ');

        foreach (self::GUARDS as $guard) {
            Auth::guard($guard)->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function student(Request $request): Student
    {
        $data = $request->validate([
            'student_id' => 'required|string|max:10',
            'id_card' => 'required|string',
        ], [], ['student_id' => 'เลขประจำตัวนักเรียน', 'id_card' => 'เลขบัตรประชาชน']);

        $idCard = Thai::digits($data['id_card']);
        $student = strlen($idCard) === 13
            ? Student::where('student_id', trim($data['student_id']))->where('id_card', $idCard)->first()
            : null;

        return $student ?? $this->fail('ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบเลขประจำตัวนักเรียนและเลขบัตรประชาชนอีกครั้ง');
    }

    private function teacher(Request $request): Teacher
    {
        $data = $request->validate([
            'teacher_id' => 'required|digits_between:1,10',
            'password' => 'required|digits:6',
        ], [
            'teacher_id.digits_between' => 'รหัสครูต้องเป็นตัวเลขเท่านั้น',
            'password.digits' => 'รหัสผ่านต้องเป็นเลขท้ายบัตรประชาชน 6 หลัก',
        ], ['teacher_id' => 'รหัสครู', 'password' => 'รหัสผ่าน']);

        if (! Setting::teacherPeriodActive()) {
            $period = Setting::period('teacher_edit');
            $this->fail(sprintf(
                'ขณะนี้ไม่อยู่ในช่วงเวลาที่อนุญาตให้ครูแก้ไขข้อมูลชุมนุม (ช่วงเวลาที่อนุญาต: %s – %s)',
                Carbon::parse($period['start'])->format('d/m/Y H:i'),
                Carbon::parse($period['end'])->format('d/m/Y H:i'),
            ));
        }

        $teacher = Teacher::find($data['teacher_id']);

        return $teacher && $teacher->id_card_last6 && hash_equals($teacher->id_card_last6, $data['password'])
            ? $teacher
            : $this->fail('รหัสครูหรือรหัสผ่าน (เลขท้ายบัตรประชาชน 6 หลัก) ไม่ถูกต้อง');
    }

    private function admin(Request $request): Admin
    {
        $data = $request->validate([
            'username' => 'required|string|max:50',
            'password' => 'required|string',
        ], [], ['username' => 'ชื่อผู้ใช้', 'password' => 'รหัสผ่าน']);

        $admin = Admin::where('username', $data['username'])->first();

        return $admin && Hash::check($data['password'], $admin->password)
            ? $admin
            : $this->fail('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['login' => $message]);
    }
}
