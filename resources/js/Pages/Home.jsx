import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Lock, Search } from 'lucide-react';
import { useState } from 'react';
import { Alert, Badge, Button, Capacity, Confirm, Field, Flash, Input, Pagination, Select, formatDateTime } from '../Components/ui';
import SiteLayout from '../Layouts/SiteLayout';

const ROLES = [
    { key: 'student', label: 'นักเรียน' },
    { key: 'teacher', label: 'ครู' },
    { key: 'admin', label: 'ผู้ดูแลระบบ' },
];

/** ฟอร์มเข้าสู่ระบบเดียวสำหรับผู้ใช้ทั้งสามประเภท */
function LoginCard() {
    const [role, setRole] = useState('student');
    const form = useForm({ student_id: '', id_card: '', teacher_id: '', username: '', password: '' });

    const switchRole = (next) => {
        setRole(next);
        form.clearErrors();
        form.setData('password', '');
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, role }));
        form.post('/login', { preserveScroll: true, onError: () => form.setData('password', '') });
    };

    const firstError = form.errors.login ?? Object.values(form.errors)[0];

    return (
        <section className="rounded-sm border border-line bg-white" aria-label="เข้าสู่ระบบ">
            <h2 className="border-b border-line px-5 py-3 text-lg font-semibold">เข้าสู่ระบบ</h2>

            <div role="tablist" aria-label="ประเภทผู้ใช้" className="grid grid-cols-3 border-b border-line">
                {ROLES.map(({ key, label }) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={role === key}
                        onClick={() => switchRole(key)}
                        className={
                            'border-b-2 px-2 py-2.5 font-heading text-[0.95rem] ' +
                            (role === key
                                ? 'border-navy-700 bg-navy-50 font-semibold text-navy-700'
                                : 'border-transparent text-muted hover:bg-paper')
                        }
                    >
                        {label}
                    </button>
                ))}
            </div>

            <form onSubmit={submit} className="space-y-4 p-5">
                {role === 'student' && (
                    <>
                        <Field label="เลขประจำตัวนักเรียน">
                            <Input
                                inputMode="numeric"
                                autoComplete="username"
                                value={form.data.student_id}
                                onChange={(e) => form.setData('student_id', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label="เลขบัตรประชาชน 13 หลัก">
                            <Input
                                type="password"
                                inputMode="numeric"
                                maxLength={13}
                                autoComplete="current-password"
                                value={form.data.id_card}
                                onChange={(e) => form.setData('id_card', e.target.value)}
                                required
                            />
                        </Field>
                    </>
                )}

                {role === 'teacher' && (
                    <>
                        <Field label="รหัสครู">
                            <Input
                                inputMode="numeric"
                                autoComplete="username"
                                value={form.data.teacher_id}
                                onChange={(e) => form.setData('teacher_id', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label="รหัสผ่าน" hint="เบอร์โทรศัพท์ 10 หลักที่ให้ไว้กับโรงเรียน">
                            <Input
                                type="password"
                                inputMode="numeric"
                                maxLength={10}
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required
                            />
                        </Field>
                    </>
                )}

                {role === 'admin' && (
                    <>
                        <Field label="ชื่อผู้ใช้">
                            <Input
                                autoComplete="username"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label="รหัสผ่าน">
                            <Input
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required
                            />
                        </Field>
                    </>
                )}

                {firstError && <Alert tone="danger">{firstError}</Alert>}

                <Button type="submit" className="w-full" disabled={form.processing}>
                    เข้าสู่ระบบ
                </Button>
            </form>
        </section>
    );
}

/** สถานะของผู้ที่ล็อกอินแล้ว แสดงแทนฟอร์มเข้าสู่ระบบ */
function AccountCard({ auth, student }) {
    return (
        <section className="rounded-sm border border-line bg-white">
            <h2 className="border-b border-line px-5 py-3 text-lg font-semibold">
                {auth.role === 'student' ? 'ชุมนุมของฉัน' : 'เข้าสู่ระบบแล้ว'}
            </h2>
            <div className="space-y-3 p-5">
                <div>
                    <div className="font-medium">{auth.name}</div>
                    {student && (
                        <div className="text-sm text-muted">
                            เลขประจำตัว {student.student_id} · ชั้น {student.class}
                        </div>
                    )}
                </div>

                {student?.club && (
                    <div className="border-l-2 border-gold pl-4">
                        <div className="text-sm text-muted">เลือกชุมนุมแล้ว</div>
                        <div className="font-heading text-lg font-semibold">{student.club.club_name}</div>
                        <div className="text-sm text-muted">
                            {student.club.teacher ?? 'ยังไม่ระบุครูที่ปรึกษา'} · {student.club.location || 'ยังไม่ระบุสถานที่'}
                        </div>
                    </div>
                )}
                {student && !student.club && <Alert tone="warn">ยังไม่ได้เลือกชุมนุม เลือกได้จากรายการด้านล่าง</Alert>}

                {auth.role === 'teacher' && (
                    <Button as={Link} href="/teacher" className="w-full">
                        จัดการชุมนุมของฉัน
                    </Button>
                )}
                {auth.role === 'admin' && (
                    <Button as={Link} href="/admin/students" className="w-full">
                        ไปหน้าผู้ดูแลระบบ
                    </Button>
                )}
            </div>
        </section>
    );
}

function RegistrationStatus({ registration }) {
    const period = registration.start && (
        <>
            {formatDateTime(registration.start)} ถึง {formatDateTime(registration.end)}
        </>
    );

    if (registration.open) {
        return (
            <p>
                <Badge tone="ok">เปิดลงทะเบียน</Badge>
                {period && <span className="ml-2 text-muted">{period}</span>}
            </p>
        );
    }

    return (
        <p>
            <Badge tone="neutral">ปิดลงทะเบียน</Badge>
            {registration.state === 'pending' && <span className="ml-2 text-muted">เปิดลงทะเบียน {period}</span>}
            {registration.state === 'expired' && <span className="ml-2 text-muted">สิ้นสุดเมื่อ {formatDateTime(registration.end)}</span>}
        </p>
    );
}

function clubStatus(club) {
    if (club.is_locked) return { tone: 'neutral', label: 'ล็อก', selectable: false };
    if (club.members >= club.max_members) return { tone: 'danger', label: 'เต็ม', selectable: false };
    return { tone: 'ok', label: `ว่าง ${club.max_members - club.members}`, selectable: true };
}

export default function Home({ clubs, filters, registration, student }) {
    const { auth, school } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [choice, setChoice] = useState(null);
    const [enrolling, setEnrolling] = useState(false);

    const canSelect = !!student && !student.club && registration.open;

    const applyFilters = (next) =>
        router.get('/', { search, per_page: filters.per_page, ...next }, { preserveState: true, preserveScroll: true, replace: true });

    const enroll = () =>
        router.post(
            '/enroll',
            { club_id: choice.id },
            { preserveScroll: true, onStart: () => setEnrolling(true), onFinish: () => (setEnrolling(false), setChoice(null)) },
        );

    return (
        <SiteLayout flash={false}>
            {/* Hero: แนะนำระบบ + เข้าสู่ระบบของผู้ใช้ทั้งสามประเภท */}
            <section className="grid gap-8 border-b border-line pb-8 lg:grid-cols-[1fr_22rem] lg:items-start">
                <div className="space-y-4 lg:pt-4">
                    <p className="font-heading text-sm font-medium tracking-wide text-gold">
                        กิจกรรมพัฒนาผู้เรียน · ภาคเรียนที่ {school.semester} ปีการศึกษา {school.academic_year}
                    </p>
                    <h1 className="text-3xl leading-snug font-semibold text-navy-800 sm:text-4xl">
                        ระบบเลือกกิจกรรมชุมนุม
                        <span className="block text-2xl font-medium text-ink sm:text-3xl">{school.name}</span>
                    </h1>
                    <p className="max-w-prose text-muted">
                        นักเรียนเลือกชุมนุมได้คนละหนึ่งชุมนุมต่อปีการศึกษา ครูที่ปรึกษาจัดการข้อมูลและรายชื่อสมาชิกชุมนุมของตนเอง
                        เข้าสู่ระบบได้จากแบบฟอร์มเดียวกันโดยเลือกประเภทผู้ใช้
                    </p>
                    <RegistrationStatus registration={registration} />
                </div>

                {auth ? <AccountCard auth={auth} student={student} /> : <LoginCard />}
            </section>

            <Flash />

            <section className="space-y-4" aria-labelledby="club-list">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="club-list" className="text-xl font-semibold">
                            รายชื่อชุมนุม
                        </h2>
                        <p className="text-sm text-muted">
                            {student ? `ชุมนุมที่เปิดรับชั้น ${student.class.split('/')[0]} ` : 'ทั้งหมด '}
                            {clubs.total} ชุมนุม
                        </p>
                    </div>
                    <form
                        className="flex flex-wrap gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilters({ search });
                        }}
                    >
                        <Input
                            type="search"
                            placeholder="ค้นหาชุมนุม ครู หรือสถานที่"
                            aria-label="ค้นหาชุมนุม"
                            className="w-64"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button type="submit" variant="outline">
                            <Search size={16} /> ค้นหา
                        </Button>
                        <Select
                            aria-label="จำนวนต่อหน้า"
                            className="w-auto"
                            value={filters.per_page}
                            onChange={(e) => applyFilters({ per_page: e.target.value })}
                        >
                            <option value={30}>30 ต่อหน้า</option>
                            <option value={100}>100 ต่อหน้า</option>
                            <option value={0}>ทั้งหมด</option>
                        </Select>
                    </form>
                </div>

                <div className="overflow-x-auto rounded-sm border border-line bg-white">
                    <table className="table-plain">
                        <thead>
                            <tr>
                                <th>ชุมนุม</th>
                                <th>ครูที่ปรึกษา</th>
                                <th>สถานที่</th>
                                <th>ระดับชั้น</th>
                                <th>จำนวน</th>
                                <th>สถานะ</th>
                                {canSelect && <th />}
                            </tr>
                        </thead>
                        <tbody>
                            {clubs.data.map((club) => {
                                const status = clubStatus(club);
                                const mine = student?.club?.id === club.id;
                                return (
                                    <tr key={club.id} className={mine ? 'bg-warn-soft/60' : undefined}>
                                        <td className="max-w-md">
                                            <div className="font-heading font-semibold">
                                                {club.club_name} {mine && <Badge tone="navy">ชุมนุมของฉัน</Badge>}
                                            </div>
                                            {club.description && <div className="text-sm text-muted">{club.description}</div>}
                                        </td>
                                        <td className="whitespace-nowrap">{club.teacher ?? '—'}</td>
                                        <td>{club.location || '—'}</td>
                                        <td className="whitespace-nowrap">
                                            {club.grades.length === 6 ? 'ทุกระดับชั้น' : club.grades.map((g) => `ม.${g}`).join(', ') || '—'}
                                        </td>
                                        <td>
                                            <Capacity members={club.members} max={club.max_members} />
                                        </td>
                                        <td>
                                            <Badge tone={status.tone}>
                                                {club.is_locked && <Lock size={11} className="mr-1 inline" />}
                                                {status.label}
                                            </Badge>
                                        </td>
                                        {canSelect && (
                                            <td className="text-right">
                                                <Button size="sm" disabled={!status.selectable} onClick={() => setChoice(club)}>
                                                    เลือก
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                            {clubs.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="py-8 text-center text-muted">
                                        ไม่พบชุมนุมที่ตรงกับคำค้นหา
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={clubs} />
            </section>

            <Confirm
                open={!!choice}
                onClose={() => setChoice(null)}
                onConfirm={enroll}
                busy={enrolling}
                title="ยืนยันการเลือกชุมนุม"
                confirmLabel="ยืนยันการเลือก"
            >
                {choice && (
                    <div className="space-y-2">
                        <p>
                            ต้องการเลือกชุมนุม <strong>{choice.club_name}</strong> ใช่หรือไม่
                        </p>
                        <p className="text-sm text-muted">
                            ครูที่ปรึกษา {choice.teacher ?? '—'} · สถานที่ {choice.location || '—'}
                        </p>
                        <Alert tone="warn">เลือกแล้วจะเปลี่ยนเองไม่ได้ หากต้องการเปลี่ยนต้องติดต่อครูที่ปรึกษาหรือผู้ดูแลระบบ</Alert>
                    </div>
                )}
            </Confirm>
        </SiteLayout>
    );
}
