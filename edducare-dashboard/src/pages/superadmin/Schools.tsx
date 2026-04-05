import React, { useEffect, useState, useCallback, useRef } from 'react';
import {
    Plus, Search, ShieldCheck, ShieldAlert, Edit, Package,
    Mail, Phone, MapPin, Upload, X, User, CheckCircle, School as SchoolIcon,
    Calendar
} from 'lucide-react';
import { superadminService } from '../../api/superadmin';
import Modal from '../../components/Modal';
import toast from 'react-hot-toast';

interface School {
    id: number;
    name: string;
    code: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    principal_name: string | null;
    school_type: string | null;
    established_year: number | null;
    logo: string | null;
    package_id: number | null;
    package_name: string | null;
    package_price: string | null;
    is_blocked: number;
    is_active: number;
    created_at: string;
    student_count: number;
    teacher_count: number;
}

interface PackageType {
    id: number;
    name: string;
    price: string;
}

const SCHOOL_TYPES = [
    'General', 'Primary', 'Middle', 'Secondary',
    'Higher Secondary', 'Private', 'Government', 'Vocational'
];

const SuperAdminSchools: React.FC = () => {
    const [schools, setSchools] = useState<School[]>([]);
    const [packages, setPackages] = useState<PackageType[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState({ total_pages: 1 });

    const [showAdd, setShowAdd] = useState(false);
    const [editSchool, setEditSchool] = useState<School | null>(null);
    const [showPackageModal, setShowPackageModal] = useState<School | null>(null);

    // Form and Logo
    const [form, setForm] = useState({
        name: '', code: '', email: '', phone: '',
        address: '', package_id: '', principal_name: '', school_type: 'General', established_year: ''
    });
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [saving, setSaving] = useState(false);

    const loadSchools = useCallback(async () => {
        setLoading(true);
        try {
            const res = await superadminService.getSchools(page, 10);
            setSchools(res.data.data || []);
            setPagination(res.data.pagination);
        } catch (err) {
            toast.error('Failed to load schools');
        } finally {
            setLoading(false);
        }
    }, [page]);

    const loadPackages = useCallback(async () => {
        try {
            const res = await superadminService.getPackages();
            setPackages(res.data.data || []);
        } catch (err) {
            console.error('Failed to load packages');
        }
    }, []);

    useEffect(() => {
        loadSchools();
        loadPackages();
    }, [loadSchools, loadPackages]);

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setLogoFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setLogoPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const handleCreateSchool = async () => {
        if (!form.name || !form.package_id) {
            toast.error('Name and Package are required');
            return;
        }

        const formData = new FormData();
        formData.append('name', form.name);
        formData.append('code', form.code);
        formData.append('package_id', form.package_id);
        formData.append('email', form.email);
        formData.append('phone', form.phone);
        formData.append('address', form.address);
        formData.append('principal_name', form.principal_name);
        formData.append('school_type', form.school_type);
        formData.append('established_year', form.established_year);
        if (form.start_grade) formData.append('start_grade', form.start_grade);
        if (form.end_grade) formData.append('end_grade', form.end_grade);
        if (logoFile) formData.append('logo', logoFile);

        setSaving(true);
        try {
            await superadminService.createSchool(formData);
            toast.success('School registered, Principal account created, and Classes provisioned!');
            setShowAdd(false);
            setForm({
                name: '', code: '', email: '', phone: '', address: '',
                package_id: '', principal_name: '', school_type: 'General',
                established_year: '', start_grade: '', end_grade: ''
            });
            setLogoFile(null);
            setLogoPreview(null);
            loadSchools();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to create school');
        } finally {
            setSaving(false);
        }
    };

    const handleUpdateSchool = async () => {
        if (!editSchool) return;
        setSaving(true);
        try {
            await superadminService.updateSchool({
                id: editSchool.id,
                name: editSchool.name,
                email: editSchool.email,
                phone: editSchool.phone,
                address: editSchool.address,
                principal_name: editSchool.principal_name,
                school_type: editSchool.school_type,
                established_year: editSchool.established_year,
                is_active: editSchool.is_active
            });
            toast.success('School updated successfully');
            setEditSchool(null);
            loadSchools();
        } catch (err) {
            toast.error('Failed to update school');
        } finally {
            setSaving(false);
        }
    };

    const handleToggleBlock = async (school: School) => {
        const action = school.is_blocked ? 'unblock' : 'block';
        if (!confirm(`Are you sure you want to ${action} ${school.name}?`)) return;

        try {
            await superadminService.updateSchool({
                id: school.id,
                is_blocked: !school.is_blocked
            });
            toast.success(`School ${action}ed successfully`);
            loadSchools();
        } catch (err) {
            toast.error(`Failed to ${action} school`);
        }
    };

    const handleUpdatePackage = async (schoolId: number, packageId: number) => {
        try {
            await superadminService.updateSchool({
                id: schoolId,
                package_id: packageId
            });
            toast.success('Package updated');
            setShowPackageModal(null);
            loadSchools();
        } catch (err) {
            toast.error('Failed to update package');
        }
    };

    const filteredSchools = schools.filter(s =>
        s.name.toLowerCase().includes(search.toLowerCase()) ||
        s.code.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <div className="fade-in">
            <div className="toolbar">
                <div className="search-bar" style={{ maxWidth: 400 }}>
                    <Search size={18} />
                    <input
                        placeholder="Search by school name or code..."
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                    />
                </div>
                <button className="btn btn-primary" onClick={() => setShowAdd(true)}>
                    <Plus size={18} /> Register New School
                </button>
            </div>

            <div className="card" style={{ marginTop: 20 }}>
                <div className="table-wrapper">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Logo</th>
                                <th>School Details</th>
                                <th>Contact</th>
                                <th>Package</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={6} style={{ textAlign: 'center', padding: 50 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : filteredSchools.length === 0 ? (
                                <tr><td colSpan={6} style={{ textAlign: 'center', padding: 50 }}>No schools found</td></tr>
                            ) : filteredSchools.map(s => (
                                <tr key={s.id}>
                                    <td style={{ width: 60 }}>
                                        <div style={{
                                            width: 44, height: 44, borderRadius: 12, overflow: 'hidden',
                                            border: '1px solid var(--bg-border)', background: 'var(--bg-elevated)',
                                            display: 'flex', alignItems: 'center', justifyContent: 'center'
                                        }}>
                                            {s.logo ? (
                                                <img src={`/backend/uploads/${s.logo}`} alt="Logo" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                            ) : (
                                                <SchoolIcon size={20} style={{ opacity: 0.3 }} />
                                            )}
                                        </div>
                                    </td>
                                    <td>
                                        <div style={{ fontWeight: 800, color: 'var(--text-primary)', fontSize: '0.95rem' }}>{s.name}</div>
                                        <div style={{ display: 'flex', gap: 6, marginTop: 4, flexWrap: 'wrap' }}>
                                            <span style={{ fontSize: '10px', fontWeight: 900, background: 'var(--primary-light)', color: 'white', padding: '1px 6px', borderRadius: 4, textTransform: 'uppercase' }}>{s.code}</span>
                                            <span style={{ fontSize: '10px', fontWeight: 700, background: 'var(--bg-elevated)', color: 'var(--text-muted)', padding: '1px 6px', borderRadius: 4, border: '1px solid var(--bg-border)' }}>{s.school_type || 'General'}</span>
                                            {s.established_year && (
                                                <span style={{ fontSize: '10px', fontWeight: 700, background: 'var(--success-faded)', color: 'var(--success)', padding: '1px 6px', borderRadius: 4, display: 'flex', alignItems: 'center', gap: 3 }}>
                                                    <Calendar size={10} /> Est. {s.established_year}
                                                </span>
                                            )}
                                        </div>
                                        {s.principal_name && (
                                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: 4, marginTop: 4, fontWeight: 500 }}>
                                                <User size={12} /> {s.principal_name}
                                            </div>
                                        )}
                                    </td>
                                    <td>
                                        <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <Mail size={12} style={{ color: 'var(--text-muted)' }} /> {s.email || '—'}
                                        </div>
                                        <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
                                            <Phone size={12} style={{ color: 'var(--text-muted)' }} /> {s.phone || '—'}
                                        </div>
                                    </td>
                                    <td>
                                        <div
                                            style={{ cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}
                                            onClick={() => setShowPackageModal(s)}
                                        >
                                            <span className="badge badge-primary">{s.package_name || 'No Package'}</span>
                                            <Package size={14} style={{ color: 'var(--text-muted)' }} />
                                        </div>
                                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4, fontWeight: 600 }}>₹{s.package_price}/yr</div>
                                    </td>
                                    <td>
                                        {s.is_blocked ? (
                                            <span className="badge badge-danger">Blocked</span>
                                        ) : (
                                            <span className="badge badge-success">Active</span>
                                        )}
                                    </td>
                                    <td style={{ textAlign: 'right' }}>
                                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                            <button className="btn-icon" onClick={() => setEditSchool(s)} title="Edit Details">
                                                <Edit size={16} />
                                            </button>
                                            <button
                                                className={`btn-icon ${s.is_blocked ? 'success' : 'danger'}`}
                                                onClick={() => handleToggleBlock(s)}
                                                title={s.is_blocked ? 'Unblock' : 'Block'}
                                            >
                                                {s.is_blocked ? <ShieldCheck size={16} /> : <ShieldAlert size={16} />}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal
                isOpen={showAdd}
                onClose={() => setShowAdd(false)}
                title="Register New School"
                maxWidth={600}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowAdd(false)}>Cancel</button>
                        <button className="btn btn-primary" onClick={handleCreateSchool} disabled={saving}>
                            {saving ? 'Registering...' : 'Register School'}
                        </button>
                    </>
                }
            >
                <div style={{ display: 'flex', gap: 24, marginBottom: 24 }}>
                    <div style={{
                        flexShrink: 0, width: 100, height: 100, borderRadius: 16,
                        border: '2px dashed var(--bg-border)',
                        background: logoPreview ? `url(${logoPreview}) center/cover` : 'var(--bg-elevated)',
                        display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                        cursor: 'pointer', position: 'relative'
                    }} onClick={() => fileInputRef.current?.click()}>
                        {!logoPreview && (
                            <>
                                <Upload size={24} style={{ opacity: 0.3 }} />
                                <span style={{ fontSize: 10, marginTop: 4, fontWeight: 700 }}>LOGO</span>
                            </>
                        )}
                        <input ref={fileInputRef} type="file" hidden accept="image/*" onChange={handleLogoChange} />
                    </div>
                    <div style={{ flex: 1 }}>
                        <div className="form-group"><label>School Name *</label><input className="input" placeholder="e.g. Green Valley Academy" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></div>
                        <div className="grid-2">
                            <div className="form-group">
                                <label>Principal Name</label>
                                <input className="input" placeholder="Name" value={form.principal_name} onChange={e => setForm({ ...form, principal_name: e.target.value })} />
                            </div>
                            <div className="form-group">
                                <label>Established Year</label>
                                <input className="input" type="number" placeholder="YYYY" value={form.established_year} onChange={e => setForm({ ...form, established_year: e.target.value ? parseInt(e.target.value) : '' })} />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid-2">
                    <div className="form-group">
                        <label>Unique Code</label>
                        <input className="input" placeholder="Auto-generated if empty" value={form.code} onChange={e => setForm({ ...form, code: e.target.value })} />
                    </div>
                    <div className="form-group">
                        <label>School Type</label>
                        <select className="input" value={form.school_type} onChange={e => setForm({ ...form, school_type: e.target.value })}>
                            {SCHOOL_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </div>
                </div>

                <div className="grid-2">
                    <div className="form-group">
                        <label>Subscription Plan *</label>
                        <select className="input" value={form.package_id} onChange={e => setForm({ ...form, package_id: e.target.value })}>
                            <option value="">Select Plan</option>
                            {packages.map(p => <option key={p.id} value={p.id}>{p.name} - ₹{p.price}</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label>Official Email *</label><input className="input" type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} /></div>
                </div>

                <div className="grid-2">
                    <div className="form-group"><label>Phone Number</label><input className="input" value={form.phone} onChange={e => setForm({ ...form, phone: e.target.value })} /></div>
                    <div className="form-group"><label>Address</label><input className="input" value={form.address} onChange={e => setForm({ ...form, address: e.target.value })} /></div>
                </div>

                <div style={{ marginTop: 24, padding: 20, background: 'var(--primary-glow)', borderRadius: 'var(--radius-lg)', border: '1px solid var(--primary-light)', opacity: 0.9 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                        <div style={{ width: 32, height: 32, borderRadius: '50%', background: 'var(--primary-light)', color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <Package size={16} />
                        </div>
                        <div>
                            <div style={{ fontWeight: 800, color: 'var(--text-primary)', fontSize: '0.9rem' }}>Auto-Provision Classes (Optional)</div>
                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Automatically create grade-level classes on registration</div>
                        </div>
                    </div>
                    <div className="grid-2">
                        <div className="form-group">
                            <label>Starting Grade</label>
                            <input className="input" type="number" placeholder="e.g. 1" value={form.start_grade} onChange={e => setForm({ ...form, start_grade: e.target.value })} />
                        </div>
                        <div className="form-group">
                            <label>Ending Grade</label>
                            <input className="input" type="number" placeholder="e.g. 10" value={form.end_grade} onChange={e => setForm({ ...form, end_grade: e.target.value })} />
                        </div>
                    </div>
                </div>
            </Modal>

            {editSchool && (
                <Modal
                    isOpen={!!editSchool}
                    onClose={() => setEditSchool(null)}
                    title={`Edit School: ${editSchool.name}`}
                    maxWidth={500}
                    footer={
                        <>
                            <button className="btn btn-secondary" onClick={() => setEditSchool(null)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleUpdateSchool} disabled={saving}>
                                {saving ? 'Saving...' : 'Save Changes'}
                            </button>
                        </>
                    }
                >
                    <div className="form-group"><label>School Name</label><input className="input" value={editSchool.name} onChange={e => setEditSchool({ ...editSchool, name: e.target.value })} /></div>
                    <div className="grid-2">
                        <div className="form-group"><label>Principal Name</label><input className="input" value={editSchool.principal_name || ''} onChange={e => setEditSchool({ ...editSchool, principal_name: e.target.value })} /></div>
                        <div className="form-group"><label>Established Year</label><input className="input" type="number" placeholder="YYYY" value={editSchool.established_year || ''} onChange={e => setEditSchool({ ...editSchool, established_year: e.target.value ? parseInt(e.target.value) : null })} /></div>
                    </div>
                    <div className="form-group">
                        <label>School Type</label>
                        <select className="input" value={editSchool.school_type || 'General'} onChange={e => setEditSchool({ ...editSchool, school_type: e.target.value })}>
                            {SCHOOL_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </div>
                    <div className="grid-2">
                        <div className="form-group"><label>Email</label><input className="input" value={editSchool.email || ''} onChange={e => setEditSchool({ ...editSchool, email: e.target.value })} /></div>
                        <div className="form-group"><label>Phone</label><input className="input" value={editSchool.phone || ''} onChange={e => setEditSchool({ ...editSchool, phone: e.target.value })} /></div>
                    </div>
                    <div className="form-group"><label>Address</label><textarea className="input" value={editSchool.address || ''} onChange={e => setEditSchool({ ...editSchool, address: e.target.value })} /></div>
                </Modal>
            )}

            {showPackageModal && (
                <Modal
                    isOpen={!!showPackageModal}
                    onClose={() => setShowPackageModal(null)}
                    title={`Update Plan: ${showPackageModal.name}`}
                    maxWidth={400}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        {packages.map(p => (
                            <div
                                key={p.id}
                                className={`card ${showPackageModal.package_id === p.id ? 'active-selection' : ''}`}
                                style={{
                                    padding: '16px 20px', cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                                    border: showPackageModal.package_id === p.id ? '2px solid var(--primary-light)' : '1px solid var(--bg-border)',
                                    background: showPackageModal.package_id === p.id ? 'var(--bg-elevated)' : 'var(--bg-card)'
                                }}
                                onClick={() => handleUpdatePackage(showPackageModal.id, p.id)}
                            >
                                <div>
                                    <div style={{ fontWeight: 800 }}>{p.name}</div>
                                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>₹{p.price}/year</div>
                                </div>
                                {showPackageModal.package_id === p.id && <CheckCircle size={20} style={{ color: 'var(--primary-light)' }} />}
                            </div>
                        ))}
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default SuperAdminSchools;
