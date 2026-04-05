import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Eye, Pencil, Trash2, ChevronLeft, ChevronRight, Filter } from 'lucide-react';
import { studentService, classService, academicService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

// ─── Types ────────────────────────────────────────────────────────────────────
interface Student {
    id: number;
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
    admission_number?: string;
    student_id?: string;
    class_id?: number;
    class_name?: string;
    section_id?: number;
    section_name?: string;
    gender?: string;
    dob?: string;
    father_name?: string;
    mother_name?: string;
    father_phone?: string;
    address?: string;
    caste?: string;
    status?: string;
    is_active?: number;
    roll_number?: string;
    profile_image?: string | null;
}

interface Pagination {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

const EMPTY_FORM = {
    first_name: '', last_name: '', email: '', phone: '',
    gender: 'male', dob: '', class_id: '', section_id: '',
    father_name: '', mother_name: '', father_phone: '',
    address: '', caste: '', admission_number: '',
};

const GENDERS = ['male', 'female', 'other'];
const CASTES = ['General', 'OBC', 'SC', 'ST', 'EWS'];

const Students: React.FC = () => {
    const [students, setStudents] = useState<Student[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [academicYears, setAcademicYears] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState<Pagination>({ current_page: 1, per_page: 20, total: 0, total_pages: 0 });

    const [classSections, setClassSections] = useState<any[]>([]);
    const [profileImageFile, setProfileImageFile] = useState<File | null>(null);

    // Filters
    const [search, setSearch] = useState('');
    const [classFilter, setClassFilter] = useState('');
    const [sectionFilter, setSectionFilter] = useState('');
    const [filterSections, setFilterSections] = useState<any[]>([]);
    const [page, setPage] = useState(1);

    // Modals
    const [viewStudent, setViewStudent] = useState<Student | null>(null);
    const [editStudent, setEditStudent] = useState<Student | null>(null);
    const [showAdd, setShowAdd] = useState(false);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);

    // ─── Load support data ──────────────────────────────────────────────────────
    useEffect(() => {
        classService.list()
            .then(r => setClasses(r.data?.data ?? []))
            .catch(() => { });
        academicService.list()
            .then(r => setAcademicYears(r.data?.data ?? []))
            .catch(() => { });
    }, []);

    // ─── Load students ──────────────────────────────────────────────────────────
    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await studentService.list({
                page,
                limit: 20,
                ...(search ? { search } : {}),
                ...(classFilter ? { class_id: classFilter } : {}),
                ...(sectionFilter ? { section_id: sectionFilter } : {}),
            });
            const d = res.data;
            setStudents(d?.data ?? []);
            if (d?.pagination) setPagination(d.pagination);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load students');
            setStudents([]);
        } finally {
            setLoading(false);
        }
    }, [page, search, classFilter, sectionFilter]);

    useEffect(() => { load(); }, [load]);

    // ─── Debounced search ───────────────────────────────────────────────────────
    useEffect(() => {
        const t = setTimeout(() => setPage(1), 350);
        return () => clearTimeout(t);
    }, [search]);

    // ─── Load Sections dynamically for Filter ────────────────────────────────────
    useEffect(() => {
        if (classFilter) {
            classService.sections(classFilter)
                .then(r => setFilterSections(r.data?.data ?? []))
                .catch(() => setFilterSections([]));
        } else {
            setFilterSections([]);
            setSectionFilter('');
        }
    }, [classFilter]);

    // ─── Load Sections dynamically when class_id changes ────────────────────────
    useEffect(() => {
        if (form.class_id) {
            classService.sections(form.class_id)
                .then(r => setClassSections(r.data?.data ?? []))
                .catch(() => setClassSections([]));
        } else {
            setClassSections([]);
        }
    }, [form.class_id]);

    // ─── Helpers ────────────────────────────────────────────────────────────────
    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const openAdd = () => {
        setForm({ ...EMPTY_FORM });
        setEditStudent(null);
        setProfileImageFile(null);
        setShowAdd(true);
    };

    const openEdit = (s: Student) => {
        setForm({
            first_name: s.first_name,
            last_name: s.last_name,
            email: s.email ?? '',
            phone: s.phone ?? '',
            gender: s.gender ?? 'male',
            dob: s.dob ?? '',
            class_id: String(s.class_id ?? ''),
            section_id: String(s.section_id ?? ''),
            father_name: s.father_name ?? '',
            mother_name: s.mother_name ?? '',
            father_phone: s.father_phone ?? '',
            address: s.address ?? '',
            caste: s.caste ?? '',
            admission_number: s.admission_number || s.student_id || '',
        });
        setEditStudent(s);
        setProfileImageFile(null);
        setShowAdd(true);
    };

    const handleSave = async () => {
        if (!form.first_name || !form.last_name) { toast.error('First and last name are required'); return; }
        setSaving(true);
        try {
            if (editStudent) {
                await studentService.update({ id: editStudent.id, ...form });
                toast.success('Student updated successfully');
            } else {
                let payload: any = form;
                // If admission_number is provided in frontend form, send it as student_id to backend
                if (form.admission_number) {
                    payload = { ...form, student_id: form.admission_number };
                }
                if (profileImageFile) {
                    payload = new FormData();
                    Object.entries(form).forEach(([k, v]) => payload.append(k, String(v)));
                    if (form.admission_number) {
                        payload.append('student_id', form.admission_number);
                    }
                    payload.append('profile_image', profileImageFile);
                }
                await studentService.create(payload);
                toast.success('Student added successfully');
            }
            setShowAdd(false);
            load();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this student? This action cannot be undone.')) return;
        try {
            await studentService.delete(id);
            toast.success('Student deleted');
            load();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Delete failed');
        }
    };

    const avatar = (s: Student) => {
        const initials = `${s.first_name[0] ?? ''}${s.last_name[0] ?? ''}`.toUpperCase();
        const hue = (s.id * 37) % 360;
        return { initials, bg: `hsl(${hue},55%,35%)` };
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1, flexWrap: 'wrap' }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 300 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder="Search students…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                        />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px' }}>
                        <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                        <select
                            value={classFilter}
                            onChange={e => { setClassFilter(e.target.value); setSectionFilter(''); setPage(1); }}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 120 }}
                        >
                            <option value="">All Classes</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px', opacity: classFilter ? 1 : 0.5, pointerEvents: classFilter ? 'auto' : 'none' }}>
                        <select
                            value={sectionFilter}
                            onChange={e => { setSectionFilter(e.target.value); setPage(1); }}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 120 }}
                            disabled={!classFilter}
                        >
                            <option value="">All Sections</option>
                            {filterSections.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    {pagination.total > 0 && (
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>
                            {pagination.total.toLocaleString()} students
                        </span>
                    )}
                    <button className="btn btn-primary" onClick={openAdd}><Plus size={16} />Add Student</button>
                </div>
            </div>

            {/* Table */}
            <div className="card">
                <div className="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th><th>Student</th><th>Class</th><th>Admission No.</th>
                                <th>Roll No.</th><th>Phone</th><th>Gender</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={9} style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : students.length === 0 ? (
                                <tr><td colSpan={9}><div className="empty-state"><Search size={36} /><p>No students found</p></div></td></tr>
                            ) : students.map((s, i) => {
                                const av = avatar(s);
                                const isActive = s.status === 'active' || s.is_active === 1;
                                return (
                                    <tr key={s.id}>
                                        <td style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>
                                            {(page - 1) * 20 + i + 1}
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                {s.profile_image
                                                    ? <img src={s.profile_image} alt="" style={{ width: 34, height: 34, borderRadius: '50%', objectFit: 'cover' }} />
                                                    : <div style={{ width: 34, height: 34, borderRadius: '50%', background: av.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.72rem', fontWeight: 700, color: 'white', flexShrink: 0 }}>{av.initials}</div>
                                                }
                                                <div>
                                                    <div style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{s.first_name} {s.last_name}</div>
                                                    {s.email && <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{s.email}</div>}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {s.class_name
                                                ? <span className="badge badge-primary">{s.class_name}{s.section_name ? ` ${s.section_name}` : ''}</span>
                                                : <span style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>—</span>
                                            }
                                        </td>
                                        <td style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{s.admission_number ?? s.student_id ?? '—'}</td>
                                        <td style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{s.roll_number ?? '—'}</td>
                                        <td style={{ fontSize: '0.82rem' }}>{s.phone ?? s.father_phone ?? '—'}</td>
                                        <td style={{ textTransform: 'capitalize', fontSize: '0.82rem' }}>{s.gender ?? '—'}</td>
                                        <td><span className={`badge ${isActive ? 'badge-success' : 'badge-danger'}`}>{isActive ? 'Active' : 'Inactive'}</span></td>
                                        <td>
                                            <div style={{ display: 'flex', gap: 6 }}>
                                                <button className="btn btn-secondary btn-sm btn-icon" onClick={() => setViewStudent(s)} title="View"><Eye size={14} /></button>
                                                <button className="btn btn-secondary btn-sm btn-icon" onClick={() => openEdit(s)} title="Edit"><Pencil size={14} /></button>
                                                <button className="btn btn-danger btn-sm btn-icon" onClick={() => handleDelete(s.id)} title="Delete"><Trash2 size={14} /></button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {pagination.total_pages > 1 && (
                    <div style={{ display: 'flex', gap: 8, padding: '14px 20px', borderTop: '1px solid var(--bg-border)', justifyContent: 'flex-end', alignItems: 'center' }}>
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginRight: 8 }}>
                            Page {pagination.current_page} of {pagination.total_pages}
                        </span>
                        <button className="btn btn-secondary btn-sm btn-icon" disabled={page <= 1} onClick={() => setPage(p => p - 1)}><ChevronLeft size={14} /></button>
                        <button className="btn btn-secondary btn-sm btn-icon" disabled={page >= pagination.total_pages} onClick={() => setPage(p => p + 1)}><ChevronRight size={14} /></button>
                    </div>
                )}
            </div>

            {/* Add/Edit Modal */}
            <Modal
                isOpen={showAdd}
                onClose={() => setShowAdd(false)}
                title={editStudent ? `Edit: ${editStudent.first_name} ${editStudent.last_name}` : 'Add New Student'}
                maxWidth={600}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowAdd(false)}>Cancel</button>
                        <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                            {saving ? <div className="spinner" /> : (editStudent ? 'Save Changes' : 'Create Student')}
                        </button>
                    </>
                }
            >
                <div className="grid-2">
                    <div className="form-group"><label>First Name *</label><input className="input" value={form.first_name} onChange={e => f('first_name', e.target.value)} placeholder="First name" /></div>
                    <div className="form-group"><label>Last Name *</label><input className="input" value={form.last_name} onChange={e => f('last_name', e.target.value)} placeholder="Last name" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Email</label><input className="input" type="email" value={form.email} onChange={e => f('email', e.target.value)} placeholder="student@school.com" /></div>
                    <div className="form-group"><label>Phone</label><input className="input" value={form.phone} onChange={e => f('phone', e.target.value)} placeholder="+91 …" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Gender</label>
                        <select value={form.gender} onChange={e => f('gender', e.target.value)}>
                            {GENDERS.map(g => <option key={g}>{g}</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label>Date of Birth</label><input className="input" type="date" value={form.dob} onChange={e => f('dob', e.target.value)} /></div>
                </div>
                {!editStudent && (
                    <div className="grid-2">
                        <div className="form-group">
                            <label>Admission No. (Leave empty to auto-generate)</label>
                            <input className="input" value={form.admission_number || ''} onChange={e => f('admission_number', e.target.value)} placeholder="e.g. ADM26001" />
                        </div>
                    </div>
                )}
                <div className="grid-2">
                    <div className="form-group"><label>Class</label>
                        <select value={form.class_id} onChange={e => f('class_id', e.target.value)}>
                            <option value="">Select class</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label>Section</label>
                        <select value={form.section_id} onChange={e => f('section_id', e.target.value)} disabled={!form.class_id}>
                            <option value="">Select section</option>
                            {classSections.map((s: any) => <option key={s.id} value={s.id}>{s.name} (Capacity: {s.capacity || 30})</option>)}
                        </select>
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                    <div className="form-group"><label>Profile Picture</label>
                        <input type="file" accept="image/*" onChange={e => {
                            if (e.target.files && e.target.files[0]) {
                                setProfileImageFile(e.target.files[0]);
                            }
                        }} style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '6px 10px', fontSize: '0.85rem', color: 'var(--text-primary)', outline: 'none' }} />
                    </div>
                </div>
                
                <h4 style={{ margin: '10px 0', fontSize: '1rem', color: 'var(--text-primary)' }}>Parent/Guardian Info</h4>
                <div className="grid-2">
                    <div className="form-group"><label>Father's Name</label><input className="input" value={form.father_name} onChange={e => f('father_name', e.target.value)} placeholder="Father's full name" /></div>
                    <div className="form-group"><label>Mother's Name</label><input className="input" value={form.mother_name} onChange={e => f('mother_name', e.target.value)} placeholder="Mother's full name" /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Father's Phone</label><input className="input" value={form.father_phone} onChange={e => f('father_phone', e.target.value)} placeholder="+91 …" /></div>
                    <div className="form-group"><label>Category / Caste</label>
                        <select value={form.caste} onChange={e => f('caste', e.target.value)}>
                            <option value="">Select</option>
                            {CASTES.map(c => <option key={c}>{c}</option>)}
                        </select>
                    </div>
                </div>
                <div className="form-group"><label>Address</label><textarea value={form.address} onChange={e => f('address', e.target.value)} rows={2} placeholder="Full address" /></div>
            </Modal>

            {/* View Modal */}
            {viewStudent && (
                <Modal isOpen={!!viewStudent} onClose={() => setViewStudent(null)} title="Student Details" maxWidth={480}
                    footer={<><button className="btn btn-secondary" onClick={() => setViewStudent(null)}>Close</button><button className="btn btn-primary" onClick={() => { openEdit(viewStudent); setViewStudent(null); }}>Edit</button></>}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
                        {[
                            ['Full Name', `${viewStudent.first_name} ${viewStudent.last_name}`],
                            ['Email', viewStudent.email ?? '—'],
                            ['Phone', viewStudent.phone ?? '—'],
                            ['Gender', viewStudent.gender ?? '—'],
                            ['DOB', viewStudent.dob ?? '—'],
                            ['Class', `${viewStudent.class_name ?? '—'}${viewStudent.section_name ? ' – ' + viewStudent.section_name : ''}`],
                            ['Admission No.', viewStudent.admission_number ?? '—'],
                            ['Category', viewStudent.caste ?? '—'],
                            ['Father', viewStudent.father_name ?? '—'],
                            ['Mother', viewStudent.mother_name ?? '—'],
                            ['Address', viewStudent.address ?? '—'],
                            ['Status', viewStudent.status ?? (viewStudent.is_active ? 'active' : 'inactive')],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginBottom: 2, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</div>
                                <div style={{ fontSize: '0.875rem', color: 'var(--text-primary)', fontWeight: 500, textTransform: 'capitalize' }}>{value}</div>
                            </div>
                        ))}
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Students;
