import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState } from 'react';
import { Alert, Button, Modal, useJson } from './ui';

/**
 * รายชื่อสมาชิกชุมนุม พร้อมปุ่มถอนรายชื่อ
 * baseUrl คือ /teacher/clubs หรือ /admin/clubs
 */
export default function MembersModal({ club, baseUrl, canRemove, onClose }) {
    // เปลี่ยน version เพื่อโหลดรายชื่อใหม่หลังถอนสมาชิก
    const [version, setVersion] = useState(0);
    const { data, loading, error } = useJson(club ? `${baseUrl}/${club.id}/members?v=${version}` : null);

    const remove = (member) => {
        if (!window.confirm(`ถอน ${member.name} ออกจากชุมนุมนี้ใช่หรือไม่`)) return;
        router.delete(`${baseUrl}/${club.id}/members/${member.student_id}`, {
            preserveScroll: true,
            onSuccess: () => setVersion((v) => v + 1),
        });
    };

    return (
        <Modal
            open={!!club}
            onClose={onClose}
            size="lg"
            title={club ? `สมาชิกชุมนุม ${club.club_name}` : ''}
            footer={
                club && (
                    <>
                        <Button as="a" variant="outline" href={`/print/clubs/${club.id}`} target="_blank" rel="noreferrer">
                            <Printer size={16} /> พิมพ์รายชื่อ
                        </Button>
                        <Button variant="outline" onClick={onClose}>
                            ปิด
                        </Button>
                    </>
                )
            }
        >
            {loading && <p className="text-muted">กำลังโหลดรายชื่อ…</p>}
            {error && <Alert tone="danger">{error}</Alert>}
            {data && data.length === 0 && <p className="text-muted">ยังไม่มีสมาชิก</p>}
            {data && data.length > 0 && (
                <table className="table-plain">
                    <thead>
                        <tr>
                            <th>ที่</th>
                            <th>เลขประจำตัว</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ชั้น</th>
                            <th>เลขที่</th>
                            {canRemove && <th />}
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((member, i) => (
                            <tr key={member.student_id}>
                                <td className="tabular-nums">{i + 1}</td>
                                <td className="tabular-nums">{member.student_id}</td>
                                <td>{member.name}</td>
                                <td className="whitespace-nowrap">
                                    {member.grade_level}/{member.class_room}
                                </td>
                                <td className="tabular-nums">{member.class_number ?? '—'}</td>
                                {canRemove && (
                                    <td className="text-right">
                                        <Button variant="danger" size="sm" onClick={() => remove(member)}>
                                            ถอนรายชื่อ
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </Modal>
    );
}
