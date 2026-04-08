import React, { useState, useEffect, useCallback } from 'react';
import {
    Plus, Search, BookOpen, MoreVertical, Edit2, Trash2, 
    CheckCircle, XCircle, RefreshCw, LayoutGrid, Users,
    Briefcase, Award, ClipboardList, Info
} from 'lucide-react';
import { subjectService, classService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface Subject {
    id: number;
    name: string;
    code: string;
    description: string;
    is_active: number;
    assigned_classes: number;
    assigned_teachers: number;
    total_assignments: number;
    total_exams: number;
}

const Subjects: React.FC = () => {
    const [subjects, setSubjects] = useState<Subject[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [stats, setStats] = useState({ total_subjects: 0, unique_teachers: 0, total_assignments: 0 });

    const [showAddModal, setShowAddModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [saving, setSaving] = useState(false);
    
    // Form state
    const [formData, setFormData] = useState({
        id: null as number | null,
        name: '',
        code: '',
        description: '',
        is_active: 1
    });

    const loadSubjects = useCallback(async () => {
        setLoading(true);
        try {
            const res = await subjectService.list();
            if (res.data?.success) {
                setSubjects(res.data.data.subjects || []);
                setStats(res.data.data.statistics || { total_subjects: 0, unique_teachers: 0, total_assignments: 0 });
            }
        } catch (err) {
            toast.error('Failed to load subjects');
        }
        setLoading(false);
    }, []);

    useEffect(() => {
        loadSubjects();
    }, [loadSubjects]);

    const handleCreate = async () => {
        if (!formData.name || !formData.code) return toast.error('Name and code are required');
        setSaving(true);
        try {
            await subjectService.create(formData);
            toast.success('Subject created successfully');
            setShowAddModal(false);
            loadSubjects();
            setFormData({ id: null, name: '', code: '', description: '', is_active: 1 });
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to create subject');
        }
        setSaving(false);
    };

    const handleUpdate = async () => {
        if (!formData.name || !formData.code) return toast.error('Name and code are required');
        setSaving(true);
        try {
            await subjectService.update(formData);
            toast.success('Subject updated successfully');
            setShowEditModal(false);
            loadSubjects();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to update subject');
        }
        setSaving(false);
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this subject? If it has assignments or exams, it will be marked as inactive instead.')) return;
        try {
            await subjectService.delete(id);
            toast.success('Action completed successfully');
            loadSubjects();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to delete');
        }
    };

    const editSubject = (s: Subject) => {
        setFormData({
            id: s.id,
            name: s.name,
            code: s.code,
            description: s.description || '',
            is_active: Number(s.is_active)
        });
        setShowEditModal(true);
    };

    const filteredSubjects = subjects.filter(s => 
        s.name.toLowerCase().includes(search.toLowerCase()) || 
        s.code.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <div className="fade-in" style={{ padding: '24px' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                <div>
                    <h1 style={{ fontSize: '24px', fontWeight: '900', color: 'var(--text-primary)', marginBottom: '4px' }}>Subject Management</h1>
                    <p style={{ color: 'var(--text-muted)', fontSize: '14px', fontWeight: '500' }}>Manage school curriculum and subject allocations</p>
                </div>
                <button onClick={() => {
                    setFormData({ id: null, name: '', code: '', description: '', is_active: 1 });
                    setShowAddModal(true);
                }} className="btn btn-primary" style={{ height: '44px', padding: '0 20px' }}>
                    <Plus size={18} /> Add New Subject
                </button>
            </div>

            {/* Stats */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '16px', marginBottom: '32px' }}>
                {[
                    { label: 'Total Subjects', value: stats.total_subjects, icon: BookOpen, color: '#6366f1' },
                    { label: 'Active Teachers', value: stats.unique_teachers, icon: Users, color: '#10b981' },
                    { label: 'Total Assignments', value: stats.total_assignments, icon: ClipboardList, color: '#f59e0b' },
                ].map(s => (
                    <div key={s.label} className="glass-card" style={{ padding: '24px', display: 'flex', alignItems: 'center', gap: '20px' }}>
                        <div style={{ padding: '12px', borderRadius: '16px', background: `${s.color}15`, display: 'flex' }}>
                            <s.icon size={24} style={{ color: s.color }} />
                        </div>
                        <div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: 'var(--text-primary)', lineHeight: 1.1 }}>{loading ? '...' : s.value}</div>
                            <div style={{ fontSize: '12px', color: 'var(--text-muted)', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '1px', marginTop: '4px' }}>{s.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Main Content */}
            <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
                <div style={{ padding: '20px', borderBottom: '1px solid var(--bg-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: 'var(--bg-card)' }}>
                    <div className="search-bar" style={{ maxWidth: '400px', flex: 1 }}>
                        <Search size={18} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder="Search by name or code..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            style={{ background: 'transparent', border: 'none', width: '100%', color: 'var(--text-primary)', outline: 'none' }}
                        />
                    </div>
                </div>

                <div className="table-wrapper">
                    <table style={{ width: '100%' }}>
                        <thead>
                            <tr>
                                <th style={{ width: '60px' }}>#</th>
                                <th>Subject Info</th>
                                <th style={{ textAlign: 'center' }}>Classes</th>
                                <th style={{ textAlign: 'center' }}>Teachers</th>
                                <th style={{ textAlign: 'center' }}>Workload</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={7} style={{ textAlign: 'center', padding: '100px 0' }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : filteredSubjects.length === 0 ? (
                                <tr>
                                    <td colSpan={7} style={{ textAlign: 'center', padding: '100px 0', color: 'var(--text-muted)' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }}>
                                            <Info size={40} style={{ opacity: 0.2 }} />
                                            <div style={{ fontWeight: '600' }}>No subjects found. Add one to get started.</div>
                                        </div>
                                    </td>
                                </tr>
                            ) : filteredSubjects.map((s, i) => (
                                <tr key={s.id}>
                                    <td style={{ color: 'var(--text-muted)', fontWeight: '600' }}>{i + 1}</td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ width: '40px', height: '40px', borderRadius: '12px', background: 'var(--primary-glow)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', color: 'var(--primary-light)' }}>
                                                {s.name[0].toUpperCase()}
                                            </div>
                                            <div>
                                                <div style={{ fontWeight: '800', color: 'var(--text-primary)' }}>{s.name}</div>
                                                <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--primary-light)', textTransform: 'uppercase' }}>{s.code}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style={{ textAlign: 'center' }}>
                                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '4px 10px', borderRadius: '20px', background: 'var(--bg-elevated)', fontSize: '12px', fontWeight: '700' }}>
                                            <LayoutGrid size={12} /> {s.assigned_classes}
                                        </div>
                                    </td>
                                    <td style={{ textAlign: 'center' }}>
                                        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '4px 10px', borderRadius: '20px', background: 'var(--bg-elevated)', fontSize: '12px', fontWeight: '700' }}>
                                            <Users size={12} /> {s.assigned_teachers}
                                        </div>
                                    </td>
                                    <td style={{ textAlign: 'center' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '4px' }}>
                                            <div style={{ fontSize: '11px', fontWeight: '700', color: 'var(--text-muted)' }}>
                                                {s.total_assignments} Assig. • {s.total_exams} Exams
                                            </div>
                                            <div style={{ width: '80px', height: '4px', background: 'var(--bg-elevated)', borderRadius: '2px', overflow: 'hidden' }}>
                                                <div style={{ width: `${Math.min(100, (s.total_assignments + s.total_exams) * 5)}%`, height: '100%', background: 'var(--accent)' }} />
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {s.is_active ? (
                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#10b981', fontSize: '12px', fontWeight: '800' }}>
                                                <CheckCircle size={14} /> Active
                                            </span>
                                        ) : (
                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#ef4444', fontSize: '12px', fontWeight: '800' }}>
                                                <XCircle size={14} /> Inactive
                                            </span>
                                        )}
                                    </td>
                                    <td style={{ textAlign: 'right' }}>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                            <button onClick={() => editSubject(s)} className="btn-icon" title="Edit Content">
                                                <Edit2 size={16} />
                                            </button>
                                            <button onClick={() => handleDelete(s.id)} className="btn-icon delete" title="Delete Subject">
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modals */}
            <Modal
                isOpen={showAddModal}
                onClose={() => setShowAddModal(false)}
                title="Create New Subject"
                maxWidth={480}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowAddModal(false)}>Cancel</button>
                        <button className="btn btn-primary" onClick={handleCreate} disabled={saving}>
                            {saving ? <RefreshCw size={16} className="spin" /> : 'Create Subject'}
                        </button>
                    </>
                }
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Subject Name</label>
                        <input className="input" value={formData.name} onChange={e => setFormData(p => ({ ...p, name: e.target.value }))} placeholder="e.g. Mathematics" />
                    </div>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Subject Code</label>
                        <input className="input" value={formData.code} onChange={e => setFormData(p => ({ ...p, code: e.target.value.toUpperCase() }))} placeholder="e.g. MATH101" />
                    </div>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Description</label>
                        <textarea className="input" rows={3} value={formData.description} onChange={e => setFormData(p => ({ ...p, description: e.target.value }))} placeholder="Optional syllabus or notes..." />
                    </div>
                </div>
            </Modal>

            <Modal
                isOpen={showEditModal}
                onClose={() => setShowEditModal(false)}
                title="Edit Subject"
                maxWidth={480}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowEditModal(false)}>Cancel</button>
                        <button className="btn btn-primary" onClick={handleUpdate} disabled={saving}>
                            {saving ? <RefreshCw size={16} className="spin" /> : 'Save Changes'}
                        </button>
                    </>
                }
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Subject Name</label>
                        <input className="input" value={formData.name} onChange={e => setFormData(p => ({ ...p, name: e.target.value }))} />
                    </div>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Subject Code</label>
                        <input className="input" value={formData.code} onChange={e => setFormData(p => ({ ...p, code: e.target.value.toUpperCase() }))} />
                    </div>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Description</label>
                        <textarea className="input" rows={3} value={formData.description} onChange={e => setFormData(p => ({ ...p, description: e.target.value }))} />
                    </div>
                    <div className="form-group">
                        <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer' }}>
                            <input type="checkbox" checked={formData.is_active === 1} onChange={e => setFormData(p => ({ ...p, is_active: e.target.checked ? 1 : 0 }))} />
                            <span style={{ fontSize: '14px', fontWeight: '700' }}>Subject is Active</span>
                        </label>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default Subjects;
