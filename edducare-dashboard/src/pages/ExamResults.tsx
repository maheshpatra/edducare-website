import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, Users, CheckCircle, AlertCircle, Search, ChevronLeft, ChevronRight, BarChart3, Target, Percent, Filter } from 'lucide-react';
import toast from 'react-hot-toast';
import { examService, classService } from '../api/services';

const PAGE_SIZE = 20;

const ExamResults: React.FC = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const examId = searchParams.get('exam_id');

    const [exam, setExam] = useState<any>(null);
    const [students, setStudents] = useState<any[]>([]);
    const [marks, setMarks] = useState<Record<number, string>>({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState<'all' | 'filled' | 'empty' | 'pass' | 'fail'>('all');

    // For exam selection
    const [exams, setExams] = useState<any[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [sections, setSections] = useState<any[]>([]);
    const [selectedClass, setSelectedClass] = useState('');
    const [selectedSection, setSelectedSection] = useState('');
    const [selectedExam, setSelectedExam] = useState('');
    const [loadingExams, setLoadingExams] = useState(false);

    useEffect(() => {
        if (!examId) {
            classService.list().then(res => {
                setClasses(res.data.data || []);
                setLoading(false);
            }).catch(() => setLoading(false));
        }
    }, [examId]);

    useEffect(() => {
        if (selectedClass) {
            classService.sections(selectedClass).then(res => {
                setSections(res.data.data || []);
                setSelectedSection('');
                setSelectedExam('');
            }).catch(() => { });
        } else {
            setSections([]);
        }
    }, [selectedClass]);

    useEffect(() => {
        if (selectedClass && selectedSection) {
            setLoadingExams(true);
            examService.list({ class_id: selectedClass }).then(res => {
                const filtered = (res.data.data || []).filter((e: any) =>
                    !e.section_id || String(e.section_id) === String(selectedSection)
                );
                setExams(filtered);
                setLoadingExams(false);
            }).catch(() => setLoadingExams(false));
        } else {
            setExams([]);
        }
    }, [selectedClass, selectedSection]);

    const loadStudents = useCallback(async (eid: string, cid: string, sid: string) => {
        setLoading(true);
        try {
            const res = await examService.getStudentsForResult({ exam_id: eid, class_id: cid, section_id: sid });
            if (res.data.success) {
                setExam(res.data.exam);
                setStudents(res.data.students || []);
                const prefilled: Record<number, string> = {};
                (res.data.students || []).forEach((s: any) => {
                    if (s.marks_obtained !== null && s.marks_obtained !== undefined) {
                        prefilled[s.student_id] = String(s.marks_obtained);
                    }
                });
                setMarks(prefilled);
            } else {
                toast.error(res.data.error || 'Failed to load students');
            }
        } catch { toast.error('Failed to load data'); }
        finally { setLoading(false); }
    }, []);

    useEffect(() => {
        if (examId) {
            examService.list().then(res => {
                const thisExam = (res.data.data || []).find((e: any) => String(e.id) === String(examId));
                if (thisExam) loadStudents(examId, thisExam.class_id, thisExam.section_id || thisExam.class_id);
                else { toast.error('Exam not found'); setLoading(false); }
            }).catch(() => { toast.error('Failed to load exam'); setLoading(false); });
        }
    }, [examId, loadStudents]);

    const totalMarks = exam?.total_marks ? Number(exam.total_marks) : 100;
    const passingMarks = exam?.passing_marks ? Number(exam.passing_marks) : Math.floor(totalMarks * 0.33);

    // Filtered & paginated students
    const filteredStudents = useMemo(() => {
        let list = students;
        if (searchTerm) {
            const s = searchTerm.toLowerCase();
            list = list.filter(st =>
                `${st.first_name} ${st.last_name}`.toLowerCase().includes(s) ||
                st.roll_number?.toString().includes(s) ||
                st.admission_number?.toLowerCase().includes(s)
            );
        }
        if (statusFilter !== 'all') {
            list = list.filter(st => {
                const val = marks[st.student_id];
                const num = val !== undefined && val !== '' ? Number(val) : null;
                switch (statusFilter) {
                    case 'filled': return num !== null;
                    case 'empty': return num === null;
                    case 'pass': return num !== null && num >= passingMarks;
                    case 'fail': return num !== null && num < passingMarks;
                    default: return true;
                }
            });
        }
        return list;
    }, [students, searchTerm, statusFilter, marks, passingMarks]);

    const totalPages = Math.max(1, Math.ceil(filteredStudents.length / PAGE_SIZE));
    const paginatedStudents = filteredStudents.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);

    useEffect(() => { setCurrentPage(1); }, [searchTerm, statusFilter]);

    // Stats
    const stats = useMemo(() => {
        let filled = 0, passCount = 0, failCount = 0, totalObt = 0;
        students.forEach(s => {
            const val = marks[s.student_id];
            if (val !== undefined && val !== '') {
                filled++;
                const n = Number(val);
                totalObt += n;
                if (n >= passingMarks) passCount++;
                else failCount++;
            }
        });
        return {
            total: students.length, filled, remaining: students.length - filled,
            passCount, failCount,
            avgMarks: filled > 0 ? (totalObt / filled).toFixed(1) : '—',
            passPercent: filled > 0 ? ((passCount / filled) * 100).toFixed(0) + '%' : '—',
        };
    }, [students, marks, passingMarks]);

    const handleMarkChange = (studentId: number, value: string) => {
        if (value !== '' && (Number(value) < 0 || Number(value) > totalMarks || isNaN(Number(value)))) return;
        setMarks(prev => ({ ...prev, [studentId]: value }));
        setSaved(false);
    };

    const handleSave = async () => {
        const eid = examId || selectedExam;
        if (!eid) return;
        const arr = students
            .filter(s => marks[s.student_id] !== undefined && marks[s.student_id] !== '')
            .map(s => ({ student_id: s.student_id, marks: Number(marks[s.student_id]) }));
        if (arr.length === 0) { toast.error('Please enter marks for at least one student'); return; }
        setSaving(true);
        try {
            const res = await examService.saveResults({ exam_id: Number(eid), results: arr });
            if (res.data.success) {
                toast.success(`Results saved! (${res.data.stats.inserted} new, ${res.data.stats.updated} updated)`);
                setSaved(true);
            } else toast.error(res.data.error || 'Failed to save');
        } catch (err: any) { toast.error(err.response?.data?.error || 'Failed to save results'); }
        finally { setSaving(false); }
    };

    const handleLoadStudents = () => {
        if (!selectedExam || !selectedClass || !selectedSection) {
            toast.error('Please select class, section, and exam');
            return;
        }
        loadStudents(selectedExam, selectedClass, selectedSection);
    };

    // ── Exam Selection View ──
    if (!exam && !loading) {
        return (
            <div className="fade-in">
                <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 28 }}>
                    <button className="btn btn-secondary" onClick={() => navigate('/exams')} style={{ padding: '10px 14px', borderRadius: 12 }}>
                        <ArrowLeft size={18} />
                    </button>
                    <div>
                        <h2 style={{ fontSize: '1.35rem', fontWeight: 800, color: 'var(--text-primary)', margin: 0 }}>Upload Exam Results</h2>
                        <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>Select class, section, and exam to begin entering marks</p>
                    </div>
                </div>

                <div className="card" style={{ padding: 32, maxWidth: 540, borderRadius: 16 }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontWeight: 700, fontSize: '0.82rem', marginBottom: 6, display: 'block', color: 'var(--text-secondary)' }}>Class *</label>
                            <select value={selectedClass} onChange={e => setSelectedClass(e.target.value)} style={{ padding: '10px 14px', borderRadius: 10, fontSize: '0.9rem' }}>
                                <option value="">— Choose a class —</option>
                                {classes.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontWeight: 700, fontSize: '0.82rem', marginBottom: 6, display: 'block', color: 'var(--text-secondary)' }}>Section *</label>
                            <select value={selectedSection} onChange={e => setSelectedSection(e.target.value)} disabled={!selectedClass} style={{ padding: '10px 14px', borderRadius: 10, fontSize: '0.9rem' }}>
                                <option value="">— Choose a section —</option>
                                {sections.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </div>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label style={{ fontWeight: 700, fontSize: '0.82rem', marginBottom: 6, display: 'block', color: 'var(--text-secondary)' }}>Exam *</label>
                            <select value={selectedExam} onChange={e => setSelectedExam(e.target.value)} disabled={!selectedSection || loadingExams} style={{ padding: '10px 14px', borderRadius: 10, fontSize: '0.9rem' }}>
                                <option value="">{loadingExams ? 'Loading exams...' : '— Choose an exam —'}</option>
                                {exams.map((e: any) => (
                                    <option key={e.id} value={e.id}>{e.name || e.exam_name} — {e.subject} ({e.exam_date})</option>
                                ))}
                            </select>
                            {selectedSection && !loadingExams && exams.length === 0 && (
                                <p style={{ fontSize: '0.75rem', color: '#ef4444', marginTop: 6 }}>No exams found for this class/section.</p>
                            )}
                        </div>
                        <button className="btn btn-primary" onClick={handleLoadStudents}
                            disabled={!selectedExam || !selectedClass || !selectedSection}
                            style={{ padding: '12px 20px', borderRadius: 10, fontSize: '0.9rem', fontWeight: 700, gap: 8, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <Users size={18} /> Load Students
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    if (loading) {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: 80 }}>
                <div className="spinner" style={{ width: 44, height: 44, marginBottom: 16 }} />
                <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>Loading students…</p>
            </div>
        );
    }

    // ── Result Entry View ──
    return (
        <div className="fade-in">
            {/* Header */}
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 20, flexWrap: 'wrap', gap: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <button className="btn btn-secondary" onClick={() => { setExam(null); setStudents([]); setMarks({}); setSaved(false); setSearchTerm(''); setStatusFilter('all'); }}
                        style={{ padding: '10px 14px', borderRadius: 12 }}>
                        <ArrowLeft size={18} />
                    </button>
                    <div>
                        <h2 style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--text-primary)', margin: 0 }}>
                            {exam?.name || exam?.exam_name}
                        </h2>
                        <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>
                            📚 {exam?.exam_subject || exam?.subject}  •  🏫 {exam?.class_name}{exam?.section_name ? ` (${exam.section_name})` : ''}  •  📅 {exam?.exam_date}  •  💯 {totalMarks} marks (pass: {passingMarks})
                        </p>
                    </div>
                </div>
                <button className="btn btn-primary" onClick={handleSave} disabled={saving || stats.filled === 0}
                    style={{ padding: '10px 20px', borderRadius: 10, fontSize: '0.85rem', fontWeight: 700, gap: 8, display: 'flex', alignItems: 'center', minWidth: 140, justifyContent: 'center' }}>
                    {saving ? <div className="spinner" style={{ width: 16, height: 16 }} /> : <Save size={16} />}
                    {saving ? 'Saving…' : saved ? '✓ Saved' : 'Save Results'}
                </button>
            </div>

            {/* Stats Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: 10, marginBottom: 16 }}>
                {[
                    { label: 'Total', value: stats.total, icon: <Users size={16} />, color: '#6366f1', bg: '#eef2ff' },
                    { label: 'Entered', value: stats.filled, icon: <Target size={16} />, color: '#10b981', bg: '#ecfdf5' },
                    { label: 'Remaining', value: stats.remaining, icon: <AlertCircle size={16} />, color: stats.remaining > 0 ? '#f59e0b' : '#10b981', bg: stats.remaining > 0 ? '#fffbeb' : '#ecfdf5' },
                    { label: 'Passed', value: stats.passCount, icon: <CheckCircle size={16} />, color: '#10b981', bg: '#ecfdf5' },
                    { label: 'Failed', value: stats.failCount, icon: <AlertCircle size={16} />, color: '#ef4444', bg: '#fef2f2' },
                    { label: 'Avg Marks', value: stats.avgMarks, icon: <BarChart3 size={16} />, color: '#0ea5e9', bg: '#f0f9ff' },
                    { label: 'Pass %', value: stats.passPercent, icon: <Percent size={16} />, color: '#8b5cf6', bg: '#f5f3ff' },
                ].map(s => (
                    <div key={s.label} style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 12, padding: '12px 14px', display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{ width: 32, height: 32, borderRadius: 8, background: s.bg, color: s.color, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                            {s.icon}
                        </div>
                        <div>
                            <div style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--text-primary)', lineHeight: 1.2 }}>{s.value}</div>
                            <div style={{ fontSize: '0.65rem', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.3px' }}>{s.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Progress Bar */}
            <div style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 12, padding: '10px 14px', marginBottom: 16, display: 'flex', alignItems: 'center', gap: 12 }}>
                <span style={{ fontSize: '0.72rem', fontWeight: 700, color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>Progress</span>
                <div style={{ flex: 1, height: 8, background: 'var(--bg-base)', borderRadius: 4, overflow: 'hidden' }}>
                    <div style={{ height: '100%', borderRadius: 4, background: stats.filled === stats.total ? '#10b981' : '#6366f1', width: stats.total > 0 ? `${(stats.filled / stats.total) * 100}%` : '0%', transition: 'width 0.4s ease' }} />
                </div>
                <span style={{ fontSize: '0.78rem', fontWeight: 800, color: 'var(--text-primary)', whiteSpace: 'nowrap' }}>
                    {stats.filled}/{stats.total}
                </span>
            </div>

            {/* Toolbar: Search + Filter */}
            <div style={{ display: 'flex', gap: 10, marginBottom: 16, flexWrap: 'wrap', alignItems: 'center' }}>
                <div style={{ flex: 1, minWidth: 200, position: 'relative' }}>
                    <Search size={16} style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
                    <input
                        type="text" placeholder="Search by name, roll no, or admission no…"
                        value={searchTerm} onChange={e => setSearchTerm(e.target.value)}
                        style={{ width: '100%', padding: '9px 12px 9px 36px', borderRadius: 10, border: '1px solid var(--bg-border)', background: 'var(--bg-card)', color: 'var(--text-primary)', fontSize: '0.82rem', outline: 'none' }}
                    />
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 4, flexShrink: 0 }}>
                    <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                    {(['all', 'filled', 'empty', 'pass', 'fail'] as const).map(f => (
                        <button key={f} onClick={() => setStatusFilter(f)}
                            style={{
                                padding: '6px 12px', borderRadius: 8, border: 'none', fontSize: '0.72rem', fontWeight: 700,
                                cursor: 'pointer', textTransform: 'capitalize', transition: 'all 0.15s',
                                background: statusFilter === f ? '#6366f1' : 'var(--bg-base)',
                                color: statusFilter === f ? 'white' : 'var(--text-secondary)',
                            }}>
                            {f}
                        </button>
                    ))}
                </div>
            </div>

            {/* Table */}
            <div className="card" style={{ overflow: 'hidden', borderRadius: 14 }}>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: 'var(--bg-base)' }}>
                                <th style={thStyle}>#</th>
                                <th style={{ ...thStyle, textAlign: 'left' }}>Roll</th>
                                <th style={{ ...thStyle, textAlign: 'left', minWidth: 160 }}>Student Name</th>
                                <th style={{ ...thStyle, textAlign: 'left' }}>Adm No.</th>
                                <th style={{ ...thStyle, minWidth: 100 }}>Marks / {totalMarks}</th>
                                <th style={thStyle}>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedStudents.map((s, idx) => {
                                const val = marks[s.student_id];
                                const num = val !== undefined && val !== '' ? Number(val) : null;
                                const isPassed = num !== null ? num >= passingMarks : null;
                                const rowIndex = (currentPage - 1) * PAGE_SIZE + idx + 1;

                                return (
                                    <tr key={s.student_id}
                                        style={{
                                            borderBottom: '1px solid var(--bg-border)',
                                            background: isPassed === false ? 'rgba(254, 226, 226, 0.15)' : isPassed === true ? 'rgba(220, 252, 231, 0.15)' : undefined,
                                            transition: 'background 0.2s',
                                        }}>
                                        <td style={{ ...tdStyle, color: 'var(--text-muted)', fontSize: '0.78rem', fontWeight: 600 }}>{rowIndex}</td>
                                        <td style={{ ...tdStyle, textAlign: 'left' }}>
                                            <span style={{ background: '#eef2ff', color: '#6366f1', padding: '2px 8px', borderRadius: 6, fontSize: '0.78rem', fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>
                                                {s.roll_number || '—'}
                                            </span>
                                        </td>
                                        <td style={{ ...tdStyle, textAlign: 'left', fontWeight: 700, fontSize: '0.85rem' }}>
                                            {s.first_name} {s.last_name}
                                        </td>
                                        <td style={{ ...tdStyle, textAlign: 'left', color: 'var(--text-muted)', fontSize: '0.78rem' }}>
                                            {s.admission_number || '—'}
                                        </td>
                                        <td style={tdStyle}>
                                            <input
                                                type="number" min="0" max={totalMarks} step="0.5"
                                                value={val ?? ''} onChange={e => handleMarkChange(s.student_id, e.target.value)}
                                                placeholder="—"
                                                style={{
                                                    width: 76, padding: '7px 8px', borderRadius: 8,
                                                    border: `2px solid ${isPassed === false ? '#fca5a5' : isPassed === true ? '#86efac' : 'var(--bg-border)'}`,
                                                    background: 'var(--bg-card)', color: 'var(--text-primary)', fontWeight: 700, fontSize: '0.88rem',
                                                    textAlign: 'center', outline: 'none', transition: 'border-color 0.2s',
                                                }}
                                            />
                                        </td>
                                        <td style={tdStyle}>
                                            {num !== null ? (
                                                isPassed ? (
                                                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, background: '#dcfce7', color: '#16a34a', padding: '4px 10px', borderRadius: 20, fontSize: '0.7rem', fontWeight: 700 }}>
                                                        <CheckCircle size={11} /> Pass
                                                    </span>
                                                ) : (
                                                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, background: '#fef2f2', color: '#dc2626', padding: '4px 10px', borderRadius: 20, fontSize: '0.7rem', fontWeight: 700 }}>
                                                        <AlertCircle size={11} /> Fail
                                                    </span>
                                                )
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)', fontSize: '0.72rem', fontStyle: 'italic' }}>Pending</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            {paginatedStudents.length === 0 && (
                                <tr>
                                    <td colSpan={6} style={{ textAlign: 'center', padding: 48, color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                                        {searchTerm || statusFilter !== 'all' ? 'No students match your filters.' : 'No students found for this class/section.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                    <div style={{
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px',
                        borderTop: '1px solid var(--bg-border)', background: 'var(--bg-base)', fontSize: '0.78rem',
                    }}>
                        <span style={{ color: 'var(--text-muted)', fontWeight: 600 }}>
                            Showing {(currentPage - 1) * PAGE_SIZE + 1}–{Math.min(currentPage * PAGE_SIZE, filteredStudents.length)} of {filteredStudents.length}
                        </span>
                        <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                            <button disabled={currentPage <= 1} onClick={() => setCurrentPage(p => p - 1)}
                                style={{ ...pageBtnStyle, opacity: currentPage <= 1 ? 0.4 : 1 }}>
                                <ChevronLeft size={14} />
                            </button>
                            {Array.from({ length: totalPages }, (_, i) => i + 1)
                                .filter(p => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1)
                                .map((p, idx, arr) => (
                                    <React.Fragment key={p}>
                                        {idx > 0 && arr[idx - 1] !== p - 1 && <span style={{ color: 'var(--text-muted)' }}>…</span>}
                                        <button onClick={() => setCurrentPage(p)}
                                            style={{ ...pageBtnStyle, background: p === currentPage ? '#6366f1' : 'transparent', color: p === currentPage ? 'white' : 'var(--text-secondary)', fontWeight: p === currentPage ? 700 : 500, minWidth: 30 }}>
                                            {p}
                                        </button>
                                    </React.Fragment>
                                ))
                            }
                            <button disabled={currentPage >= totalPages} onClick={() => setCurrentPage(p => p + 1)}
                                style={{ ...pageBtnStyle, opacity: currentPage >= totalPages ? 0.4 : 1 }}>
                                <ChevronRight size={14} />
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Bottom Save Bar */}
            <div style={{
                position: 'sticky', bottom: 0, left: 0, right: 0, zIndex: 10, marginTop: 16,
                background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 12,
                padding: '12px 16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                boxShadow: '0 -4px 20px rgba(0,0,0,0.06)',
            }}>
                <div style={{ fontSize: '0.82rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                    {stats.filled} of {stats.total} marks entered • {stats.passCount} pass, {stats.failCount} fail
                </div>
                <button className="btn btn-primary" onClick={handleSave} disabled={saving || stats.filled === 0}
                    style={{ padding: '10px 24px', borderRadius: 10, fontSize: '0.85rem', fontWeight: 700, gap: 8, display: 'flex', alignItems: 'center' }}>
                    {saving ? <div className="spinner" style={{ width: 16, height: 16 }} /> : <Save size={16} />}
                    {saving ? 'Saving…' : saved ? '✓ Saved' : 'Save All Results'}
                </button>
            </div>
        </div>
    );
};

const thStyle: React.CSSProperties = {
    padding: '11px 14px', fontSize: '0.68rem', fontWeight: 700,
    textTransform: 'uppercase', letterSpacing: '0.5px', color: 'var(--text-muted)',
    textAlign: 'center', borderBottom: '2px solid var(--bg-border)',
};

const tdStyle: React.CSSProperties = {
    padding: '10px 14px', fontSize: '0.85rem', textAlign: 'center', color: 'var(--text-primary)',
};

const pageBtnStyle: React.CSSProperties = {
    padding: '4px 8px', borderRadius: 6, border: 'none', cursor: 'pointer',
    background: 'transparent', color: 'var(--text-secondary)', fontSize: '0.78rem',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
};

export default ExamResults;
