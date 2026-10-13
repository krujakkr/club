<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\Thai;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacy extends Command
{
    protected $signature = 'club:import-legacy {--force : ล้างข้อมูลในฐานข้อมูลใหม่ก่อนนำเข้าโดยไม่ถาม}';

    protected $description = 'นำเข้าข้อมูลจากฐานข้อมูลระบบเดิม (connection: legacy) พร้อมปรับโครงสร้างตาม plan_club.md';

    /** @var list<string> */
    private array $notes = [];

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $tables = ['enrollments', 'clubs', 'students', 'teachers', 'admins', 'logs', 'system_settings'];

        $existing = collect($tables)->sum(fn ($t) => DB::table($t)->count());
        if ($existing > 0 && ! $this->option('force')
            && ! $this->confirm("ฐานข้อมูลใหม่มีข้อมูลอยู่ {$existing} แถว ต้องการล้างแล้วนำเข้าใหม่หรือไม่?")) {
            return self::FAILURE;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = now();

        DB::transaction(function () use ($legacy, $now) {
            // ----- ตั้งค่าระบบ -----
            $settings = $legacy->table('system_settings')->pluck('setting_value', 'setting_name')->all() + Setting::DEFAULTS;
            foreach ($settings as $name => $value) {
                DB::table('system_settings')->insert([
                    'setting_name' => $name, 'setting_value' => $value, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $year = (int) $settings['academic_year'];

            // ----- ผู้ดูแลระบบ (รหัสผ่านเป็น bcrypt อยู่แล้ว ใช้ต่อได้) -----
            foreach ($legacy->table('admins')->get() as $admin) {
                DB::table('admins')->insert([
                    'id' => $admin->admin_id, 'username' => $admin->username, 'password' => $admin->password,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // ----- ครู -----
            $teachers = [];
            foreach ($legacy->table('teachers')->orderBy('teacher_id')->get() as $t) {
                $id = Thai::clean($t->teacher_id);
                if (! preg_match('/^\d{3}$/', $id)) {
                    $this->notes[] = "ครู {$id} ({$t->firstname} {$t->lastname}): รหัสไม่ใช่ 3 หลัก (teacher_code เดิม = '{$t->teacher_code}') นำเข้าตามเดิม กรุณาแก้ให้ตรงกับ ปพ.5";
                }
                $phone = Thai::digits($t->telephon);
                if (strlen($phone) !== 10) {
                    $this->notes[] = "ครู {$id}: เบอร์โทร '{$t->telephon}' ไม่ครบ 10 หลัก ครูจะเข้าสู่ระบบไม่ได้จนกว่าจะแก้";
                }
                $department = Thai::department($t->department);
                if ($department === null && Thai::clean($t->department) !== '') {
                    $this->notes[] = "ครู {$id}: ไม่รู้จักกลุ่มสาระ '{$t->department}' จึงเว้นว่างไว้";
                }
                $teachers[] = [
                    'teacher_id' => $id,
                    'firstname' => Thai::clean($t->firstname),
                    'lastname' => Thai::clean($t->lastname),
                    'phone' => $phone ?: null,
                    'department' => $department,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('teachers')->insert($teachers);
            $teacherIds = array_flip(array_column($teachers, 'teacher_id'));

            // ----- ชุมนุม (ใส่ปีการศึกษา, teacher_id เป็นข้อความชนิดเดียวกับตารางครู) -----
            $clubs = [];
            foreach ($legacy->table('clubs')->orderBy('club_id')->get() as $c) {
                $teacherId = $c->teacher_id === null ? null : (string) $c->teacher_id;
                if ($teacherId !== null && ! isset($teacherIds[$teacherId])) {
                    $this->notes[] = "ชุมนุม {$c->club_id} ({$c->club_name}): ไม่พบครูรหัส {$teacherId} จึงเว้นครูที่ปรึกษาไว้";
                    $teacherId = null;
                }
                $clubs[] = [
                    'id' => $c->club_id, 'academic_year' => $year,
                    'club_name' => Thai::clean($c->club_name),
                    'description' => $c->description, 'location' => $c->location,
                    'max_members' => $c->max_members, 'teacher_id' => $teacherId,
                    'allow_m1' => $c->allow_m1, 'allow_m2' => $c->allow_m2, 'allow_m3' => $c->allow_m3,
                    'allow_m4' => $c->allow_m4, 'allow_m5' => $c->allow_m5, 'allow_m6' => $c->allow_m6,
                    'is_locked' => $c->is_locked,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('clubs')->insert($clubs);
            $clubIds = array_flip(array_column($clubs, 'id'));

            // ----- นักเรียน + การเลือกชุมนุม -----
            $students = $enrollments = $seats = $idCards = [];
            foreach ($legacy->table('students')->orderBy('student_id')->get() as $s) {
                [$prefix, $firstname] = Thai::splitPrefix($s->firstname);
                $label = "นักเรียน {$s->student_id} ({$s->firstname} {$s->lastname})";

                $idCard = Thai::digits($s->id_card);
                if (strlen($idCard) !== 13 || isset($idCards[$idCard])) {
                    $this->notes[] = "{$label}: เลขบัตรประชาชน '{$s->id_card}' ไม่ใช่เลข 13 หลักหรือซ้ำ จึงเว้นว่างไว้ นักเรียนจะเข้าสู่ระบบไม่ได้จนกว่าจะแก้";
                    $idCard = null;
                } else {
                    $idCards[$idCard] = true;
                }

                $number = (int) $s->class_number ?: null;
                $seat = "{$s->grade_level}/{$s->class_room}/{$number}";
                if ($number !== null && isset($seats[$seat])) {
                    $this->notes[] = "{$label}: {$s->grade_level}/{$s->class_room} เลขที่ {$number} ซ้ำกับนักเรียน {$seats[$seat]} จึงเว้นเลขที่ไว้ กรุณาแก้ให้ถูกต้อง";
                    $number = null;
                } elseif ($number !== null) {
                    $seats[$seat] = $s->student_id;
                }

                $students[] = [
                    'student_id' => $s->student_id, 'id_card' => $idCard,
                    'prefix' => $prefix, 'firstname' => $firstname, 'lastname' => Thai::clean($s->lastname),
                    'grade_level' => $s->grade_level, 'class_room' => $s->class_room, 'class_number' => $number,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                if ($s->selection_status && $s->club_id !== null) {
                    if (isset($clubIds[$s->club_id])) {
                        // ระบบเดิมไม่ได้เก็บเวลาที่เลือก จึงเป็น NULL
                        $enrollments[] = [
                            'student_id' => $s->student_id, 'club_id' => $s->club_id,
                            'academic_year' => $year, 'enrolled_at' => null,
                        ];
                    } else {
                        $this->notes[] = "{$label}: ไม่พบชุมนุมรหัส {$s->club_id} จึงไม่ได้นำเข้าการเลือก";
                    }
                }
            }
            foreach (array_chunk($students, 500) as $chunk) {
                DB::table('students')->insert($chunk);
            }
            foreach (array_chunk($enrollments, 1000) as $chunk) {
                DB::table('enrollments')->insert($chunk);
            }

            // ----- ประวัติการใช้งาน -----
            $legacy->table('logs')->orderBy('log_id')->chunk(2000, function ($rows) {
                DB::table('logs')->insert($rows->map(fn ($l) => [
                    'id' => $l->log_id, 'user_id' => $l->user_id, 'user_type' => $l->user_type,
                    'action' => $l->action, 'details' => $l->details,
                    'ip_address' => $l->ip_address, 'created_at' => $l->created_at,
                ])->all());
            });
        });

        Setting::flush();

        $this->table(['ตาราง', 'ระบบเดิม', 'ระบบใหม่'], [
            ['admins', $legacy->table('admins')->count(), DB::table('admins')->count()],
            ['teachers', $legacy->table('teachers')->count(), DB::table('teachers')->count()],
            ['clubs', $legacy->table('clubs')->count(), DB::table('clubs')->count()],
            ['students', $legacy->table('students')->count(), DB::table('students')->count()],
            ['enrollments (จาก students.club_id)', $legacy->table('students')->where('selection_status', 1)->whereNotNull('club_id')->count(), DB::table('enrollments')->count()],
            ['logs', $legacy->table('logs')->count(), DB::table('logs')->count()],
        ]);

        if ($this->notes) {
            $this->warn('รายการที่ต้องตรวจสอบด้วยตนเอง ('.count($this->notes).' รายการ):');
            foreach ($this->notes as $note) {
                $this->line(" - {$note}");
            }
        }

        return self::SUCCESS;
    }
}
