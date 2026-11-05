<?php

namespace App\Services;

use App\Models\Teacher;
use App\Support\Thai;
use Illuminate\Support\Facades\DB;

/**
 * ดึงรายชื่อครูจากฐานข้อมูลหลัก knwacth_tel (อ่านอย่างเดียว)
 * เอาเฉพาะรหัสครู 100-999 ที่สถานะเป็น "ครู" พร้อมเลขท้ายบัตรประชาชน 6 หลัก
 */
class TelTeacherImport
{
    /** @return array{added: int, updated: int, no_card: int} */
    public function run(): array
    {
        $config = config('services.tel');
        $col = array_filter($config['columns']);

        $select = collect(['id', 'prefix', 'firstname', 'lastname', 'id_card', 'department'])
            ->filter(fn ($key) => isset($col[$key]))
            ->map(fn ($key) => "{$col[$key]} as {$key}")
            ->all();

        $rows = DB::connection('tel')->table($config['table'])
            ->select($select)
            ->where($col['status'], $config['status_value'])
            ->get()
            ->map(fn ($row) => [
                'teacher_id' => Thai::clean($row->id),
                'firstname' => Thai::clean(($row->prefix ?? '').$row->firstname),
                'lastname' => Thai::clean($row->lastname),
                'id_card_last6' => $this->last6($row->id_card),
                'department' => Thai::department($row->department ?? null),
            ])
            // รหัสครู 3 หลัก 100-999 เท่านั้น
            ->filter(fn ($t) => preg_match('/^[1-9]\d{2}$/', $t['teacher_id']) && $t['firstname'] !== '')
            ->keyBy('teacher_id');

        $result = ['added' => 0, 'updated' => 0, 'no_card' => 0];
        $existing = Teacher::whereIn('teacher_id', $rows->keys())->get()->keyBy('teacher_id');

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

    private function last6(?string $idCard): ?string
    {
        $digits = Thai::digits($idCard);

        return strlen($digits) === 13 ? substr($digits, -6) : null;
    }
}
