<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    public function club(Club $club): Response
    {
        $teacher = Auth::guard('teacher')->user();
        abort_unless(Auth::guard('admin')->check() || $club->teacher_id === $teacher?->teacher_id, 403);

        return Inertia::render('Print/ClubMembers', ['clubs' => [$this->sheet($club)]]);
    }

    public function all(): Response
    {
        abort_unless(Auth::guard('admin')->check(), 403);

        return Inertia::render('Print/ClubMembers', [
            'clubs' => Club::current()->with(['teacher', 'enrollments.student'])->orderBy('club_name')->get()
                ->map(fn ($club) => $this->sheet($club)),
        ]);
    }

    private function sheet(Club $club): array
    {
        $club->loadMissing(['teacher', 'enrollments.student']);

        return [
            'id' => $club->id,
            'club_name' => $club->club_name,
            'location' => $club->location,
            'academic_year' => $club->academic_year,
            'max_members' => $club->max_members,
            'teacher' => $club->teacher?->full_name,
            'members' => $club->enrollments->pluck('student')->filter()
                ->sortBy([['grade_level', 'asc'], ['class_room', 'asc'], ['class_number', 'asc'], ['student_id', 'asc']])
                ->values()
                ->map(fn ($s) => [
                    'student_id' => $s->student_id,
                    'name' => $s->full_name,
                    'grade_level' => $s->grade_level,
                    'class_room' => $s->class_room,
                    'class_number' => $s->class_number,
                ]),
        ];
    }
}
