import { useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';
import { Alert, Button, Card, Field, Select } from '../../Components/ui';
import SiteLayout, { PageTitle } from '../../Layouts/SiteLayout';

const TYPES = {
    teachers: { label: 'ครู', sample: 'import_teacher.csv', note: 'รหัสครูซ้ำกับที่มีอยู่จะเป็นการปรับปรุงข้อมูลเดิม' },
    clubs: { label: 'ชุมนุม', sample: 'import_club.csv', note: 'นำเข้าเป็นชุมนุมของปีการศึกษาปัจจุบัน ชื่อซ้ำจะเป็นการปรับปรุงข้อมูลเดิม ต้องนำเข้าครูก่อน' },
    students: {
        label: 'นักเรียน',
        sample: 'import_student.csv',
        note: 'เลขประจำตัวซ้ำจะเป็นการปรับปรุงข้อมูลเดิม คอลัมน์ prefix และ club_id เว้นว่างได้',
    },
};

export default function Import({ columns, departments, result }) {
    const form = useForm({ type: 'teachers', file: null });
    const type = TYPES[form.data.type];

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/import', { preserveScroll: true, onSuccess: () => form.reset('file') });
    };

    return (
        <SiteLayout title="นำเข้าข้อมูล">
            <PageTitle title="นำเข้าข้อมูลจากไฟล์ CSV" description="แนะนำให้นำเข้าตามลำดับ ครู → ชุมนุม → นักเรียน" />

            {result && (
                <Alert tone={result.failed ? 'warn' : 'ok'}>
                    <p>
                        นำเข้า{TYPES[result.type].label}สำเร็จ {result.imported} รายการ
                        {result.failed > 0 && ` · ไม่สำเร็จ ${result.failed} รายการ`}
                    </p>
                    {result.errors.length > 0 && (
                        <ul className="mt-2 max-h-60 list-disc overflow-y-auto pl-5 text-sm">
                            {result.errors.map((error) => (
                                <li key={error}>{error}</li>
                            ))}
                        </ul>
                    )}
                </Alert>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title="อัปโหลดไฟล์">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="ประเภทข้อมูล" error={form.errors.type}>
                            <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                {Object.entries(TYPES).map(([key, t]) => (
                                    <option key={key} value={key}>
                                        {t.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="ไฟล์ CSV" error={form.errors.file} hint="เข้ารหัส UTF-8 ขนาดไม่เกิน 5 MB บรรทัดแรกเป็นหัวคอลัมน์">
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                className="block w-full rounded-sm border border-line bg-white p-2 text-sm file:mr-3 file:rounded-sm file:border-0 file:bg-navy-50 file:px-3 file:py-1.5 file:text-navy-700"
                                onChange={(e) => form.setData('file', e.target.files[0] ?? null)}
                                required
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing || !form.data.file}>
                            <Upload size={16} /> {form.processing ? 'กำลังนำเข้า…' : 'นำเข้าข้อมูล'}
                        </Button>
                    </form>
                </Card>

                <Card
                    title={`รูปแบบไฟล์: ${type.label}`}
                    actions={
                        <Button as="a" variant="outline" size="sm" href={`/sample_files/${type.sample}`} download>
                            <Download size={14} /> ไฟล์ตัวอย่าง
                        </Button>
                    }
                >
                    <p className="mb-3 text-muted">{type.note}</p>
                    <ol className="list-decimal space-y-0.5 pl-6 font-mono text-sm">
                        {columns[form.data.type].map((column) => (
                            <li key={column}>{column}</li>
                        ))}
                    </ol>
                    {form.data.type === 'teachers' && (
                        <div className="mt-4">
                            <p className="mb-1 text-sm font-medium">ชื่อกลุ่มสาระต้องสะกดตรงกับ ปพ.5</p>
                            <ul className="list-disc pl-6 text-sm text-muted">
                                {departments.map((d) => (
                                    <li key={d}>{d}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {form.data.type === 'students' && (
                        <p className="mt-4 text-sm text-muted">
                            เลขบัตรประชาชนเป็นตัวเลข 13 หลักล้วน และห้อง/เลขที่ห้ามซ้ำกัน grade_level ใช้รูปแบบ ม.1 ถึง ม.6
                        </p>
                    )}
                </Card>
            </div>
        </SiteLayout>
    );
}
