import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Filter, Megaphone, Pencil, Trash2, Globe, Users, BookOpen } from 'lucide-react';
import { announcementService, classService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface Announcement {
    id: number;
    title: string;
    content: string;
    target_audience: string;
    priority: 'low' | 'medium' | 'high' | 'urgent';
    is_published: number;
    is_active: number;
    created_at: string;
    expires_at?: string;
    created_by_name?: string;
    class_id?: number;
    section_id?: number;
}

const PRIORITY_COLOR: Record<string, string> = {
    urgent: '#ef4444', high: '#f59e0b', medium: '#6366f1', low: '#10b981',
};
const AUDIENCE_ICON: Record<string, React.ReactNode> = {
    all: <Globe size={12} />, students: <Users size={12} />,
    teachers: <BookOpen size={12} />, parents: <Users size={12} />, specific_class: <BookOpen size={12} />,
};

const EMPTY_FORM = {
    title: '', content: '', target_audience: 'all',
    priority: 'medium', class_id: '', expires_at: '', is_published: '1',
};

const Announcements: React.FC = () => {
    const [list, setList] = useState<Announcement[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [priorityFilter, setPriorityFilter] = useState('');
    const [page, setPage] = useState(1);
    const [total, setTotal] = useState(0);

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<Announcement | null>(null);
    const [form, setForm] = useState({ ...EMPTY_FORM });
    const [saving, setSaving] = useState(false);

    // Load classes for the "specific_class" audience option
    useEffect(() => {
        classService.list().then(r => setClasses(r.data?.data ?? [])).catch(() => { });
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await announcementService.list({
                page, limit: 20,
                ...(search ? { search } : {}),
                ...(priorityFilter ? { priority: priorityFilter } : {}),
            });
            const d = res.data;
            setList(d?.data ?? []);
            setTotal(d?.pagination?.total ?? 0);
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Failed to load announcements');
        } finally { setLoading(false); }
    }, [page, search, priorityFilter]);

    useEffect(() => { load(); }, [load]);
    useEffect(() => { const t = setTimeout(() => setPage(1), 350); return () => clearTimeout(t); }, [search]);

    const f = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

    const openCreate = () => { setForm({ ...EMPTY_FORM }); setEditing(null); setShowCreate(true); };
    const openEdit = (a: Announcement) => {
        setForm({
            title: a.title, content: a.content,
            target_audience: a.target_audience, priority: a.priority,
            class_id: String(a.class_id ?? ''), expires_at: a.expires_at?.split('T')[0] ?? '',
            is_published: String(a.is_published ?? 1),
        });
        setEditing(a); setShowCreate(true);
    };

    const handleSave = async () => {
        if (!form.title || !form.content) { toast.error('Title and content are required'); return; }
        setSaving(true);
        try {
            const payload = {
                title: form.title, content: form.content,
                target_audience: form.target_audience as any,
                priority: form.priority as any,
                ...(form.class_id ? { class_id: Number(form.class_id) } : {}),
                ...(form.expires_at ? { expires_at: form.expires_at } : {}),
                is_published: form.is_published === '1',
            };
            if (editing) {
                await announcementService.update({ id: editing.id, ...payload });
                toast.success('Announcement updated');
            } else {
                await announcementService.create(payload);
                toast.success('Announcement published');
            }
            setShowCreate(false); load();
        } catch (err: any) {
            toast.error(err?.response?.data?.error ?? 'Save failed');
        } finally { setSaving(false); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this announcement?')) return;
        try { await announcementService.delete(id); toast.success('Deleted'); load(); }
        catch (err: any) { toast.error(err?.response?.data?.error ?? 'Delete failed'); }
    };

    const timeSince = (dt: string) => {
        const diff = Date.now() - new Date(dt).getTime();
        const h = Math.floor(diff / 3_600_000);
        if (h < 1) return 'Just now';
        if (h < 24) return `${h}h ago`;
        return `${Math.floor(h / 24)}d ago`;
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1 }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 300 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input placeholder="Search announcements…" value={search} onChange={e => setSearch(e.target.value)} />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px' }}>
                        <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                        <select value={priorityFilter} onChange={e => { setPriorityFilter(e.target.value); setPage(1); }}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 120 }}>
                            <option value="">All Priorities</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                    {total > 0 && <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>{total} announcements</span>}
                    <button className="btn btn-primary" onClick={openCreate}><Plus size={16} />New Announcement</button>
                </div>
            </div>

            {/* List */}
            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: 60 }}><div className="spinner" style={{ width: 36, height: 36 }} /></div>
            ) : list.length === 0 ? (
                <div className="card"><div className="empty-state"><Megaphone size={40} /><p>No announcements found</p></div></div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {list.map(a => {
                        const pc = PRIORITY_COLOR[a.priority] ?? 'var(--text-muted)';
                        return (
                            <div key={a.id} className="card" style={{ padding: 0, overflow: 'hidden', border: '1px solid var(--bg-border)' }}>
                                <div style={{ height: 4, background: pc }} />
                                <div style={{ padding: '16px 20px 20px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }}>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 8, flexWrap: 'wrap' }}>
                                                <span style={{ fontSize: '0.72rem', fontWeight: 700, padding: '3px 9px', borderRadius: 20, background: `${pc}20`, color: pc, textTransform: 'uppercase', letterSpacing: '0.04em' }}>{a.priority}</span>
                                                <span style={{ fontSize: '0.72rem', padding: '3px 9px', borderRadius: 20, background: 'var(--bg-elevated)', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: 4, textTransform: 'capitalize' }}>
                                                    {AUDIENCE_ICON[a.target_audience] ?? <Globe size={12} />}{a.target_audience.replace('_', ' ')}
                                                </span>
                                                {a.is_published ? (
                                                    <span style={{ fontSize: '0.72rem', padding: '3px 9px', borderRadius: 20, background: 'rgba(16,185,129,0.12)', color: 'var(--success)' }}>Published</span>
                                                ) : (
                                                    <span style={{ fontSize: '0.72rem', padding: '3px 9px', borderRadius: 20, background: 'rgba(245,158,11,0.12)', color: 'var(--warning)' }}>Draft</span>
                                                )}
                                            </div>
                                            <h3 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text-primary)', marginBottom: 8 }}>{a.title}</h3>
                                            <p style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', lineHeight: 1.6, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{a.content}</p>
                                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 10, display: 'flex', gap: 12 }}>
                                                <span>{a.created_by_name ?? 'Admin'}</span>
                                                <span>·</span>
                                                <span>{timeSince(a.created_at)}</span>
                                                {a.expires_at && <><span>·</span><span>Expires {new Date(a.expires_at).toLocaleDateString()}</span></>}
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                                            <button className="btn btn-secondary btn-sm btn-icon" onClick={() => openEdit(a)}><Pencil size={13} /></button>
                                            <button className="btn btn-danger btn-sm btn-icon" onClick={() => handleDelete(a.id)}><Trash2 size={13} /></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Pagination */}
            {Math.ceil(total / 20) > 1 && (
                <div style={{ display: 'flex', justifyContent: 'center', gap: 8, marginTop: 20 }}>
                    <button className="btn btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>← Prev</button>
                    <span style={{ color: 'var(--text-muted)', fontSize: '0.82rem', alignSelf: 'center' }}>Page {page} / {Math.ceil(total / 20)}</span>
                    <button className="btn btn-secondary btn-sm" disabled={page >= Math.ceil(total / 20)} onClick={() => setPage(p => p + 1)}>Next →</button>
                </div>
            )}

            {/* Create / Edit Modal */}
            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)}
                title={editing ? 'Edit Announcement' : 'New Announcement'} maxWidth={560}
                footer={<><button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <div className="spinner" /> : (editing ? 'Save Changes' : 'Publish')}</button></>}>
                <div className="form-group"><label>Title *</label><input className="input" value={form.title} onChange={e => f('title', e.target.value)} placeholder="Announcement title" /></div>
                <div className="form-group"><label>Content *</label><textarea value={form.content} onChange={e => f('content', e.target.value)} rows={4} placeholder="Announcement message…" /></div>
                <div className="grid-2">
                    <div className="form-group"><label>Target Audience</label>
                        <select value={form.target_audience} onChange={e => f('target_audience', e.target.value)}>
                            <option value="all">All</option>
                            <option value="students">Students</option>
                            <option value="teachers">Teachers</option>
                            <option value="parents">Parents</option>
                            <option value="specific_class">Specific Class</option>
                        </select>
                    </div>
                    <div className="form-group"><label>Priority</label>
                        <select value={form.priority} onChange={e => f('priority', e.target.value)}>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                {form.target_audience === 'specific_class' && (
                    <div className="form-group"><label>Class</label>
                        <select value={form.class_id} onChange={e => f('class_id', e.target.value)}>
                            <option value="">Select class</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                )}
                <div className="grid-2">
                    <div className="form-group"><label>Expires At</label><input className="input" type="date" value={form.expires_at} onChange={e => f('expires_at', e.target.value)} /></div>
                    <div className="form-group"><label>Publish Status</label>
                        <select value={form.is_published} onChange={e => f('is_published', e.target.value)}>
                            <option value="1">Published</option>
                            <option value="0">Draft</option>
                        </select>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default Announcements;
