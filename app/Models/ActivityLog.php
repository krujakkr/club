<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    protected $table = 'logs';

    public $timestamps = false;

    protected $fillable = ['user_id', 'user_type', 'action', 'details', 'ip_address', 'created_at'];

    /** บันทึกการทำงานของผู้ใช้ที่ล็อกอินอยู่ */
    public static function record(string $action, string $details = ''): void
    {
        foreach (['admin', 'teacher', 'student'] as $type) {
            if ($user = Auth::guard($type)->user()) {
                static::create([
                    'user_id' => $type === 'admin' ? $user->username : $user->getKey(),
                    'user_type' => $type,
                    'action' => $action,
                    'details' => $details,
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                return;
            }
        }
    }
}
