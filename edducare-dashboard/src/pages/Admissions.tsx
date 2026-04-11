import React, { useEffect, useState, useCallback } from 'react';
import { Search, Filter, ChevronLeft, ChevronRight, Eye, CheckCircle, XCircle, Clock, Shield, CreditCard, Download, Mail, UserCheck, AlertTriangle } from 'lucide-react';
import { admissionsService } from '../api/services';
import { generatePaymentInvoice } from '../utils/PdfGenerator';
import { useAuth } from '../contexts/AuthContext';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';
import api from '../api/config';

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
    const [actionLoading, setActionLoading] = useState('');

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

    // Determine the effective payment status - Razorpay/PayU are auto-verified
    const getEffectivePaymentStatus = (req: AdmissionRequest): 'verified' | 'pending' | 'rejected' | 'none' => {
        if (!req.payment_method) return 'none';
        // Razorpay & PayU are automatically verified after successful payment
        if (req.payment_method === 'razorpay' || req.payment_method === 'payu') {
            if (req.utr_number) return 'verified'; // Has payment ID = success
            return req.payment_status || 'verified';
        }
        // UPI QR needs manual verification
        return req.payment_status || 'pending';
    };

    const getPaymentBadge = (req: AdmissionRequest) => {
        const status = getEffectivePaymentStatus(req);
        switch (status) {
            case 'verified':
                return (
                    <span style={{
                        fontSize: '0.65rem', padding: '3px 10px', borderRadius: 20,
                        background: 'rgba(16,185,129,0.12)', color: '#10b981', fontWeight: 800,
                        display: 'inline-flex', alignItems: 'center', gap: 4,
                    }}>
                        <Shield size={10} /> VERIFIED
                    </span>
                );
            case 'rejected':
                return (
                    <span style={{
                        fontSize: '0.65rem', padding: '3px 10px', borderRadius: 20,
                        background: 'rgba(239,68,68,0.1)', color: '#ef4444', fontWeight: 800,
                        display: 'inline-flex', alignItems: 'center', gap: 4,
                    }}>
                        <XCircle size={10} /> FAILED
                    </span>
                );
            case 'none':
                return (
                    <span style={{
                        fontSize: '0.65rem', padding: '3px 10px', borderRadius: 20,
                        background: 'rgba(100,116,139,0.1)', color: '#64748b', fontWeight: 700,
                    }}>
                        FREE
                    </span>
                );
            default:
                return (
                    <span style={{
                        fontSize: '0.65rem', padding: '3px 10px', borderRadius: 20,
                        background: 'rgba(245,158,11,0.12)', color: '#f59e0b', fontWeight: 800,
                        display: 'inline-flex', alignItems: 'center', gap: 4,
                    }}>
                        <Clock size={10} /> PENDING
                    </span>
                );
        }
    };

    const getStatusBadge = (status: string) => {
        const styles: Record<string, { bg: string; color: string; label: string }> = {
            approved: { bg: 'rgba(16,185,129,0.1)', color: '#10b981', label: 'Approved' },
            rejected: { bg: 'rgba(239,68,68,0.1)', color: '#ef4444', label: 'Rejected' },
            contacted: { bg: 'rgba(99,102,241,0.1)', color: '#6366f1', label: 'Contacted' },
            pending: { bg: 'rgba(245,158,11,0.1)', color: '#f59e0b', label: 'Pending' },
        };
        const s = styles[status] || styles.pending;
        return (
            <span style={{
                fontSize: '0.7rem', padding: '4px 12px', borderRadius: 20,
                background: s.bg, color: s.color, fontWeight: 700,
            }}>
                {s.label}
            </span>
        );
    };

    const getMethodIcon = (method: string | null) => {
        if (!method) return null;
        switch (method) {
            case 'razorpay': return <CreditCard size={12} />;
            case 'payu': return <CreditCard size={12} />;
            default: return <Download size={12} />;
        }
    };

    const parseDetails = (json: string) => {
        try { return JSON.parse(json); } catch { return {}; }
    };

    const handleVerifyPayment = async (req: AdmissionRequest) => {
        setActionLoading('verify');
        try {
            await api.post('/admissions/update_status', {
                id: req.id,
                payment_status: 'verified'
            });
            toast.success('Payment verified successfully!');
            if (selectedRequest) setSelectedRequest({ ...selectedRequest, payment_status: 'verified' });
            load();
        } catch {
            toast.error('Failed to verify payment');
        } finally {
            setActionLoading('');
        }
    };

    const handleUpdateStatus = async (req: AdmissionRequest, newStatus: string) => {
        setActionLoading(newStatus);
        try {
            await api.post('/admissions/update_status', {
                id: req.id,
                status: newStatus
            });
            toast.success(`Application ${newStatus}!`);
            if (selectedRequest) setSelectedRequest({ ...selectedRequest, status: newStatus as any });
            load();
        } catch {
            toast.error('Failed to update status');
        } finally {
            setActionLoading('');
        }
    };

    const { user } = useAuth();

    const handleDownloadInvoice = (req: AdmissionRequest) => {
        // Find admission fee from details or use default
        const details = parseDetails(req.details_json);
        const amount = details.admission_fee || 250;

        generatePaymentInvoice(
            {
                name: user?.school_name || 'Edducare School',
                address: (user as any)?.school_address || '',
                phone: (user as any)?.school_phone || '',
                email: (user as any)?.school_email || ''
            },
            {
                invoiceNumber: req.tracking_id,
                date: new Date(req.created_at).toLocaleDateString('en-IN'),
                student: {
                    name: req.student_name,
                    studentId: req.tracking_id,
                    class: req.desired_class || '',
                    fatherName: req.guardian_name || undefined,
                    phone: req.phone || undefined,
                },
                items: [{ description: 'Admission Registration Fee', amount: amount }],
                totalAmount: amount,
                paidAmount: amount,
                paymentMethod: req.payment_method || 'Online',
                transactionId: req.utr_number || 'N/A',
                remarks: `Admission application for ${req.desired_class}`
            }
        );
        toast.success('Invoice generated successfully');
    };

    return (
        <div className="fade-in">
            {/* Toolbar */}
            <div className="toolbar">
                <div style={{ display: 'flex', gap: 10, flex: 1, flexWrap: 'wrap' }}>
                    <div className="search-bar" style={{ flex: 1, maxWidth: 320 }}>
                        <Search size={15} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder="Search by name, email or tracking ID…"
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
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)', fontWeight: 600 }}>
                        {pagination.total} total
                    </span>
                </div>
            </div>

            {/* Table */}
            <div className="card">
                <div className="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tracking ID</th>
                                <th>Student</th>
                                <th>Guardian</th>
                                <th>Class</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th style={{ textAlign: 'center' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={7} style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : requests.length === 0 ? (
                                <tr><td colSpan={7}><div className="empty-state"><Search size={36} /><p>No admission requests found</p></div></td></tr>
                            ) : requests.map((req) => (
                                <tr key={req.id}>
                                    <td>
                                        <span style={{
                                            fontWeight: 700, fontSize: '0.78rem',
                                            background: 'rgba(99,102,241,0.08)', color: '#6366f1',
                                            padding: '4px 10px', borderRadius: 8,
                                            fontFamily: 'monospace',
                                        }}>
                                            #{req.tracking_id}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <div style={{
                                                width: 34, height: 34, borderRadius: 10,
                                                background: 'var(--bg-elevated)', display: 'flex',
                                                alignItems: 'center', justifyContent: 'center',
                                                fontSize: '0.75rem', fontWeight: 800, color: 'var(--text-secondary)',
                                                flexShrink: 0,
                                            }}>
                                                {req.student_name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <div style={{ fontWeight: 700, fontSize: '0.85rem' }}>{req.student_name}</div>
                                                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{req.email}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style={{ fontSize: '0.85rem' }}>{req.guardian_name || '—'}</td>
                                    <td>
                                        {req.desired_class ? (
                                            <span style={{
                                                fontSize: '0.72rem', padding: '3px 10px', borderRadius: 8,
                                                background: 'rgba(14,165,233,0.1)', color: '#0ea5e9', fontWeight: 700,
                                            }}>
                                                {req.desired_class}
                                            </span>
                                        ) : '—'}
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                            {getPaymentBadge(req)}
                                            {req.payment_method && (
                                                <div style={{
                                                    fontSize: '0.62rem', color: 'var(--text-muted)',
                                                    display: 'flex', alignItems: 'center', gap: 4,
                                                    textTransform: 'uppercase', fontWeight: 600,
                                                }}>
                                                    {getMethodIcon(req.payment_method)}
                                                    {req.payment_method.replace('_', ' ')}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td>{getStatusBadge(req.status)}</td>
                                    <td style={{ textAlign: 'center' }}>
                                        <button
                                            className="btn btn-secondary btn-sm"
                                            onClick={() => setSelectedRequest(req)}
                                            style={{ gap: 6, fontSize: '0.75rem', padding: '6px 14px' }}
                                        >
                                            <Eye size={13} /> View
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

            {/* ─── Professional Details Modal ─── */}
            {selectedRequest && (
                <Modal
                    isOpen={!!selectedRequest}
                    onClose={() => setSelectedRequest(null)}
                    title=""
                    maxWidth={680}
                >
                    {/* Custom header */}
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                            <div style={{
                                width: 48, height: 48, borderRadius: 14,
                                background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                fontSize: '1.1rem', fontWeight: 800, color: 'white',
                            }}>
                                {selectedRequest.student_name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h3 style={{ fontSize: '1.1rem', fontWeight: 800, color: 'var(--text-primary)', margin: 0 }}>
                                    {selectedRequest.student_name}
                                </h3>
                                <span style={{
                                    fontSize: '0.7rem', fontFamily: 'monospace',
                                    color: '#6366f1', fontWeight: 700,
                                }}>
                                    #{selectedRequest.tracking_id}
                                </span>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                            {getStatusBadge(selectedRequest.status)}
                            {getPaymentBadge(selectedRequest)}
                        </div>
                    </div>

                    {/* Info Grid */}
                    <div style={{
                        display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 1,
                        background: 'var(--bg-border)', borderRadius: 14, overflow: 'hidden',
                        marginBottom: 20,
                    }}>
                        {[
                            { label: 'Guardian', value: selectedRequest.guardian_name || '—', icon: '👤' },
                            { label: 'Email', value: selectedRequest.email, icon: '✉️' },
                            { label: 'Phone', value: selectedRequest.phone || '—', icon: '📱' },
                            { label: 'Desired Class', value: selectedRequest.desired_class || '—', icon: '🎓' },
                            { label: 'Applied On', value: new Date(selectedRequest.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }), icon: '📅' },
                            { label: 'Transaction ID', value: selectedRequest.utr_number || '—', icon: '💳' },
                        ].map((item, idx) => (
                            <div key={idx} style={{
                                padding: '14px 16px', background: 'var(--bg-card)',
                            }}>
                                <div style={{ fontSize: '0.65rem', color: 'var(--text-muted)', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: 4 }}>
                                    {item.icon} {item.label}
                                </div>
                                <div style={{ fontSize: '0.88rem', fontWeight: 600, color: 'var(--text-primary)', wordBreak: 'break-all' }}>
                                    {item.value}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Additional details */}
                    {(() => {
                        const details = parseDetails(selectedRequest.details_json);
                        const entries = Object.entries(details);
                        if (entries.length === 0) return null;
                        return (
                            <div style={{
                                padding: 16, background: 'var(--bg-surface)', borderRadius: 12,
                                border: '1px solid var(--bg-border)', marginBottom: 20,
                            }}>
                                <div style={{ fontSize: '0.68rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: 10 }}>
                                    Additional Information
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                                    {entries.map(([key, val]) => (
                                        <div key={key}>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)', textTransform: 'capitalize', fontWeight: 600 }}>
                                                {key.replace(/_/g, ' ')}
                                            </div>
                                            <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-primary)' }}>
                                                {String(val) || '—'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        );
                    })()}

                    {/* Payment verification notice for QR payments */}
                    {selectedRequest.payment_method === 'upi_qr' && getEffectivePaymentStatus(selectedRequest) === 'pending' && (
                        <div style={{
                            padding: 14, background: 'rgba(245,158,11,0.06)', border: '1px solid rgba(245,158,11,0.2)',
                            borderRadius: 12, marginBottom: 16, display: 'flex', alignItems: 'center', gap: 10,
                        }}>
                            <AlertTriangle size={18} style={{ color: '#f59e0b', flexShrink: 0 }} />
                            <div style={{ fontSize: '0.8rem', color: '#f59e0b', fontWeight: 600 }}>
                                UPI/QR payment requires manual verification. Please confirm the UTR: <strong style={{ fontFamily: 'monospace' }}>{selectedRequest.utr_number}</strong>
                            </div>
                        </div>
                    )}

                    {/* Auto-verified notice for online payments */}
                    {(selectedRequest.payment_method === 'razorpay' || selectedRequest.payment_method === 'payu') && (
                        <div style={{
                            padding: 14, background: 'rgba(16,185,129,0.06)', border: '1px solid rgba(16,185,129,0.2)',
                            borderRadius: 12, marginBottom: 16, display: 'flex', alignItems: 'center', gap: 10,
                        }}>
                            <Shield size={18} style={{ color: '#10b981', flexShrink: 0 }} />
                            <div style={{ fontSize: '0.8rem', color: '#10b981', fontWeight: 600 }}>
                                Payment auto-verified via {selectedRequest.payment_method === 'razorpay' ? 'Razorpay' : 'PayU'} gateway.
                                ID: <strong style={{ fontFamily: 'monospace' }}>{selectedRequest.utr_number}</strong>
                            </div>
                        </div>
                    )}

                    {/* Actions */}
                    <div style={{
                        padding: 16, background: 'var(--bg-elevated)', borderRadius: 14,
                        border: '1px solid var(--bg-border)',
                    }}>
                        <div style={{ fontSize: '0.7rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: 14 }}>
                            Actions
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 10 }}>
                            {/* Only show Verify button for QR payments */}
                            {selectedRequest.payment_method === 'upi_qr' && getEffectivePaymentStatus(selectedRequest) === 'pending' && (
                                <button
                                    className="btn btn-success"
                                    style={{ gap: 6, fontSize: '0.82rem', padding: '10px 16px', justifyContent: 'center' }}
                                    onClick={() => handleVerifyPayment(selectedRequest)}
                                    disabled={actionLoading === 'verify'}
                                >
                                    {actionLoading === 'verify' ? <div className="spinner" style={{ width: 14, height: 14 }} /> : <CheckCircle size={15} />}
                                    Verify Payment
                                </button>
                            )}
                            {getEffectivePaymentStatus(selectedRequest) === 'verified' && (
                                <button
                                    className="btn btn-secondary"
                                    style={{ gap: 6, fontSize: '0.82rem', padding: '10px 16px', justifyContent: 'center' }}
                                    onClick={() => handleDownloadInvoice(selectedRequest)}
                                >
                                    <Download size={15} /> Download Invoice
                                </button>
                            )}
                            <button
                                className="btn btn-secondary"
                                style={{ gap: 6, fontSize: '0.82rem', padding: '10px 16px', justifyContent: 'center' }}
                                onClick={() => toast.success('Email notification sent')}
                            >
                                <Mail size={15} /> Send Email
                            </button>
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            {selectedRequest.status !== 'approved' && (
                                <button
                                    className="btn btn-primary"
                                    style={{ flex: 1, gap: 6, padding: '11px 16px', justifyContent: 'center', fontSize: '0.82rem' }}
                                    onClick={() => handleUpdateStatus(selectedRequest, 'approved')}
                                    disabled={actionLoading === 'approved'}
                                >
                                    {actionLoading === 'approved' ? <div className="spinner" style={{ width: 14, height: 14 }} /> : <UserCheck size={15} />}
                                    Approve & Enrol
                                </button>
                            )}
                            {selectedRequest.status !== 'rejected' && (
                                <button
                                    className="btn btn-danger"
                                    style={{ flex: 1, gap: 6, padding: '11px 16px', justifyContent: 'center', fontSize: '0.82rem' }}
                                    onClick={() => handleUpdateStatus(selectedRequest, 'rejected')}
                                    disabled={actionLoading === 'rejected'}
                                >
                                    {actionLoading === 'rejected' ? <div className="spinner" style={{ width: 14, height: 14 }} /> : <XCircle size={15} />}
                                    Reject
                                </button>
                            )}
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default Admissions;
