import { router, useForm } from '@inertiajs/react';
import { DatabaseZap, Download, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge, Button, Confirm, Field, Input, Modal, Select, Stat, formatDateTime } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

function TeacherModal({ teacher, departments, open, onClose }) {
    const isNew = !teacher;
    const form = useForm({
        teacher_id: teacher?.teacher_id ?? '',
        firstname: teacher?.firstname ?? '',
        lastname: teacher?.lastname ?? '',
        phone: teacher?.phone ?? '',
        id_card_last6: teacher?.id_card_last6 ?? '',
        department: teacher?.department ?? '',
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        isNew ? form.post('/admin/teachers', options) : form.put(`/admin/teachers/${teacher.teacher_id}`, options);
    };

    return (
        <Modal open={open} onClose={onClose} title={isNew ? 'เพิ่มครู' : `แก้ไขข้อมูล ${teacher.full_name}`}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="รหัสครู" error={errors.teacher_id} hint="ตัวเลข 3 หลัก ชุดเดียวกับ ปพ.5">
                        <Input inputMode="numeric" maxLength={10} value={data.teacher_id} onChange={(e) => setData('teacher_id', e.target.value)} required />
                    </Field>
                    <Field label="เลขท้ายบัตรประชาชน" error={errors.id_card_last6} hint="6 หลัก ใช้เป็นรหัสผ่านเข้าสู่ระบบของครู">
                        <Input
                            inputMode="numeric"
                            maxLength={6}
                            value={data.id_card_last6 ?? ''}
                            onChange={(e) => setData('id_card_last6', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="เบอร์โทรศัพท์" error={errors.phone} hint="10 หลัก">
                    <Input inputMode="numeric" maxLength={12} value={data.phone ?? ''} onChange={(e) => setData('phone', e.target.value)} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="ชื่อ (รวมคำนำหน้า)" error={errors.firstname}>
                        <Input value={data.firstname} onChange={(e) => setData('firstname', e.target.value)} required />
                    </Field>
                    <Field label="นามสกุล" error={errors.lastname}>
                        <Input value={data.lastname} onChange={(e) => setData('lastname', e.target.value)} required />
                    </Field>
                </div>
                <Field label="กลุ่มสาระการเรียนรู้" error={errors.department}>
                    <Select value={data.department ?? ''} onChange={(e) => setData('department', e.target.value)}>
                        <option value="">— ยังไม่ระบุ —</option>
                        {departments.map((d) => (
                            <option key={d}>{d}</option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onClose}>
                        ยกเลิก
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        บันทึก
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Teachers({ teachers, departments }) {
    const [search, setSearch] = useState('');
    const [department, setDepartment] = useState('');
    const [editing, setEditing] = useState(null); // ครูที่กำลังแก้ไข หรือ 'new'
    const [deleting, setDeleting] = useState(null);
    const [importing, setImporting] = useState(false);
    const [importBusy, setImportBusy] = useState(false);

    const runImport = () =>
        router.post('/admin/teachers/import', {}, {
            preserveScroll: true,
            onStart: () => setImportBusy(true),
            onFinish: () => {
                setImportBusy(false);
                setImporting(false);
            },
        });

    const visible = useMemo(() => {
        const keyword = search.trim().toLowerCase();
        return teachers.filter(
            (t) =>
                (!department || t.department === department) &&
                (!keyword || `${t.teacher_id} ${t.full_name}`.toLowerCase().includes(keyword)),
        );
    }, [teachers, search, department]);

    const advisers = teachers.filter((t) => t.clubs_count > 0);
    const updated = advisers.filter((t) => t.update_count > 0).length;

    return (
        <SiteLayout title="จัดการครู">
            <PageTitle
                title="ครู"
                description={`${teachers.length} คน`}
                actions={
                    <>
                        <Button as="a" variant="outline" href="/admin/export/teachers">
                            <Download size={16} /> ส่งออก CSV
                        </Button>
                        <Button variant="outline" onClick={() => setImporting(true)}>
                            <DatabaseZap size={16} /> นำเข้า
                        </Button>
                        <Button onClick={() => setEditing('new')}>
                            <Plus size={16} /> เพิ่มครู
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="ครูที่ปรึกษาชุมนุม" value={advisers.length} />
                <Stat label="แก้ไขข้อมูลชุมนุมแล้ว" value={updated} />
                <Stat label="ยังไม่ได้แก้ไข" value={advisers.length - updated} />
            </div>

            <div className="flex flex-wrap gap-2">
                <Input
                    type="search"
                    className="w-64"
                    placeholder="รหัสครูหรือชื่อ"
                    aria-label="ค้นหาครู"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Select className="w-auto" aria-label="กลุ่มสาระ" value={department} onChange={(e) => setDepartment(e.target.value)}>
                    <option value="">ทุกกลุ่มสาระ</option>
                    {departments.map((d) => (
                        <option key={d}>{d}</option>
                    ))}
                </Select>
            </div>

            <div className="overflow-x-auto rounded-sm border border-line bg-white">
                <table className="table-plain">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>กลุ่มสาระ</th>
                            <th>เลขท้ายบัตร</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>ชุมนุม</th>
                            <th>เข้าสู่ระบบล่าสุด</th>
                            <th>แก้ไขชุมนุมล่าสุด</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((t) => (
                            <tr key={t.teacher_id}>
                                <td className="tabular-nums">
                                    {t.teacher_id} {!/^\d{3}$/.test(t.teacher_id) && <Badge tone="warn">ไม่ใช่ 3 หลัก</Badge>}
                                </td>
                                <td className="whitespace-nowrap">{t.full_name}</td>
                                <td>{t.department ?? '—'}</td>
                                <td className="tabular-nums">{t.id_card_last6 ?? <Badge tone="warn">ไม่มี</Badge>}</td>
                                <td className="tabular-nums">{t.phone ?? '—'}</td>
                                <td className="tabular-nums">{t.clubs_count}</td>
                                <td className="text-sm whitespace-nowrap">{formatDateTime(t.last_login)}</td>
                                <td className="text-sm whitespace-nowrap">
                                    {formatDateTime(t.last_update)}
                                    {t.update_count > 0 && <span className="text-muted"> ({t.update_count} ครั้ง)</span>}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <Button variant="outline" size="sm" onClick={() => setEditing(t)}>
                                        แก้ไข
                                    </Button>{' '}
                                    <Button variant="danger" size="sm" onClick={() => setDeleting(t)}>
                                        ลบ
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <TeacherModal
                key={editing === 'new' ? 'new' : (editing?.teacher_id ?? 'closed')}
                open={!!editing}
                teacher={editing === 'new' ? null : editing}
                departments={departments}
                onClose={() => setEditing(null)}
            />

            <Confirm
                open={importing}
                onClose={() => setImporting(false)}
                title="นำเข้าครูจากฐานข้อมูลหลัก"
                confirmLabel="นำเข้า"
                busy={importBusy}
                onConfirm={runImport}
            >
                <p>ดึงรายชื่อครูรหัส 100–999 ที่มีสถานะ "ครู" จากฐานข้อมูล knwacth_tel พร้อมเลขท้ายบัตรประชาชน 6 หลัก</p>
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted">
                    <li>ครูที่ยังไม่มีในระบบจะถูกเพิ่มใหม่</li>
                    <li>ครูที่มีอยู่แล้วจะอัปเดตชื่อและเลขท้ายบัตร</li>
                    <li>ครูที่ไม่มีในฐานข้อมูลหลักจะไม่ถูกลบ</li>
                </ul>
            </Confirm>

            <Confirm
                open={!!deleting}
                onClose={() => setDeleting(null)}
                danger
                title="ลบข้อมูลครู"
                confirmLabel="ลบ"
                onConfirm={() =>
                    router.delete(`/admin/teachers/${deleting.teacher_id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })
                }
            >
                {deleting && (
                    <p>
                        ลบข้อมูล <strong>{deleting.full_name}</strong> (รหัส {deleting.teacher_id}) ใช่หรือไม่
                    </p>
                )}
            </Confirm>
        </SiteLayout>
    );
}
