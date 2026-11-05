<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * แสดงโครงสร้างตารางใน knwacth_tel เพื่อใช้ตั้งค่า TEL_COL_ ใน .env
 * แสดงค่าตัวอย่างเฉพาะคอลัมน์ที่ค่าซ้ำกันเยอะ (เช่น สถานะ) จึงไม่แสดงเลขบัตรหรือชื่อคน
 */
class TelInspect extends Command
{
    protected $signature = 'tel:inspect {table=teachers : ชื่อตาราง}';

    protected $description = 'แสดงคอลัมน์ของตารางครูในฐานข้อมูลหลัก knwacth_tel';

    public function handle(): int
    {
        $db = DB::connection('tel');
        $table = $this->argument('table');

        $this->line('TABLES: '.collect($db->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])->implode(', '));
        $rows = $db->table($table)->count();
        $this->line("COLUMNS OF {$table} ({$rows} rows):");

        foreach ($db->select("SHOW COLUMNS FROM `{$table}`") as $col) {
            $values = '';
            $distinct = $db->table($table)->distinct()->count($col->Field);
            // แสดงเฉพาะคอลัมน์ที่ค่าซ้ำกันเยอะ (เช่น สถานะ) ไม่ใช่ข้อมูลรายคน
            if ($distinct <= 20 && $distinct * 5 <= $rows) {
                $values = '  values: '.$db->table($table)->distinct()->orderBy($col->Field)->pluck($col->Field)->implode(' / ');
            }
            $this->line("  {$col->Field} | {$col->Type}{$values}");
        }

        return self::SUCCESS;
    }
}
