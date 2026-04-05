import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Eye, Pencil, Trash2, Filter, Mail, Phone, Award } from 'lucide-react';
import { teacherService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface Teacher {
    id: number;
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
    employee_id?: string;
    subject_specialization?: string;
    qualification?: string;
    experience_years?: number;
    role_name?: string;
    teacher_type?: string;
    is_active?: number;
    joining_date?: string;
    address?: string;
    profile_image?: string | null;
    subject_assignments?: number;
    class_teacher_assignments?: number;
    timetable_periods?: number;
}

const EMPTY_FORM = {
    first_name: '', last_name: '', email: '', phone: '',
    subject_specialization: '', qualification: '', experience_years: '',
    role_id: '3', joining_date: '', address: '',
};

const ROLES = [
    { id: 3, label: 'Academic Teacher' },
    { id: 4, label: 'Administrative Teacher' },
];

const Teachers: React.FC = () => {
    const [teachers, setTeachers] = useState<Teacher[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(0);

    const [viewTeacher, setViewTeacher] = useState<Teacher | null>(null);
    const [editTeacher, setEditTeacher] = useState<Teacher | null>(null);
    const [showAdd, setShowAdd] = useState(false);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);
    const [profileImageFile, setProfileImageFile] = useState<File | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await teacherService.list({
                page, limit: 20,
                ...(search ? { search } : {}),
                ...(typeFilter ? { teacher_type: typeFilter } : {}),
            });
            const d = res.data?.data ?? res.data;
            setTeachers(d?.teachers ?? d ?? []);
            const p = res.data?.data?.pagination ?? res.data?.pagination;
            if (p) { setTotal(p.total); setTotalPages(p.total_pages); }
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load teachers');
            setTeachers([]);
        } finally {
            setLoading(false);
        }
    }, [page, search, typeFilter]);

    useEffect(() => { load(); }, [load]);
    useEffect(() => { const t = setTimeout(() => setPage(1), 350); return () => clearTimeout(t); }, [search]);

    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const openAdd = () => { 
        setForm({ ...EMPTY_FORM }); 
        setEditTeacher(null); 
        setProfileImageFile(null);
        setShowAdd(true); 
    };
    const openEdit = (t: Teacher) => {
        setForm({
            first_name: t.first_name, last_name: t.last_name, email: t.email ?? '',
            phone: t.phone ?? '', subject_specialization: t.subject_specialization ?? '',
            qualification: t.qualification ?? '', experience_years: String(t.experience_years ?? ''),
            role_id: String(t.role_name === 'teacher_administrative' ? 4 : 3),
            joining_date: t.joining_date ?? '', address: t.address ?? '',
        });
        setEditTeacher(t); 
        setProfileImageFile(null);
        setShowAdd(true);
    };

    const handleSave = async () => {
        if (!form.first_name || !form.last_name || !form.email || !form.phone) {
            toast.error('First name, last name, email and phone are required'); return;
        }
        setSaving(true);
        try {
            if (editTeacher) {
                await teacherService.update({ id: editTeacher.id, ...form, role_id: Number(form.role_id) });
                toast.success('Teacher updated');
            } else {
                let payload: any = { ...form, role_id: Number(form.role_id) };
                if (profileImageFile) {
                    payload = new FormData();
                    Object.entries(form).forEach(([k, v]) => payload.append(k, String(v)));
                    payload.append('role_id', String(form.role_id));
                    payload.append('profile_image', profileImageFile);
                }
                await teacherService.create(payload);
                toast.success('Teacher created');
            }
            setShowAdd(false); load();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Save failed');
        } finally { setSaving(false); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this teacher? This cannot be undone.')) return;
        try { await teacherService.delete(id); toast.success('Teacher deleted'); load(); }
        catch (err: any) { toast.error(err?.response?.data?.error ?? 'Delete failed'); }
    };

    const initials = (t: Teacher) => `${t.first_name[0] ?? ''}${t.last_name[0] ?? ''}`.toUpperCase();
    const hue = (id: number) => (id * 47) % 360;

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1 }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 300 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input placeholder="Search teachers…" value={search} onChange={e => setSearch(e.target.value)} />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px' }}>
                        <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                        <select value={typeFilter} onChange={e => { setTypeFilter(e.target.value); setPage(1); }}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 130 }}>
                            <option value="">All Types</option>
                            <option value="academic">Academic</option>
                            <option value="administrative">Administrative</option>
                        </select>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    {total > 0 && <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{total} teachers</span>}
                    <button className="btn btn-primary" onClick={openAdd}><Plus size={16} />Add Teacher</button>
                </div>
            </div>

            {/* Card grid */}
            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}><div className="spinner" style={{ width: 36, height: 36 }} /></div>
            ) : teachers.length === 0 ? (
                <div className="card"><div className="empty-state"><Search size={36} /><p>No teachers found</p></div></div>
            ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(290px, 1fr))', gap: 16 }}>
                    {teachers.map(t => (
                        <div key={t.id} className="card" style={{ padding: 0, overflow: 'hidden', transition: 'transform 0.15s', cursor: 'default' }}
                            onMouseEnter={e => (e.currentTarget as HTMLDivElement).style.transform = 'translateY(-2px)'}
                            onMouseLeave={e => (e.currentTarget as HTMLDivElement).style.transform = ''}>
                            {/* Card header strip */}
                            <div style={{ height: 6, background: `hsl(${hue(t.id)},60%,50%)` }} />
                            <div style={{ padding: 20 }}>
                                <div style={{ display: 'flex', gap: 14, alignItems: 'flex-start', marginBottom: 16 }}>
                                    {t.profile_image
                                        ? <img src={t.profile_image} alt="" style={{ width: 52, height: 52, borderRadius: '50%', objectFit: 'cover' }} />
                                        : <div style={{ width: 52, height: 52, borderRadius: '50%', background: `hsl(${hue(t.id)},55%,35%)`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.1rem', fontWeight: 700, color: 'white', flexShrink: 0 }}>{initials(t)}</div>
                                    }
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.first_name} {t.last_name}</div>
                                        <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: 2, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {t.subject_specialization ?? (t.role_name?.replace('_', ' ') ?? 'Teacher')}
                                        </div>
                                        <span style={{ marginTop: 6, display: 'inline-block', fontSize: '0.7rem', padding: '2px 8px', borderRadius: 20, background: t.is_active ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)', color: t.is_active ? 'var(--success)' : 'var(--danger)', fontWeight: 600 }}>
                                            {t.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>

                                {/* Info rows */}
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: '0.8rem', color: 'var(--text-secondary)', marginBottom: 16 }}>
                                    {t.email && <div style={{ display: 'flex', alignItems: 'center', gap: 7, overflow: 'hidden' }}><Mail size={13} /><span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.email}</span></div>}
                                    {t.phone && <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}><Phone size={13} /><span>{t.phone}</span></div>}
                                    {t.qualification && <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}><Award size={13} /><span>{t.qualification}{t.experience_years ? ` · ${t.experience_years}y exp` : ''}</span></div>}
                                </div>

                                {/* Stats row */}
                                <div style={{ display: 'flex', gap: 0, borderTop: '1px solid var(--bg-border)', paddingTop: 12, justifyContent: 'space-around', textAlign: 'center' }}>
                                    {[
                                        { label: 'Classes', value: t.class_teacher_assignments ?? 0 },
                                        { label: 'Subjects', value: t.subject_assignments ?? 0 },
                                        { label: 'Periods/wk', value: t.timetable_periods ?? 0 },
                                    ].map(({ label, value }) => (
                                        <div key={label}>
                                            <div style={{ fontWeight: 800, fontSize: '1.1rem', color: 'var(--text-primary)' }}>{value}</div>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)', marginTop: 1 }}>{label}</div>
                                        </div>
                                    ))}
                                </div>

                                {/* Actions */}
                                <div style={{ display: 'flex', gap: 6, marginTop: 14 }}>
                                    <button className="btn btn-secondary btn-sm" style={{ flex: 1 }} onClick={() => setViewTeacher(t)}><Eye size={13} /> View</button>
                                    <button className="btn btn-secondary btn-sm" style={{ flex: 1 }} onClick={() => openEdit(t)}><Pencil size={13} /> Edit</button>
                                    <button className="btn btn-danger btn-sm btn-icon" onClick={() => handleDelete(t.id)}><Trash2 size={14} /></button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
                <div style={{ display: 'flex', justifyContent: 'center', gap: 8, marginTop: 20 }}>
                    <button className="btn btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>← Prev</button>
                    <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem', alignSelf: 'center' }}>Page {page} / {totalPages}</span>
                    <button className="btn btn-secondary btn-sm" disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>Next →</button>
                </div>
            )}

            {/* Add / Edit Modal */}
            <Modal isOpen={showAdd} onClose={() => setShowAdd(false)}
                title={editTeacher ? `Edit: ${editTeacher.first_name} ${editTeacher.last_name}` : 'Add New Teacher'}
                maxWidth={560}
                footer={<><button className="btn btn-secondary" onClick={() => setShowAdd(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : (editTeacher ? 'Save Changes' : 'Create Teacher')}</button></>}>
                <div className="grid-2">
                    <div className="form-group"><label>First Name *</label><input className="input" value={form.first_name} onChange={e => f('first_name', e.target.value)} placeholder="First name" /></div>
                    <div className="form-group"><label>Last Name *</label><input className="input" value={form.last_name} onChange={e => f('last_name', e.target.value)} placeholder="Last name" /></div>
                </div>
                
                <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: 16, marginBottom: 16 }}>
                    <div className="form-group"><label>Profile Picture</label>
                        <input type="file" accept="image/*" onChange={e => {
                            if (e.target.files && e.target.files[0]) {
                                setProfileImageFile(e.target.files[0]);
                            }
                        }} style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '6px 10px', fontSize: '0.85rem', color: 'var(--text-primary)', outline: 'none' }} />
                    </div>
                </div>

                <div className="grid-2">
                    <div className="form-group"><label>Email *</label><input className="input" type="email" value={form.email} onChange={e => f('email', e.target.value)} placeholder="teacher@school.com" /></div>
                    <div className="form-group"><label>Phone *</label><input className="input" value={form.phone} onChange={e => f('phone', e.target.value)} placeholder="+91 …" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Role *</label>
                        <select value={form.role_id} onChange={e => f('role_id', e.target.value)}>
                            {ROLES.map(r => <option key={r.id} value={r.id}>{r.label}</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label>Subject Specialization</label><input className="input" value={form.subject_specialization} onChange={e => f('subject_specialization', e.target.value)} placeholder="e.g. Mathematics" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Qualification</label><input className="input" value={form.qualification} onChange={e => f('qualification', e.target.value)} placeholder="e.g. M.Sc, B.Ed" /></div>
                    <div className="form-group"><label>Experience (years)</label><input className="input" type="number" min="0" value={form.experience_years} onChange={e => f('experience_years', e.target.value)} placeholder="5" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Joining Date</label><input className="input" type="date" value={form.joining_date} onChange={e => f('joining_date', e.target.value)} /></div>
                </div>
                <div className="form-group"><label>Address</label><textarea value={form.address} onChange={e => f('address', e.target.value)} rows={2} placeholder="Full address" /></div>
            </Modal>

            {/* View Modal */}
            {viewTeacher && (
                <Modal isOpen={!!viewTeacher} onClose={() => setViewTeacher(null)} title="Teacher Details" maxWidth={460}
                    footer={<><button className="btn btn-secondary" onClick={() => setViewTeacher(null)}>Close</button><button className="btn btn-primary" onClick={() => { openEdit(viewTeacher); setViewTeacher(null); }}>Edit</button></>}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        {[
                            ['Full Name', `${viewTeacher.first_name} ${viewTeacher.last_name}`],
                            ['Employee ID', viewTeacher.employee_id ?? '—'],
                            ['Email', viewTeacher.email ?? '—'],
                            ['Phone', viewTeacher.phone ?? '—'],
                            ['Qualification', viewTeacher.qualification ?? '—'],
                            ['Specialization', viewTeacher.subject_specialization ?? '—'],
                            ['Experience', viewTeacher.experience_years ? `${viewTeacher.experience_years} years` : '—'],
                            ['Joining Date', viewTeacher.joining_date ?? '—'],
                            ['Role', viewTeacher.role_name?.replace(/_/g, ' ') ?? '—'],
                            ['Status', viewTeacher.is_active ? 'Active' : 'Inactive'],
                        ].map(([l, v]) => (
                            <div key={l}><div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginBottom: 2, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{l}</div><div style={{ fontSize: '0.875rem', color: 'var(--text-primary)', fontWeight: 500, textTransform: 'capitalize' }}>{v}</div></div>
                        ))}
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Teachers;
