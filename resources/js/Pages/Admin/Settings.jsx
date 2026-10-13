import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, Badge, Button, Card, Field, Input, Modal, Select, formatDateTime } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

const STATE_BADGES = {
    none: ['neutral', 'ไม่ได้กำหนดช่วงเวลา'],
    pending: ['warn', 'ยังไม่ถึงเวลา'],
    active: ['ok', 'อยู่ในช่วงเวลา'],
    expired: ['danger', 'สิ้นสุดแล้ว'],
};

function PeriodForm({ section, period, children }) {
    const form = useForm({ section, start: period.start ?? '', end: period.end ?? '' });
    const [tone, label] = STATE_BADGES[period.state];
    const options = { preserveScroll: true };

    const clear = () => {
        form.transform(() => ({ section, start: '', end: '' }));
        form.put('/admin/settings', { ...options, onSuccess: () => form.setData({ section, start: '', end: '' }) });
    };

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => data);
                form.put('/admin/settings', options);
            }}
        >
            <p>
                <Badge tone={tone}>{label}</Badge>
                {period.start && (
                    <span className="ml-2 text-sm text-muted">
                        {formatDateTime(period.start)} ถึง {formatDateTime(period.end)}
                    </span>
                )}
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="เริ่มต้น" error={form.errors.start}>
                    <Input type="datetime-local" value={form.data.start} onChange={(e) => form.setData('start', e.target.value)} required />
                </Field>
                <Field label="สิ้นสุด" error={form.errors.end}>
                    <Input type="datetime-local" value={form.data.end} onChange={(e) => form.setData('end', e.target.value)} required />
                </Field>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>
                    บันทึกช่วงเวลา
                </Button>
                {period.start && (
                    <Button variant="outline" onClick={clear} disabled={form.processing}>
                        ล้างช่วงเวลา
                    </Button>
                )}
            </div>
            {children}
        </form>
    );
}

function GeneralForm({ settings }) {
    const form = useForm({ section: 'general', ...settings });

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings', { preserveScroll: true });
            }}
        >
            <Field label="ชื่อโรงเรียน" error={form.errors.school_name}>
                <Input value={form.data.school_name} onChange={(e) => form.setData('school_name', e.target.value)} required />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="ปีการศึกษา"
                    error={form.errors.academic_year}
                    hint="เปลี่ยนปีแล้วระบบจะแสดงเฉพาะชุมนุมและการเลือกของปีนั้น ข้อมูลปีเก่ายังอยู่"
                >
                    <Input
                        type="number"
                        value={form.data.academic_year}
                        onChange={(e) => form.setData('academic_year', e.target.value)}
                        required
                    />
                </Field>
                <Field label="ภาคเรียน" error={form.errors.semester}>
                    <Select value={form.data.semester} onChange={(e) => form.setData('semester', e.target.value)}>
                        <option>1</option>
                        <option>2</option>
                    </Select>
                </Field>
            </div>
            <Button type="submit" disabled={form.processing}>
                บันทึก
            </Button>
        </form>
    );
}

function PasswordForm() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    const field = (name) => ({
        type: 'password',
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        required: true,
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/settings/password', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <Field label="รหัสผ่านปัจจุบัน" error={form.errors.current_password}>
                <Input autoComplete="current-password" {...field('current_password')} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="รหัสผ่านใหม่" error={form.errors.password} hint="อย่างน้อย 8 ตัวอักษร">
                    <Input autoComplete="new-password" {...field('password')} />
                </Field>
                <Field label="ยืนยันรหัสผ่านใหม่">
                    <Input autoComplete="new-password" {...field('password_confirmation')} />
                </Field>
            </div>
            <Button type="submit" disabled={form.processing}>
                เปลี่ยนรหัสผ่าน
            </Button>
        </form>
    );
}

