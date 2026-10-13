import { router, useForm } from '@inertiajs/react';
import { Lock, LockOpen, Plus, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import ClubForm, { clubFormData } from '../../Components/ClubForm';
import MembersModal from '../../Components/MembersModal';
import { Badge, Button, Capacity, Confirm, Input, Modal, gradeLabel } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

function ClubModal({ club, teachers, open, onClose }) {
    const form = useForm(clubFormData(club));
    const isNew = !club;

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        isNew ? form.post('/admin/clubs', options) : form.put(`/admin/clubs/${club.id}`, options);
    };

    return (
        <Modal open={open} onClose={onClose} title={isNew ? 'เพิ่มชุมนุม' : `แก้ไขชุมนุม ${club.club_name}`}>
            <form onSubmit={submit} className="space-y-5">
                <ClubForm form={form} teachers={teachers} showLock={!isNew} />
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

export default function Clubs({ clubs, teachers }) {
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState(null); // ชุมนุมที่กำลังแก้ไข หรือ 'new'
    const [members, setMembers] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const visible = useMemo(() => {
        const keyword = search.trim().toLowerCase();
        return keyword
            ? clubs.filter((c) => `${c.club_name} ${c.teacher ?? ''} ${c.location ?? ''}`.toLowerCase().includes(keyword))
            : clubs;
    }, [clubs, search]);

    return (
        <SiteLayout title="จัดการชุมนุม">
            <PageTitle
                title="ชุมนุม"
                description={`${clubs.length} ชุมนุมในปีการศึกษานี้`}
                actions={
                    <>
                        <Button as="a" variant="outline" href="/print/clubs" target="_blank" rel="noreferrer">
                            <Printer size={16} /> พิมพ์รายชื่อทั้งหมด
                        </Button>
                        <Button onClick={() => setEditing('new')}>
                            <Plus size={16} /> เพิ่มชุมนุม
                        </Button>
                    </>
                }
            />

            <Input
                type="search"
                className="w-80 max-w-full"
                placeholder="ค้นหาชุมนุม ครู หรือสถานที่"
                aria-label="ค้นหาชุมนุม"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
            />

            <div className="overflow-x-auto rounded-sm border border-line bg-white">
                <table className="table-plain">
                    <thead>
                        <tr>
                            <th>ชุมนุม</th>
                            <th>ครูที่ปรึกษา</th>
                            <th>สถานที่</th>
                            <th>ระดับชั้น</th>
                            <th>สมาชิก</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {visible.map((club) => (
                            <tr key={club.id}>
                                <td className="max-w-xs">
                                    <span className="font-heading font-semibold">{club.club_name}</span>{' '}
                                    {club.is_locked && <Badge>ล็อก</Badge>}
                                </td>
                                <td className="whitespace-nowrap">{club.teacher ?? <Badge tone="warn">ยังไม่ระบุ</Badge>}</td>
                                <td>{club.location || '—'}</td>
                                <td className="whitespace-nowrap">{gradeLabel(club)}</td>
                                <td>
                                    <Capacity members={club.members} max={club.max_members} />
                                </td>
                                <td className="text-right whitespace-nowrap">
                                    <Button variant="outline" size="sm" onClick={() => setMembers(club)}>
                                        สมาชิก
                                    </Button>{' '}
                                    <Button variant="outline" size="sm" onClick={() => setEditing(club)}>
                                        แก้ไข
                                    </Button>{' '}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        aria-label={club.is_locked ? 'ปลดล็อกชุมนุม' : 'ล็อกชุมนุม'}
                                        title={club.is_locked ? 'ปลดล็อกชุมนุม' : 'ล็อกชุมนุม'}
                                        onClick={() => router.post(`/admin/clubs/${club.id}/lock`, {}, { preserveScroll: true })}
                                    >
                                        {club.is_locked ? <LockOpen size={14} /> : <Lock size={14} />}
                                    </Button>{' '}
                                    <Button variant="danger" size="sm" onClick={() => setDeleting(club)}>
                                        ลบ
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {visible.length === 0 && (
                            <tr>
                                <td colSpan={6} className="py-8 text-center text-muted">
                                    ไม่พบชุมนุม
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <ClubModal
                key={editing === 'new' ? 'new' : (editing?.id ?? 'closed')}
                open={!!editing}
                club={editing === 'new' ? null : editing}
                teachers={teachers}
                onClose={() => setEditing(null)}
            />
            <MembersModal club={members} baseUrl="/admin/clubs" canRemove onClose={() => setMembers(null)} />

            <Confirm
                open={!!deleting}
                onClose={() => setDeleting(null)}
                danger
                title="ลบชุมนุม"
                confirmLabel="ลบชุมนุม"
                onConfirm={() => router.delete(`/admin/clubs/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            >
                {deleting && (
                    <p>
                        ลบชุมนุม <strong>{deleting.club_name}</strong> ใช่หรือไม่
                        {deleting.members > 0 && ` สมาชิก ${deleting.members} คนจะกลับเป็นสถานะยังไม่เลือกชุมนุม`}
                    </p>
                )}
            </Confirm>
        </SiteLayout>
    );
}
