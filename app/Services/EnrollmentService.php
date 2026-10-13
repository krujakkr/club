<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use DomainException;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    /**
     * ลงทะเบียนนักเรียนเข้าชุมนุมของปีการศึกษาปัจจุบัน
     *
     * @param  bool  $strict  ตรวจการล็อกและระดับชั้นที่รับ (ใช้เมื่อนักเรียนเลือกเอง)
     * @param  bool  $replace  ย้ายจากชุมนุมเดิมถ้ามี (ใช้เมื่อผู้ดูแลระบบแก้ไข)
     *
     * @throws DomainException พร้อมข้อความภาษาไทยสำหรับแสดงผู้ใช้
     */
    public function enroll(Student $student, int $clubId, bool $strict = true, bool $replace = false): Club
    {
        $year = Setting::academicYear();

        return DB::transaction(function () use ($student, $clubId, $strict, $replace, $year) {
            // ล็อกแถวชุมนุมกันการรับเกินจำนวนเมื่อเลือกพร้อมกัน
            $club = Club::whereKey($clubId)->where('academic_year', $year)->lockForUpdate()->first();

            if (! $club) {
                throw new DomainException('ไม่พบชุมนุมที่เลือก');
            }
            if ($strict && $club->is_locked) {
                throw new DomainException('ชุมนุมนี้ถูกล็อก ไม่สามารถเลือกได้');
            }
            if ($strict && ! $club->allowsGrade($student->gradeNumber())) {
                throw new DomainException("ชุมนุมนี้ไม่เปิดรับนักเรียนระดับชั้น {$student->grade_level}");
            }

            $current = Enrollment::where('student_id', $student->student_id)
                ->where('academic_year', $year)->first();

            if ($current?->club_id === $club->id) {
                return $club;
            }
            if ($current && ! $replace) {
                throw new DomainException('นักเรียนเลือกชุมนุมไปแล้ว');
            }
            if ($club->enrollments()->count() >= $club->max_members) {
                throw new DomainException('ชุมนุมนี้เต็มแล้ว กรุณาเลือกชุมนุมอื่น');
            }

            $current?->delete();
            Enrollment::create([
                'student_id' => $student->student_id,
                'club_id' => $club->id,
                'academic_year' => $year,
                'enrolled_at' => now(),
            ]);

            return $club;
        });
    }

    /** ยกเลิกการเลือกชุมนุมของปีการศึกษาปัจจุบัน */
    public function withdraw(string $studentId, ?int $clubId = null): bool
    {
        return (bool) Enrollment::where('student_id', $studentId)
            ->where('academic_year', Setting::academicYear())
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->delete();
    }
}
