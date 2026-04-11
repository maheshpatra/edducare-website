import React, { useEffect, useState, useCallback } from 'react';
import {
    ArrowUpCircle, Search, CheckCircle, AlertTriangle, Users, ChevronRight,
    Calendar, School, Layers, RefreshCw, Info, Edit2
} from 'lucide-react';
import { promotionService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

// ─── Types ────────────────────────────────────────────────────────────────────
interface Session {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_current: number;
}

interface ClassItem {
    id: number;
    name: string;
    grade_level: number;
    student_count: number;
}

interface SectionItem {
    id: number;
    name: string;
    capacity: number;
    student_count: number;
}

interface Student {
    id: number;
    first_name: string;
    last_name: string;
    admission_number: string;
    student_code: string;
    father_name: string;
    roll_number: string;
    enrollment_id: number;
    session_name: string;
    class_name: string;
    section_name: string;
}

// ─── Component ────────────────────────────────────────────────────────────────
const PromoteStudents: React.FC = () => {
    // Sessions
    const [sessions, setSessions] = useState<Session[]>([]);
    const [loadingSessions, setLoadingSessions] = useState(true);

    // Classes & Sections are school-wide (one-time creation, NOT session-specific)
    const [allClasses, setAllClasses] = useState<ClassItem[]>([]);

    // Source (Promote FROM)
    const [fromSession, setFromSession] = useState('');
    const [fromClass, setFromClass] = useState('');
    const [fromSections, setFromSections] = useState<SectionItem[]>([]);
    const [fromSection, setFromSection] = useState('');

    // Destination (Promote TO)
    const [toSession, setToSession] = useState('');
    const [toClass, setToClass] = useState('');
    const [toSections, setToSections] = useState<SectionItem[]>([]);
    const [toSection, setToSection] = useState('');

    // Students
    const [students, setStudents] = useState<Student[]>([]);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [searchTerm, setSearchTerm] = useState('');
    const [loadingStudents, setLoadingStudents] = useState(false);

    // Roll Numbers for promotion
    const [rollNumbers, setRollNumbers] = useState<Record<number, string>>({});

    // Promotion
    const [promoting, setPromoting] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const [promotionResult, setPromotionResult] = useState<any>(null);
    const [showResult, setShowResult] = useState(false);

    // ─── Load sessions on mount ────────────────────────────────────────────
    useEffect(() => {
        setLoadingSessions(true);
        promotionService.getSessions()
            .then(r => {
                const sessData = r.data?.data ?? [];
                setSessions(sessData);
                // Auto-select current session as "from"
                const current = sessData.find((s: Session) => s.is_current === 1 || s.is_current === true);
                if (current) {
                    setFromSession(String(current.id));
                }
            })
            .catch(() => toast.error('Failed to load academic sessions'))
            .finally(() => setLoadingSessions(false));
    }, []);

    // ─── Load all classes on mount (classes are school-wide, NOT session-specific) ─
    useEffect(() => {
        // Try the promotion endpoint first, fallback to classService
        promotionService.getClasses()
            .then(r => {
                const cls = r.data?.data ?? [];
                setAllClasses(cls);
            })
            .catch(() => {
                // Fallback: use the standard class management endpoint
                import('../api/services').then(({ classService }) => {
                    classService.list()
                        .then(r => setAllClasses(r.data?.data ?? []))
                        .catch(() => toast.error('Failed to load classes'));
                });
            });
    }, []);

    // ─── Load FROM sections when from-class changes ──────────────────────────
    useEffect(() => {
        if (!fromClass) { setFromSections([]); setFromSection(''); return; }
        promotionService.getSectionsByClass(fromClass)
            .then(r => setFromSections(r.data?.data ?? []))
            .catch(() => setFromSections([]));
        setFromSection('');
        setStudents([]);
        setSelectedIds(new Set());
    }, [fromClass]);

    // ─── Load TO sections when to-class changes ──────────────────────────────
    useEffect(() => {
        if (!toClass) { setToSections([]); setToSection(''); return; }
        promotionService.getSectionsByClass(toClass)
            .then(r => setToSections(r.data?.data ?? []))
            .catch(() => setToSections([]));
        setToSection('');
    }, [toClass]);

    // ─── Find Students ───────────────────────────────────────────────────────
    const findStudents = useCallback(async () => {
        if (!fromClass || !fromSection) {
            toast.error('Please select both class and section');
            return;
        }
        if (!fromSession) {
            toast.error('Please select the current session');
            return;
        }
        setLoadingStudents(true);
        setSelectedIds(new Set());
        try {
            const res = await promotionService.getStudents({
                class_id: fromClass,
                section_id: fromSection,
                academic_year_id: fromSession,
            });
            const studentData = res.data?.data ?? [];
            setStudents(studentData);
            // Pre-fill roll numbers from current data
            const initialRolls: Record<number, string> = {};
            studentData.forEach((s: Student) => {
                initialRolls[s.id] = s.roll_number || '';
            });
            setRollNumbers(initialRolls);
            if (studentData.length === 0) {
                toast('No students found for this selection', { icon: '📭' });
            }
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load students');
            setStudents([]);
        } finally {
            setLoadingStudents(false);
        }
    }, [fromClass, fromSection, fromSession]);

    // ─── Selection helpers ───────────────────────────────────────────────────
    const toggleStudent = (id: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const toggleAll = () => {
        const filtered = filteredStudents;
        if (selectedIds.size === filtered.length && filtered.length > 0) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(filtered.map(s => s.id)));
        }
    };

    // ─── Filtered students by search ─────────────────────────────────────────
    const filteredStudents = searchTerm
        ? students.filter(s =>
            `${s.first_name} ${s.last_name} ${s.admission_number} ${s.father_name}`
                .toLowerCase().includes(searchTerm.toLowerCase())
        )
        : students;

    // ─── Promote ─────────────────────────────────────────────────────────────
    const handlePromote = async () => {
        setShowConfirm(false);
        setPromoting(true);
        try {
            const res = await promotionService.promote({
                student_ids: Array.from(selectedIds),
                from_academic_year_id: Number(fromSession),
                from_class_id: Number(fromClass),
                from_section_id: Number(fromSection),
                to_academic_year_id: Number(toSession),
                to_class_id: Number(toClass),
                to_section_id: Number(toSection),
                roll_numbers: Object.fromEntries(
                    Array.from(selectedIds).map(id => [id, rollNumbers[id] || ''])
                ),
            });
            const result = res.data;
            setPromotionResult(result);
            setShowResult(true);
            toast.success(`${result.stats?.promoted ?? 0} students promoted successfully!`);
            // Refresh the student list
            findStudents();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Promotion failed');
        } finally {
            setPromoting(false);
        }
    };

    // ─── Helpers ─────────────────────────────────────────────────────────────
    const getSessionLabel = (id: string) => sessions.find(s => String(s.id) === id)?.name ?? '';
    const getClassLabel = (id: string) => allClasses.find(c => String(c.id) === id)?.name ?? '';
    const getFromSectionLabel = () => fromSections.find(s => String(s.id) === fromSection)?.name ?? '';
    const getToSectionLabel = () => toSections.find(s => String(s.id) === toSection)?.name ?? '';

    const canPromote = selectedIds.size > 0 && toSession && toClass && toSection;
    const allSelected = filteredStudents.length > 0 && selectedIds.size === filteredStudents.length;

    if (loadingSessions) {
        return (
            <div className="fade-in" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: 400 }}>
                <div style={{ textAlign: 'center' }}>
                    <div className="spinner" style={{ width: 40, height: 40, margin: '0 auto 16px' }} />
                    <p style={{ color: 'var(--text-muted)' }}>Loading sessions…</p>
                </div>
            </div>
        );
    }

    return (
        <div className="fade-in">
            {/* ─── Toolbar ────────────────────────────────────────────── */}
            <div className="toolbar" style={{ justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <ArrowUpCircle size={22} style={{ color: 'var(--primary)' }} />
                    <span style={{ fontSize: '1.15rem', fontWeight: 700 }}>Promote Students</span>
                </div>
                {students.length > 0 && (
                    <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                        {selectedIds.size} of {filteredStudents.length} selected
                    </span>
                )}
            </div>

            {/* ─── Step 1: Select Class to Promote From ───────────── */}
            <div className="card" style={{ marginBottom: 20 }}>
                <div style={{
                    background: 'linear-gradient(135deg, #1a365d 0%, #2563eb 100%)',
                    color: 'white',
                    padding: '14px 20px',
                    borderRadius: 'var(--radius-sm) var(--radius-sm) 0 0',
                    fontWeight: 700,
                    fontSize: '0.95rem',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                }}>
                    <School size={18} />
                    Select Class to Promote From
                </div>
                <div style={{ padding: 20 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 16 }}>
                        <div className="form-group">
                            <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <Calendar size={14} style={{ color: 'var(--primary)' }} />
                                Current Session
                            </label>
                            <select value={fromSession} onChange={e => { setFromSession(e.target.value); setStudents([]); setSelectedIds(new Set()); }}>
                                <option value="">-- Select Session --</option>
                                {sessions.map(s => (
                                    <option key={s.id} value={s.id}>
                                        {s.name} {s.is_current ? '(Current)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="form-group">
                            <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <Layers size={14} style={{ color: 'var(--primary)' }} />
                                Current Class
                            </label>
                            <select
                                value={fromClass}
                                onChange={e => setFromClass(e.target.value)}
                                disabled={!fromSession}
                            >
                                <option value="">-- Select Class --</option>
                                {allClasses.map(c => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="form-group">
                            <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                <Users size={14} style={{ color: 'var(--primary)' }} />
                                Current Section
                            </label>
                            <select
                                value={fromSection}
                                onChange={e => setFromSection(e.target.value)}
                                disabled={!fromClass}
                            >
                                <option value="">-- Select Section --</option>
                                {fromSections.map(s => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div style={{ marginTop: 16 }}>
                        <button
                            className="btn btn-primary"
                            onClick={findStudents}
                            disabled={!fromSession || !fromClass || !fromSection || loadingStudents}
                            style={{ minWidth: 160 }}
                        >
                            {loadingStudents
                                ? <div className="spinner" style={{ width: 16, height: 16 }} />
                                : <><Search size={16} /> Find Students</>
                            }
                        </button>
                    </div>
                </div>
            </div>

            {/* ─── Step 2: Promote To ─────────────────────────────── */}
            {students.length > 0 && (
                <div className="card" style={{ marginBottom: 20 }}>
                    <div style={{
                        background: 'linear-gradient(135deg, #065f46 0%, #10b981 100%)',
                        color: 'white',
                        padding: '14px 20px',
                        borderRadius: 'var(--radius-sm) var(--radius-sm) 0 0',
                        fontWeight: 700,
                        fontSize: '0.95rem',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                    }}>
                        <ArrowUpCircle size={18} />
                        Promote Students to Next Session
                    </div>
                    <div style={{ padding: 20 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 16 }}>
                            <div className="form-group">
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <Calendar size={14} style={{ color: 'var(--success)' }} />
                                    Promote to Session
                                </label>
                                <select value={toSession} onChange={e => setToSession(e.target.value)}>
                                    <option value="">-- Select Session --</option>
                                    {sessions.map(s => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} {s.is_current ? '(Current)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="form-group">
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <Layers size={14} style={{ color: 'var(--success)' }} />
                                    Promote to Class
                                </label>
                                <select
                                    value={toClass}
                                    onChange={e => setToClass(e.target.value)}
                                    disabled={!toSession}
                                >
                                    <option value="">-- Select Class --</option>
                                    {allClasses.map(c => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="form-group">
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <Users size={14} style={{ color: 'var(--success)' }} />
                                    Promote to Section
                                </label>
                                <select
                                    value={toSection}
                                    onChange={e => setToSection(e.target.value)}
                                    disabled={!toClass}
                                >
                                    <option value="">-- Select Section --</option>
                                    {toSections.map(s => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} (Capacity: {s.capacity || 30})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {/* Info banner */}
                        {toSession && toClass && toSection && (
                            <div style={{
                                marginTop: 16,
                                padding: '10px 16px',
                                background: 'rgba(16, 185, 129, 0.08)',
                                border: '1px solid rgba(16, 185, 129, 0.25)',
                                borderRadius: 'var(--radius-sm)',
                                fontSize: '0.84rem',
                                color: 'var(--text-secondary)',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                            }}>
                                <Info size={16} style={{ color: 'var(--success)', flexShrink: 0 }} />
                                Selected students will be promoted to <strong style={{ margin: '0 3px' }}>{getClassLabel(toClass)} {getToSectionLabel()}</strong> in session <strong style={{ margin: '0 3px' }}>{getSessionLabel(toSession)}</strong>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ─── Step 3: Student List ───────────────────────────── */}
            {students.length > 0 && (
                <div className="card">
                    <div style={{
                        padding: '14px 20px',
                        borderBottom: '1px solid var(--bg-border)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: 12,
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                            <span style={{ fontWeight: 700, fontSize: '0.95rem' }}>
                                Students in {getClassLabel(fromClass)} {getFromSectionLabel()} — {getSessionLabel(fromSession)}
                            </span>
                            <span className="badge badge-primary" style={{ fontSize: '0.75rem' }}>
                                {students.length} students
                            </span>
                        </div>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                            <div className="search-bar" style={{ maxWidth: 220 }}>
                                <Search size={14} style={{ color: 'var(--text-muted)' }} />
                                <input
                                    placeholder="Search students…"
                                    value={searchTerm}
                                    onChange={e => setSearchTerm(e.target.value)}
                                    style={{ fontSize: '0.82rem' }}
                                />
                            </div>
                            <button className="btn btn-secondary btn-sm" onClick={() => findStudents()}>
                                <RefreshCw size={14} /> Refresh
                            </button>
                        </div>
                    </div>

                    <div className="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onChange={toggleAll}
                                            style={{ width: 16, height: 16, cursor: 'pointer' }}
                                        />
                                    </th>
                                    <th>#</th>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Father's Name</th>
                                    <th>Roll No</th>
                                    <th style={{ minWidth: 110 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <Edit2 size={13} style={{ color: 'var(--success)' }} />
                                            New Roll No
                                        </div>
                                    </th>
                                    <th>Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loadingStudents ? (
                                    <tr>
                                        <td colSpan={8} style={{ textAlign: 'center', padding: 40 }}>
                                            <div className="spinner" style={{ margin: 'auto' }} />
                                        </td>
                                    </tr>
                                ) : filteredStudents.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <div className="empty-state">
                                                <Users size={36} />
                                                <p>No students found</p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredStudents.map((s, i) => {
                                        const isSelected = selectedIds.has(s.id);
                                        return (
                                            <tr
                                                key={s.id}
                                                onClick={() => toggleStudent(s.id)}
                                                style={{
                                                    cursor: 'pointer',
                                                    background: isSelected ? 'rgba(37, 99, 235, 0.06)' : undefined,
                                                    transition: 'background 0.15s',
                                                }}
                                            >
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        checked={isSelected}
                                                        onChange={() => toggleStudent(s.id)}
                                                        style={{ width: 16, height: 16, cursor: 'pointer', accentColor: 'var(--primary)' }}
                                                    />
                                                </td>
                                                <td style={{ color: 'var(--text-muted)', fontSize: '0.8rem' }}>{i + 1}</td>
                                                <td style={{ fontWeight: 600, fontSize: '0.85rem' }}>{s.admission_number || s.student_code || '—'}</td>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                        <div style={{
                                                            width: 30, height: 30, borderRadius: '50%',
                                                            background: `hsl(${(s.id * 37) % 360}, 55%, 35%)`,
                                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                            fontSize: '0.68rem', fontWeight: 700, color: 'white', flexShrink: 0,
                                                        }}>
                                                            {`${s.first_name[0]}${s.last_name?.[0] ?? ''}`.toUpperCase()}
                                                        </div>
                                                        <span style={{ fontWeight: 600 }}>
                                                            {s.first_name} {s.last_name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>{s.father_name || '—'}</td>
                                                <td style={{ fontSize: '0.85rem' }}>{s.roll_number || '—'}</td>
                                                <td onClick={e => e.stopPropagation()}>
                                                    <input
                                                        type="text"
                                                        value={rollNumbers[s.id] ?? ''}
                                                        onChange={e => setRollNumbers(prev => ({ ...prev, [s.id]: e.target.value }))}
                                                        placeholder="—"
                                                        style={{
                                                            width: 80,
                                                            padding: '5px 8px',
                                                            fontSize: '0.84rem',
                                                            fontWeight: 600,
                                                            border: '1.5px solid var(--bg-border)',
                                                            borderRadius: 8,
                                                            background: 'var(--bg-elevated)',
                                                            color: 'var(--text-primary)',
                                                            textAlign: 'center',
                                                            outline: 'none',
                                                            transition: 'border-color 0.2s',
                                                        }}
                                                        onFocus={e => e.target.style.borderColor = 'var(--primary)'}
                                                        onBlur={e => e.target.style.borderColor = 'var(--bg-border)'}
                                                    />
                                                </td>
                                                <td>
                                                    <span className="badge badge-primary" style={{ fontSize: '0.72rem' }}>
                                                        {s.session_name || getSessionLabel(fromSession)}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Promote Button */}
                    <div style={{
                        padding: '16px 20px',
                        borderTop: '1px solid var(--bg-border)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                    }}>
                        <div style={{ fontSize: '0.84rem', color: 'var(--text-muted)' }}>
                            {selectedIds.size > 0
                                ? `${selectedIds.size} student${selectedIds.size > 1 ? 's' : ''} selected for promotion`
                                : 'Select students to promote'
                            }
                        </div>
                        <button
                            className="btn btn-primary"
                            disabled={!canPromote || promoting}
                            onClick={() => setShowConfirm(true)}
                            style={{
                                minWidth: 180,
                                background: canPromote ? 'linear-gradient(135deg, #065f46 0%, #10b981 100%)' : undefined,
                            }}
                        >
                            {promoting
                                ? <><div className="spinner" style={{ width: 16, height: 16 }} /> Promoting…</>
                                : <><ArrowUpCircle size={16} /> Promote {selectedIds.size > 0 ? `(${selectedIds.size})` : ''}</>
                            }
                        </button>
                    </div>
                </div>
            )}

            {/* ─── Confirmation Modal ─────────────────────────────── */}
            <Modal
                isOpen={showConfirm}
                onClose={() => setShowConfirm(false)}
                title="Confirm Promotion"
                maxWidth={520}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowConfirm(false)}>Cancel</button>
                        <button
                            className="btn btn-primary"
                            onClick={handlePromote}
                            disabled={promoting}
                            style={{ background: 'linear-gradient(135deg, #065f46 0%, #10b981 100%)' }}
                        >
                            {promoting ? <div className="spinner" /> : <><ArrowUpCircle size={16} /> Confirm Promotion</>}
                        </button>
                    </>
                }
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                    <div style={{
                        padding: '14px 16px',
                        background: 'rgba(245, 158, 11, 0.08)',
                        border: '1px solid rgba(245, 158, 11, 0.25)',
                        borderRadius: 'var(--radius-sm)',
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 10,
                    }}>
                        <AlertTriangle size={20} style={{ color: '#f59e0b', flexShrink: 0, marginTop: 2 }} />
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                            This action will promote <strong>{selectedIds.size}</strong> student{selectedIds.size > 1 ? 's' : ''} and
                            deactivate their current enrollment. This action is logged but <strong>cannot be auto-reversed</strong>.
                        </div>
                    </div>

                    <div style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr auto 1fr',
                        gap: 12,
                        alignItems: 'center',
                    }}>
                        {/* FROM */}
                        <div style={{
                            padding: 14,
                            background: 'var(--bg-elevated)',
                            borderRadius: 'var(--radius-sm)',
                            border: '1px solid var(--bg-border)',
                            textAlign: 'center',
                        }}>
                            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 6 }}>From</div>
                            <div style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--text-primary)' }}>{getClassLabel(fromClass)} {getFromSectionLabel()}</div>
                            <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: 4 }}>{getSessionLabel(fromSession)}</div>
                        </div>

                        <ChevronRight size={24} style={{ color: 'var(--primary)' }} />

                        {/* TO */}
                        <div style={{
                            padding: 14,
                            background: 'rgba(16, 185, 129, 0.08)',
                            borderRadius: 'var(--radius-sm)',
                            border: '1px solid rgba(16, 185, 129, 0.25)',
                            textAlign: 'center',
                        }}>
                            <div style={{ fontSize: '0.72rem', color: 'var(--success)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 6 }}>To</div>
                            <div style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--text-primary)' }}>{getClassLabel(toClass)} {getToSectionLabel()}</div>
                            <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: 4 }}>{getSessionLabel(toSession)}</div>
                        </div>
                    </div>

                    <div style={{ fontSize: '0.84rem', color: 'var(--text-secondary)' }}>
                        <strong>Students:</strong> {selectedIds.size} selected
                    </div>
                </div>
            </Modal>

            {/* ─── Result Modal ────────────────────────────────────── */}
            <Modal
                isOpen={showResult}
                onClose={() => setShowResult(false)}
                title="Promotion Complete"
                maxWidth={440}
                footer={
                    <button className="btn btn-primary" onClick={() => setShowResult(false)}>Done</button>
                }
            >
                {promotionResult && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, textAlign: 'center' }}>
                        <CheckCircle size={48} style={{ color: 'var(--success)', margin: '0 auto' }} />
                        <div>
                            <div style={{ fontSize: '1.4rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                                {promotionResult.stats?.promoted ?? 0}
                            </div>
                            <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', marginTop: 4 }}>
                                Students promoted successfully
                            </div>
                        </div>
                        {(promotionResult.stats?.skipped ?? 0) > 0 && (
                            <div style={{
                                padding: '10px 14px',
                                background: 'rgba(245, 158, 11, 0.08)',
                                border: '1px solid rgba(245, 158, 11, 0.25)',
                                borderRadius: 'var(--radius-sm)',
                                fontSize: '0.82rem',
                                color: 'var(--text-secondary)',
                            }}>
                                <AlertTriangle size={14} style={{ color: '#f59e0b', marginRight: 6 }} />
                                {promotionResult.stats.skipped} student{promotionResult.stats.skipped > 1 ? 's' : ''} skipped (already enrolled)
                            </div>
                        )}
                        {promotionResult.errors?.length > 0 && (
                            <div style={{ textAlign: 'left', fontSize: '0.78rem', color: 'var(--text-muted)', maxHeight: 120, overflow: 'auto' }}>
                                {promotionResult.errors.map((err: string, i: number) => (
                                    <div key={i} style={{ padding: '3px 0' }}>• {err}</div>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </div>
    );
};

export default PromoteStudents;
