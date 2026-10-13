<?php

namespace App\Models;

use App\Support\Thai;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Student extends Authenticatable
{
    protected $primaryKey = 'student_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'student_id', 'id_card', 'prefix', 'firstname', 'lastname',
        'grade_level', 'class_room', 'class_number',
    ];

    protected $hidden = ['id_card'];

    protected $appends = ['full_name'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id', 'student_id');
    }

    /** การเลือกชุมนุมของปีการศึกษาปัจจุบัน */
    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'student_id', 'student_id')
            ->where('enrollments.academic_year', Setting::academicYear());
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->prefix}{$this->firstname} {$this->lastname}");
    }

    public function gradeNumber(): int
    {
        return Thai::gradeNumber($this->grade_level);
    }
}
