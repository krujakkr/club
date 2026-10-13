<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): Response
    {
        $year = Setting::academicYear();

        $byClass = DB::table('students as s')
            ->leftJoin('enrollments as e', fn ($j) => $j->on('e.student_id', '=', 's.student_id')->where('e.academic_year', $year))
            ->selectRaw('s.grade_level, s.class_room, COUNT(*) as total, COUNT(e.id) as selected')
            ->groupBy('s.grade_level', 's.class_room')
            ->orderBy('s.grade_level')->orderBy('s.class_room')->get();

        $clubs = Club::current()->with('teacher')->withCount('enrollments')
            ->orderByDesc('enrollments_count')->orderBy('club_name')->get();

        return Inertia::render('Admin/Reports', [
            'summary' => [
                'students' => $byClass->sum('total'),
                'selected' => $byClass->sum('selected'),
                'clubs' => $clubs->count(),
                'capacity' => $clubs->sum('max_members'),
                'full' => $clubs->filter(fn ($c) => $c->enrollments_count >= $c->max_members)->count(),
                'locked' => $clubs->where('is_locked', true)->count(),
                'teachers' => Teacher::count(),
            ],
            'byGrade' => $byClass->groupBy('grade_level')->map(fn (Collection $rows, $grade) => [
                'grade_level' => $grade, 'total' => $rows->sum('total'), 'selected' => $rows->sum('selected'),
            ])->values(),
            'byClass' => $byClass,
            'clubs' => $clubs->map(fn (Club $c) => [
                'id' => $c->id, 'club_name' => $c->club_name, 'location' => $c->location,
                'teacher' => $c->teacher?->full_name, 'members' => $c->enrollments_count,
                'max_members' => $c->max_members, 'is_locked' => $c->is_locked,
            ]),
            'teachers' => Teacher::with(['clubs' => fn ($q) => $q->where('academic_year', $year)->withCount('enrollments')])
                ->orderBy('teacher_id')->get()
                ->map(fn (Teacher $t) => [
                    'teacher_id' => $t->teacher_id, 'name' => $t->full_name, 'department' => $t->department,
                    'clubs' => $t->clubs->pluck('club_name'), 'students' => $t->clubs->sum('enrollments_count'),
                ]),
        ]);
    }

    public function export(string $type): StreamedResponse
    {
        $year = Setting::academicYear();

        [$header, $rows] = match ($type) {
            'students' => [
                ['เลขประจำตัว', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'ระดับชั้น', 'ห้อง', 'เลขที่', 'ชุมนุม', 'ครูที่ปรึกษา', 'สถานที่'],
                Student::with('enrollment.club.teacher')
                    ->orderBy('grade_level')->orderBy('class_room')->orderBy('class_number')->lazy(500)
                    ->map(fn (Student $s) => [
                        $s->student_id, $s->prefix, $s->firstname, $s->lastname, $s->grade_level, $s->class_room, $s->class_number,
                        $s->enrollment?->club?->club_name, $s->enrollment?->club?->teacher?->full_name, $s->enrollment?->club?->location,
                    ]),
            ],
            'clubs' => [
                ['รหัสชุมนุม', 'ปีการศึกษา', 'ชื่อชุมนุม', 'คำอธิบาย', 'สถานที่', 'รหัสครู', 'ครูที่ปรึกษา', 'จำนวนรับ', 'สมาชิก',
                    'ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6', 'ล็อก'],
                Club::current()->with('teacher')->withCount('enrollments')->orderBy('club_name')->get()
                    ->map(fn (Club $c) => [
                        $c->id, $c->academic_year, $c->club_name, $c->description, $c->location, $c->teacher_id,
                        $c->teacher?->full_name, $c->max_members, $c->enrollments_count,
                        ...array_map(fn ($g) => (int) $c->{"allow_m{$g}"}, range(1, 6)), (int) $c->is_locked,
                    ]),
            ],
            'teachers' => [
                ['รหัสครู', 'ชื่อ', 'นามสกุล', 'เบอร์โทรศัพท์', 'กลุ่มสาระ', 'ชุมนุมที่ดูแล'],
                Teacher::with(['clubs' => fn ($q) => $q->where('academic_year', $year)])->orderBy('teacher_id')->get()
                    ->map(fn (Teacher $t) => [
                        $t->teacher_id, $t->firstname, $t->lastname, $t->phone, $t->department, $t->clubs->pluck('club_name')->implode(', '),
                    ]),
            ],
        };

        ActivityLog::record('export_csv', "ส่งออกข้อมูล {$type}");

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยได้
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "{$type}_{$year}_".now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
