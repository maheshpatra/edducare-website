import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Award, Upload, Settings, Trash2, Tag } from 'lucide-react';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

import { examService, classService } from '../api/services';

interface ExamType {
    id: number;
    name: string;
    slug: string;
    description: string;
    is_active: number;
    sort_order: number;
}

const Exams: React.FC = () => {
    const navigate = useNavigate();
    const [exams, setExams] = useState<any[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [examTypes, setExamTypes] = useState<ExamType[]>([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [showTypeModal, setShowTypeModal] = useState(false);
    const [form, setForm] = useState({
        exam_name: '',
        exam_type: '',
        class_id: '',
        subject: '',
        exam_date: '',
        total_marks: '100',
        passing_marks: '33'
    });
    const [saving, setSaving] = useState(false);

    // Exam type form
    const [typeForm, setTypeForm] = useState({ name: '', description: '' });
    const [savingType, setSavingType] = useState(false);

    const loadData = async () => {
        setLoading(true);
        try {
            const [exRes, clRes, etRes] = await Promise.all([
                examService.list(),
                classService.list(),
                examService.getExamTypes()
            ]);
            setExams(exRes.data.data || []);
            setClasses(clRes.data.data || []);
            const types = etRes.data.data || [];
            setExamTypes(types);
            // Auto-select first type if form is empty
            if (types.length > 0 && !form.exam_type) {
                setForm(p => ({ ...p, exam_type: types[0].slug }));
            }
        } catch (err) {
            console.error('Exams load error:', err);
            toast.error('Failed to load exams');
        } finally {
            setLoading(false);
        }
    };

    React.useEffect(() => {
        loadData();
    }, []);

    const handleSave = async () => {
        if (!form.exam_name || !form.class_id || !form.subject || !form.exam_date) {
            toast.error('Please fill all required fields');
            return;
        }
        setSaving(true);
        try {
            await examService.create(form as any);
            toast.success('Exam scheduled successfully');
            setShowModal(false);
            setForm({ exam_name: '', exam_type: examTypes[0]?.slug || '', class_id: '', subject: '', exam_date: '', total_marks: '100', passing_marks: '33' });
            loadData();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to schedule exam');
        } finally {
            setSaving(false);
        }
    };

    const handleSaveType = async () => {
        if (!typeForm.name.trim()) {
            toast.error('Exam type name is required');
            return;
        }
        setSavingType(true);
        try {
            await examService.saveExamType(typeForm);
            toast.success('Exam type added!');
            setTypeForm({ name: '', description: '' });
            // Refresh exam types
            const etRes = await examService.getExamTypes();
            setExamTypes(etRes.data.data || []);
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to save exam type');
        } finally {
            setSavingType(false);
        }
    };

    const handleDeleteType = async (id: number) => {
        if (!confirm('Delete this exam type? Existing exams using it will not be affected.')) return;
        try {
            await examService.deleteExamType(id);
            toast.success('Exam type deleted');
            setExamTypes(prev => prev.filter(t => t.id !== id));
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to delete');
        }
    };

    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const getStatus = (date: string) => {
        const d = new Date(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (d < today) return 'completed';
        if (d.getTime() === today.getTime()) return 'ongoing';
        return 'upcoming';
    };

    const getTypeColor = (typeSlug: string) => {
        const colors: Record<string, string> = {
            unit_test: '#6366f1',
            mid_term: '#f59e0b',
            final: '#ef4444',
            quarterly: '#0ea5e9',
            half_yearly: '#8b5cf6',
            pre_board: '#ec4899',
        };
        return colors[typeSlug] || '#6366f1';
    };

    return (
        <div className="fade-in">
            {/* Summary */}
            <div className="stat-grid" style={{ marginBottom: 24 }}>
                {[
                    { label: 'Total Exams', value: exams.length, color: '#6366f1' },
                    { label: 'Completed', value: exams.filter(e => getStatus(e.exam_date) === 'completed').length, color: '#10b981' },
                    { label: 'Upcoming', value: exams.filter(e => getStatus(e.exam_date) === 'upcoming').length, color: '#f59e0b' },
                    { label: 'Ongoing', value: exams.filter(e => getStatus(e.exam_date) === 'ongoing').length, color: '#0ea5e9' },
                ].map(s => (
                    <div key={s.label} style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-md)', padding: 20, borderTop: `3px solid ${s.color}` }}>
                        <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--text-primary)' }}>{s.value}</div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: 4 }}>{s.label}</div>
                    </div>
                ))}
            </div>

            <div className="toolbar">
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.875rem' }}>{exams.length} exams found</span>
                <div style={{ display: 'flex', gap: 8 }}>
                    <button className="btn btn-secondary" onClick={() => setShowTypeModal(true)}>
                        <Settings size={16} /> Exam Types
                    </button>
                    <button className="btn btn-secondary" onClick={() => navigate('/exam-results')}>
                        <Upload size={16} /> Upload Results
                    </button>
                    <button className="btn btn-primary" onClick={() => setShowModal(true)}>
                        <Plus size={16} /> Schedule Exam
                    </button>
                </div>
            </div>

            {loading ? (
                <div style={{ textAlign: 'center', padding: 50 }}><div className="spinner" style={{ margin: 'auto' }} /></div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {exams.map(e => {
                        const status = getStatus(e.exam_date);
                        const typeColor = getTypeColor(e.type || e.exam_type);
                        return (
                            <div key={e.id} className="card" style={{ padding: 20, transition: 'all 0.2s' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                                    <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-md)', background: `${typeColor}20`, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                        <Award size={22} style={{ color: typeColor }} />
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
                                            <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '0.95rem' }}>{e.name || e.exam_name}</div>
                                            <span className={`badge ${status === 'completed' ? 'badge-success' : status === 'ongoing' ? 'badge-warning' : 'badge-info'}`}>{status}</span>
                                        </div>
                                        <div style={{ display: 'flex', gap: 16, fontSize: '0.8rem', color: 'var(--text-secondary)', flexWrap: 'wrap' }}>
                                            <span style={{ background: `${typeColor}20`, color: typeColor, padding: '2px 8px', borderRadius: 20, fontSize: '0.72rem', fontWeight: 700 }}>
                                                {examTypes.find(t => t.slug === (e.type || e.exam_type))?.name || (e.type || e.exam_type)?.replace('_', ' ')}
                                            </span>
                                            <span>🏫 {e.class_name} {e.section_name ? `(${e.section_name})` : ''}</span>
                                            <span>📚 {e.subject}</span>
                                            <span>📅 {e.exam_date}</span>
                                            <span>💯 {e.total_marks} marks</span>
                                        </div>
                                    </div>
                                    <button
                                        className="btn btn-secondary"
                                        style={{ padding: '6px 14px', fontSize: '0.75rem', fontWeight: 700 }}
                                        onClick={() => navigate(`/exam-results?exam_id=${e.id}`)}
                                        title="Upload Results"
                                    >
                                        <Upload size={14} /> Results
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                    {exams.length === 0 && <div className="card" style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No exams scheduled for your school yet.</div>}
                </div>
            )}

            {/* Schedule Exam Modal */}
            <Modal isOpen={showModal} onClose={() => setShowModal(false)} title="Schedule Exam"
                footer={<><button className="btn btn-secondary" onClick={() => setShowModal(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : 'Schedule'}</button></>}>
                <div className="form-group"><label>Exam Name *</label><input className="input" value={form.exam_name} onChange={e => f('exam_name', e.target.value)} placeholder="e.g. Unit Test 1" /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Type</label>
                        <select value={form.exam_type} onChange={e => f('exam_type', e.target.value)}>
                            {examTypes.length > 0 ? (
                                examTypes.map(t => (
                                    <option key={t.id} value={t.slug}>{t.name}</option>
                                ))
                            ) : (
                                <>
                                    <option value="unit_test">Unit Test</option>
                                    <option value="mid_term">Mid Term</option>
                                    <option value="final">Final Exam</option>
                                    <option value="other">Other</option>
                                </>
                            )}
                        </select>
                    </div>
                    <div className="form-group"><label>For Class *</label>
                        <select value={form.class_id} onChange={e => f('class_id', e.target.value)}>
                            <option value="">Select Class</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                </div>
                <div className="form-group"><label>Subject *</label><input className="input" value={form.subject} onChange={e => f('subject', e.target.value)} placeholder="e.g. Mathematics" /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Exam Date *</label><input className="input" type="date" value={form.exam_date} onChange={e => f('exam_date', e.target.value)} /></div>
                    <div className="form-group"><label>Total Marks *</label><input className="input" type="number" value={form.total_marks} onChange={e => f('total_marks', e.target.value)} /></div>
                </div>
                <div className="form-group"><label>Passing Marks</label><input className="input" type="number" value={form.passing_marks} onChange={e => f('passing_marks', e.target.value)} placeholder="e.g. 33" /></div>
            </Modal>

            {/* Manage Exam Types Modal */}
            <Modal isOpen={showTypeModal} onClose={() => setShowTypeModal(false)} title="Manage Exam Types" maxWidth={560}
                footer={<button className="btn btn-primary" onClick={() => setShowTypeModal(false)}>Done</button>}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                    {/* Add New Type */}
                    <div style={{
                        padding: 20,
                        background: 'var(--bg-elevated)',
                        borderRadius: 16,
                        border: '1px solid var(--bg-border)',
                    }}>
                        <div style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 14 }}>
                            Add New Exam Type
                        </div>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end' }}>
                            <div style={{ flex: 1 }}>
                                <input
                                    className="input"
                                    value={typeForm.name}
                                    onChange={e => setTypeForm(p => ({ ...p, name: e.target.value }))}
                                    placeholder="e.g. Weekly Test, Practical, Oral"
                                    style={{ marginBottom: 0 }}
                                />
                            </div>
                            <button
                                className="btn btn-primary"
                                onClick={handleSaveType}
                                disabled={savingType || !typeForm.name.trim()}
                                style={{ height: 42, padding: '0 20px', flexShrink: 0 }}
                            >
                                {savingType ? <div className="spinner" style={{ width: 16, height: 16 }} /> : <><Plus size={16} /> Add</>}
                            </button>
                        </div>
                        <input
                            className="input"
                            value={typeForm.description}
                            onChange={e => setTypeForm(p => ({ ...p, description: e.target.value }))}
                            placeholder="Optional description..."
                            style={{ marginTop: 10, fontSize: '0.82rem' }}
                        />
                    </div>

                    {/* Existing Types */}
                    <div>
                        <div style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 12 }}>
                            Current Exam Types ({examTypes.length})
                        </div>
                        {examTypes.length > 0 ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {examTypes.map(t => (
                                    <div
                                        key={t.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 12,
                                            padding: '12px 16px',
                                            background: 'var(--bg-surface)',
                                            borderRadius: 12,
                                            border: '1px solid var(--bg-border)',
                                            transition: 'all 0.15s',
                                        }}
                                    >
                                        <div style={{
                                            width: 36, height: 36, borderRadius: 10,
                                            background: `${getTypeColor(t.slug)}18`,
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            flexShrink: 0,
                                        }}>
                                            <Tag size={16} style={{ color: getTypeColor(t.slug) }} />
                                        </div>
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontWeight: 700, fontSize: '0.9rem', color: 'var(--text-primary)' }}>
                                                {t.name}
                                            </div>
                                            {t.description && (
                                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2 }}>
                                                    {t.description}
                                                </div>
                                            )}
                                        </div>
                                        <span style={{
                                            fontSize: '0.68rem', fontWeight: 700, color: 'var(--text-muted)',
                                            background: 'var(--bg-elevated)', padding: '3px 8px', borderRadius: 6,
                                            fontFamily: 'monospace',
                                        }}>
                                            {t.slug}
                                        </span>
                                        <button
                                            onClick={() => handleDeleteType(t.id)}
                                            style={{
                                                width: 32, height: 32, borderRadius: 8,
                                                border: '1px solid var(--bg-border)',
                                                background: 'transparent',
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                cursor: 'pointer', color: 'var(--text-muted)',
                                                transition: 'all 0.15s',
                                                flexShrink: 0,
                                            }}
                                            onMouseEnter={e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444'; e.currentTarget.style.borderColor = '#ef4444'; }}
                                            onMouseLeave={e => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--text-muted)'; e.currentTarget.style.borderColor = 'var(--bg-border)'; }}
                                            title="Delete exam type"
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div style={{ textAlign: 'center', padding: 30, color: 'var(--text-muted)', fontSize: '0.85rem' }}>
                                No exam types configured yet. Add your first one above.
                            </div>
                        )}
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default Exams;
