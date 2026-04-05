import React, { useEffect, useState, useCallback } from 'react';
import { CheckCircle, XCircle, Clock, Search, Save } from 'lucide-react';
import { attendanceService, classService } from '../api/services';
import toast from 'react-hot-toast';

interface AttStudent {
    student_id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    admission_number?: string;
    roll_number?: string;
    status: 'present' | 'absent' | 'late' | 'half_day';
    remarks?: string;
}

interface ReportRow {
    date?: string;
    class_name?: string;
    section_name?: string;
    attendance_date?: string;
    present_count?: number;
    absent_count?: number;
    total_marked?: number;
    attendance_percentage?: number;
}

const STATUS_CYCLE: AttStudent['status'][] = ['present', 'absent', 'late', 'half_day'];
const STATUS_COLOR: Record<string, string> = {
    present: '#10b981', absent: '#ef4444', late: '#f59e0b', half_day: '#8b5cf6',
};
const STATUS_ICON: Record<string, React.ReactNode> = {
    present: <CheckCircle size={14} />,
    absent: <XCircle size={14} />,
    late: <Clock size={14} />,
    half_day: <Clock size={14} />,
};

const TODAY = new Date().toISOString().split('T')[0];

const Attendance: React.FC = () => {
    const [tab, setTab] = useState<'mark' | 'report'>('mark');

    // Mark attendance state
    const [classes, setClasses] = useState<any[]>([]);
    const [classId, setClassId] = useState('');
    const [sectionId, setSectionId] = useState('');
    const [classSections, setClassSections] = useState<any[]>([]);
    const [date, setDate] = useState(TODAY);
    const [students, setStudents] = useState<AttStudent[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    // Report state
    const [reportRows, setReportRows] = useState<ReportRow[]>([]);
    const [reportLoading, setReportLoading] = useState(false);
    const [rClassId, setRClassId] = useState('');
    const [rStart, setRStart] = useState(new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]);
    const [rEnd, setREnd] = useState(TODAY);

    // Load classes
    useEffect(() => {
        classService.list().then(r => setClasses(r.data?.data ?? [])).catch(() => { });
    }, []);

    // Load sections dynamically
    useEffect(() => {
        if (classId) {
            classService.sections(classId).then(r => setClassSections(r.data?.data ?? [])).catch(() => setClassSections([]));
        } else {
            setClassSections([]);
            setSectionId('');
        }
    }, [classId]);

    // Load students when class/section/date changes
    const loadStudents = useCallback(async () => {
        if (!classId || !sectionId) { setStudents([]); return; }
        setLoading(true);
        try {
            const res = await attendanceService.listStudents({ class_id: classId, section_id: sectionId, date });
            const raw: any[] = res.data?.students ?? res.data?.data ?? [];
            setStudents(raw.map(s => ({ ...s, status: s.status ?? 'present' })));
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load students');
            setStudents([]);
        } finally { setLoading(false); }
    }, [classId, sectionId, date]);

    useEffect(() => { loadStudents(); }, [loadStudents]);

    // Load report
    const loadReport = useCallback(async () => {
        setReportLoading(true);
        try {
            const res = await attendanceService.report({ class_id: rClassId || undefined, start_date: rStart, end_date: rEnd });
            setReportRows(res.data?.data ?? res.data ?? []);
        } catch {
            setReportRows([]);
        } finally { setReportLoading(false); }
    }, [rClassId, rStart, rEnd]);

    useEffect(() => { if (tab === 'report') loadReport(); }, [tab, loadReport]);

    // Toggle status cycle
    const toggleStatus = (idx: number) => {
        setStudents(p => p.map((s, i) => {
            if (i !== idx) return s;
            const ci = STATUS_CYCLE.indexOf(s.status);
            return { ...s, status: STATUS_CYCLE[(ci + 1) % STATUS_CYCLE.length] };
        }));
    };

    const markAll = (status: AttStudent['status']) =>
        setStudents(p => p.map(s => ({ ...s, status })));

    const handleSubmit = async () => {
        if (!classId || !sectionId) { toast.error('Select a class and section first'); return; }
        if (students.length === 0) { toast.error('No students to mark attendance for'); return; }
        setSaving(true);
        try {
            await attendanceService.mark({
                date,
                attendance_data: students.map(s => ({
                    student_id: s.student_id,
                    status: s.status,
                    remarks: s.remarks,
                })),
            });
            toast.success(`Attendance marked for ${students.length} students`);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to submit attendance');
        } finally { setSaving(false); }
    };

    // Live stats
    const stats = {
        present: students.filter(s => s.status === 'present').length,
        absent: students.filter(s => s.status === 'absent').length,
        late: students.filter(s => s.status === 'late').length,
        half_day: students.filter(s => s.status === 'half_day').length,
    };
    const rate = students.length > 0
        ? (((stats.present + stats.late + stats.half_day) / students.length) * 100).toFixed(1)
        : '—';

    const visible = search
        ? students.filter(s => (s.full_name ?? `${s.first_name} ${s.last_name}`).toLowerCase().includes(search.toLowerCase()))
        : students;

    return (
        <div className="fade-in">
            {/* Tab switch */}
            <div style={{ display: 'flex', gap: 4, background: 'var(--bg-elevated)', borderRadius: 'var(--radius-md)', padding: 4, width: 'fit-content', marginBottom: 24 }}>
                {(['mark', 'report'] as const).map(t => (
                    <button key={t} onClick={() => setTab(t)}
                        style={{
                            padding: '8px 20px', borderRadius: 'var(--radius-sm)', border: 'none', cursor: 'pointer', fontSize: '0.875rem', fontWeight: 600, transition: 'all 0.2s',
                            background: tab === t ? 'var(--primary)' : 'transparent',
                            color: tab === t ? 'white' : 'var(--text-secondary)'
                        }}>
                        {t === 'mark' ? '✅ Mark Attendance' : '📊 Attendance Report'}
                    </button>
                ))}
            </div>

            {tab === 'mark' && (
                <>
                    {/* Filters */}
                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 20 }}>
                        <div className="form-group" style={{ margin: 0, minWidth: 160 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Class</label>
                            <select value={classId} onChange={e => { setClassId(e.target.value); setSectionId(''); }}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none', minWidth: 150 }}>
                                <option value="">Select class</option>
                                {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div className="form-group" style={{ margin: 0, minWidth: 130 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Section</label>
                            <select value={sectionId} onChange={e => setSectionId(e.target.value)} disabled={!classId}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none', minWidth: 120 }}>
                                <option value="">Select section</option>
                                {classSections.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Date</label>
                            <input type="date" value={date} max={TODAY} onChange={e => setDate(e.target.value)}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none' }} />
                        </div>
                        <div className="form-group" style={{ margin: 0, flex: 1 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Search</label>
                            <div className="search-bar" style={{ maxWidth: 260 }}>
                                <Search size={14} style={{ color: 'var(--text-muted)' }} />
                                <input placeholder="Search student…" value={search} onChange={e => setSearch(e.target.value)} />
                            </div>
                        </div>
                    </div>

                    {/* Summary strip */}
                    {students.length > 0 && (
                        <div style={{ display: 'flex', gap: 16, marginBottom: 16, flexWrap: 'wrap', alignItems: 'center' }}>
                            {Object.entries(stats).map(([k, v]) => (
                                <div key={k} style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                    <span style={{ color: STATUS_COLOR[k] }}>{STATUS_ICON[k]}</span>
                                    <span style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-primary)' }}>{v}</span>
                                    <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)', textTransform: 'capitalize' }}>{k.replace('_', ' ')}</span>
                                </div>
                            ))}
                            <span style={{ fontSize: '0.82rem', color: 'var(--success)', fontWeight: 700, marginLeft: 'auto' }}>Rate: {rate}%</span>
                            {/* Bulk actions */}
                            <div style={{ display: 'flex', gap: 6 }}>
                                {STATUS_CYCLE.map(s => (
                                    <button key={s} onClick={() => markAll(s)}
                                        style={{ fontSize: '0.72rem', padding: '4px 10px', borderRadius: 20, border: `1px solid ${STATUS_COLOR[s]}`, background: `${STATUS_COLOR[s]}18`, color: STATUS_COLOR[s], cursor: 'pointer', textTransform: 'capitalize' }}>
                                        All {s.replace('_', ' ')}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Student list */}
                    <div className="card">
                        {loading ? (
                            <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}><div className="spinner" style={{ width: 36, height: 36 }} /></div>
                        ) : !classId || !sectionId ? (
                            <div className="empty-state"><CheckCircle size={40} /><p>Select a class and section to mark attendance</p></div>
                        ) : visible.length === 0 ? (
                            <div className="empty-state"><Search size={40} /><p>No students found</p></div>
                        ) : (
                            <div className="table-wrapper">
                                <table>
                                    <thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Roll</th><th>Status</th><th>Remarks</th></tr></thead>
                                    <tbody>
                                        {visible.map((s, i) => (
                                            <tr key={s.student_id}>
                                                <td style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>{i + 1}</td>
                                                <td style={{ fontWeight: 600 }}>{s.full_name ?? `${s.first_name} ${s.last_name}`}</td>
                                                <td style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{s.admission_number ?? '—'}</td>
                                                <td style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{s.roll_number ?? '—'}</td>
                                                <td>
                                                    <button onClick={() => toggleStatus(students.indexOf(s))}
                                                        style={{ display: 'flex', alignItems: 'center', gap: 5, padding: '5px 14px', borderRadius: 20, border: `1px solid ${STATUS_COLOR[s.status]}`, background: `${STATUS_COLOR[s.status]}18`, color: STATUS_COLOR[s.status], cursor: 'pointer', fontWeight: 600, fontSize: '0.8rem', textTransform: 'capitalize' }}>
                                                        {STATUS_ICON[s.status]} {s.status.replace('_', ' ')}
                                                    </button>
                                                </td>
                                                <td>
                                                    <input placeholder="Remarks (optional)" value={s.remarks ?? ''}
                                                        onChange={e => setStudents(p => p.map(x => x.student_id === s.student_id ? { ...x, remarks: e.target.value } : x))}
                                                        style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '5px 10px', fontSize: '0.8rem', color: 'var(--text-primary)', outline: 'none', width: '100%', maxWidth: 200 }} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Save button */}
                    {students.length > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 16 }}>
                            <button className="btn btn-primary" onClick={handleSubmit} disabled={saving} style={{ gap: 8 }}>
                                {saving ? <div className="spinner" /> : <Save size={16} />}
                                {saving ? 'Submitting…' : `Submit Attendance (${students.length} students)`}
                            </button>
                        </div>
                    )}
                </>
            )}

            {/* Report tab */}
            {tab === 'report' && (
                <>
                    <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Class</label>
                            <select value={rClassId} onChange={e => setRClassId(e.target.value)}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none', minWidth: 150 }}>
                                <option value="">All Classes</option>
                                {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>Start Date</label>
                            <input type="date" value={rStart} onChange={e => setRStart(e.target.value)}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none' }} />
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>End Date</label>
                            <input type="date" value={rEnd} max={TODAY} onChange={e => setREnd(e.target.value)}
                                style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', color: 'var(--text-primary)', padding: '8px 12px', fontSize: '0.875rem', outline: 'none' }} />
                        </div>
                        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                            <button className="btn btn-primary btn-sm" onClick={loadReport} disabled={reportLoading}>
                                {reportLoading ? <div className="spinner" /> : '🔄 Refresh'}
                            </button>
                        </div>
                    </div>
                    <div className="card">
                        <div className="table-wrapper">
                            <table>
                                <thead><tr><th>Date</th><th>Class</th><th>Section</th><th>Present</th><th>Absent</th><th>Total Marked</th><th>Attendance %</th></tr></thead>
                                <tbody>
                                    {reportLoading ? (
                                        <tr><td colSpan={7} style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                                    ) : reportRows.length === 0 ? (
                                        <tr><td colSpan={7}><div className="empty-state"><CheckCircle size={36} /><p>No report data for the selected filters</p></div></td></tr>
                                    ) : reportRows.map((r, i) => (
                                        <tr key={i}>
                                            <td style={{ fontWeight: 600 }}>{r.date ?? r.attendance_date ?? '—'}</td>
                                            <td>{r.class_name ?? '—'}</td>
                                            <td>{r.section_name ?? '—'}</td>
                                            <td><span style={{ color: 'var(--success)', fontWeight: 700 }}>{r.present_count ?? '—'}</span></td>
                                            <td><span style={{ color: 'var(--danger)', fontWeight: 700 }}>{r.absent_count ?? '—'}</span></td>
                                            <td>{r.total_marked ?? '—'}</td>
                                            <td>
                                                {r.attendance_percentage !== undefined
                                                    ? <span style={{ fontWeight: 700, color: Number(r.attendance_percentage) >= 90 ? 'var(--success)' : Number(r.attendance_percentage) >= 75 ? 'var(--warning)' : 'var(--danger)' }}>
                                                        {Number(r.attendance_percentage).toFixed(1)}%
                                                    </span>
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};

export default Attendance;
