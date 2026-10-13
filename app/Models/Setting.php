<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const DEFAULTS = [
        'registration_open' => 'false',
        'academic_year' => '2569',
        'semester' => '1',
        'school_name' => 'โรงเรียนแก่นนครวิทยาลัย',
        'teacher_edit_start' => '',
        'teacher_edit_end' => '',
        'registration_start' => '',
        'registration_end' => '',
    ];

    protected $table = 'system_settings';

    protected $fillable = ['setting_name', 'setting_value'];

    private static ?array $cache = null;

    public static function value(string $name): string
    {
        self::$cache ??= static::pluck('setting_value', 'setting_name')->all();

        return (string) (self::$cache[$name] ?? self::DEFAULTS[$name] ?? '');
    }

    public static function put(string $name, ?string $value): void
    {
        static::updateOrCreate(['setting_name' => $name], ['setting_value' => (string) $value]);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function academicYear(): int
    {
        return (int) self::value('academic_year');
    }

    /**
     * สถานะของช่วงเวลา: none (ไม่ได้ตั้ง), pending, active, expired
     *
     * @return array{start: ?string, end: ?string, state: string}
     */
    public static function period(string $prefix): array
    {
        $start = self::value("{$prefix}_start");
        $end = self::value("{$prefix}_end");

        if ($start === '' || $end === '') {
            return ['start' => null, 'end' => null, 'state' => 'none'];
        }

        $now = CarbonImmutable::now();
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($end);

        return [
            'start' => $from->format('Y-m-d\TH:i'),
            'end' => $to->format('Y-m-d\TH:i'),
            'state' => $now->lt($from) ? 'pending' : ($now->gt($to) ? 'expired' : 'active'),
        ];
    }

    /** ถ้าตั้งช่วงเวลาไว้ให้ยึดช่วงเวลา ถ้าไม่ได้ตั้งให้ยึดสวิตช์เปิด-ปิดด้วยมือ */
    public static function registrationOpen(): bool
    {
        $state = self::period('registration')['state'];

        return $state === 'none' ? self::value('registration_open') === 'true' : $state === 'active';
    }

    /** ครูแก้ไขได้ตลอดถ้าไม่ได้ตั้งช่วงเวลา */
    public static function teacherPeriodActive(): bool
    {
        return in_array(self::period('teacher_edit')['state'], ['none', 'active'], true);
    }
}
