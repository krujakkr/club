import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Button, Flash } from '../Components/ui';

const ROLE_LABELS = { student: 'นักเรียน', teacher: 'ครู', admin: 'ผู้ดูแลระบบ' };

const ADMIN_MENU = [
    ['/admin/students', 'นักเรียน'],
    ['/admin/clubs', 'ชุมนุม'],
    ['/admin/teachers', 'ครู'],
    ['/admin/import', 'นำเข้าข้อมูล'],
    ['/admin/reports', 'รายงาน'],
    ['/admin/settings', 'ตั้งค่าระบบ'],
];

export default function SiteLayout({ title, children, flash = true }) {
    const { auth, school } = usePage().props;
    const { url } = usePage();

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={title} />

            <header className="no-print border-b border-line bg-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
                    <Link href="/" className="block">
                        <span className="block font-heading text-lg leading-tight font-semibold text-navy-700">ระบบเลือกกิจกรรมชุมนุม</span>
                        <span className="block text-sm text-muted">
                            {school.name} · ภาคเรียนที่ {school.semester} ปีการศึกษา {school.academic_year}
                        </span>
                    </Link>

                    {auth && (
                        <div className="flex items-center gap-3 text-sm">
                            <span className="text-right leading-tight">
                                <span className="block font-medium">{auth.name}</span>
                                <span className="block text-muted">{ROLE_LABELS[auth.role]}</span>
                            </span>
                            <Button variant="outline" size="sm" onClick={() => router.post('/logout')}>
                                <LogOut size={14} /> ออกจากระบบ
                            </Button>
                        </div>
                    )}
                </div>

                {auth?.role === 'admin' && (
                    <nav className="border-t border-line" aria-label="เมนูผู้ดูแลระบบ">
                        <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4">
                            {ADMIN_MENU.map(([href, label]) => (
                                <Link
                                    key={href}
                                    href={href}
                                    className={
                                        'border-b-2 px-3 py-2 font-heading text-[0.95rem] whitespace-nowrap ' +
                                        (url.startsWith(href)
                                            ? 'border-navy-700 font-semibold text-navy-700'
                                            : 'border-transparent text-muted hover:text-ink')
                                    }
                                >
                                    {label}
                                </Link>
                            ))}
                        </div>
                    </nav>
                )}
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 space-y-6 px-4 py-6">
                {flash && <Flash />}
                {children}
            </main>

            <footer className="no-print border-t border-line bg-white">
                <div className="mx-auto max-w-6xl px-4 py-4 text-sm text-muted">
                    {school.name} · ระบบเลือกกิจกรรมชุมนุมออนไลน์
                </div>
            </footer>
        </div>
    );
}

export function PageTitle({ title, description, actions }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-3">
            <div>
                <h1 className="text-2xl font-semibold">{title}</h1>
                {description && <p className="text-muted">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
