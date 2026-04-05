import React, { useState } from 'react';
import { Plus, Award, ChevronRight } from 'lucide-react';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

import { examService, classService } from '../api/services';

const Exams: React.FC = () => {
    const [exams, setExams] = useState<any[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [form, setForm] = useState({
        exam_name: '',
        exam_type: 'unit_test',
        class_id: '',
        subject: '',
        exam_date: '',
        total_marks: '100',
        passing_marks: '33'
    });
    const [saving, setSaving] = useState(false);

    const loadData = async () => {
        setLoading(true);
        try {
            const [exRes, clRes] = await Promise.all([
                examService.list(),
                classService.list()
            ]);
            setExams(exRes.data.data || []);
            setClasses(clRes.data.data || []);
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
            await examService.create(form);
            toast.success('Exam scheduled successfully');
            setShowModal(false);
            setForm({ exam_name: '', exam_type: 'unit_test', class_id: '', subject: '', exam_date: '', total_marks: '100', passing_marks: '33' });
            loadData();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to schedule exam');
        } finally {
            setSaving(false);
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
                <button className="btn btn-primary" onClick={() => setShowModal(true)}><Plus size={16} />Schedule Exam</button>
            </div>

            {loading ? (
                <div style={{ textAlign: 'center', padding: 50 }}><div className="spinner" style={{ margin: 'auto' }} /></div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {exams.map(e => {
                        const status = getStatus(e.exam_date);
                        const typeColor = '#6366f1';
                        return (
                            <div key={e.id} className="card" style={{ padding: 20, cursor: 'pointer', transition: 'all 0.2s' }}>
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
                                            <span style={{ background: `${typeColor}20`, color: typeColor, padding: '2px 8px', borderRadius: 20, fontSize: '0.72rem', fontWeight: 700 }}>{(e.type || e.exam_type)?.replace('_', ' ')}</span>
                                            <span>🏫 {e.class_name} {e.section_name ? `(${e.section_name})` : ''}</span>
                                            <span>📚 {e.subject}</span>
                                            <span>📅 {e.exam_date}</span>
                                            <span>💯 {e.total_marks} marks</span>
                                        </div>
                                    </div>
                                    <ChevronRight size={18} style={{ color: 'var(--text-muted)' }} />
                                </div>
                            </div>
                        );
                    })}
                    {exams.length === 0 && <div className="card" style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No exams scheduled for your school yet.</div>}
                </div>
            )}

            <Modal isOpen={showModal} onClose={() => setShowModal(false)} title="Schedule Exam"
                footer={<><button className="btn btn-secondary" onClick={() => setShowModal(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : 'Schedule'}</button></>}>
                <div className="form-group"><label>Exam Name *</label><input className="input" value={form.exam_name} onChange={e => f('exam_name', e.target.value)} placeholder="e.g. Unit Test 1" /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Type</label>
                        <select value={form.exam_type} onChange={e => f('exam_type', e.target.value)}>
                            <option value="unit_test">Unit Test</option><option value="mid_term">Mid Term</option><option value="final">Final Exam</option><option value="other">Other</option>
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
            </Modal>
        </div>
    );
};

export default Exams;
