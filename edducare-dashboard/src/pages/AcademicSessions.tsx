import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Calendar, CheckCircle } from 'lucide-react';
import { academicService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface AcademicYear {
    id: number;
    school_id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_current: number;
}

const EMPTY_FORM = { name: '', start_date: '', end_date: '', is_current: false };

const AcademicSessions: React.FC = () => {
    const [sessions, setSessions] = useState<AcademicYear[]>([]);
    const [loading, setLoading] = useState(true);

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<AcademicYear | null>(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await academicService.list();
            setSessions(res.data?.data ?? []);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load academic sessions');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    const f = (k: string, v: any) => setForm(p => ({ ...p, [k]: v }));

    const openCreate = () => { setForm({ ...EMPTY_FORM }); setEditing(null); setShowCreate(true); };
    const openEdit = (s: AcademicYear) => {
        setForm({
            name: s.name,
            start_date: s.start_date.split('T')[0],
            end_date: s.end_date.split('T')[0],
            is_current: s.is_current === 1
        });
        setEditing(s); setShowCreate(true);
    };

    const handleSave = async () => {
        if (!form.name || !form.start_date || !form.end_date) {
            toast.error('All fields except "Current Session" are required');
            return;
        }
        setSaving(true);
        try {
            if (editing) {
                await academicService.update({
                    id: editing.id,
                    name: form.name,
                    start_date: form.start_date,
                    end_date: form.end_date,
                    is_current: form.is_current
                });
                toast.success('Academic session updated');
            } else {
                await academicService.create({
                    name: form.name,
                    start_date: form.start_date,
                    end_date: form.end_date,
                    is_current: form.is_current
                } as any);
                toast.success('Academic session created');
            }
            setShowCreate(false);
            load();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Save failed');
        } finally {
            setSaving(false);
        }
    };

    const markCurrent = async (s: AcademicYear) => {
        if (s.is_current) return;
        try {
            toast.loading('Updating current session...', { id: 'mc' });
            await academicService.update({ id: s.id, is_current: true });
            toast.success('Current active session updated', { id: 'mc' });
            load();
        } catch (err: any) {
            toast.error('Failed to set current session', { id: 'mc' });
        }
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar" style={{ justifyContent: 'space-between' }}>
                <div style={{ fontSize: '1.2rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: 10 }}>
                    <Calendar size={22} style={{ color: 'var(--primary)' }} />
                    <span>Academic Sessions (Years)</span>
                </div>
                <button className="btn btn-primary" onClick={openCreate}><Plus size={16} />Add Session</button>
            </div>

            <div className="card">
                <div className="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Session Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={5} style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : sessions.length === 0 ? (
                                <tr><td colSpan={5}><div className="empty-state"><Calendar size={36} /><p>No academic sessions found</p></div></td></tr>
                            ) : (
                                sessions.map(s => (
                                    <tr key={s.id}>
                                        <td style={{ fontWeight: 600 }}>{s.name}</td>
                                        <td>{new Date(s.start_date).toLocaleDateString()}</td>
                                        <td>{new Date(s.end_date).toLocaleDateString()}</td>
                                        <td>
                                            {s.is_current ? (
                                                <span style={{ fontSize: '0.75rem', padding: '3px 10px', borderRadius: 20, background: 'rgba(16,185,129,0.15)', color: 'var(--success)', fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4, width: 'fit-content' }}>
                                                    <CheckCircle size={14} /> Current
                                                </span>
                                            ) : (
                                                <span style={{ fontSize: '0.75rem', padding: '3px 10px', borderRadius: 20, background: 'var(--bg-elevated)', color: 'var(--text-muted)' }}>
                                                    Past/Future
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', gap: 8 }}>
                                                {!s.is_current && (
                                                    <button className="btn btn-secondary btn-sm" onClick={() => markCurrent(s)}>Make Current</button>
                                                )}
                                                <button className="btn btn-secondary btn-sm" onClick={() => openEdit(s)}>Edit</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)}
                title={editing ? `Edit Session: ${editing.name}` : 'Add New Academic Session'} maxWidth={460}
                footer={<><button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : (editing ? 'Save Changes' : 'Create Session')}</button></>}>
                <div className="form-group">
                    <label>Session Name *</label>
                    <input className="input" value={form.name} onChange={e => f('name', e.target.value)} placeholder="e.g. 2023-2024" />
                </div>
                <div className="grid-2">
                    <div className="form-group">
                        <label>Start Date *</label>
                        <input className="input" type="date" value={form.start_date} onChange={e => f('start_date', e.target.value)} />
                    </div>
                    <div className="form-group">
                        <label>End Date *</label>
                        <input className="input" type="date" value={form.end_date} onChange={e => f('end_date', e.target.value)} />
                    </div>
                </div>
                <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 10 }}>
                    <input type="checkbox" id="is_current" checked={form.is_current} onChange={e => f('is_current', e.target.checked)} style={{ width: 16, height: 16 }} />
                    <label htmlFor="is_current" style={{ margin: 0, cursor: 'pointer' }}>Mark as Current Active Session</label>
                </div>
            </Modal>
        </div>
    );
};

export default AcademicSessions;
