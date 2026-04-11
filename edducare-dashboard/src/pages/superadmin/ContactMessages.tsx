import React, { useState, useEffect } from 'react';
import { 
    Search, Mail, Phone, Calendar, RefreshCcw, 
    Eye, Trash2, CheckCircle, Clock, Package 
} from 'lucide-react';
import { mainSiteService } from '../../api/services';
import Modal from '../../components/Modal';
import toast from 'react-hot-toast';

export default function ContactMessages() {
    const [messages, setMessages] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [selectedMsg, setSelectedMsg] = useState<any>(null);

    useEffect(() => {
        fetchMessages();
    }, []);

    const fetchMessages = async () => {
        setLoading(true);
        try {
            const res = await mainSiteService.getContacts();
            if (res.data.success) {
                setMessages(res.data.data);
            }
        } catch (err) {
            toast.error("Failed to load messages.");
        } finally {
            setLoading(false);
        }
    };

    const handleStatusUpdate = async (id: number, status: string) => {
        try {
            await mainSiteService.updateContactStatus(id, status);
            setMessages(messages.map(m => m.id === id ? { ...m, status } : m));
            if (selectedMsg?.id === id) setSelectedMsg({ ...selectedMsg, status });
            toast.success(`Inquiry marked as ${status}`);
        } catch (err) {
            toast.error("Failed to update status");
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Are you sure you want to delete this message?')) return;
        try {
            await mainSiteService.deleteContact(id);
            setMessages(messages.filter(m => m.id !== id));
            if (selectedMsg?.id === id) setSelectedMsg(null);
            toast.success("Inquiry deleted");
        } catch (err) {
            toast.error("Failed to delete message");
        }
    };

    const filteredMessages = messages.filter(m => {
        const matchesFilter = filter === 'all' || m.status === filter;
        const matchesSearch = 
            m.school_name?.toLowerCase().includes(search.toLowerCase()) ||
            m.email?.toLowerCase().includes(search.toLowerCase());
        return matchesFilter && matchesSearch;
    });

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending': return <span className="badge badge-warning">Pending</span>;
            case 'contacted': return <span className="badge badge-primary">Contacted</span>;
            case 'resolved': return <span className="badge badge-success">Resolved</span>;
            default: return <span className="badge">Unknown</span>;
        }
    };

    return (
        <div className="fade-in">
            <div className="toolbar">
                <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                    <div className="search-bar" style={{ maxWidth: 300 }}>
                        <Search size={18} />
                        <input 
                            placeholder="Search by school or email..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <select 
                        className="input" 
                        style={{ width: '150px', padding: '8px' }}
                        value={filter}
                        onChange={(e) => setFilter(e.target.value)}
                    >
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="contacted">Contacted</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                <button className="btn btn-secondary" onClick={fetchMessages} disabled={loading}>
                    <RefreshCcw size={18} className={loading ? "animate-spin" : ""} /> Refresh
                </button>
            </div>

            <div className="card" style={{ marginTop: 20 }}>
                <div className="table-wrapper">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>School & Plan</th>
                                <th>Contact Information</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={5} style={{ textAlign: 'center', padding: 50 }}>
                                        <div className="spinner" style={{ margin: 'auto' }} />
                                    </td>
                                </tr>
                            ) : filteredMessages.length === 0 ? (
                                <tr>
                                    <td colSpan={5} style={{ textAlign: 'center', padding: 50, color: 'var(--text-muted)' }}>
                                        No messages found.
                                    </td>
                                </tr>
                            ) : (
                                filteredMessages.map(msg => (
                                    <tr key={msg.id}>
                                        <td>
                                            <div style={{ fontWeight: 800, color: 'var(--text-primary)', fontSize: '0.95rem' }}>
                                                {msg.school_name}
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 4, fontSize: '0.75rem', color: 'var(--primary)', fontWeight: 600 }}>
                                                <Package size={12} /> {msg.plan}
                                            </div>
                                        </td>
                                        <td>
                                            <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: 6 }}>
                                                <Mail size={12} style={{ color: 'var(--text-muted)' }} /> {msg.email}
                                            </div>
                                            <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
                                                <Phone size={12} style={{ color: 'var(--text-muted)' }} /> {msg.phone || '—'}
                                            </div>
                                        </td>
                                        <td>
                                            <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: 6 }}>
                                                <Calendar size={12} style={{ color: 'var(--text-muted)' }} />
                                                {new Date(msg.created_at).toLocaleDateString()}
                                            </div>
                                        </td>
                                        <td>
                                            {getStatusBadge(msg.status)}
                                        </td>
                                        <td style={{ textAlign: 'right' }}>
                                            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                                                <button 
                                                    className="btn-icon" 
                                                    onClick={() => setSelectedMsg(msg)} 
                                                    title="View Full Message"
                                                >
                                                    <Eye size={16} />
                                                </button>
                                                <button 
                                                    className="btn-icon danger" 
                                                    onClick={() => handleDelete(msg.id)} 
                                                    title="Delete Message"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {selectedMsg && (
                <Modal
                    isOpen={!!selectedMsg}
                    onClose={() => setSelectedMsg(null)}
                    title="Inquiry Details"
                    maxWidth={600}
                    footer={
                        <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%' }}>
                            <div style={{ display: 'flex', gap: 10 }}>
                                {selectedMsg.status !== 'pending' && (
                                    <button className="btn btn-secondary" onClick={() => handleStatusUpdate(selectedMsg.id, 'pending')}>
                                        <Clock size={16} /> Mark Pending
                                    </button>
                                )}
                                {selectedMsg.status !== 'contacted' && (
                                    <button className="btn btn-primary" style={{ background: 'var(--primary)', color: 'white' }} onClick={() => handleStatusUpdate(selectedMsg.id, 'contacted')}>
                                        <Mail size={16} /> Mark Contacted
                                    </button>
                                )}
                                {selectedMsg.status !== 'resolved' && (
                                    <button className="btn btn-primary" style={{ background: 'var(--success)', color: 'white' }} onClick={() => handleStatusUpdate(selectedMsg.id, 'resolved')}>
                                        <CheckCircle size={16} /> Mark Resolved
                                    </button>
                                )}
                            </div>
                            <button className="btn btn-secondary" onClick={() => setSelectedMsg(null)}>Close</button>
                        </div>
                    }
                >
                    <div style={{ padding: '10px 0' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' }}>
                            <div>
                                <h4 style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '4px' }}>School</h4>
                                <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text-primary)' }}>{selectedMsg.school_name}</div>
                            </div>
                            <div>
                                <h4 style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '4px' }}>Interested Plan</h4>
                                <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--primary)' }}>{selectedMsg.plan}</div>
                            </div>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '24px', paddingBottom: '20px', borderBottom: '1px solid var(--bg-border)' }}>
                            <div>
                                <h4 style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '4px' }}>Email</h4>
                                <a href={`mailto:${selectedMsg.email}`} style={{ fontSize: '0.9rem', color: 'var(--text-secondary)', textDecoration: 'none' }}>{selectedMsg.email}</a>
                            </div>
                            <div>
                                <h4 style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '4px' }}>Phone</h4>
                                <a href={`tel:${selectedMsg.phone}`} style={{ fontSize: '0.9rem', color: 'var(--text-secondary)', textDecoration: 'none' }}>{selectedMsg.phone || 'N/A'}</a>
                            </div>
                        </div>

                        <div>
                            <h4 style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '8px' }}>Message</h4>
                            <div style={{ 
                                padding: '16px', 
                                background: 'var(--bg-elevated)', 
                                border: '1px solid var(--bg-border)', 
                                borderRadius: '8px',
                                fontSize: '0.95rem',
                                color: 'var(--text-primary)',
                                lineHeight: 1.6,
                                whiteSpace: 'pre-wrap'
                            }}>
                                {selectedMsg.message}
                            </div>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}

