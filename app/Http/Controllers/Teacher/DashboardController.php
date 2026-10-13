<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentService;
use App\Support\ClubRules;
use App\Support\Thai;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teacher = $this->teacher();

        return Inertia::render('Teacher/Dashboard', [
            'teacher' => $teacher->only('teacher_id', 'full_name', 'department'),
            'clubs' => Club::current()->where('teacher_id', $teacher->teacher_id)
                ->withCount('enrollments')->orderBy('club_name')->get(),
            'period' => ['active' => Setting::teacherPeriodActive()] + Setting::period('teacher_edit'),
            'rooms' => Student::select('grade_level', 'class_room')->distinct()
                ->orderBy('grade_level')->orderBy('class_room')->get()
                ->groupBy('grade_level')->map->pluck('class_room'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensurePeriod();
        $data = ClubRules::validate($request);

        Club::create($data + [
            'academic_year' => Setting::academicYear(),
            'teacher_id' => $this->teacher()->teacher_id,
        ]);
        ActivityLog::record('create_club', "สร้างชุมนุมใหม่: {$data['club_name']}");

        return back()->with('success', "สร้างชุมนุม \"{$data['club_name']}\" เรียบร้อยแล้ว");
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        $this->ensurePeriod();
        $this->authorizeClub($club);

        $club->update(ClubRules::validate($request, $club) + ['is_locked' => $request->boolean('is_locked')]);
        ActivityLog::record('update_club', "อัพเดทชุมนุม club_id={$club->id}");

        return back()->with('success', 'บันทึกข้อมูลชุมนุมเรียบร้อยแล้ว');
    }

    public function members(Club $club): JsonResponse
    {
        $this->authorizeClub($club);

        return response()->json(ClubRules::members($club));
    }

    /** รายชื่อนักเรียนตามระดับชั้น/ห้อง สำหรับเลือกเพิ่มเข้าชุมนุม */
    public function candidates(Request $request, Club $club): JsonResponse
    {
        $this->authorizeClub($club);
        $grade = Thai::grade($request->query('grade'));
        $room = (int) $request->query('room');
        abort_unless($grade && $room, 422, 'กรุณาเลือกระดับชั้นและห้อง');

        return response()->json(
            Student::where('grade_level', $grade)->where('class_room', $room)
                ->with('enrollment.club:id,club_name')->orderBy('class_number')->get()
                ->map(fn (Student $s) => [
                    'student_id' => $s->student_id,
                    'name' => $s->full_name,
                    'class_number' => $s->class_number,
                    'club' => $s->enrollment?->club?->club_name,
                ])
        );
    }

    public function addMembers(Request $request, Club $club, EnrollmentService $enrollments): RedirectResponse
    {
        $this->ensurePeriod();
        $this->authorizeClub($club);
        $ids = $request->validate(['student_ids' => 'required|array|min:1', 'student_ids.*' => 'string'])['student_ids'];

        $added = 0;
        $reasons = [];
        foreach (Student::whereIn('student_id', $ids)->get() as $student) {
            try {
                // ครูเพิ่มนักเรียนเองได้แม้ชุมนุมล็อกหรือไม่ได้เปิดรับระดับชั้นนั้น แต่ต้องไม่เกินจำนวนรับ
                $enrollments->enroll($student, $club->id, strict: false);
                ActivityLog::record('update_club', "นำเข้านักเรียน student_id={$student->student_id} เข้า club_id={$club->id}");
                $added++;
            } catch (DomainException $e) {
                $reasons[$e->getMessage()] = ($reasons[$e->getMessage()] ?? 0) + 1;
            }
        }

        $skipped = collect($reasons)->map(fn ($n, $reason) => "{$reason} {$n} คน")->implode(', ');

        return $added
            ? back()->with('success', "เพิ่มนักเรียนสำเร็จ {$added} คน".($skipped ? " (ข้าม: {$skipped})" : ''))
            : back()->with('error', 'ไม่มีนักเรียนที่เพิ่มได้'.($skipped ? " ({$skipped})" : ''));
    }

    public function removeMember(Club $club, Student $student, EnrollmentService $enrollments): RedirectResponse
    {
        $this->ensurePeriod();
        $this->authorizeClub($club);

        if (! $enrollments->withdraw($student->student_id, $club->id)) {
            return back()->with('error', 'ไม่พบนักเรียนในชุมนุมนี้');
        }
        ActivityLog::record('remove_student', "ยกเลิกนักเรียน student_id={$student->student_id} จาก club_id={$club->id}");

        return back()->with('success', 'ยกเลิกนักเรียนออกจากชุมนุมเรียบร้อยแล้ว');
    }

    private function teacher(): Teacher
    {
        return Auth::guard('teacher')->user();
    }

    private function authorizeClub(Club $club): void
    {
        abort_unless(
            $club->teacher_id === $this->teacher()->teacher_id && $club->academic_year === Setting::academicYear(),
            403, 'ไม่มีสิทธิ์จัดการชุมนุมนี้'
        );
    }

    private function ensurePeriod(): void
    {
        abort_unless(Setting::teacherPeriodActive(), 403, 'ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไข');
    }
}
