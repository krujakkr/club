<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Support\Thai;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'grade' => Thai::grade($request->query('grade')) ?? '',
            'status' => in_array($request->query('status'), ['selected', 'none'], true) ? $request->query('status') : '',
        ];

        $students = Student::with('enrollment.club.teacher')
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('student_id', "%{$filters['search']}%")
                ->orWhereLike('firstname', "%{$filters['search']}%")
                ->orWhereLike('lastname', "%{$filters['search']}%")))
            ->when($filters['grade'], fn ($q, $grade) => $q->where('grade_level', $grade))
            ->when($filters['status'] === 'selected', fn ($q) => $q->has('enrollment'))
            ->when($filters['status'] === 'none', fn ($q) => $q->doesntHave('enrollment'))
            ->orderBy('grade_level')->orderBy('class_room')->orderBy('class_number')->orderBy('student_id')
            ->paginate(20)->withQueryString()
            ->through(fn (Student $s) => [
                ...$s->only('student_id', 'prefix', 'firstname', 'lastname', 'full_name', 'grade_level', 'class_room', 'class_number'),
                'id_card' => $s->id_card,
                'club' => $s->enrollment ? [
                    'id' => $s->enrollment->club_id,
                    'club_name' => $s->enrollment->club->club_name,
                    'teacher' => $s->enrollment->club->teacher?->full_name,
                ] : null,
            ]);

        $total = Student::count();
        $selected = Enrollment::where('academic_year', Setting::academicYear())->count();

        return Inertia::render('Admin/Students', [
            'students' => $students,
            'filters' => $filters,
            'stats' => ['total' => $total, 'selected' => $selected, 'none' => $total - $selected],
            'clubs' => Club::current()->with('teacher')->withCount('enrollments')->orderBy('club_name')->get()
                ->map(fn (Club $c) => [
                    'id' => $c->id,
                    'club_name' => $c->club_name,
                    'teacher' => $c->teacher?->full_name,
                    'members' => $c->enrollments_count,
                    'max_members' => $c->max_members,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $student = Student::create($data);
        ActivityLog::record('add_student', "เพิ่มนักเรียน: {$student->full_name} ({$student->student_id})");

        return back()->with('success', "เพิ่มนักเรียน {$student->full_name} เรียบร้อยแล้ว");
    }

    public function update(Request $request, Student $student, EnrollmentService $enrollments): RedirectResponse
    {
        $data = $this->validated($request, $student);
        $clubId = $request->validate(['club_id' => 'nullable|integer'])['club_id'] ?? null;

        try {
            DB::transaction(function () use ($student, $data, $clubId, $enrollments) {
                $student->update($data);
                // ผู้ดูแลระบบย้ายชุมนุมได้แม้ชุมนุมล็อก แต่ยังต้องตรงระดับชั้นและไม่เกินจำนวนรับ
                if ($clubId) {
                    $club = Club::current()->find($clubId);
                    if ($club && ! $club->allowsGrade($student->gradeNumber())) {
                        throw new DomainException("ชุมนุมที่เลือกไม่เปิดรับระดับชั้น {$student->grade_level}");
                    }
                    $enrollments->enroll($student, $clubId, strict: false, replace: true);
                } else {
                    $enrollments->withdraw($student->student_id);
                }
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('edit_student', "แก้ไขข้อมูลนักเรียน: {$student->full_name}");

        return back()->with('success', 'แก้ไขข้อมูลนักเรียนเรียบร้อยแล้ว');
    }

    public function resetSelection(Student $student, EnrollmentService $enrollments): RedirectResponse
    {
        $enrollments->withdraw($student->student_id);
        ActivityLog::record('reset_selection', "ยกเลิกการเลือกชุมนุมของ {$student->full_name} ({$student->student_id})");

        return back()->with('success', "ยกเลิกการเลือกชุมนุมของ {$student->full_name} เรียบร้อยแล้ว");
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $request->merge([
            'id_card' => Thai::digits($request->input('id_card')) ?: null,
            'class_number' => $request->input('class_number') ?: null,
        ]);

        $data = $request->validate([
            'student_id' => [$student ? 'prohibited' : 'required', 'regex:/^\d{1,10}$/', 'unique:students,student_id'],
            // ปพ.5 ต้องการเลขบัตรเป็นตัวเลข 13 หลักล้วน
            'id_card' => ['nullable', 'digits:13', Rule::unique('students')->ignore($student)],
            'prefix' => 'nullable|string|max:20',
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'grade_level' => ['required', Rule::in(Thai::GRADES)],
            'class_room' => 'required|integer|min:1|max:99',
            // ห้องและเลขที่ห้ามซ้ำกัน ตามที่ ปพ.5 ตรวจ
            'class_number' => ['nullable', 'integer', 'min:1', 'max:99',
                Rule::unique('students')->ignore($student)
                    ->where('grade_level', $request->input('grade_level'))
                    ->where('class_room', $request->input('class_room'))],
        ], [
            'student_id.unique' => 'เลขประจำตัวนักเรียนนี้มีในระบบแล้ว',
            'student_id.regex' => 'เลขประจำตัวนักเรียนต้องเป็นตัวเลข',
            'id_card.unique' => 'เลขบัตรประชาชนนี้มีในระบบแล้ว',
            'id_card.digits' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก',
            'class_number.unique' => 'มีนักเรียนคนอื่นใช้ห้องและเลขที่นี้แล้ว',
        ], [
            'firstname' => 'ชื่อ', 'lastname' => 'นามสกุล', 'grade_level' => 'ระดับชั้น',
            'class_room' => 'ห้อง', 'class_number' => 'เลขที่',
        ]);

        $data['prefix'] = $data['prefix'] ?? '';

        return $data;
    }
}
