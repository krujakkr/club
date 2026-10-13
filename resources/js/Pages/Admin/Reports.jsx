import { Download } from 'lucide-react';
import { Badge, Button, Capacity, Card, Stat } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

const percent = (part, total) => (total > 0 ? `${((part / total) * 100).toFixed(1)}%` : '—');

function SelectionTable({ rows, label }) {
    return (
        <table className="table-plain">
            <thead>
                <tr>
                    <th>{label}</th>
                    <th className="text-right!">นักเรียน</th>
                    <th className="text-right!">เลือกแล้ว</th>
                    <th className="text-right!">ยังไม่เลือก</th>
                    <th className="text-right!">ร้อยละ</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.key}>
                        <td>{row.key}</td>
                        <td className="text-right tabular-nums">{row.total}</td>
                        <td className="text-right tabular-nums">{row.selected}</td>
                        <td className="text-right tabular-nums">{row.total - row.selected}</td>
                        <td className="text-right tabular-nums">{percent(row.selected, row.total)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function Reports({ summary, byGrade, byClass, clubs, teachers }) {
    return (
        <SiteLayout title="รายงาน">
            <PageTitle
                title="รายงาน"
                actions={
                    <>
                        <Button as="a" variant="outline" href="/admin/export/students">
                            <Download size={16} /> นักเรียน (CSV)
                        </Button>
                        <Button as="a" variant="outline" href="/admin/export/clubs">
                            <Download size={16} /> ชุมนุม (CSV)
                        </Button>
                    </>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label="เลือกชุมนุมแล้ว"
                    value={`${summary.selected.toLocaleString()} / ${summary.students.toLocaleString()}`}
                    note={percent(summary.selected, summary.students)}
                />
                <Stat label="ชุมนุม" value={summary.clubs} note={`เต็ม ${summary.full} · ล็อก ${summary.locked}`} />
                <Stat label="จำนวนรับรวม" value={summary.capacity.toLocaleString()} note="ที่นั่งทั้งหมด" />
                <Stat label="ครู" value={summary.teachers} />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="การเลือกชุมนุมตามระดับชั้น">
                    <SelectionTable label="ระดับชั้น" rows={byGrade.map((g) => ({ ...g, key: g.grade_level }))} />
                </Card>
                <Card title="การเลือกชุมนุมตามห้องเรียน">
                    <div className="max-h-80 overflow-y-auto">
                        <SelectionTable label="ห้อง" rows={byClass.map((c) => ({ ...c, key: `${c.grade_level}/${c.class_room}` }))} />
                    </div>
                </Card>
            </div>

            <Card title="ชุมนุมเรียงตามจำนวนสมาชิก">
                <div className="max-h-[32rem] overflow-auto">
                    <table className="table-plain">
                        <thead>
                            <tr>
                                <th>ชุมนุม</th>
                                <th>ครูที่ปรึกษา</th>
                                <th>สถานที่</th>
                                <th>สมาชิก</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clubs.map((club) => (
                                <tr key={club.id}>
                                    <td>{club.club_name}</td>
                                    <td className="whitespace-nowrap">{club.teacher ?? '—'}</td>
                                    <td>{club.location || '—'}</td>
                                    <td>
                                        <Capacity members={club.members} max={club.max_members} />
                                    </td>
                                    <td>
                                        {club.is_locked ? (
                                            <Badge>ล็อก</Badge>
                                        ) : club.members >= club.max_members ? (
                                            <Badge tone="danger">เต็ม</Badge>
                                        ) : (
                                            <Badge tone="ok">ว่าง</Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card title="ครูและชุมนุมที่ดูแล">
                <div className="max-h-[32rem] overflow-auto">
                    <table className="table-plain">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>กลุ่มสาระ</th>
                                <th>ชุมนุมที่ดูแล</th>
                                <th className="text-right!">นักเรียน</th>
                            </tr>
                        </thead>
                        <tbody>
                            {teachers.map((t) => (
                                <tr key={t.teacher_id}>
                                    <td className="tabular-nums">{t.teacher_id}</td>
                                    <td className="whitespace-nowrap">{t.name}</td>
                                    <td>{t.department ?? '—'}</td>
                                    <td>{t.clubs.join(', ') || <span className="text-muted">ไม่มี</span>}</td>
                                    <td className="text-right tabular-nums">{t.students}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </SiteLayout>
    );
}
