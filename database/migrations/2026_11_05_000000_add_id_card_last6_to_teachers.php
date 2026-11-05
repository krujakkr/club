<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // เลขท้ายบัตรประชาชน 6 หลัก ใช้เป็นรหัสผ่านของครู (เก็บแค่ 6 หลัก ไม่เก็บเลขเต็ม)
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->char('id_card_last6', 6)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('id_card_last6');
        });
    }
};
