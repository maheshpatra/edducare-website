import React, { useEffect, useState, useCallback } from 'react';
import { Search, Filter, ChevronLeft, ChevronRight, Eye, Mail, Phone, Calendar, User, ClipboardList, Clock } from 'lucide-react';
import { websiteService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface ContactMessage {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    subject: string;
    message: string;
    status: 'new' | 'read' | 'replied' | 'archived';
    created_at: string;
}

const ContactMessages: React.FC = () => {
    const [messages, setMessages] = useState<ContactMessage[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState({ current_page: 1, total_pages: 1, total: 0 });
    const [selectedMessage, setSelectedMessage] = useState<ContactMessage | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await websiteService.listContactMessages({
                page,
                search,
                status: statusFilter,
                limit: 20
            });
            if (res.data.success) {
                setMessages(res.data.data);
                setPagination(res.data.pagination);
            }
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to load messages');
        } finally {
            setLoading(false);
        }
    }, [page, search, statusFilter]);

    useEffect(() => {
        load();
    }, [load]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'read': return <span className="badge badge-info">Read</span>;
            case 'replied': return <span className="badge badge-success">Replied</span>;
            case 'archived': return <span className="badge badge-secondary">Archived</span>;
            default: return <span className="badge badge-primary">New</span>;
        }
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar" style={{ marginBottom: 24, background: 'var(--bg-card)', padding: '16px 20px', borderRadius: 16, border: '1px solid var(--bg-border)' }}>
                <div style={{ display: 'flex', gap: 12, flex: 1, alignItems: 'center' }}>
                   <div style={{ padding: 10, background: 'var(--primary-glow)', color: 'var(--primary-light)', borderRadius: 12 }}>
                        <Mail size={20} />
                   </div>
                   <div>
                        <h3 style={{ fontSize: '1.1rem', fontWeight: 800 }}>Contact Inquiries</h3>
                        <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Manage messages from your website contact form.</p>
                   </div>
                </div>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: { base: 16, sm: 0 } }}>
                    <div className="search-bar" style={{ flex: 1, minWidth: 240 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder="Search by name, email, subject…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                        />
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-sm)', padding: '0 12px' }}>
                        <Filter size={14} style={{ color: 'var(--text-muted)' }} />
                        <select
                            value={statusFilter}
                            onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
                            style={{ background: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', fontSize: '0.875rem', padding: '9px 0', minWidth: 120 }}
                        >
                            <option value="">All Messages</option>
                            <option value="new">New Inquiries</option>
                            <option value="read">Already Read</option>
                            <option value="replied">Replied</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="card" style={{ border: 'none', background: 'transparent', boxShadow: 'none' }}>
                <div className="table-wrapper" style={{ boxShadow: 'var(--shadow-card)', border: '1px solid var(--bg-border)', background: 'var(--bg-card)', borderRadius: 16 }}>
                    <table className="modern-table">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Subject</th>
                                <th>Date Received</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={5} style={{ textAlign: 'center', padding: 60 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : messages.length === 0 ? (
                                <tr><td colSpan={5}><div className="empty-state" style={{ padding: 60 }}><Mail size={48} /><p>No contact messages yet.</p></div></td></tr>
                            ) : messages.map((msg) => (
                                <tr key={msg.id} style={{ cursor: 'pointer' }} onClick={() => setSelectedMessage(msg)}>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                            <div style={{ width: 36, height: 36, borderRadius: 10, background: 'var(--bg-elevated)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, color: 'var(--primary-light)', fontSize: '0.8rem' }}>
                                                {msg.full_name[0].toUpperCase()}
                                            </div>
                                            <div>
                                                <div style={{ fontWeight: 700, fontSize: '0.9rem' }}>{msg.full_name}</div>
                                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{msg.email}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style={{ fontWeight: 500, color: 'var(--text-secondary)', fontSize: '0.85rem', maxWidth: 250, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {msg.subject}
                                        </div>
                                    </td>
                                    <td style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                                            <Calendar size={12} />
                                            {new Date(msg.created_at).toLocaleDateString()}
                                        </div>
                                    </td>
                                    <td>{getStatusBadge(msg.status)}</td>
                                    <td style={{ textAlign: 'right' }}>
                                        <button className="btn btn-secondary btn-sm btn-icon" onClick={(e) => { e.stopPropagation(); setSelectedMessage(msg); }} title="Read Message">
                                            <Eye size={14} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {pagination.total_pages > 1 && (
                    <div style={{ display: 'flex', gap: 8, padding: '20px 0', justifyContent: 'flex-end', alignItems: 'center' }}>
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginRight: 12 }}>
                            Showing page {pagination.current_page} of {pagination.total_pages}
                        </span>
                        <div style={{ display: 'flex', gap: 6 }}>
                             <button className="btn btn-secondary btn-sm btn-icon" disabled={page <= 1} onClick={() => setPage(p => p - 1)}><ChevronLeft size={14} /></button>
                             <button className="btn btn-secondary btn-sm btn-icon" disabled={page >= pagination.total_pages} onClick={() => setPage(p => p + 1)}><ChevronRight size={14} /></button>
                        </div>
                    </div>
                )}
            </div>

            {/* Details Modal */}
            {selectedMessage && (
                <Modal
                    isOpen={!!selectedMessage}
                    onClose={() => setSelectedMessage(null)}
                    title="Inquiry Details"
                    maxWidth={650}
                >
                    <div style={{ display: 'grid', gap: 24 }}>
                        {/* Header Info */}
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16 }}>
                            <div className="form-group-fancy" style={{ padding: 16 }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}><User size={12}/> Sender</label>
                                <div style={{ fontWeight: 700 }}>{selectedMessage.full_name}</div>
                            </div>
                            <div className="form-group-fancy" style={{ padding: 16 }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}><Mail size={12}/> Email</label>
                                <div style={{ wordBreak: 'break-all' }}>{selectedMessage.email}</div>
                            </div>
                            <div className="form-group-fancy" style={{ padding: 16 }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}><Phone size={12}/> Phone</label>
                                <div>{selectedMessage.phone || '—'}</div>
                            </div>
                        </div>

                        {/* Subject & Time */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '0 4px', alignItems: 'flex-end' }}>
                            <div>
                                <label style={{ fontSize: '0.72rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Subject</label>
                                <h3 style={{ fontSize: '1.2rem', fontWeight: 700, marginTop: 4 }}>{selectedMessage.subject}</h3>
                            </div>
                            <div style={{ textAlign: 'right', fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                                <Clock size={12} style={{ verticalAlign: 'middle', marginRight: 4 }} />
                                {new Date(selectedMessage.created_at).toLocaleString()}
                            </div>
                        </div>

                        {/* Message Content */}
                        <div className="form-group-fancy" style={{ padding: 24, background: 'var(--bg-elevated)', minHeight: 160 }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}><ClipboardList size={12}/> The Message</label>
                            <div style={{ fontSize: '0.95rem', lineHeight: '1.7', color: 'var(--text-secondary)', whiteSpace: 'pre-wrap' }}>
                                {selectedMessage.message}
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div style={{ display: 'flex', gap: 12, marginTop: 8 }}>
                             <button className="btn btn-primary" style={{ flex: 1, padding: '14px' }} onClick={() => window.open(`mailto:${selectedMessage.email}`)}>
                                <Mail size={18} style={{ marginRight: 8 }} /> Reply via Email
                             </button>
                             <button className="btn btn-secondary" style={{ flex: 0.5 }} onClick={() => toast('Message archived (logic coming soon)')}>
                                Archive
                             </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default ContactMessages;
