import React, { useEffect, useState, useCallback } from 'react';
import { Search, Filter, ChevronLeft, ChevronRight, Eye, CheckCircle, XCircle, Clock } from 'lucide-react';
import { admissionsService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

interface AdmissionRequest {
    id: number;
    tracking_id: string;
    student_name: string;
    guardian_name: string;
    email: string;
    phone: string;
    desired_class: string;
    status: 'pending' | 'approved' | 'rejected' | 'contacted';
    details_json: string;
    payment_method: string | null;
    utr_number: string | null;
    payment_status: 'pending' | 'verified' | 'rejected';
    created_at: string;
}

const Admissions: React.FC = () => {
    const [requests, setRequests] = useState<AdmissionRequest[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const [pagination, setPagination] = useState({ current_page: 1, total_pages: 1, total: 0 });
    const [selectedRequest, setSelectedRequest] = useState<AdmissionRequest | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await admissionsService.list({
                page,
                search,
                status: statusFilter,
                limit: 16
            });
            if (res.data.success) {
                setRequests(res.data.data);
                setPagination(res.data.pagination);
            }
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to load admission requests');
        } finally {
            setLoading(false);
        }
    }, [page, search, statusFilter]);

    useEffect(() => {
        load();
    }, [load]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved': return <span className="badge badge-success">Approved</span>;
            case 'rejected': return <span className="badge badge-danger">Rejected</span>;
            case 'contacted': return <span className="badge badge-primary">Contacted</span>;
            default: return <span className="badge badge-secondary">Pending Request</span>;
        }
    };

    const getPaymentBadge = (status: string) => {
        switch (status) {
            case 'verified': return <span style={{ fontSize: '0.65rem', padding: '2px 8px', borderRadius: 10, background: 'rgba(16,185,129,0.1)', color: '#10b981', fontWeight: 800 }}>PAID</span>;
            case 'rejected': return <span style={{ fontSize: '0.65rem', padding: '2px 8px', borderRadius: 10, background: 'rgba(239,68,68,0.1)', color: '#ef4444', fontWeight: 800 }}>FAILED</span>;
            default: return <span style={{ fontSize: '0.65rem', padding: '2px 8px', borderRadius: 10, background: 'rgba(245,158,11,0.1)', color: '#f59e0b', fontWeight: 800 }}>UNVERIFIED</span>;
        }
    };

    const parseDetails = (json: string) => {
        try {
            return JSON.parse(json);
        } catch {
            return {};
        }
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1, flexWrap: 'wrap' }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 300 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder="Search by name or tracking ID…"
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
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="contacted">Contacted</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="card">
                <div className="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tracking ID</th>
                                <th>Student Name</th>
                                <th>Guardian</th>
                                <th>Desired Class</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={7} style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : requests.length === 0 ? (
                                <tr><td colSpan={7}><div className="empty-state"><Search size={36} /><p>No admission requests found</p></div></td></tr>
                            ) : requests.map((req) => (
                                <tr key={req.id}>
                                    <td style={{ fontWeight: 700, color: 'var(--accent)', fontSize: '0.85rem' }}>#{req.tracking_id}</td>
                                    <td>
                                        <div style={{ fontWeight: 600 }}>{req.student_name}</div>
                                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{req.email}</div>
                                    </td>
                                    <td>{req.guardian_name || '—'}</td>
                                    <td><span className="badge badge-primary">{req.desired_class || '—'}</span></td>
                                    <td>
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                            {getPaymentBadge(req.payment_status)}
                                            {req.utr_number && <div style={{ fontSize: '0.65rem', color: 'var(--text-muted)', fontFamily: 'monospace' }}>{req.utr_number}</div>}
                                        </div>
                                    </td>
                                    <td>{getStatusBadge(req.status)}</td>
                                    <td>
                                        <button className="btn btn-secondary btn-sm btn-icon" onClick={() => setSelectedRequest(req)} title="View Details">
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
                    <div style={{ display: 'flex', gap: 8, padding: '14px 20px', borderTop: '1px solid var(--bg-border)', justifyContent: 'flex-end', alignItems: 'center' }}>
                        <span style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginRight: 8 }}>
                            Page {pagination.current_page} of {pagination.total_pages}
                        </span>
                        <button className="btn btn-secondary btn-sm btn-icon" disabled={page <= 1} onClick={() => setPage(p => p - 1)}><ChevronLeft size={14} /></button>
                        <button className="btn btn-secondary btn-sm btn-icon" disabled={page >= pagination.total_pages} onClick={() => setPage(p => p + 1)}><ChevronRight size={14} /></button>
                    </div>
                )}
            </div>

            {/* Details Modal */}
            {selectedRequest && (
                <Modal
                    isOpen={!!selectedRequest}
                    onClose={() => setSelectedRequest(null)}
                    title={`Admission Request: #${selectedRequest.tracking_id}`}
                    maxWidth={600}
                >
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                        <div>
                            <h4 style={{ fontSize: '0.9rem', marginBottom: 12, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Basic Information</h4>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Student Name</label><div style={{ fontWeight: 600 }}>{selectedRequest.student_name}</div></div>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Guardian Name</label><div>{selectedRequest.guardian_name || '—'}</div></div>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Email</label><div>{selectedRequest.email}</div></div>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Phone</label><div>{selectedRequest.phone || '—'}</div></div>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Desired Class</label><div>{selectedRequest.desired_class || '—'}</div></div>
                                <div><label style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Submission Date</label><div>{new Date(selectedRequest.created_at).toLocaleString()}</div></div>
                            </div>
                        </div>

                        <div>
                            <h4 style={{ fontSize: '0.9rem', marginBottom: 12, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Additional Details</h4>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                {Object.entries(parseDetails(selectedRequest.details_json)).map(([key, val]) => (
                                    <div key={key}>
                                        <label style={{ fontSize: '0.75rem', color: 'var(--text-muted)', textTransform: 'capitalize' }}>{key.replace(/_/g, ' ')}</label>
                                        <div style={{ fontWeight: 500 }}>{String(val) || '—'}</div>
                                    </div>
                                ))}
                                {Object.keys(parseDetails(selectedRequest.details_json)).length === 0 && (
                                    <div style={{ fontSize: '0.85rem', color: 'var(--text-muted)', fontStyle: 'italic' }}>No additional details provided.</div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div style={{ marginTop: 24, padding: 20, background: 'var(--bg-elevated)', borderRadius: 12, border: '1px solid var(--bg-border)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
                            <h4 style={{ fontSize: '0.9rem', margin: 0 }}>Action Center</h4>
                            {selectedRequest.payment_method && (
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 10px', background: 'var(--bg-surface)', borderRadius: 8, border: '1px solid var(--bg-border)' }}>
                                    <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>{selectedRequest.payment_method}</div>
                                    <div style={{ width: 1, height: 10, background: 'var(--bg-border)' }}></div>
                                    <div style={{ fontSize: '0.7rem', fontWeight: 800, color: selectedRequest.payment_status === 'verified' ? '#10b981' : '#f59e0b' }}>{selectedRequest.payment_status.toUpperCase()}</div>
                                </div>
                            )}
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 10 }}>
                            <button className="btn btn-secondary" style={{ gap: 6 }} onClick={() => toast('Payment verified!')}>
                                <CheckCircle size={16} /> Verify Payment
                            </button>
                            <button className="btn btn-secondary" style={{ gap: 6 }} onClick={() => toast('Status update coming soon')}>
                                <Clock size={16} /> Log Result
                            </button>
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <button className="btn btn-primary" style={{ flex: 1, gap: 6 }} onClick={() => toast('Move to Student logic coming soon')}>
                                <CheckCircle size={16} /> Approve & Enrol
                            </button>
                            <button className="btn btn-danger" style={{ flex: 1, gap: 6 }} onClick={() => toast('Rejection logic coming soon')}>
                                <XCircle size={16} /> Reject
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Admissions;
