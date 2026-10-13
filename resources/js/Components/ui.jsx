import { Link, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

const cx = (...classes) => classes.filter(Boolean).join(' ');

const BUTTON_VARIANTS = {
    primary: 'bg-navy-700 text-white border-navy-700 hover:bg-navy-800',
    outline: 'bg-white text-navy-700 border-navy-700/40 hover:bg-navy-50',
    ghost: 'bg-transparent text-navy-700 border-transparent hover:bg-navy-50',
    danger: 'bg-white text-danger border-danger/40 hover:bg-danger-soft',
};

export function Button({ variant = 'primary', size = 'md', className, as: Tag = 'button', ...props }) {
    return (
        <Tag
            type={Tag === 'button' ? 'button' : undefined}
            className={cx(
                'inline-flex items-center justify-center gap-1.5 rounded-sm border font-medium whitespace-nowrap transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-50',
                size === 'sm' ? 'px-2.5 py-1 text-sm' : 'px-4 py-2',
                BUTTON_VARIANTS[variant],
                className,
            )}
            {...props}
        />
    );
}

const CONTROL =
    'rounded-sm border border-line bg-white px-3 py-2 text-ink placeholder:text-muted/60 focus:border-navy-600 focus:outline-none focus:ring-1 focus:ring-navy-600 disabled:bg-paper disabled:text-muted';

// กว้างเต็มช่องเป็นค่าเริ่มต้น เว้นแต่ผู้เรียกกำหนดความกว้างเอง
const control = (className) => cx(CONTROL, /(^|\s)(w|max-w)-/.test(className ?? '') ? null : 'w-full', className);

export function Input({ className, ...props }) {
    return <input className={control(className)} {...props} />;
}

export function Select({ className, children, ...props }) {
    return (
        <select className={control(className)} {...props}>
            {children}
        </select>
    );
}

export function Textarea({ className, ...props }) {
    return <textarea className={control(className)} {...props} />;
}

export function Field({ label, error, hint, className, children }) {
    return (
        <label className={cx('block min-w-0', className)}>
            <span className="mb-1 block text-sm font-medium">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-sm text-muted">{hint}</span>}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

const BADGE_TONES = {
    neutral: 'border-line bg-white text-muted',
    ok: 'border-ok/30 bg-ok-soft text-ok',
    warn: 'border-warn/30 bg-warn-soft text-warn',
    danger: 'border-danger/30 bg-danger-soft text-danger',
    navy: 'border-navy-600/30 bg-navy-50 text-navy-700',
};

export function Badge({ tone = 'neutral', children }) {
    return (
        <span className={cx('inline-block rounded-sm border px-2 py-0.5 text-xs font-medium whitespace-nowrap', BADGE_TONES[tone])}>
            {children}
        </span>
    );
}

export function Card({ title, actions, className, children }) {
    return (
        <section className={cx('rounded-sm border border-line bg-white', className)}>
            {title && (
                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    {actions}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export function Alert({ tone = 'navy', children }) {
    return <div className={cx('rounded-sm border px-4 py-3 text-[0.95rem]', BADGE_TONES[tone])}>{children}</div>;
}

/** ข้อความแจ้งผลจากเซิร์ฟเวอร์ (session flash) */
export function Flash() {
    const { flash } = usePage().props;

    return (
        <>
            {flash?.success && <Alert tone="ok">{flash.success}</Alert>}
            {flash?.error && <Alert tone="danger">{flash.error}</Alert>}
        </>
    );
}

export function Modal({ open, onClose, title, size = 'md', children, footer }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    const width = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl' }[size];

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 sm:items-center" onMouseDown={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className={cx('w-full rounded-sm border border-line bg-white shadow-lg', width)}
                onMouseDown={(e) => e.stopPropagation()}
            >
                <header className="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <button type="button" onClick={onClose} aria-label="ปิด" className="rounded-sm p-1 text-muted hover:bg-navy-50">
                        <X size={18} />
                    </button>
                </header>
                <div className="max-h-[70vh] overflow-y-auto p-5">{children}</div>
                {footer && <footer className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3">{footer}</footer>}
            </div>
        </div>
    );
}

/** กล่องยืนยันก่อนทำรายการที่ย้อนกลับไม่ได้ */
export function Confirm({ open, onClose, onConfirm, title, confirmLabel = 'ยืนยัน', danger, busy, children }) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="sm"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        ยกเลิก
                    </Button>
                    <Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} disabled={busy}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {children}
        </Modal>
    );
}

export function Pagination({ paginator }) {
    if (!paginator || paginator.last_page <= 1) return null;

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="เปลี่ยนหน้า">
            <span className="text-muted">
                แสดง {paginator.from}–{paginator.to} จาก {paginator.total} รายการ
            </span>
            <div className="flex flex-wrap gap-1">
                {paginator.links.map((link, i) => {
                    const label = link.label.replace('&laquo; Previous', '‹ ก่อนหน้า').replace('Next &raquo;', 'ถัดไป ›');
                    const className = cx(
                        'min-w-9 rounded-sm border px-2.5 py-1 text-center',
                        link.active ? 'border-navy-700 bg-navy-700 text-white' : 'border-line bg-white',
                        link.url ? 'hover:border-navy-600' : 'text-muted/50',
                    );
                    return link.url ? (
                        <Link key={i} href={link.url} preserveScroll preserveState className={className}>
                            {label}
                        </Link>
                    ) : (
                        <span key={i} className={className}>
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}

export function Stat({ label, value, note }) {
    return (
        <div className="border-l-2 border-gold pl-4">
            <div className="text-sm text-muted">{label}</div>
            <div className="font-heading text-2xl font-semibold tabular-nums">{value}</div>
            {note && <div className="text-sm text-muted">{note}</div>}
        </div>
    );
}

/** แถบสัดส่วนจำนวนสมาชิกต่อจำนวนรับ */
export function Capacity({ members, max }) {
    const full = members >= max;
    const percent = max > 0 ? Math.min(100, Math.round((members / max) * 100)) : 0;

    return (
        <div className="min-w-24">
            <div className="text-sm tabular-nums">
                {members} / {max}
            </div>
            <div className="mt-1 h-1 bg-line">
                <div className={full ? 'h-1 bg-danger' : 'h-1 bg-navy-600'} style={{ width: `${percent}%` }} />
            </div>
        </div>
    );
}

export const GRADES = ['ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];

export function GradeChecks({ data, setData, disabled }) {
    return (
        <fieldset>
            <legend className="mb-1 text-sm font-medium">ระดับชั้นที่รับ</legend>
            <div className="flex flex-wrap gap-x-5 gap-y-1">
                {GRADES.map((grade, i) => (
                    <label key={grade} className="inline-flex items-center gap-1.5">
                        <input
                            type="checkbox"
                            className="size-4 accent-navy-700"
                            checked={!!data[`allow_m${i + 1}`]}
                            disabled={disabled}
                            onChange={(e) => setData(`allow_m${i + 1}`, e.target.checked)}
                        />
                        {grade}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

export function gradeLabel(club) {
    const grades = GRADES.filter((_, i) => club[`allow_m${i + 1}`]);
    return grades.length === 6 ? 'ทุกระดับชั้น' : grades.join(', ') || '—';
}

const DATE_FORMAT = new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' });

export function formatDateTime(value) {
    return value ? DATE_FORMAT.format(new Date(value.replace(' ', 'T'))) : '—';
}

/** ดึงข้อมูล JSON ด้วย GET และจัดการสถานะโหลด/ผิดพลาด */
export function useJson(url) {
    const [state, setState] = useState({ data: null, loading: false, error: null });

    useEffect(() => {
        if (!url) {
            setState({ data: null, loading: false, error: null });
            return;
        }
        let cancelled = false;
        setState({ data: null, loading: true, error: null });
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(async (res) => {
                const body = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(body.message || 'โหลดข้อมูลไม่สำเร็จ');
                return body;
            })
            .then((data) => !cancelled && setState({ data, loading: false, error: null }))
            .catch((error) => !cancelled && setState({ data: null, loading: false, error: error.message }));
        return () => {
            cancelled = true;
        };
    }, [url]);

    return state;
}
