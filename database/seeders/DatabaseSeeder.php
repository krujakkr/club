<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * ค่าเริ่มต้นสำหรับการติดตั้งใหม่ (ไม่ได้นำเข้าจากระบบเดิม)
     */
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $name => $value) {
            Setting::firstOrCreate(['setting_name' => $name], ['setting_value' => $value]);
        }

        if (! Admin::exists()) {
            $password = Str::password(12, symbols: false);
            Admin::create(['username' => 'admin', 'password' => $password]);
            $this->command?->warn("สร้างผู้ดูแลระบบ admin รหัสผ่าน: {$password} (กรุณาเปลี่ยนหลังเข้าสู่ระบบ)");
        }
    }
}
