<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $this->currentUser(),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'school' => fn () => [
                'name' => Setting::value('school_name'),
                'academic_year' => Setting::value('academic_year'),
                'semester' => Setting::value('semester'),
            ],
        ];
    }

    /** @return array{role: string, id: string, name: string}|null */
    private function currentUser(): ?array
    {
        if ($admin = Auth::guard('admin')->user()) {
            return ['role' => 'admin', 'id' => $admin->username, 'name' => $admin->username];
        }
        if ($teacher = Auth::guard('teacher')->user()) {
            return ['role' => 'teacher', 'id' => $teacher->teacher_id, 'name' => $teacher->full_name];
        }
        if ($student = Auth::guard('student')->user()) {
            return ['role' => 'student', 'id' => $student->student_id, 'name' => $student->full_name];
        }

        return null;
    }
}
