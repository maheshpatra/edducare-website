import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Filter, ClipboardList, Pencil, Trash2 } from 'lucide-react';
import { assignmentService, classService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface Assignment {
    id: number;
    title: string;
    description?: string;
    subject_id?: number;
    subject_name?: string;
    class_id?: number;
    class_name?: string;
    section_name?: string;
    due_date?: string;
    max_marks?: number;
    status?: string;
    created_at?: string;
    teacher_name?: string;
    submission_count?: number;
}

const STATUS_COLORS: Record<string, string> = {
    active: '#10b981', completed: '#6366f1', draft: '#f59e0b', overdue: '#ef4444',
};

const EMPTY_FORM = {
    title: '', description: '', subject_id: '', class_id: '',
    due_date: '', max_marks: '100',
};

const Assignments: React.FC = () => {
    const [assignments, setAssignments] = useState<Assignment[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [classFilter, setClassFilter] = useState('');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<Assignment | null>(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        classService.list().then(r => setClasses(r.data?.data ?? [])).catch(() => { });
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await assignmentService.list({
                ...(classFilter ? { class_id: classFilter } : {}),
            });
            let data = res.data?.data ?? res.data ?? [];
            if (!Array.isArray(data)) data = [];
            // Client-side search
            if (search) data = data.filter((a: Assignment) =>
                a.title?.toLowerCase().includes(search.toLowerCase()) ||
                a.subject_name?.toLowerCase().includes(search.toLowerCase())
            );
            setAssignments(data);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load assignments');
        } finally { setLoading(false); }
    }, [classFilter, search]);

    useEffect(() => { load(); }, [load]);

    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const openCreate = () => { setForm({ ...EMPTY_FORM }); setEditing(null); setShowCreate(true); };
    const openEdit = (a: Assignment) => {
        setForm({
            title: a.title, description: a.description ?? '', subject_id: String(a.subject_id ?? ''),
            class_id: String(a.class_id ?? ''), due_date: a.due_date?.split('T')[0] ?? '',
            max_marks: String(a.max_marks ?? 100)
        });
        setEditing(a); setShowCreate(true);
    };

    const handleSave = async () => {
        if (!form.title) { toast.error('Title is required'); return; }
        setSaving(true);
        try {
            if (editing) {
                await assignmentService.edit({ id: editing.id, ...form, max_marks: Number(form.max_marks), subject_id: form.subject_id ? Number(form.subject_id) : undefined, class_id: form.class_id ? Number(form.class_id) : undefined });
                toast.success('Assignment updated');
            } else {
                await assignmentService.create({ ...form, max_marks: Number(form.max_marks), subject_id: form.subject_id ? Number(form.subject_id) : undefined, class_id: form.class_id ? Number(form.class_id) : undefined });
                toast.success('Assignment created');
            }
            setShowCreate(false); load();
        } catch (err: any) { toast.error(err?.response?.data?.error ?? 'Save failed'); }
        finally { setSaving(false); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this assignment?')) return;
        try { await assignmentService.delete(id); toast.success('Deleted'); load(); }
        catch (err: any) { toast.error(err?.response?.data?.error ?? 'Delete failed'); }
    };

    const dueBadge = (date?: string) => {
        if (!date) return { label: 'No due date', color: 'var(--text-muted)' };
        const d = new Date(date);
        const now = new Date();
        if (d < now) return { label: `Overdue ${d.toLocaleDateString()}`, color: '#ef4444' };
        const days = Math.ceil((d.getTime() - now.getTime()) / 86400000);
        if (days <= 3) return { label: `Due in ${days}d`, color: '#f59e0b' };
        return { label: `Due ${d.toLocaleDateString()}`, color: '#10b981' };
    };

    return (
        <div className="fade-in">
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1 }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 300 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input placeholder="Search assignments…" value={search} onChange={e => setSearch(e.target.value)} />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px' }}>
                        <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                        <select value={classFilter} onChange={e => setClassFilter(e.target.value)}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 120 }}>
                            <option value="">All Classes</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                </div>
                <button className="btn btn-primary" onClick={openCreate}><Plus size={16} />New Assignment</button>
            </div>

            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}><div className="spinner" style={{ width: 36, height: 36 }} /></div>
            ) : assignments.length === 0 ? (
                <div className="card"><div className="empty-state"><ClipboardList size={40} /><p>No assignments found</p></div></div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                    {assignments.map(a => {
                        const due = dueBadge(a.due_date);
                        const sc = STATUS_COLORS[a.status ?? 'active'] ?? '#6366f1';
                        return (
                            <div key={a.id} className="card" style={{ padding: '16px 20px', display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                                <div style={{ width: 44, height: 44, borderRadius: 'var(--radius-md)', background: `${sc}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                    <ClipboardList size={20} style={{ color: sc }} />
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 6 }}>
                                        <h3 style={{ fontSize: '0.95rem', fontWeight: 700, color: 'var(--text-primary)' }}>{a.title}</h3>
                                        {a.status && <span style={{ fontSize: '0.7rem', padding: '2px 8px', borderRadius: 20, background: `${sc}20`, color: sc, textTransform: 'capitalize' }}>{a.status}</span>}
                                        <span style={{ fontSize: '0.7rem', padding: '2px 8px', borderRadius: 20, background: `${due.color}15`, color: due.color }}>{due.label}</span>
                                    </div>
                                    {a.description && <p style={{ fontSize: '0.82rem', color: 'var(--text-secondary)', marginBottom: 8, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{a.description}</p>}
                                    <div style={{ display: 'flex', gap: 14, fontSize: '0.75rem', color: 'var(--text-muted)', flexWrap: 'wrap' }}>
                                        {a.subject_name && <span>📚 {a.subject_name}</span>}
                                        {a.class_name && <span>🏫 {a.class_name}{a.section_name ? ` ${a.section_name}` : ''}</span>}
                                        {a.max_marks && <span>📊 Max: {a.max_marks} marks</span>}
                                        {a.submission_count !== undefined && <span>📬 {a.submission_count} submitted</span>}
                                        {a.teacher_name && <span>👤 {a.teacher_name}</span>}
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                                    <button className="btn btn-secondary btn-sm btn-icon" onClick={() => openEdit(a)}><Pencil size={13} /></button>
                                    <button className="btn btn-danger btn-sm btn-icon" onClick={() => handleDelete(a.id)}><Trash2 size={13} /></button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)}
                title={editing ? 'Edit Assignment' : 'New Assignment'} maxWidth={520}
                footer={<><button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : (editing ? 'Save Changes' : 'Create Assignment')}</button></>}>
                <div className="form-group"><label>Title *</label><input className="input" value={form.title} onChange={e => f('title', e.target.value)} placeholder="Assignment title" /></div>
                <div className="form-group"><label>Description</label><textarea value={form.description} onChange={e => f('description', e.target.value)} rows={3} placeholder="Describe the assignment…" /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Class</label>
                        <select value={form.class_id} onChange={e => f('class_id', e.target.value)}>
                            <option value="">Select class</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label>Max Marks</label><input className="input" type="number" min="1" value={form.max_marks} onChange={e => f('max_marks', e.target.value)} placeholder="100" /></div>
                </div>
                <div className="form-group"><label>Due Date</label><input className="input" type="date" value={form.due_date} onChange={e => f('due_date', e.target.value)} /></div>
            </Modal>
        </div>
    );
};

export default Assignments;