function ClearDataModal({ action, onClose }) {
    const form = useForm({ type: action?.type ?? '', confirm: '' });

    return (
        <Modal open={!!action} onClose={onClose} title={action?.label ?? ''} size="sm">
            {action && (
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/admin/settings/clear', { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <Alert tone="danger">
                        {action.warning} การดำเนินการนี้ย้อนกลับไม่ได้ ควรส่งออกข้อมูลเก็บไว้ก่อน
                    </Alert>
                    <Field label="พิมพ์คำว่า ยืนยัน เพื่อดำเนินการ" error={form.errors.confirm}>
                        <Input value={form.data.confirm} onChange={(e) => form.setData('confirm', e.target.value)} required />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={onClose}>
                            ยกเลิก
                        </Button>
                        <Button type="submit" variant="danger" disabled={form.processing || form.data.confirm !== 'ยืนยัน'}>
                            {action.label}
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}

export default function Settings({ settings, registration, teacherPeriod, stats }) {
    const [clearing, setClearing] = useState(null);

    const clearActions = [
        {
            type: 'reset_selections',
            label: 'รีเซ็ตการเลือกชุมนุม',
            warning: `ยกเลิกการเลือกชุมนุมของนักเรียน ${stats.selected.toLocaleString()} คนในปีการศึกษา ${settings.academic_year}`,
        },
        {
            type: 'clear_clubs',
            label: 'ลบชุมนุมทั้งหมดของปีนี้',
            warning: `ลบชุมนุม ${stats.clubs} ชุมนุมของปีการศึกษา ${settings.academic_year} พร้อมการเลือกของนักเรียน`,
        },
        {
            type: 'clear_students',
            label: 'ลบนักเรียนทั้งหมด',
            warning: `ลบนักเรียน ${stats.students.toLocaleString()} คน พร้อมประวัติการเลือกชุมนุมทุกปีการศึกษา`,
        },
        {
            type: 'clear_teachers',
            label: 'ลบครูทั้งหมด',
            warning: `ลบครู ${stats.teachers} คน ชุมนุมจะยังอยู่แต่ไม่มีครูที่ปรึกษา`,
        },
    ];

    return (
        <SiteLayout title="ตั้งค่าระบบ">
            <PageTitle title="ตั้งค่าระบบ" />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="ช่วงเวลาลงทะเบียนของนักเรียน">
                    <PeriodForm section="registration" period={registration}>
                        {registration.state === 'none' && (
                            <div className="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                                <span>
                                    สถานะปัจจุบัน:{' '}
                                    <Badge tone={registration.open ? 'ok' : 'neutral'}>{registration.open ? 'เปิดลงทะเบียน' : 'ปิดลงทะเบียน'}</Badge>
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post('/admin/settings/registration', {}, { preserveScroll: true })}
                                >
                                    {registration.open ? 'ปิดลงทะเบียน' : 'เปิดลงทะเบียนทันที'}
                                </Button>
                            </div>
                        )}
                    </PeriodForm>
                </Card>

                <Card title="ช่วงเวลาที่ครูเข้าแก้ไขข้อมูลชุมนุม">
                    <PeriodForm section="teacher_edit" period={teacherPeriod}>
                        <p className="text-sm text-muted">ถ้าไม่กำหนดช่วงเวลา ครูเข้าสู่ระบบและแก้ไขได้ตลอด</p>
                    </PeriodForm>
                </Card>

                <Card title="ข้อมูลทั่วไป">
                    <GeneralForm settings={settings} />
                </Card>

                <Card title="รหัสผ่านผู้ดูแลระบบ">
                    <PasswordForm />
                </Card>
            </div>

            <Card title="ล้างข้อมูล">
                <p className="mb-4 text-muted">ใช้เมื่อเริ่มปีการศึกษาใหม่หรือต้องการนำเข้าข้อมูลใหม่ทั้งหมด</p>
                <div className="flex flex-wrap gap-2">
                    {clearActions.map((action) => (
                        <Button key={action.type} variant="danger" onClick={() => setClearing(action)}>
                            {action.label}
                        </Button>
                    ))}
                </div>
            </Card>

            <ClearDataModal key={clearing?.type ?? 'closed'} action={clearing} onClose={() => setClearing(null)} />
        </SiteLayout>
    );
}
