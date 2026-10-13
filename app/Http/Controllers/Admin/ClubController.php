<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentService;
use App\Support\ClubRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    private const EXTRA_RULES = ['teacher_id' => 'nullable|exists:teachers,teacher_id'];

    public function index(): Response
    {
        return Inertia::render('Admin/Clubs', [
            'clubs' => Club::current()->with('teacher')->withCount('enrollments')->orderBy('club_name')->get()
                ->map(fn (Club $c) => [
                    ...$c->only(
                        'id', 'club_name', 'description', 'location', 'max_members', 'teacher_id', 'is_locked',
                        'allow_m1', 'allow_m2', 'allow_m3', 'allow_m4', 'allow_m5', 'allow_m6',
                    ),
                    'teacher' => $c->teacher?->full_name,
                    'members' => $c->enrollments_count,
                ]),
            'teachers' => Teacher::orderBy('teacher_id')->get(['teacher_id', 'firstname', 'lastname']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = ClubRules::validate($request, extra: self::EXTRA_RULES);
        Club::create($data + ['academic_year' => Setting::academicYear()]);
        ActivityLog::record('add_club', "เพิ่มชุมนุม: {$data['club_name']}");

        return back()->with('success', "เพิ่มชุมนุม \"{$data['club_name']}\" เรียบร้อยแล้ว");
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        $club->update(ClubRules::validate($request, $club, self::EXTRA_RULES) + ['is_locked' => $request->boolean('is_locked')]);
        ActivityLog::record('update_club', "อัพเดทชุมนุม: {$club->club_name}");

        return back()->with('success', 'บันทึกข้อมูลชุมนุมเรียบร้อยแล้ว');
    }

    public function destroy(Club $club): RedirectResponse
    {
        $members = $club->enrollments()->count();
        $club->delete();
        ActivityLog::record('delete_club', "ลบชุมนุม: {$club->club_name} (สมาชิก {$members} คนถูกยกเลิกการเลือก)");

        return back()->with('success', "ลบชุมนุม \"{$club->club_name}\" เรียบร้อยแล้ว");
    }

    public function toggleLock(Club $club): RedirectResponse
    {
        $club->update(['is_locked' => ! $club->is_locked]);
        ActivityLog::record('toggle_club_lock', ($club->is_locked ? 'ล็อก' : 'ปลดล็อก')."ชุมนุม: {$club->club_name}");

        return back()->with('success', ($club->is_locked ? 'ล็อก' : 'ปลดล็อก')."ชุมนุม \"{$club->club_name}\" แล้ว");
    }

    public function members(Club $club): JsonResponse
    {
        return response()->json(ClubRules::members($club));
    }

    public function removeMember(Club $club, Student $student, EnrollmentService $enrollments): RedirectResponse
    {
        if (! $enrollments->withdraw($student->student_id, $club->id)) {
            return back()->with('error', 'ไม่พบนักเรียนในชุมนุมนี้');
        }
        ActivityLog::record('remove_member', "ถอนนักเรียน {$student->student_id} ออกจากชุมนุม: {$club->club_name}");

        return back()->with('success', "ถอน {$student->full_name} ออกจากชุมนุมเรียบร้อยแล้ว");
    }
}
