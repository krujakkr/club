<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'academic_year', 'club_name', 'description', 'location', 'max_members', 'teacher_id',
        'allow_m1', 'allow_m2', 'allow_m3', 'allow_m4', 'allow_m5', 'allow_m6', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'allow_m1' => 'boolean', 'allow_m2' => 'boolean', 'allow_m3' => 'boolean',
            'allow_m4' => 'boolean', 'allow_m5' => 'boolean', 'allow_m6' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** ชุมนุมของปีการศึกษาปัจจุบัน */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('clubs.academic_year', Setting::academicYear());
    }

    public function allowsGrade(int $grade): bool
    {
        return $grade >= 1 && $grade <= 6 && (bool) $this->{"allow_m{$grade}"};
    }
}
