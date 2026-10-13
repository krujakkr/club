import { Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '../../Components/ui';

/** ใบรายชื่อสมาชิกชุมนุม ขนาด A4 หนึ่งชุมนุมขึ้นหน้าใหม่ */
export default function ClubMembers({ clubs }) {
    const { school } = usePage().props;

    return (
        <div className="mx-auto max-w-[210mm] bg-white p-6 print:p-0">
            <Head title="พิมพ์รายชื่อสมาชิกชุมนุม" />

            <div className="no-print mb-6 flex items-center justify-between border-b border-line pb-4">
                <span className="text-muted">{clubs.length} ชุมนุม</span>
                <Button onClick={() => window.print()}>
                    <Printer size={16} /> พิมพ์
                </Button>
            </div>

            {clubs.map((club) => (
                <section key={club.id} className="mb-10 break-after-page last:break-after-auto">
                    <header className="mb-4 text-center">
                        <h1 className="text-xl font-semibold">รายชื่อสมาชิกชุมนุม {club.club_name}</h1>
                        <p>
                            {school.name} · ภาคเรียนที่ {school.semester} ปีการศึกษา {club.academic_year}
                        </p>
                        <p className="text-sm">
                            ครูที่ปรึกษา {club.teacher ?? '—'} · สถานที่ {club.location || '—'} · สมาชิก {club.members.length}/{club.max_members} คน
                        </p>
                    </header>

                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                {['ที่', 'เลขประจำตัว', 'ชื่อ-นามสกุล', 'ชั้น', 'เลขที่', 'หมายเหตุ'].map((heading) => (
                                    <th key={heading} className="border border-ink px-2 py-1">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {club.members.map((member, i) => (
                                <tr key={member.student_id}>
                                    <td className="border border-ink px-2 py-1 text-center">{i + 1}</td>
                                    <td className="border border-ink px-2 py-1 text-center">{member.student_id}</td>
                                    <td className="border border-ink px-2 py-1">{member.name}</td>
                                    <td className="border border-ink px-2 py-1 text-center">
                                        {member.grade_level}/{member.class_room}
                                    </td>
                                    <td className="border border-ink px-2 py-1 text-center">{member.class_number ?? ''}</td>
                                    <td className="w-32 border border-ink px-2 py-1" />
                                </tr>
                            ))}
                            {club.members.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="border border-ink px-2 py-4 text-center">
                                        ยังไม่มีสมาชิก
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            ))}
        </div>
    );
}
