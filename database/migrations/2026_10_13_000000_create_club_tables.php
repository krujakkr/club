<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->timestamps();
        });

        // รหัสครู 3 หลัก ชุดเดียวกับ ปพ.5 เป็นตัวอ้างอิงหลัก
        Schema::create('teachers', function (Blueprint $table) {
            $table->string('teacher_id', 10)->primary();
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->string('phone', 20)->nullable();
            $table->string('department', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->string('student_id', 10)->primary();
            $table->char('id_card', 13)->nullable()->unique();
            $table->string('prefix', 20)->default('');
            $table->string('firstname', 100);
            $table->string('lastname', 100);
            $table->enum('grade_level', ['ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6']);
            $table->unsignedSmallInteger('class_room');
            $table->unsignedSmallInteger('class_number')->nullable();
            $table->timestamps();

            // ปพ.5 ไม่ยอมให้นักเรียนสองคนใช้ห้องและเลขที่เดียวกัน
            $table->unique(['grade_level', 'class_room', 'class_number'], 'students_seat_unique');
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('academic_year');
            $table->string('club_name', 100);
            $table->text('description')->nullable();
            $table->string('location', 100)->nullable();
            $table->unsignedInteger('max_members');
            $table->string('teacher_id', 10)->nullable();
            foreach (range(1, 6) as $grade) {
                $table->boolean("allow_m{$grade}")->default(true);
            }
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['academic_year', 'club_name']);
            $table->foreign('teacher_id')->references('teacher_id')->on('teachers')
                ->cascadeOnUpdate()->nullOnDelete();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 10);
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedSmallInteger('academic_year');
            $table->timestamp('enrolled_at')->nullable();

            // หนึ่งคนหนึ่งชุมนุมต่อปีการศึกษา
            $table->unique(['student_id', 'academic_year']);
            $table->foreign('student_id')->references('student_id')->on('students')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->enum('user_type', ['student', 'teacher', 'admin']);
            $table->string('action');
            $table->text('details')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['user_type', 'user_id', 'action']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_name', 50)->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['system_settings', 'logs', 'enrollments', 'clubs', 'students', 'teachers', 'admins'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
