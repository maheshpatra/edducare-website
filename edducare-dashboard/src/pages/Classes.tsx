import React, { useEffect, useState, useCallback } from 'react';
import { Plus, ChevronDown, ChevronRight, Users, BookOpen } from 'lucide-react';
import { classService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface ClassRecord {
    id: number;
    name: string;
    grade_level: number;
    description?: string;
    room_number?: string;
    capacity?: number;
    section_count?: number;
    student_count?: number;
    teacher_first_name?: string;
    teacher_last_name?: string;
    academic_year_name?: string;
    class_teacher_id?: number;
}

const EMPTY_FORM = { name: '', grade_level: '', description: '', capacity: '30', room_number: '' };

const Classes: React.FC = () => {
    const [classes, setClasses] = useState<ClassRecord[]>([]);
    const [loading, setLoading] = useState(true);
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<ClassRecord | null>(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);

    // Section management state
    const [sectionsModalClass, setSectionsModalClass] = useState<ClassRecord | null>(null);
    const [classSections, setClassSections] = useState<any[]>([]);
    const [loadingSections, setLoadingSections] = useState(false);
    const [newSectionName, setNewSectionName] = useState('');
    const [newSectionCapacity, setNewSectionCapacity] = useState('30');
    const [savingSection, setSavingSection] = useState(false);

    // Summary stats
    const totalStudents = classes.reduce((s, c) => s + Number(c.student_count ?? 0), 0);
    const totalSections = classes.reduce((s, c) => s + Number(c.section_count ?? 0), 0);


    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await classService.list();
            setClasses(res.data?.data ?? []);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load classes');
        } finally { setLoading(false); }
    }, []);

    useEffect(() => { load(); }, [load]);

    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const openCreate = () => { setForm({ ...EMPTY_FORM }); setEditing(null); setShowCreate(true); };
    const openEdit = (c: ClassRecord) => {
        setForm({ name: c.name, grade_level: String(c.grade_level), description: c.description ?? '', capacity: String(c.capacity ?? 30), room_number: c.room_number ?? '' });
        setEditing(c); setShowCreate(true);
    };

    const handleSave = async () => {
        if (!form.name || !form.grade_level) { toast.error('Class name and grade level are required'); return; }
        setSaving(true);
        try {
            if (editing) {
                await classService.update({ id: editing.id, name: form.name, grade_level: form.grade_level, description: form.description, capacity: Number(form.capacity), room_number: form.room_number });
                toast.success('Class updated');
            } else {
                await classService.create({ name: form.name, grade_level: form.grade_level, description: form.description, capacity: Number(form.capacity), room_number: form.room_number, sections: [{ name: 'A', capacity: Number(form.capacity) }] });
                toast.success('Class created');
            }
            setShowCreate(false); load();
        } catch (err: any) { toast.error(err?.response?.data?.error ?? 'Save failed'); }
        finally { setSaving(false); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this class? Ensure no active students are enrolled.')) return;
        try { await classService.delete(id); toast.success('Class deleted'); load(); }
        catch (err: any) { toast.error(err?.response?.data?.error ?? 'Delete failed'); }
    };

    const toggle = (id: number) => setExpanded(p => {
        const n = new Set(p);
        n.has(id) ? n.delete(id) : n.add(id);
        return n;
    });

    const openSectionsModal = async (c: ClassRecord) => {
        setSectionsModalClass(c);
        loadSections(c.id);
    };

    const loadSections = async (classId: number) => {
        setLoadingSections(true);
        try {
            const res = await classService.sections(classId);
            setClassSections(res.data?.data ?? []);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load sections');
        } finally { setLoadingSections(false); }
    };

    const handleAddSection = async () => {
        if (!newSectionName.trim()) { toast.error('Section name is required'); return; }
        if (!sectionsModalClass) return;
        setSavingSection(true);
        try {
            await classService.addSection({ 
                class_id: sectionsModalClass.id, 
                name: newSectionName.toUpperCase(), 
                capacity: Number(newSectionCapacity) 
            });
            toast.success('Section added successfully');
            setNewSectionName('');
            loadSections(sectionsModalClass.id);
            load(); // Reload main class list to update counts
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to add section');
        } finally { setSavingSection(false); }
    };

    const handleDeleteSection = async (sectionId: number) => {
        if (!confirm('Are you sure you want to delete this section?')) return;
        try {
            await classService.deleteSection(sectionId);
            toast.success('Section deleted successfully');
            if (sectionsModalClass) loadSections(sectionsModalClass.id);
            load(); // Reload main class list
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to delete section');
        }
    };

    return (
        <div className="fade-in">
            {/* Stats */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginBottom: 24 }}>
                {[
                    { label: 'Total Classes', value: loading ? '—' : classes.length, icon: '🏫', color: '#6366f1' },
                    { label: 'Total Sections', value: loading ? '—' : totalSections, icon: '📋', color: '#10b981' },
                    { label: 'Total Students', value: loading ? '—' : totalStudents.toLocaleString(), icon: '🎓', color: '#f59e0b' },
                ].map(s => (
                    <div key={s.label} style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-lg)', padding: 20, borderTop: `3px solid ${s.color}`, display: 'flex', gap: 16, alignItems: 'center' }}>
                        <span style={{ fontSize: '2rem' }}>{s.icon}</span>
                        <div>
                            <div style={{ fontSize: '1.8rem', fontWeight: 900, color: 'var(--text-primary)', lineHeight: 1 }}>{s.value}</div>
                            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)', marginTop: 4 }}>{s.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Toolbar */}
            <div className="toolbar">
                <span style={{ fontSize: '0.9rem', color: 'var(--text-secondary)', fontWeight: 600 }}>{classes.length} classes</span>
                <button className="btn btn-primary" onClick={openCreate}><Plus size={16} />Add Class</button>
            </div>

            {/* Accordion list */}
            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}><div className="spinner" style={{ width: 36, height: 36 }} /></div>
            ) : classes.length === 0 ? (
                <div className="card"><div className="empty-state"><BookOpen size={40} /><p>No classes found</p></div></div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                    {classes.map(c => {
                        const isOpen = expanded.has(c.id);
                        const teacherName = c.teacher_first_name ? `${c.teacher_first_name} ${c.teacher_last_name}` : null;
                        return (
                            <div key={c.id} className="card" style={{ padding: 0, overflow: 'hidden' }}>
                                {/* Header row */}
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '16px 20px', cursor: 'pointer', userSelect: 'none' }}
                                    onClick={() => toggle(c.id)}>
                                    <div style={{ width: 36, height: 36, borderRadius: 'var(--radius-md)', background: 'var(--primary-glow)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                        {isOpen ? <ChevronDown size={16} style={{ color: 'var(--primary-light)' }} /> : <ChevronRight size={16} style={{ color: 'var(--primary-light)' }} />}
                                    </div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1rem' }}>{c.name}</div>
                                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2 }}>
                                            Grade {c.grade_level}{teacherName ? ` · Class Teacher: ${teacherName}` : ''}{c.room_number ? ` · Room ${c.room_number}` : ''}
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', gap: 20, alignItems: 'center' }}>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: 700, fontSize: '1.1rem', color: 'var(--text-primary)' }}>{c.section_count ?? 0}</div>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)' }}>Sections</div>
                                        </div>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: 700, fontSize: '1.1rem', color: 'var(--text-primary)' }}>{c.student_count ?? 0}</div>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)' }}>Students</div>
                                        </div>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: 700, fontSize: '1.1rem', color: 'var(--text-primary)' }}>{c.capacity ?? 0}</div>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)' }}>Capacity</div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button className="btn btn-secondary btn-sm" onClick={e => { e.stopPropagation(); openSectionsModal(c); }}>Sections</button>
                                            <button className="btn btn-secondary btn-sm" onClick={e => { e.stopPropagation(); openEdit(c); }}>Edit</button>
                                            <button className="btn btn-danger btn-sm" onClick={e => { e.stopPropagation(); handleDelete(c.id); }}>Delete</button>
                                        </div>
                                    </div>
                                </div>

                                {/* Expanded section detail */}
                                {isOpen && (
                                    <div style={{ borderTop: '1px solid var(--bg-border)', padding: '14px 20px', background: 'var(--bg-elevated)', display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                            <Users size={14} style={{ color: 'var(--text-muted)' }} />
                                            <span style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{c.student_count ?? 0} enrolled students</span>
                                        </div>
                                        {c.description && (
                                            <span style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{c.description}</span>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)}
                title={editing ? `Edit: ${editing.name}` : 'Add New Class'} maxWidth={480}
                footer={<><button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : (editing ? 'Save Changes' : 'Create Class')}</button></>}>
                <div className="grid-2">
                    <div className="form-group"><label>Class Name *</label><input className="input" value={form.name} onChange={e => f('name', e.target.value)} placeholder="e.g. Class 10" /></div>
                    <div className="form-group"><label>Grade Level *</label><input className="input" type="number" min="1" max="12" value={form.grade_level} onChange={e => f('grade_level', e.target.value)} placeholder="10" /></div>
                </div>

                <div className="grid-2">
                    <div className="form-group"><label>Capacity</label><input className="input" type="number" min="1" value={form.capacity} onChange={e => f('capacity', e.target.value)} placeholder="30" /></div>
                    <div className="form-group"><label>Room Number</label><input className="input" value={form.room_number} onChange={e => f('room_number', e.target.value)} placeholder="101" /></div>
                </div>
                <div className="form-group"><label>Description</label><textarea value={form.description} onChange={e => f('description', e.target.value)} rows={2} placeholder="Optional description" /></div>
            </Modal>

            {/* Sections Management Modal */}
            <Modal isOpen={!!sectionsModalClass} onClose={() => setSectionsModalClass(null)}
                title={`Manage Sections: ${sectionsModalClass?.name}`} maxWidth={550}>
                
                <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 20 }}>
                    <input className="input" style={{ flex: 1 }} value={newSectionName} onChange={e => setNewSectionName(e.target.value)} placeholder="Section Name (e.g. A, B, C)" />
                    <input className="input" type="number" style={{ width: 100 }} value={newSectionCapacity} onChange={e => setNewSectionCapacity(e.target.value)} placeholder="Capacity" />
                    <button className="btn btn-primary" onClick={handleAddSection} disabled={savingSection || !newSectionName.trim()}>
                        {savingSection ? <div className="spinner" style={{ width: 14, height: 14 }} /> : <><Plus size={16} /> Add</>}
                    </button>
                </div>

                <div className="table-container">
                    {loadingSections ? (
                        <div style={{ display: 'flex', justifyContent: 'center', padding: 40 }}><div className="spinner" /></div>
                    ) : classSections.length === 0 ? (
                        <div className="empty-state" style={{ padding: 30 }}><p>No sections found for this class</p></div>
                    ) : (
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Section Name</th>
                                    <th>Capacity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {classSections.map(s => (
                                    <tr key={s.id}>
                                        <td style={{ fontWeight: 600 }}>{s.name}</td>
                                        <td>{s.capacity ?? 30}</td>
                                        <td>
                                            <button className="btn btn-danger btn-sm" onClick={() => handleDeleteSection(s.id)}>Delete</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </Modal>
        </div>
    );
};

export default Classes;
