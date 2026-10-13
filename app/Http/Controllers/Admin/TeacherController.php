<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Teacher;
use App\Support\Thai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(): Response
    {
        $log = fn (string $action, string $select) => DB::table('logs')->selectRaw($select)
            ->whereColumn('logs.user_id', 'teachers.teacher_id')
            ->where('user_type', 'teacher')->where('action', $action);

        return Inertia::render('Admin/Teachers', [
            'teachers' => Teacher::query()
                ->withCount(['clubs' => fn ($q) => $q->where('academic_year', Setting::academicYear())])
                ->addSelect([
                    'last_login' => $log('login', 'MAX(created_at)'),
                    'last_update' => $log('update_club', 'MAX(created_at)'),
                    'update_count' => $log('update_club', 'COUNT(*)'),
                ])
                ->orderBy('teacher_id')->get(),
            'departments' => Thai::DEPARTMENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = Teacher::create($this->validated($request));
        ActivityLog::record('add_teacher', "เพิ่มครูใหม่: {$teacher->full_name}");

        return back()->with('success', 'เพิ่มข้อมูลครูเรียบร้อยแล้ว');
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        // เปลี่ยนรหัสครูได้ clubs.teacher_id จะตามไปเองด้วย ON UPDATE CASCADE
        $teacher->update($this->validated($request, $teacher));
        ActivityLog::record('edit_teacher', "แก้ไขข้อมูลครู: {$teacher->full_name}");

        return back()->with('success', 'แก้ไขข้อมูลครูเรียบร้อยแล้ว');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        if ($count = $teacher->clubs()->count()) {
            return back()->with('error', "ไม่สามารถลบครูได้ เนื่องจากครูท่านนี้เป็นที่ปรึกษาชุมนุมอยู่ {$count} ชุมนุม");
        }

        $teacher->delete();
        ActivityLog::record('delete_teacher', "ลบข้อมูลครู ID: {$teacher->teacher_id}");

        return back()->with('success', 'ลบข้อมูลครูเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $request->merge(['phone' => Thai::digits($request->input('phone')) ?: null]);

        // รหัสครูต้องเป็น 3 หลักชุดเดียวกับ ปพ.5 (รหัสเดิมที่ผิดรูปแบบยังบันทึกข้อมูลอื่นได้ถ้าไม่ได้แก้รหัส)
        $idChanged = ! $teacher || $request->input('teacher_id') !== $teacher->teacher_id;

        return $request->validate([
            'teacher_id' => ['required', $idChanged ? 'digits:3' : 'string', Rule::unique('teachers')->ignore($teacher)],
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'phone' => 'nullable|digits:10',
            'department' => ['nullable', Rule::in(Thai::DEPARTMENTS)],
        ], [
            'teacher_id.digits' => 'รหัสครูต้องเป็นตัวเลข 3 หลัก',
            'teacher_id.unique' => 'รหัสครูนี้มีในระบบแล้ว กรุณาใช้รหัสอื่น',
            'phone.digits' => 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก (ใช้เป็นรหัสผ่านของครู)',
        ], ['teacher_id' => 'รหัสครู', 'firstname' => 'ชื่อ', 'lastname' => 'นามสกุล', 'department' => 'กลุ่มสาระ']);
    }
}
