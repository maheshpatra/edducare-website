import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Edit, Clock, Users, ShieldAlert, Trash2 } from 'lucide-react';
import { superadminService } from '../../api/superadmin';
import Modal from '../../components/Modal';
import toast from 'react-hot-toast';

interface PackageType {
    id: number;
    name: string;
    description: string | null;
    price: string;
    duration_months: number;
    max_students: number | null;
    max_teachers: number | null;
    features: string[] | string | null;
    is_active: number;
}

const SuperAdminPackages: React.FC = () => {
    const [packages, setPackages] = useState<PackageType[]>([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [editingPackage, setEditingPackage] = useState<PackageType | null>(null);
    const [form, setForm] = useState({
        name: '', description: '', price: '', duration_months: 12,
        max_students: '', max_teachers: '', features: ''
    });
    const [saving, setSaving] = useState(false);

    const loadPackages = useCallback(async () => {
        setLoading(true);
        try {
            const res = await superadminService.getPackages();
            setPackages(res.data.data || []);
        } catch (err) {
            toast.error('Failed to load packages');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { loadPackages(); }, [loadPackages]);

    const handleSave = async () => {
        if (!form.name || !form.price || !form.duration_months) {
            toast.error('Name, Price, and Duration are required');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                ...form,
                features: form.features ? form.features.split(',').map(f => f.trim()) : []
            };
            if (editingPackage) {
                await superadminService.updatePackage({ id: editingPackage.id, ...payload });
                toast.success('Package updated');
            } else {
                await superadminService.createPackage(payload);
                toast.success('Package created');
            }
            setShowModal(false);
            loadPackages();
        } catch (err) {
            toast.error('Failed to save package');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this package? This will fail if any schools are using it.')) return;
        try {
            await superadminService.deletePackage(id);
            toast.success('Package deleted');
            loadPackages();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to delete package');
        }
    };

    const openEdit = (p: PackageType) => {
        setEditingPackage(p);
        setForm({
            name: p.name,
            description: p.description || '',
            price: p.price,
            duration_months: p.duration_months,
            max_students: String(p.max_students || ''),
            max_teachers: String(p.max_teachers || ''),
            features: Array.isArray(p.features) ? p.features.join(', ') : (typeof p.features === 'string' ? JSON.parse(p.features).join(', ') : '')
        });
        setShowModal(true);
    };

    const openAdd = () => {
        setEditingPackage(null);
        setForm({ name: '', description: '', price: '', duration_months: 12, max_students: '', max_teachers: '', features: '' });
        setShowModal(true);
    };

    return (
        <div className="fade-in">
            <div className="toolbar">
                <div style={{ fontSize: '0.875rem', color: 'var(--text-muted)' }}>
                    Manage subscription plans and pricing for all schools.
                </div>
                <button className="btn btn-primary" onClick={openAdd}>
                    <Plus size={18} /> New Package
                </button>
            </div>

            <div className="grid-3" style={{ marginTop: 24, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24 }}>
                {loading ? (
                    <div className="loading-container">Loading...</div>
                ) : packages.map(p => (
                    <div key={p.id} className="card" style={{ padding: 0, position: 'relative', overflow: 'hidden' }}>
                        <div style={{ background: 'var(--primary-glow)', padding: '24px 20px', textAlign: 'center' }}>
                            <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--primary-light)' }}>{p.name}</div>
                            <div style={{ fontSize: '2rem', fontWeight: 900, color: 'var(--text-primary)', marginTop: 10 }}>₹{Number(p.price).toLocaleString('en-IN')}</div>
                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>for {p.duration_months} months</div>
                        </div>
                        <div style={{ padding: 24 }}>
                            <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginBottom: 20 }}>{p.description || 'No description provided.'}</p>

                            <ul style={{ listStyle: 'none', padding: 0, margin: 0, fontSize: '0.875rem', display: 'flex', flexDirection: 'column', gap: 12 }}>
                                <li style={{ display: 'flex', alignItems: 'center', gap: 10 }}><Users size={16} className="text-muted" /> Max Students: <strong>{p.max_students || 'Unlimited'}</strong></li>
                                <li style={{ display: 'flex', alignItems: 'center', gap: 10 }}><ShieldAlert size={16} className="text-muted" /> Max Teachers: <strong>{p.max_teachers || 'Unlimited'}</strong></li>
                                <li style={{ display: 'flex', alignItems: 'center', gap: 10 }}><Clock size={16} className="text-muted" /> Duration: <strong>{p.duration_months} months</strong></li>
                            </ul>

                            <div style={{ marginTop: 24, padding: '16px 0', borderTop: '1px solid var(--bg-border)', display: 'flex', gap: 10 }}>
                                <button className="btn btn-secondary" style={{ flex: 1 }} onClick={() => openEdit(p)}>
                                    <Edit size={16} /> Edit
                                </button>
                                <button className="btn btn-danger btn-icon" onClick={() => handleDelete(p.id)}>
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Add/Edit Modal */}
            <Modal
                isOpen={showModal}
                onClose={() => setShowModal(false)}
                title={editingPackage ? `Edit Package: ${editingPackage.name}` : 'Create New Package'}
                maxWidth={600}
                footer={
                    <>
                        <button className="btn btn-secondary" onClick={() => setShowModal(false)}>Cancel</button>
                        <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                            {saving ? 'Saving...' : 'Save Package'}
                        </button>
                    </>
                }
            >
                <div className="form-group"><label>Package Name *</label><input className="input" placeholder="e.g. Premium Plan" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Price (₹) *</label><input className="input" type="number" placeholder="499.99" value={form.price} onChange={e => setForm({ ...form, price: e.target.value })} /></div>
                    <div className="form-group"><label>Duration (Months) *</label><input className="input" type="number" placeholder="12" value={form.duration_months} onChange={e => setForm({ ...form, duration_months: Number(e.target.value) })} /></div>
                </div>
                <div className="grid-2">
                    <div className="form-group"><label>Max Students (Optional)</label><input className="input" type="number" placeholder="Unlimited if empty" value={form.max_students} onChange={e => setForm({ ...form, max_students: e.target.value })} /></div>
                    <div className="form-group"><label>Max Teachers (Optional)</label><input className="input" type="number" placeholder="Unlimited if empty" value={form.max_teachers} onChange={e => setForm({ ...form, max_teachers: e.target.value })} /></div>
                </div>
                <div className="form-group"><label>Features (Comma separated)</label><textarea className="input" rows={2} placeholder="Attendance, Library, Exams, ..." value={form.features} onChange={e => setForm({ ...form, features: e.target.value })} /></div>
                <div className="form-group"><label>Description</label><textarea className="input" rows={2} placeholder="Short description of the plan..." value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} /></div>
            </Modal>
        </div>
    );
};

export default SuperAdminPackages;
