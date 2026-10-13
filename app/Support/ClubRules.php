<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** กติกาของข้อมูลชุมนุมที่หน้าครูและหน้าผู้ดูแลระบบใช้ร่วมกัน */
class ClubRules
{
    public static function validate(Request $request, ?Club $club = null, array $extra = []): array
    {
        $data = $request->validate([
            // ชื่อยาวไม่เกิน 100 ตัวอักษร ตรงกับขีดจำกัดของ ปพ.5 และห้ามซ้ำภายในปีการศึกษาเดียวกัน
            'club_name' => ['required', 'string', 'max:100',
                Rule::unique('clubs')->where('academic_year', $club?->academic_year ?? Setting::academicYear())->ignore($club)],
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:100',
            'max_members' => 'required|integer|min:1|max:1000',
        ] + $extra, [
            'club_name.unique' => 'ชื่อชุมนุมนี้มีในปีการศึกษานี้แล้ว กรุณาใช้ชื่ออื่น',
        ], [
            'club_name' => 'ชื่อชุมนุม', 'location' => 'สถานที่', 'max_members' => 'จำนวนที่รับ', 'teacher_id' => 'ครูที่ปรึกษา',
        ]);

        foreach (range(1, 6) as $grade) {
            $data["allow_m{$grade}"] = $request->boolean("allow_m{$grade}");
        }

        return $data;
    }

    public static function members(Club $club): Collection
    {
        return Student::whereHas('enrollments', fn ($q) => $q->where('club_id', $club->id))
            ->orderBy('grade_level')->orderBy('class_room')->orderBy('class_number')->get()
            ->map(fn (Student $s) => [
                'student_id' => $s->student_id,
                'name' => $s->full_name,
                'grade_level' => $s->grade_level,
                'class_room' => $s->class_room,
                'class_number' => $s->class_number,
            ]);
    }
}
