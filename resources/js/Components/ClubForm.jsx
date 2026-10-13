import { Field, GradeChecks, Input, Select, Textarea } from './ui';

export const emptyClub = {
    club_name: '',
    description: '',
    location: '',
    max_members: 30,
    teacher_id: '',
    allow_m1: true,
    allow_m2: true,
    allow_m3: true,
    allow_m4: true,
    allow_m5: true,
    allow_m6: true,
    is_locked: false,
};

export function clubFormData(club) {
    return Object.fromEntries(Object.keys(emptyClub).map((key) => [key, club?.[key] ?? emptyClub[key]]));
}

/** ช่องกรอกข้อมูลชุมนุม ใช้ทั้งหน้าครูและหน้าผู้ดูแลระบบ (ส่ง teachers มาเมื่อให้เลือกครูที่ปรึกษาได้) */
export default function ClubForm({ form, teachers, showLock }) {
    const { data, setData, errors } = form;

    return (
        <div className="space-y-4">
            <Field label="ชื่อชุมนุม" error={errors.club_name} hint="ไม่เกิน 100 ตัวอักษร และไม่ซ้ำกันในปีการศึกษาเดียวกัน">
                <Input maxLength={100} value={data.club_name} onChange={(e) => setData('club_name', e.target.value)} required />
            </Field>
            <Field label="คำอธิบาย" error={errors.description}>
                <Textarea rows={3} value={data.description ?? ''} onChange={(e) => setData('description', e.target.value)} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="สถานที่" error={errors.location}>
                    <Input maxLength={100} value={data.location ?? ''} onChange={(e) => setData('location', e.target.value)} />
                </Field>
                <Field label="จำนวนที่รับ" error={errors.max_members}>
                    <Input
                        type="number"
                        min={1}
                        value={data.max_members}
                        onChange={(e) => setData('max_members', e.target.value)}
                        required
                    />
                </Field>
            </div>
            {teachers && (
                <Field label="ครูที่ปรึกษา" error={errors.teacher_id}>
                    <Select value={data.teacher_id ?? ''} onChange={(e) => setData('teacher_id', e.target.value)}>
                        <option value="">— ยังไม่ระบุ —</option>
                        {teachers.map((t) => (
                            <option key={t.teacher_id} value={t.teacher_id}>
                                {t.teacher_id} · {t.firstname} {t.lastname}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            <GradeChecks data={data} setData={setData} />
            {showLock && (
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        className="size-4 accent-navy-700"
                        checked={!!data.is_locked}
                        onChange={(e) => setData('is_locked', e.target.checked)}
                    />
                    ล็อกชุมนุม (นักเรียนเลือกเองไม่ได้)
                </label>
            )}
        </div>
    );
}
