<?php

namespace App\Services;

use App\Models\Teacher;
use App\Support\Thai;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ดึงรายชื่อครูจากฐานข้อมูลหลัก knwacth_tel (อ่านอย่างเดียว)
 * เอาเฉพาะรหัสครู 100-999 ที่สถานะเป็น "ปัจจุบัน" พร้อมเลขท้ายบัตรประชาชน 6 หลัก
 */
class TelTeacherImport
{
    /** @return array{added: int, updated: int, no_card: int} */
    public function run(): array
    {
        $rows = $this->teachers();
        $existing = Teacher::whereIn('teacher_id', $rows->keys())->get()->keyBy('teacher_id');
        $result = ['added' => 0, 'updated' => 0, 'no_card' => 0];

        DB::transaction(function () use ($rows, $existing, &$result) {
            foreach ($rows as $id => $data) {
                if (! $data['id_card_last6']) {
                    $result['no_card']++;
                }

                $teacher = $existing[$id] ?? null;

                if (! $teacher) {
                    Teacher::create($data);
                    $result['added']++;

                    continue;
                }

                // กลุ่มสาระที่แอดมินตั้งไว้แล้วไม่ทับ เติมให้เฉพาะคนที่ยังว่าง
                $teacher->fill([
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'id_card_last6' => $data['id_card_last6'] ?? $teacher->id_card_last6,
                    'department' => $teacher->department ?? $data['department'],
                ]);

                if ($teacher->isDirty()) {
                    $teacher->save();
                    $result['updated']++;
                }
            }
        });

        return $result;
    }

    /** @return Collection<string, array<string, ?string>> รายชื่อครูจาก knwacth_tel โดยใช้รหัสครูเป็น key */
    private function teachers(): Collection
    {
        $db = DB::connection('tel');
        $name = config('services.tel.status_name');

        // ตารางอ้างอิงมีไม่กี่แถว ดึงมาเทียบใน PHP เลี่ยงปัญหา collation ต่างกันระหว่างสองฐาน
        $statuses = $db->table('statuses')->pluck('status_name', 'status_id')->map(fn ($v) => Thai::clean($v));
        $departments = $db->table('departments')->pluck('department_name', 'department_id');

        $statusIds = $statuses->filter(fn ($v) => $v === $name)->keys();

        if ($statusIds->isEmpty()) {
            throw new RuntimeException("ไม่พบสถานะ \"{$name}\" ในฐานข้อมูลหลัก — สถานะที่มี: {$statuses->implode(', ')}");
        }

        return $db->table('teachers')
            ->select('teacher_code', 'first_name', 'last_name', 'department_id', 'id_card_last_6_digits')
            ->whereIn('status_id', $statusIds->all())
            ->get()
            ->map(fn ($row) => [
                'teacher_id' => Thai::clean($row->teacher_code),
                'firstname' => Thai::clean($row->first_name),
                'lastname' => Thai::clean($row->last_name),
                'id_card_last6' => preg_match('/^\d{6}$/', $card = Thai::digits($row->id_card_last_6_digits)) ? $card : null,
                'department' => $this->department($departments[$row->department_id] ?? null),
            ])
            // รหัสครู 3 หลัก 100-999 เท่านั้น
            ->filter(fn ($t) => preg_match('/^[1-9]\d{2}$/', $t['teacher_id']) && $t['firstname'] !== '')
            ->keyBy('teacher_id');
    }

    private function department(?string $name): ?string
    {
        $name = preg_replace('/^กลุ่ม(สาระ)?(การเรียนรู้)?/u', '', Thai::clean($name));

        return Thai::department($name);
    }
}
