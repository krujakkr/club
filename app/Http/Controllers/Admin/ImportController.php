<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentService;
use App\Support\Thai;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ImportController extends Controller
{
    /** คอลัมน์ของไฟล์ CSV แต่ละประเภท (เรียงตามลำดับในไฟล์) */
    private const COLUMNS = [
        'students' => ['student_id', 'id_card', 'prefix', 'firstname', 'lastname', 'grade_level', 'class_room', 'class_number', 'club_id'],
        'teachers' => ['teacher_id', 'firstname', 'lastname', 'phone', 'department'],
        'clubs' => ['club_name', 'description', 'location', 'max_members', 'teacher_id',
            'allow_m1', 'allow_m2', 'allow_m3', 'allow_m4', 'allow_m5', 'allow_m6'],
    ];

    /** ชื่อหัวคอลัมน์แบบระบบเดิมที่ยังรับได้ */
    private const HEADER_ALIASES = ['telephon' => 'phone', 'class_level' => 'class_room'];

    public function index(): Response
    {
        return Inertia::render('Admin/Import', [
            'columns' => self::COLUMNS,
            'departments' => Thai::DEPARTMENTS,
            'result' => session('import_result'),
        ]);
    }

    public function store(Request $request, EnrollmentService $enrollments): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:students,teachers,clubs',
            'file' => 'required|file|max:5120|mimes:csv,txt',
        ], [], ['file' => 'ไฟล์ CSV']);

        $rows = $this->read($data['file']->getRealPath(), $data['type']);
        if ($rows === null) {
            return back()->with('error', 'อ่านไฟล์ไม่ได้: บรรทัดแรกต้องเป็นหัวคอลัมน์ตามไฟล์ตัวอย่าง');
        }

        $imported = 0;
        $errors = [];
        foreach ($rows as $line => $row) {
            try {
                match ($data['type']) {
                    'students' => $this->student($row, $enrollments),
                    'teachers' => $this->teacher($row),
                    'clubs' => $this->club($row),
                };
                $imported++;
            } catch (DomainException $e) {
                $errors[] = "บรรทัด {$line}: {$e->getMessage()}";
            } catch (Throwable $e) {
                report($e);
                $errors[] = "บรรทัด {$line}: บันทึกไม่สำเร็จ";
            }
        }

        ActivityLog::record('import_csv', "นำเข้าข้อมูล {$data['type']}: {$imported} รายการ".($errors ? ' (ผิดพลาด '.count($errors).' รายการ)' : ''));

        return back()->with('import_result', [
            'type' => $data['type'], 'imported' => $imported,
            'failed' => count($errors), 'errors' => array_slice($errors, 0, 100),
        ]);
    }

    /**
     * อ่าน CSV เป็นแถวข้อมูล โดยจับคู่คอลัมน์จากหัวตาราง
     *
     * @return array<int, array<string, string>>|null key คือเลขบรรทัดในไฟล์
     */
    private function read(string $path, string $type): ?array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        // ไฟล์ที่บันทึกจาก Excel รุ่นเก่ามักเป็น Windows-874
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = iconv('CP874', 'UTF-8//IGNORE', $content);
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($lines[0] ?? '', $d))->first();

        $header = array_map(function ($name) {
            $name = strtolower(Thai::clean($name));

            return self::HEADER_ALIASES[$name] ?? $name;
        }, str_getcsv(array_shift($lines) ?? '', $delimiter, '"', ''));

        $required = array_diff(self::COLUMNS[$type], ['prefix', 'club_id', 'description', 'location', 'department']);
        if (array_diff($required, $header)) {
            return null;
        }

        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, $delimiter, '"', '');
            $row = [];
            foreach ($header as $index => $name) {
                $row[$name] = Thai::clean($values[$index] ?? '');
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    private function student(array $row, EnrollmentService $enrollments): void
    {
        // รองรับไฟล์แบบเดิมที่คำนำหน้าติดอยู่กับชื่อ
        if (($row['prefix'] ?? '') === '') {
            [$row['prefix'], $row['firstname']] = Thai::splitPrefix($row['firstname']);
        }
        $row['id_card'] = Thai::digits($row['id_card']) ?: null;
        $row['grade_level'] = Thai::grade($row['grade_level']);
        $row['class_number'] = $row['class_number'] ?: null;
        $existing = Student::find($row['student_id']);

        $data = $this->check($row, [
            'student_id' => ['required', 'regex:/^\d{1,10}$/'],
            'id_card' => ['nullable', 'digits:13', Rule::unique('students')->ignore($existing)],
            'prefix' => 'nullable|string|max:20',
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'grade_level' => ['required', Rule::in(Thai::GRADES)],
            'class_room' => 'required|integer|min:1|max:99',
            'class_number' => ['nullable', 'integer', 'min:1', 'max:99',
                Rule::unique('students')->ignore($existing)
                    ->where('grade_level', $row['grade_level'])->where('class_room', $row['class_room'])],
        ], [
            'id_card.digits' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก',
            'id_card.unique' => 'เลขบัตรประชาชนซ้ำกับนักเรียนคนอื่น',
            'class_number.unique' => 'ห้องและเลขที่ซ้ำกับนักเรียนคนอื่น',
            'grade_level.required' => 'ระดับชั้นต้องเป็น ม.1 ถึง ม.6',
        ]);
        $data['prefix'] ??= '';

        $student = Student::updateOrCreate(['student_id' => $data['student_id']], $data);

        if (($row['club_id'] ?? '') !== '' && strtoupper($row['club_id']) !== 'NULL') {
            $enrollments->enroll($student, (int) $row['club_id'], strict: false, replace: true);
        }
    }

    private function teacher(array $row): void
    {
        $department = Thai::department($row['department'] ?? '');
        if ($department === null && ($row['department'] ?? '') !== '') {
            throw new DomainException("ไม่รู้จักกลุ่มสาระ \"{$row['department']}\" (ต้องสะกดตรงกับ ปพ.5)");
        }
        $row['department'] = $department;
        $row['phone'] = Thai::digits($row['phone']) ?: null;

        $data = $this->check($row, [
            'teacher_id' => 'required|digits:3',
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'phone' => 'nullable|digits:10',
            'department' => 'nullable|string',
        ], [
            'teacher_id.digits' => 'รหัสครูต้องเป็นตัวเลข 3 หลัก',
            'phone.digits' => 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก',
        ]);

        Teacher::updateOrCreate(['teacher_id' => $data['teacher_id']], $data);
    }

    private function club(array $row): void
    {
        $row['teacher_id'] = $row['teacher_id'] ?: null;

        $data = $this->check($row, [
            'club_name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:100',
            'max_members' => 'required|integer|min:1|max:1000',
            'teacher_id' => 'nullable|exists:teachers,teacher_id',
        ] + array_fill_keys(array_map(fn ($g) => "allow_m{$g}", range(1, 6)), 'required|in:0,1'), [
            'teacher_id.exists' => "ไม่พบครูรหัส {$row['teacher_id']} กรุณานำเข้าข้อมูลครูก่อน",
            'club_name.max' => 'ชื่อชุมนุมยาวเกิน 100 ตัวอักษร',
        ]);

        // ชื่อซ้ำในปีการศึกษาเดียวกันถือเป็นการปรับปรุงข้อมูลชุมนุมเดิม
        Club::updateOrCreate(
            ['academic_year' => Setting::academicYear(), 'club_name' => $data['club_name']],
            $data,
        );
    }

    private function check(array $row, array $rules, array $messages = []): array
    {
        $validator = Validator::make($row, $rules, $messages);
        if ($validator->fails()) {
            throw new DomainException($validator->errors()->first());
        }

        return $validator->validated();
    }
}
