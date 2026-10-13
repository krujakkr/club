import { useForm } from '@inertiajs/react';
import { Lock, Plus } from 'lucide-react';
import { useState } from 'react';
import ClubForm, { clubFormData } from '../../Components/ClubForm';
import MembersModal from '../../Components/MembersModal';
import { Alert, Badge, Button, Capacity, Field, GRADES, Modal, Select, formatDateTime, gradeLabel, useJson } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

function ClubModal({ club, open, onClose }) {
    const form = useForm(clubFormData(club));
    const isNew = !club;

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        isNew ? form.post('/teacher/clubs', options) : form.put(`/teacher/clubs/${club.id}`, options);
    };

    return (
        <Modal open={open} onClose={onClose} title={isNew ? 'สร้างชุมนุมใหม่' : `แก้ไขชุมนุม ${club.club_name}`}>
            <form onSubmit={submit} className="space-y-5">
                <ClubForm form={form} showLock={!isNew} />
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

/** เลือกนักเรียนจากระดับชั้น/ห้อง เพื่อเพิ่มเข้าชุมนุม */
function AddStudentsModal({ club, rooms, onClose }) {
    const [grade, setGrade] = useState('');
    const [room, setRoom] = useState('');
    const form = useForm({ student_ids: [] });

    const url = club && grade && room ? `/teacher/clubs/${club.id}/candidates?grade=${encodeURIComponent(grade)}&room=${room}` : null;
    const { data: students, loading, error } = useJson(url);

    const remaining = club ? club.max_members - club.enrollments_count : 0;
    const selected = form.data.student_ids;
    const toggle = (id) => form.setData('student_ids', selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);

    const close = () => {
        form.reset();
        setGrade('');
        setRoom('');
        onClose();
    };

    const submit = () => form.post(`/teacher/clubs/${club.id}/members`, { preserveScroll: true, onSuccess: close });

    return (
        <Modal
            open={!!club}
            onClose={close}
            size="lg"
            title={club ? `เพิ่มนักเรียนเข้าชุมนุม ${club.club_name}` : ''}
            footer={
                <>
                    <span className="mr-auto self-center text-sm text-muted">
                        เลือกแล้ว {selected.length} คน · รับได้อีก {remaining} คน
                    </span>
                    <Button variant="outline" onClick={close}>
                        ยกเลิก
                    </Button>
                    <Button onClick={submit} disabled={form.processing || selected.length === 0 || selected.length > remaining}>
                        เพิ่มเข้าชุมนุม
                    </Button>
                </>
            }
        >
            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                <Field label="ระดับชั้น">
                    <Select
                        value={grade}
                        onChange={(e) => {
                            setGrade(e.target.value);
                            setRoom('');
                            form.setData('student_ids', []);
                        }}
                    >
                        <option value="">— เลือกระดับชั้น —</option>
                        {GRADES.filter((g) => rooms[g]).map((g) => (
                            <option key={g}>{g}</option>
                        ))}
                    </Select>
                </Field>
                <Field label="ห้อง">
                    <Select
                        value={room}
                        disabled={!grade}
                        onChange={(e) => {
                            setRoom(e.target.value);
                            form.setData('student_ids', []);
                        }}
                    >
                        <option value="">— เลือกห้อง —</option>
                        {(rooms[grade] ?? []).map((r) => (
                            <option key={r}>{r}</option>
                        ))}
                    </Select>
                </Field>
            </div>

            {selected.length > remaining && <Alert tone="danger">เลือกเกินจำนวนที่รับได้</Alert>}
            {loading && <p className="text-muted">กำลังโหลดรายชื่อ…</p>}
            {error && <Alert tone="danger">{error}</Alert>}
            {!url && <p className="text-muted">เลือกระดับชั้นและห้องเพื่อแสดงรายชื่อนักเรียน</p>}
            {students && (
                <table className="table-plain">
                    <thead>
                        <tr>
                            <th />
                            <th>เลขที่</th>
                            <th>เลขประจำตัว</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.map((s) => (
                            <tr key={s.student_id}>
                                <td>
                                    <input
                                        type="checkbox"
                                        className="size-4 accent-navy-700"
                                        aria-label={`เลือก ${s.name}`}
                                        disabled={!!s.club}
                                        checked={selected.includes(s.student_id)}
                                        onChange={() => toggle(s.student_id)}
                                    />
                                </td>
                                <td className="tabular-nums">{s.class_number ?? '—'}</td>
                                <td className="tabular-nums">{s.student_id}</td>
                                <td>{s.name}</td>
                                <td>{s.club ? <Badge>{s.club}</Badge> : <Badge tone="ok">ยังไม่เลือก</Badge>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}

export default function Dashboard({ teacher, clubs, period, rooms }) {
    const [editing, setEditing] = useState(null); // ชุมนุมที่กำลังแก้ไข หรือ 'new'
    const [members, setMembers] = useState(null);
    const [adding, setAdding] = useState(null);

    const canEdit = period.active;
    // ใช้ข้อมูลล่าสุดจาก props เสมอ เพราะจำนวนสมาชิกเปลี่ยนหลังเพิ่ม/ถอน
    const current = (club) => club && clubs.find((c) => c.id === club.id);

    return (
        <SiteLayout title="จัดการชุมนุม">
            <PageTitle
                title="ชุมนุมของฉัน"
                description={`${teacher.full_name} · รหัสครู ${teacher.teacher_id}${teacher.department ? ` · กลุ่มสาระ${teacher.department}` : ''}`}
                actions={
                    canEdit && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus size={16} /> สร้างชุมนุมใหม่
                        </Button>
                    )
                }
            />

            {period.state === 'none' ? (
                <Alert tone="ok">แก้ไขข้อมูลชุมนุมได้ (ผู้ดูแลระบบยังไม่ได้กำหนดช่วงเวลา)</Alert>
            ) : (
                <Alert tone={canEdit ? 'ok' : 'warn'}>
                    {canEdit ? 'อยู่ในช่วงเวลาที่แก้ไขข้อมูลชุมนุมได้' : 'ไม่อยู่ในช่วงเวลาที่อนุญาตให้แก้ไข ดูข้อมูลได้อย่างเดียว'} ·{' '}
                    {formatDateTime(period.start)} ถึง {formatDateTime(period.end)}
                </Alert>
            )}

            {clubs.length === 0 && <p className="py-8 text-center text-muted">ยังไม่มีชุมนุมในปีการศึกษานี้</p>}

            <div className="grid gap-4 md:grid-cols-2">
                {clubs.map((club) => (
                    <article key={club.id} className="flex flex-col rounded-sm border border-line bg-white">
                        <header className="border-b border-line px-5 py-3">
                            <h2 className="text-lg font-semibold">
                                {club.club_name}{' '}
                                {club.is_locked && (
                                    <Badge>
                                        <Lock size={11} className="mr-1 inline" />
                                        ล็อก
                                    </Badge>
                                )}
                            </h2>
                        </header>
                        <div className="flex-1 space-y-3 p-5">
                            {club.description && <p className="text-muted">{club.description}</p>}
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-[0.95rem]">
                                <dt className="text-muted">สถานที่</dt>
                                <dd>{club.location || '—'}</dd>
                                <dt className="text-muted">ระดับชั้นที่รับ</dt>
                                <dd>{gradeLabel(club)}</dd>
                                <dt className="text-muted">สมาชิก</dt>
                                <dd>
                                    <Capacity members={club.enrollments_count} max={club.max_members} />
                                </dd>
                            </dl>
                        </div>
                        <footer className="flex flex-wrap gap-2 border-t border-line px-5 py-3">
                            <Button variant="outline" size="sm" onClick={() => setMembers(club)}>
                                รายชื่อสมาชิก
                            </Button>
                            {canEdit && (
                                <>
                                    <Button variant="outline" size="sm" onClick={() => setAdding(club)}>
                                        เพิ่มนักเรียน
                                    </Button>
                                    <Button variant="outline" size="sm" onClick={() => setEditing(club)}>
                                        แก้ไขข้อมูล
                                    </Button>
                                </>
                            )}
                        </footer>
                    </article>
                ))}
            </div>

            {/* key ทำให้ฟอร์มเริ่มใหม่ทุกครั้งที่เปลี่ยนชุมนุม */}
            <ClubModal
                key={editing === 'new' ? 'new' : (editing?.id ?? 'closed')}
                open={!!editing}
                club={editing === 'new' ? null : editing}
                onClose={() => setEditing(null)}
            />
            <MembersModal club={current(members)} baseUrl="/teacher/clubs" canRemove={canEdit} onClose={() => setMembers(null)} />
            <AddStudentsModal club={current(adding)} rooms={rooms} onClose={() => setAdding(null)} />
        </SiteLayout>
    );
}
