import React, { useEffect, useState, useCallback } from 'react';
import {
    Plus, Search, DollarSign, CheckCircle, AlertCircle, Clock,
    MoreVertical, User, Briefcase, LayoutGrid, Users
} from 'lucide-react';
import { feeService, studentService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';
import '../timetable.css';

interface FeeRecord {
    id: number;
    fee_category?: string;
    total_students?: number;
    payments_received?: number;
    total_collected: number;
    total_expected: number;
    first_name?: string;
    last_name?: string;
    student_code?: string;
    class_name?: string;
    section_name?: string;
    total_pending?: number;
    overdue_count?: number;
}

const Fees: React.FC = () => {
    const [viewMode, setViewMode] = useState<'summary' | 'students'>('summary');
    const [fees, setFees] = useState<FeeRecord[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');

    const [stats, setStats] = useState({
        collected: 0,
        pending: 0,
        overdue: 0,
        rate: '0'
    });

    const [showPayment, setShowPayment] = useState(false);
    const [students, setStudents] = useState<any[]>([]);
    const [selectedStudentPending, setSelectedStudentPending] = useState<any[]>([]);
    const [loadingPending, setLoadingPending] = useState(false);

    const [payForm, setPayForm] = useState({
        student_id: '',
        fee_id: '',
        amount_paid: '',
        payment_method: 'cash' as 'cash' | 'upi' | 'bank_transfer' | 'cheque',
        payment_date: new Date().toISOString().split('T')[0],
        transaction_id: '',
        remarks: '',
    });
    const [saving, setSaving] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await feeService.getAll({ action: viewMode });
            const list = res.data?.data || [];
            setFees(list);

            if (viewMode === 'summary') {
                let col = 0, exp = 0;
                list.forEach((r: any) => {
                    col += Number(r.total_collected || 0);
                    exp += Number(r.total_expected || 0);
                });
                setStats({
                    collected: col,
                    pending: exp - col,
                    overdue: 0,
                    rate: exp > 0 ? ((col / exp) * 100).toFixed(1) : '0'
                });
            }
        } catch (err: any) {
            toast.error('Failed to load fee information');
        } finally {
            setLoading(false);
        }
    }, [viewMode]);

    useEffect(() => { load(); }, [load]);

    useEffect(() => {
        studentService.list({ limit: 1000 }).then(r => setStudents(r.data?.data || []));
    }, []);

    useEffect(() => {
        if (!payForm.student_id) {
            setSelectedStudentPending([]);
            return;
        }
        setLoadingPending(true);
        feeService.getAll({ student_id: payForm.student_id })
            .then(res => {
                const pend = (res.data?.data || []).filter((f: any) => f.status !== 'paid');
                setSelectedStudentPending(pend);
                if (pend.length > 0) setPayForm(p => ({ ...p, fee_id: pend[0].id.toString(), amount_paid: (pend[0].amount - pend[0].paid_amount).toString() }));
            })
            .finally(() => setLoadingPending(false));
    }, [payForm.student_id]);

    const handlePayment = async () => {
        if (!payForm.fee_id || !payForm.amount_paid) {
            toast.error('Please select a fee record and enter an amount');
            return;
        }
        setSaving(true);
        try {
            await feeService.record({
                fee_id: payForm.fee_id,
                amount_paid: payForm.amount_paid,
                payment_method: payForm.payment_method,
                payment_date: payForm.payment_date,
                transaction_id: payForm.transaction_id || undefined,
                remarks: payForm.remarks || undefined,
                student_id: payForm.student_id
            } as any);
            toast.success('Payment recorded successfully');
            setShowPayment(false);
            load();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Payment failed');
        } finally {
            setSaving(false);
        }
    };

    const pf = (k: string, v: any) => setPayForm(p => ({ ...p, [k]: v }));
    const fmtCurrency = (n: number) => `₹${Number(n).toLocaleString('en-IN')}`;

    const filtered = fees.filter(r => {
        const searchStr = search.toLowerCase();
        if (viewMode === 'summary') return (r.fee_category || '').toLowerCase().includes(searchStr);
        const name = `${r.first_name} ${r.last_name}`.toLowerCase();
        return name.includes(searchStr) || (r.student_code || '').toLowerCase().includes(searchStr);
    });

    return (
        <div className="fade-in" style={{ padding: '24px' }}>
            {/* Page Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                <div>
                    <h1 style={{ fontSize: '24px', fontWeight: '900', color: 'var(--text-primary)', marginBottom: '4px' }}>Fee Management</h1>
                    <p style={{ color: 'var(--text-muted)', fontSize: '14px', fontWeight: '500' }}>Monitor collections and record student payments</p>
                </div>
                <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                    <div style={{ background: 'var(--bg-elevated)', padding: '4px', borderRadius: '12px', display: 'flex', gap: '4px' }}>
                        <button
                            onClick={() => setViewMode('summary')}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px',
                                border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: '700', transition: 'all 0.2s',
                                background: viewMode === 'summary' ? 'var(--bg-card)' : 'transparent',
                                color: viewMode === 'summary' ? 'var(--primary-light)' : 'var(--text-muted)',
                                boxShadow: viewMode === 'summary' ? '0 2px 4px rgba(0,0,0,0.1)' : 'none'
                            }}
                        >
                            <LayoutGrid size={16} /> Overview
                        </button>
                        <button
                            onClick={() => setViewMode('students')}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px',
                                border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: '700', transition: 'all 0.2s',
                                background: viewMode === 'students' ? 'var(--bg-card)' : 'transparent',
                                color: viewMode === 'students' ? 'var(--primary-light)' : 'var(--text-muted)',
                                boxShadow: viewMode === 'students' ? '0 2px 4px rgba(0,0,0,0.1)' : 'none'
                            }}
                        >
                            <Users size={16} /> Student Ledgers
                        </button>
                    </div>
                    <button onClick={() => setShowPayment(true)} className="btn btn-primary" style={{ height: '44px', padding: '0 20px' }}>
                        <Plus size={18} /> Record Payment
                    </button>
                </div>
            </div>

            {/* Stats Bar */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px', marginBottom: '32px' }}>
                {[
                    { label: 'Total Collected', value: fmtCurrency(stats.collected), icon: CheckCircle, color: '#10b981' },
                    { label: 'Pending Dues', value: fmtCurrency(stats.pending), icon: AlertCircle, color: '#f59e0b' },
                    { label: 'Overdue Dues', value: fmtCurrency(stats.overdue), icon: Clock, color: '#ef4444' },
                    { label: 'Collection Rate', value: `${stats.rate}%`, icon: DollarSign, color: '#6366f1', progress: stats.rate },
                ].map(s => (
                    <div key={s.label} className="glass-card" style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div style={{ padding: '10px', borderRadius: '12px', background: `${s.color}15`, display: 'flex' }}>
                                <s.icon size={20} style={{ color: s.color }} />
                            </div>
                            {s.progress && (
                                <span style={{ fontSize: '12px', fontWeight: '800', color: s.color }}>{s.progress}%</span>
                            )}
                        </div>
                        <div>
                            <div style={{ fontSize: '24px', fontWeight: '900', color: 'var(--text-primary)' }}>{loading ? '...' : s.value}</div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '1px' }}>{s.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* List Section */}
            <div className="card" style={{ overflow: 'hidden', padding: '0' }}>
                <div style={{ padding: '20px', borderBottom: '1px solid var(--bg-border)' }}>
                    <div className="search-bar" style={{ maxWidth: '400px' }}>
                        <Search size={18} style={{ color: 'var(--text-muted)' }} />
                        <input
                            placeholder={viewMode === 'summary' ? "Search category..." : "Search student by name or ID..."}
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            style={{ background: 'transparent', border: 'none', width: '100%', color: 'var(--text-primary)', outline: 'none' }}
                        />
                    </div>
                </div>

                <div className="table-wrapper">
                    <table style={{ width: '100%' }}>
                        <thead>
                            <tr>
                                <th style={{ width: '60px' }}>#</th>
                                {viewMode === 'summary' ? (
                                    <>
                                        <th>Fee Category</th>
                                        <th style={{ textAlign: 'center' }}>Enrollment</th>
                                        <th>Target Value</th>
                                        <th>Collection Progress</th>
                                    </>
                                ) : (
                                    <>
                                        <th>Student Details</th>
                                        <th>Class / Section</th>
                                        <th>Balance Dues</th>
                                        <th>Progress</th>
                                    </>
                                )}
                                <th style={{ textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan={10} style={{ textAlign: 'center', padding: '100px 0' }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                            ) : filtered.length === 0 ? (
                                <tr><td colSpan={10} style={{ textAlign: 'center', padding: '100px 0', color: 'var(--text-muted)', fontWeight: '600' }}>No records found in this view</td></tr>
                            ) : filtered.map((r, i) => {
                                const col = Number(r.total_collected || 0);
                                const exp = Number(r.total_expected || 0);
                                const rate = exp > 0 ? (col / exp) * 100 : 0;

                                return (
                                    <tr key={r.id || i}>
                                        <td style={{ color: 'var(--text-muted)', fontWeight: '600' }}>{i + 1}</td>

                                        {viewMode === 'summary' ? (
                                            <>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                        <div style={{ padding: '8px', background: 'var(--primary-glow)', borderRadius: '8px' }}><Briefcase size={16} style={{ color: 'var(--primary-light)' }} /></div>
                                                        <span style={{ fontWeight: '700', color: 'var(--text-primary)' }}>{r.fee_category}</span>
                                                    </div>
                                                </td>
                                                <td style={{ textAlign: 'center', fontWeight: '800' }}>{r.total_students}</td>
                                                <td style={{ fontWeight: '700' }}>{fmtCurrency(exp)}</td>
                                            </>
                                        ) : (
                                            <>
                                                <td>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                        <div style={{ width: '40px', height: '40px', borderRadius: '12px', background: 'var(--bg-elevated)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', color: 'var(--text-muted)' }}>{r.first_name?.[0]}{r.last_name?.[0]}</div>
                                                        <div>
                                                            <div style={{ fontWeight: '800', color: 'var(--text-primary)' }}>{r.first_name} {r.last_name}</div>
                                                            <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--primary-light)', textTransform: 'uppercase' }}>{r.student_code}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style={{ padding: '4px 10px', borderRadius: '20px', background: 'var(--bg-elevated)', fontSize: '11px', fontWeight: '700', color: 'var(--text-secondary)' }}>
                                                        {r.class_name} • {r.section_name}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style={{ fontWeight: '800', color: 'var(--text-primary)' }}>{fmtCurrency(exp)}</div>
                                                    {r.total_pending! > 0 && <div style={{ fontSize: '10px', fontWeight: '700', color: '#f59e0b' }}>{fmtCurrency(r.total_pending!)} Pending</div>}
                                                </td>
                                            </>
                                        )}

                                        <td style={{ minWidth: '160px' }}>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '10px', fontWeight: '800' }}>
                                                    <span style={{ color: '#10b981' }}>{fmtCurrency(col)} collected</span>
                                                    <span style={{ color: 'var(--text-muted)' }}>{rate.toFixed(1)}%</span>
                                                </div>
                                                <div style={{ width: '100%', height: '6px', background: 'var(--bg-elevated)', borderRadius: '3px', overflow: 'hidden' }}>
                                                    <div style={{ width: `${rate}%`, height: '100%', background: '#10b981', boxShadow: '0 0 8px rgba(16,185,129,0.4)' }} />
                                                </div>
                                            </div>
                                        </td>

                                        <td style={{ textAlign: 'right' }}>
                                            <button style={{ background: 'transparent', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}><MoreVertical size={18} /></button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal for payment */}
            <Modal isOpen={showPayment} onClose={() => setShowPayment(false)} title="Record Fee Payment" maxWidth={480}
                footer={<><button className="btn btn-secondary" onClick={() => setShowPayment(false)}>Dismiss</button><button className="btn btn-primary" onClick={handlePayment} disabled={saving}>{saving ? <div className="spinner" /> : 'Confirm Payment'}</button></>}>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                    <div className="form-group">
                        <label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)', marginBottom: '8px' }}>Select Student</label>
                        <select className="input" value={payForm.student_id} onChange={e => pf('student_id', e.target.value)}>
                            <option value="">Choose a student...</option>
                            {students.map(s => <option key={s.id} value={s.id}>{s.first_name} {s.last_name} ({s.student_id})</option>)}
                        </select>
                    </div>

                    {payForm.student_id && (
                        <div style={{ padding: '16px', background: 'var(--bg-elevated)', borderRadius: '12px' }}>
                            {loadingPending ? (
                                <div style={{ textAlign: 'center', padding: '10px', fontSize: '12px', color: 'var(--text-muted)' }}>Fetching pending dues...</div>
                            ) : selectedStudentPending.length === 0 ? (
                                <div style={{ textAlign: 'center', color: '#10b981', fontWeight: '700' }}>No pending fees found.</div>
                            ) : (
                                <div className="form-group">
                                    <label style={{ fontSize: '11px', fontWeight: '800', marginBottom: '8px' }}>Pending Allocations</label>
                                    <select className="input" value={payForm.fee_id} onChange={e => {
                                        const f = selectedStudentPending.find(x => x.id.toString() === e.target.value);
                                        setPayForm(p => ({ ...p, fee_id: e.target.value, amount_paid: (f.amount - f.paid_amount).toString() }));
                                    }}>
                                        {selectedStudentPending.map(f => (
                                            <option key={f.id} value={f.id}>{f.fee_name} — {fmtCurrency(f.amount - f.paid_amount)}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="grid-2">
                        <div className="form-group">
                            <label style={{ fontSize: '12px', fontWeight: '800', marginBottom: '8px' }}>Amount to Pay</label>
                            <input className="input" type="number" value={payForm.amount_paid} onChange={e => pf('amount_paid', e.target.value)} />
                        </div>
                        <div className="form-group">
                            <label style={{ fontSize: '12px', fontWeight: '800', marginBottom: '8px' }}>Payment Date</label>
                            <input className="input" type="date" value={payForm.payment_date} onChange={e => pf('payment_date', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid-2">
                        <div className="form-group">
                            <label style={{ fontSize: '12px', fontWeight: '800', marginBottom: '8px' }}>Payment Mode</label>
                            <select className="input" value={payForm.payment_method} onChange={e => pf('payment_method', e.target.value)}>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI / QR</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label style={{ fontSize: '12px', fontWeight: '800', marginBottom: '8px' }}>Reference ID</label>
                            <input className="input" value={payForm.transaction_id} onChange={e => pf('transaction_id', e.target.value)} placeholder="Optional" />
                        </div>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default Fees;
