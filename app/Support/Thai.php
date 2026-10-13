<?php

namespace App\Support;

class Thai
{
    public const GRADES = ['ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];

    /** กลุ่มสาระ สะกดตรงกับ ปพ.5 ทุกตัวอักษร */
    public const DEPARTMENTS = [
        'การงานอาชีพ',
        'กิจกรรมพัฒนาผู้เรียน',
        'คณิตศาสตร์',
        'ภาษาต่างประเทศ',
        'ภาษาต่างประเทศ 2',
        'ภาษาไทย',
        'วิทยาศาสตร์และเทคโนโลยี',
        'ศิลปะ',
        'สังคมศึกษา ศาสนา และวัฒนธรรม',
        'สุขศึกษาและพลศึกษา',
    ];

    /** ชื่อกลุ่มสาระแบบเดิมที่พบในระบบเก่าและไฟล์ CSV */
    private const DEPARTMENT_ALIASES = [
        'สังคมศึกษาฯ' => 'สังคมศึกษา ศาสนา และวัฒนธรรม',
        'สังคมศึกษา' => 'สังคมศึกษา ศาสนา และวัฒนธรรม',
        'พัฒนาผู้เรียน' => 'กิจกรรมพัฒนาผู้เรียน',
        'วิทยาศาสตร์' => 'วิทยาศาสตร์และเทคโนโลยี',
        'สุขศึกษาและพละศึกษา' => 'สุขศึกษาและพลศึกษา',
        'สุขศึกษา' => 'สุขศึกษาและพลศึกษา',
        'การงานอาชีพและเทคโนโลยี' => 'การงานอาชีพ',
    ];

    // เรียงคำที่ยาวกว่าไว้ก่อน เพื่อไม่ให้ "นาง" ตัด "นางสาว"
    private const PREFIXES = ['เด็กชาย', 'เด็กหญิง', 'นางสาว', 'นาย', 'นาง', 'ด.ช.', 'ด.ญ.', 'น.ส.'];

    /** คืนชื่อกลุ่มสาระตามรายการ ปพ.5 หรือ null ถ้าไม่รู้จัก */
    public static function department(?string $name): ?string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        $name = self::DEPARTMENT_ALIASES[$name] ?? $name;

        return in_array($name, self::DEPARTMENTS, true) ? $name : null;
    }

    /**
     * แยกคำนำหน้าออกจากชื่อ
     *
     * @return array{0: string, 1: string} [คำนำหน้า, ชื่อ]
     */
    public static function splitPrefix(string $firstname): array
    {
        $firstname = self::clean($firstname);

        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($firstname, $prefix) && $firstname !== $prefix) {
                return [$prefix, trim(mb_substr($firstname, mb_strlen($prefix)))];
            }
        }

        return ['', $firstname];
    }

    public static function gradeNumber(?string $grade): int
    {
        return preg_match('/[1-6]/', (string) $grade, $m) ? (int) $m[0] : 0;
    }

    /** แปลง "ม.1", "ม 1", "1" ให้เป็นรูปแบบ "ม.1" */
    public static function grade(?string $grade): ?string
    {
        $number = self::gradeNumber($grade);

        return $number ? "ม.{$number}" : null;
    }

    /** ตัดช่องว่าง แท็บ และอักขระมองไม่เห็นที่ติดมากับข้อมูล */
    public static function clean(?string $value): string
    {
        return trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\t\r\n]+/u', '', (string) $value));
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
