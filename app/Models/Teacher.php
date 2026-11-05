<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Teacher extends Authenticatable
{
    protected $primaryKey = 'teacher_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['teacher_id', 'firstname', 'lastname', 'phone', 'id_card_last6', 'department'];

    // รหัสผ่านของครู ไม่ส่งออกไปหน้าเว็บ ยกเว้นหน้าจัดการครูของแอดมิน
    protected $hidden = ['id_card_last6'];

    protected $appends = ['full_name'];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class, 'teacher_id', 'teacher_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }
}
