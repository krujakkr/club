<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Setting;
use App\Services\EnrollmentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 30);
        $perPage = in_array($perPage, [30, 100, 0], true) ? $perPage : 30;

        $student = Auth::guard('student')->user();
        $enrollment = $student?->enrollment()->with('club.teacher')->first();

        $query = Club::current()->with('teacher')->withCount('enrollments')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('club_name', "%{$search}%")
                ->orWhereLike('description', "%{$search}%")
                ->orWhereLike('location', "%{$search}%")
                ->orWhereHas('teacher', fn ($t) => $t->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$search}%"]))))
            // นักเรียนเห็นเฉพาะชุมนุมที่เปิดรับระดับชั้นของตน
            ->when($student, fn ($q) => $q->where('allow_m'.max(1, $student->gradeNumber()), true))
            ->orderBy('club_name');

        $clubs = $query->paginate($perPage ?: max(1, (clone $query)->count()))->withQueryString()
            ->through(fn (Club $club) => [
                'id' => $club->id,
                'club_name' => $club->club_name,
                'description' => $club->description,
                'location' => $club->location,
                'teacher' => $club->teacher?->full_name,
                'max_members' => $club->max_members,
                'members' => $club->enrollments_count,
                'is_locked' => $club->is_locked,
                'grades' => array_values(array_filter(range(1, 6), fn ($g) => $club->allowsGrade($g))),
            ]);

        return Inertia::render('Home', [
            'clubs' => $clubs,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'registration' => ['open' => Setting::registrationOpen()] + Setting::period('registration'),
            'student' => $student ? [
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'class' => "{$student->grade_level}/{$student->class_room}",
                'club' => $enrollment ? [
                    'id' => $enrollment->club_id,
                    'club_name' => $enrollment->club->club_name,
                    'location' => $enrollment->club->location,
                    'teacher' => $enrollment->club->teacher?->full_name,
                ] : null,
            ] : null,
        ]);
    }

    public function enroll(Request $request, EnrollmentService $enrollments): RedirectResponse
    {
        $data = $request->validate(['club_id' => 'required|integer']);

        if (! Setting::registrationOpen()) {
            return back()->with('error', 'ขณะนี้ระบบปิดการลงทะเบียน');
        }

        try {
            $club = $enrollments->enroll(Auth::guard('student')->user(), (int) $data['club_id']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record('select_club', "เลือกชุมนุม: {$club->club_name}");

        return back()->with('success', "เลือกชุมนุม {$club->club_name} เรียบร้อยแล้ว");
    }
}
