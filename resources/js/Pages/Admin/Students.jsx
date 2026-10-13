import { router, useForm } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge, Button, Confirm, Field, GRADES, Input, Modal, Pagination, Select, Stat } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

const PREFIXES = ['เด็กชาย', 'เด็กหญิง', 'นาย', 'นางสาว'];

function StudentModal({ student, clubs, open, onClose }) {
    const isNew = !student;
    const form = useForm({
        student_id: student?.student_id ?? '',
        id_card: student?.id_card ?? '',
        prefix: student?.prefix ?? '',
        firstname: student?.firstname ?? '',
        lastname: student?.lastname ?? '',
        grade_level: student?.grade_level ?? 'ม.1',
        class_room: student?.class_room ?? '',
        class_number: student?.class_number ?? '',
        club_id: student?.club?.id ?? '',
    });
    const { data, setData, errors } = form;
    const [clubSearch, setClubSearch] = useState('');

    const clubOptions = useMemo(() => {
        const keyword = clubSearch.trim().toLowerCase();
        return clubs.filter(
            (c) => c.id === Number(data.club_id) || !keyword || `${c.club_name} ${c.teacher ?? ''}`.toLowerCase().includes(keyword),
        );
    }, [clubs, clubSearch, data.club_id]);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (isNew) {
            form.transform(({ club_id, ...rest }) => rest);
            form.post('/admin/students', options);
        } else {
            form.transform(({ student_id, ...rest }) => rest);
            form.put(`/admin/students/${student.student_id}`, options);
        }
    };

    return (
        <Modal open={open} onClose={onClose} title={isNew ? 'เพิ่มนักเรียน' : `แก้ไขข้อมูล ${student.full_name}`}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="เลขประจำตัวนักเรียน" error={errors.student_id}>
                        <Input
                            inputMode="numeric"
                            value={data.student_id}
                            disabled={!isNew}
                            onChange={(e) => setData('student_id', e.target.value)}
                            required
                        />
                    </Field>
                    <Field label="เลขบัตรประชาชน" error={errors.id_card} hint="ตัวเลข 13 หลัก ไม่มีขีดหรือเว้นวรรค">
                        <Input inputMode="numeric" maxLength={13} value={data.id_card ?? ''} onChange={(e) => setData('id_card', e.target.value)} />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-[9rem_1fr_1fr]">
                    <Field label="คำนำหน้า" error={errors.prefix}>
                        <Select value={data.prefix} onChange={(e) => setData('prefix', e.target.value)}>
                            <option value="">—</option>
                            {[...new Set([...PREFIXES, data.prefix].filter(Boolean))].map((p) => (
                                <option key={p}>{p}</option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="ชื่อ" error={errors.firstname}>
                        <Input value={data.firstname} onChange={(e) => setData('firstname', e.target.value)} required />
                    </Field>
                    <Field label="นามสกุล" error={errors.lastname}>
                        <Input value={data.lastname} onChange={(e) => setData('lastname', e.target.value)} required />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="ระดับชั้น" error={errors.grade_level}>
                        <Select value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)}>
                            {GRADES.map((g) => (
                                <option key={g}>{g}</option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="ห้อง" error={errors.class_room}>
                        <Input type="number" min={1} value={data.class_room} onChange={(e) => setData('class_room', e.target.value)} required />
                    </Field>
                    <Field label="เลขที่" error={errors.class_number}>
                        <Input type="number" min={1} value={data.class_number ?? ''} onChange={(e) => setData('class_number', e.target.value)} />
                    </Field>
                </div>

                {!isNew && (
                    <Field label="ชุมนุม" error={errors.club_id} hint="พิมพ์เพื่อกรองรายชื่อชุมนุมหรือครูที่ปรึกษา">
                        <Input
                            type="search"
                            placeholder="ค้นหาชุมนุม"
                            className="mb-2"
                            value={clubSearch}
                            onChange={(e) => setClubSearch(e.target.value)}
                        />
                        <Select value={data.club_id ?? ''} onChange={(e) => setData('club_id', e.target.value)}>
                            <option value="">— ยังไม่เลือกชุมนุม —</option>
                            {clubOptions.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.club_name} ({c.members}/{c.max_members}){c.teacher ? ` · ${c.teacher}` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}

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

export default function Students({ students, filters, stats, clubs }) {
    const [query, setQuery] = useState(filters);
    const [editing, setEditing] = useState(null); // นักเรียนที่กำลังแก้ไข หรือ 'new'
    const [resetting, setResetting] = useState(null);

    const apply = (next) => {
        const merged = { ...query, ...next };
        setQuery(merged);
        router.get('/admin/students', merged, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <SiteLayout title="จัดการนักเรียน">
            <PageTitle
                title="นักเรียน"
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus size={16} /> เพิ่มนักเรียน
                    </Button>
                }
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="นักเรียนทั้งหมด" value={stats.total.toLocaleString()} />
                <Stat label="เลือกชุมนุมแล้ว" value={stats.selected.toLocaleString()} />
                <Stat label="ยังไม่เลือก" value={stats.none.toLocaleString()} />
            </div>

            <form
                className="flex flex-wrap gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply({});
                }}
            >
                <Input
                    type="search"
                    className="w-64"
                    placeholder="เลขประจำตัว ชื่อ หรือนามสกุล"
                    aria-label="ค้นหานักเรียน"
                    value={query.search}
                    onChange={(e) => setQuery({ ...query, search: e.target.value })}
                />
                <Select className="w-auto" aria-label="ระดับชั้น" value={query.grade} onChange={(e) => apply({ grade: e.target.value })}>
                    <option value="">ทุกระดับชั้น</option>
                    {GRADES.map((g) => (
                        <option key={g}>{g}</option>
                    ))}
                </Select>
                <Select className="w-auto" aria-label="สถานะ" value={query.status} onChange={(e) => apply({ status: e.target.value })}>
                    <option value="">ทุกสถานะ</option>
                    <option value="selected">เลือกแล้ว</option>
                    <option value="none">ยังไม่เลือก</option>
                </Select>
                <Button type="submit" variant="outline">
                    <Search size={16} /> ค้นหา
                </Button>
            </form>

            <div className="overflow-x-auto rounded-sm border border-line bg-white">
                <table className="table-plain">
                    <thead>
                        <tr>
                            <th>เลขประจำตัว</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ชั้น</th>
                            <th>เลขที่</th>
                            <th>ชุมนุม</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {students.data.map((s) => (
                            <tr key={s.student_id}>
                                <td className="tabular-nums">{s.student_id}</td>
                                <td>
                                    {s.full_name}
                                    {!s.id_card && (
                                        <span className="ml-2">
                                            <Badge tone="warn">ไม่มีเลขบัตร</Badge>
                                        </span>
                                    )}
                                </td>
                                <td className="whitespace-nowrap">
                                    {s.grade_level}/{s.class_room}
                                </td>
                                <td className="tabular-nums">{s.class_number ?? <Badge tone="warn">ไม่มีเลขที่</Badge>}</td>
                                <td>
                                    {s.club ? (
                                        <>
                                            {s.club.club_name}
                                            {s.club.teacher && <div className="text-sm text-muted">{s.club.teacher}</div>}
                                        </>
                                    ) : (
                                        <Badge>ยังไม่เลือก</Badge>
                                    )}
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <Button variant="outline" size="sm" onClick={() => setEditing(s)}>
                                        แก้ไข
                                    </Button>{' '}
                                    {s.club && (
                                        <Button variant="danger" size="sm" onClick={() => setResetting(s)}>
                                            ยกเลิกการเลือก
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {students.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-8 text-center text-muted">
                                    ไม่พบนักเรียนตามเงื่อนไข
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={students} />

            <StudentModal
                key={editing === 'new' ? 'new' : (editing?.student_id ?? 'closed')}
                open={!!editing}
                student={editing === 'new' ? null : editing}
                clubs={clubs}
                onClose={() => setEditing(null)}
            />

            <Confirm
                open={!!resetting}
                onClose={() => setResetting(null)}
                danger
                title="ยกเลิกการเลือกชุมนุม"
                confirmLabel="ยกเลิกการเลือก"
                onConfirm={() =>
                    router.delete(`/admin/students/${resetting.student_id}/enrollment`, {
                        preserveScroll: true,
                        onFinish: () => setResetting(null),
                    })
                }
            >
                {resetting && (
                    <p>
                        ยกเลิกการเลือกชุมนุม <strong>{resetting.club?.club_name}</strong> ของ {resetting.full_name} ใช่หรือไม่
                    </p>
                )}
            </Confirm>
        </SiteLayout>
    );
}
