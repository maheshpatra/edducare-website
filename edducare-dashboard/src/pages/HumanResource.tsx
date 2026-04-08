import React, { useEffect, useState, useCallback } from 'react';
import {
    Users, UserCheck, Cake, Calendar, Building2, Briefcase, DollarSign,
    ClipboardList, Plus, Check, X, Search, ChevronDown, AlertTriangle,
    Gift, Clock, CreditCard, Trash2, ArrowUpDown
} from 'lucide-react';
import { hrService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';

type Tab = 'overview' | 'birthdays' | 'leaves' | 'payroll' | 'departments' | 'designations' | 'leave_types';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const HumanResource: React.FC = () => {
    const [tab, setTab] = useState<Tab>('overview');

    const tabs: { key: Tab; label: string; icon: any }[] = [
        { key: 'overview', label: 'Overview', icon: Users },
        { key: 'birthdays', label: 'Birthdays', icon: Cake },
        { key: 'leaves', label: 'Leave Management', icon: Calendar },
        { key: 'payroll', label: 'Payroll', icon: DollarSign },
        { key: 'departments', label: 'Departments', icon: Building2 },
        { key: 'designations', label: 'Designations', icon: Briefcase },
        { key: 'leave_types', label: 'Leave Types', icon: ClipboardList },
    ];

    return (
        <div>
            {/* Tabs */}
            <div style={{ display: 'flex', gap: 4, marginBottom: 24, overflowX: 'auto', paddingBottom: 4,
                background: 'var(--bg-card)', borderRadius: 'var(--radius-lg)', padding: 6, border: '1px solid var(--bg-border)' }}>
                {tabs.map(t => (
                    <button key={t.key} onClick={() => setTab(t.key)}
                        style={{
                            display: 'flex', alignItems: 'center', gap: 8, padding: '10px 16px',
                            borderRadius: 'var(--radius-sm)', border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
                            fontSize: '0.82rem', fontWeight: 600, transition: 'all 0.2s',
                            background: tab === t.key ? 'var(--primary-glow)' : 'transparent',
                            color: tab === t.key ? 'var(--primary-light)' : 'var(--text-secondary)',
                        }}>
                        <t.icon size={16} /> {t.label}
                    </button>
                ))}
            </div>

            {tab === 'overview' && <OverviewTab />}
            {tab === 'birthdays' && <BirthdaysTab />}
            {tab === 'leaves' && <LeavesTab />}
            {tab === 'payroll' && <PayrollTab />}
            {tab === 'departments' && <DepartmentsTab />}
            {tab === 'designations' && <DesignationsTab />}
            {tab === 'leave_types' && <LeaveTypesTab />}
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   OVERVIEW TAB
   ════════════════════════════════════════════════════════════════════════════ */
const OverviewTab: React.FC = () => {
    const [dashboard, setDashboard] = useState<any>(null);
    const [birthdays, setBirthdays] = useState<any[]>([]);
    const [onLeave, setOnLeave] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.allSettled([
            hrService.dashboard(),
            hrService.birthdays({ days: 14 }),
            hrService.leavesToday(),
        ]).then(([d, b, l]) => {
            if (d.status === 'fulfilled') setDashboard(d.value.data?.data);
            if (b.status === 'fulfilled') setBirthdays(b.value.data?.data?.birthdays ?? []);
            if (l.status === 'fulfilled') setOnLeave(l.value.data?.data?.staff_on_leave ?? []);
        }).finally(() => setLoading(false));
    }, []);

    if (loading) return <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}><div className="spinner" style={{ width: 32, height: 32, margin: '0 auto 12px' }} />Loading HR data…</div>;

    const stats = [
        { label: 'Total Staff', value: dashboard?.total_staff ?? 0, icon: Users, color: 'purple', gradient: 'var(--grad-primary)' },
        { label: 'Active Staff', value: dashboard?.active_staff ?? 0, icon: UserCheck, color: 'green', gradient: 'var(--grad-success)' },
        { label: 'On Leave Today', value: dashboard?.on_leave_today ?? 0, icon: Calendar, color: 'yellow', gradient: 'var(--grad-warning)' },
        { label: 'Pending Leaves', value: dashboard?.pending_leaves ?? 0, icon: Clock, color: 'red', gradient: 'var(--grad-danger)' },
    ];

    return (
        <div>
            {/* Stat Cards */}
            <div className="stat-grid" style={{ marginBottom: 24 }}>
                {stats.map((s, i) => (
                    <div key={i} className={`stat-card ${s.color}`}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                            <div className={`stat-icon ${s.color}`}><s.icon size={22} /></div>
                        </div>
                        <div className="stat-value">{s.value}</div>
                        <div className="stat-label">{s.label}</div>
                    </div>
                ))}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                {/* Staff on Leave Today */}
                <div className="card">
                    <div className="card-header">
                        <div>
                            <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <Calendar size={18} style={{ color: 'var(--warning)' }} /> Staff on Leave Today
                            </div>
                        </div>
                    </div>
                    <div className="card-body">
                        {onLeave.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: 30, color: 'var(--success)' }}>
                                <UserCheck size={32} style={{ margin: '0 auto 8px', display: 'block', opacity: 0.6 }} />
                                <div style={{ fontSize: '0.875rem', fontWeight: 600 }}>All staff are present today! 🎉</div>
                            </div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                {onLeave.map((s: any, i: number) => (
                                    <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 14px', background: 'var(--bg-elevated)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--bg-border)' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <div className="avatar" style={{ width: 32, height: 32, fontSize: '0.7rem' }}>{s.staff_name?.[0]}</div>
                                            <div>
                                                <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)' }}>{s.staff_name}</div>
                                                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>{s.leave_type_name}</div>
                                            </div>
                                        </div>
                                        <span className="badge badge-warning">{s.days}d</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Upcoming Birthdays */}
                <div className="card">
                    <div className="card-header">
                        <div>
                            <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <Cake size={18} style={{ color: 'var(--danger)' }} /> Upcoming Birthdays
                            </div>
                            <div className="card-subtitle">Next 14 days</div>
                        </div>
                    </div>
                    <div className="card-body">
                        {birthdays.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: 30, color: 'var(--text-muted)' }}>
                                <Gift size={32} style={{ margin: '0 auto 8px', display: 'block', opacity: 0.5 }} />
                                <div style={{ fontSize: '0.875rem' }}>No upcoming birthdays this week</div>
                            </div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {birthdays.slice(0, 8).map((b: any, i: number) => (
                                    <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 14px', background: 'var(--bg-elevated)', borderRadius: 'var(--radius-sm)', border: '1px solid var(--bg-border)' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <div style={{ width: 32, height: 32, borderRadius: '50%', background: b.days_until === 0 ? 'var(--grad-danger)' : 'var(--grad-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                <Cake size={14} color="white" />
                                            </div>
                                            <div>
                                                <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)' }}>
                                                    {b.full_name}
                                                    {b.days_until === 0 && <span style={{ marginLeft: 6 }}>🎂</span>}
                                                </div>
                                                <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
                                                    {b.person_type === 'student' ? `${b.class_name || ''} ${b.section_name ? `(${b.section_name})` : ''}` : 'Staff'}
                                                </div>
                                            </div>
                                        </div>
                                        <span className={`badge ${b.days_until === 0 ? 'badge-danger' : 'badge-success'}`}>
                                            {b.days_until === 0 ? 'Today!' : b.birthday_display}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   BIRTHDAYS TAB
   ════════════════════════════════════════════════════════════════════════════ */
const BirthdaysTab: React.FC = () => {
    const [birthdays, setBirthdays] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [type, setType] = useState('all');
    const [days, setDays] = useState(30);

    const load = useCallback(() => {
        setLoading(true);
        hrService.birthdays({ type, days }).then(r => setBirthdays(r.data?.data?.birthdays ?? []))
            .catch(() => toast.error('Failed to load birthdays'))
            .finally(() => setLoading(false));
    }, [type, days]);

    useEffect(() => { load(); }, [load]);

    return (
        <div className="card">
            <div className="card-header" style={{ padding: '18px 20px' }}>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Cake size={20} style={{ color: '#f87171' }} /> Upcoming Birthdays
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    <select value={type} onChange={e => setType(e.target.value)} style={{ width: 120 }}>
                        <option value="all">All</option>
                        <option value="staff">Staff Only</option>
                        <option value="student">Students Only</option>
                    </select>
                    <select value={days} onChange={e => setDays(Number(e.target.value))} style={{ width: 120 }}>
                        <option value={7}>Next 7 days</option>
                        <option value={14}>Next 14 days</option>
                        <option value={30}>Next 30 days</option>
                        <option value={60}>Next 60 days</option>
                    </select>
                </div>
            </div>
            <div className="card-body">
                {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : birthdays.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}>
                        <Gift size={40} style={{ margin: '0 auto 12px', display: 'block', opacity: 0.4 }} />
                        No upcoming birthdays in this period
                    </div>
                ) : (
                    <div className="table-wrapper">
                        <table>
                            <thead><tr><th>Name</th><th>Type</th><th>Class</th><th>Birthday</th><th>Days Until</th></tr></thead>
                            <tbody>
                                {birthdays.map((b, i) => (
                                    <tr key={i}>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                <div style={{ width: 32, height: 32, borderRadius: '50%', background: b.days_until === 0 ? 'var(--grad-danger)' : 'var(--grad-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                                    <Cake size={14} color="white" />
                                                </div>
                                                <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{b.full_name} {b.days_until === 0 && '🎂'}</span>
                                            </div>
                                        </td>
                                        <td><span className={`badge ${b.person_type === 'staff' ? 'badge-primary' : 'badge-info'}`}>{b.person_type}</span></td>
                                        <td style={{ color: 'var(--text-secondary)' }}>{b.person_type === 'student' ? `${b.class_name || '-'} (${b.section_name || '-'})` : '-'}</td>
                                        <td><span className="badge badge-success">{b.birthday_display}</span></td>
                                        <td>
                                            {b.days_until === 0 ? (
                                                <span className="badge badge-danger" style={{ animation: 'pulse 2s infinite' }}>🎉 Today!</span>
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)' }}>{b.days_until} day{b.days_until > 1 ? 's' : ''}</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   LEAVES TAB
   ════════════════════════════════════════════════════════════════════════════ */
const LeavesTab: React.FC = () => {
    const [requests, setRequests] = useState<any[]>([]);
    const [staff, setStaff] = useState<any[]>([]);
    const [leaveTypes, setLeaveTypes] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ user_id: '', leave_type_id: '', from_date: '', to_date: '', reason: '' });

    const load = useCallback(() => {
        setLoading(true);
        Promise.allSettled([
            hrService.getLeaveRequests(statusFilter || undefined),
            hrService.staff(),
            hrService.getLeaveTypes(),
        ]).then(([r, s, lt]) => {
            if (r.status === 'fulfilled') setRequests(r.value.data?.data?.leave_requests ?? []);
            if (s.status === 'fulfilled') setStaff(s.value.data?.data?.staff ?? []);
            if (lt.status === 'fulfilled') setLeaveTypes(lt.value.data?.data?.leave_types ?? []);
        }).finally(() => setLoading(false));
    }, [statusFilter]);

    useEffect(() => { load(); }, [load]);

    const handleSubmit = async () => {
        try {
            await hrService.createLeaveRequest(form);
            toast.success('Leave request submitted');
            setShowCreate(false);
            setForm({ user_id: '', leave_type_id: '', from_date: '', to_date: '', reason: '' });
            load();
        } catch { toast.error('Failed to submit leave request'); }
    };

    const handleAction = async (id: number, action: 'approve' | 'reject') => {
        try {
            if (action === 'approve') await hrService.approveLeave(id);
            else await hrService.rejectLeave(id);
            toast.success(`Leave ${action}d`);
            load();
        } catch { toast.error(`Failed to ${action} leave`); }
    };

    return (
        <div className="card">
            <div className="card-header" style={{ padding: '18px 20px' }}>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Calendar size={20} style={{ color: 'var(--primary)' }} /> Leave Requests
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                    <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} style={{ width: 130 }}>
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button className="btn btn-primary" onClick={() => setShowCreate(true)}>
                        <Plus size={16} /> Apply Leave
                    </button>
                </div>
            </div>
            <div className="card-body">
                {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : (
                    <div className="table-wrapper">
                        <table>
                            <thead><tr><th>Staff</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                {requests.length === 0 ? (
                                    <tr><td colSpan={8} style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No leave requests found</td></tr>
                                ) : requests.map((r: any) => (
                                    <tr key={r.id}>
                                        <td style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{r.staff_name}</td>
                                        <td><span className="badge badge-primary">{r.leave_type_name}</span></td>
                                        <td>{r.from_date}</td>
                                        <td>{r.to_date}</td>
                                        <td>{r.days}</td>
                                        <td style={{ maxWidth: 180, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.reason || '-'}</td>
                                        <td>
                                            <span className={`badge ${r.status === 'approved' ? 'badge-success' : r.status === 'rejected' ? 'badge-danger' : 'badge-warning'}`}>
                                                {r.status}
                                            </span>
                                        </td>
                                        <td>
                                            {r.status === 'pending' && (
                                                <div style={{ display: 'flex', gap: 4 }}>
                                                    <button className="btn btn-success btn-sm" onClick={() => handleAction(r.id, 'approve')} title="Approve">
                                                        <Check size={14} />
                                                    </button>
                                                    <button className="btn btn-danger btn-sm" onClick={() => handleAction(r.id, 'reject')} title="Reject">
                                                        <X size={14} />
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Create Leave Modal */}
            {showCreate && (
                <Modal isOpen={showCreate} title="Apply Leave" onClose={() => setShowCreate(false)}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="form-group">
                            <label>Staff Member *</label>
                            <select value={form.user_id} onChange={e => setForm({ ...form, user_id: e.target.value })}>
                                <option value="">Select staff</option>
                                {staff.map(s => <option key={s.id} value={s.id}>{s.full_name} ({s.employee_id})</option>)}
                            </select>
                        </div>
                        <div className="form-group">
                            <label>Leave Type *</label>
                            <select value={form.leave_type_id} onChange={e => setForm({ ...form, leave_type_id: e.target.value })}>
                                <option value="">Select type</option>
                                {leaveTypes.map(lt => <option key={lt.id} value={lt.id}>{lt.name} ({lt.days_allowed} days)</option>)}
                            </select>
                        </div>
                        <div className="grid-2">
                            <div className="form-group"><label>From Date *</label><input className="input" type="date" value={form.from_date} onChange={e => setForm({ ...form, from_date: e.target.value })} /></div>
                            <div className="form-group"><label>To Date *</label><input className="input" type="date" value={form.to_date} onChange={e => setForm({ ...form, to_date: e.target.value })} /></div>
                        </div>
                        <div className="form-group"><label>Reason</label><textarea value={form.reason} onChange={e => setForm({ ...form, reason: e.target.value })} placeholder="Reason for leave..." rows={3} /></div>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleSubmit} disabled={!form.user_id || !form.leave_type_id || !form.from_date || !form.to_date}>Submit</button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   PAYROLL TAB
   ════════════════════════════════════════════════════════════════════════════ */
const PayrollTab: React.FC = () => {
    const now = new Date();
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [year, setYear] = useState(now.getFullYear());
    const [payroll, setPayroll] = useState<any[]>([]);
    const [summary, setSummary] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [showSalary, setShowSalary] = useState(false);
    const [staff, setStaff] = useState<any[]>([]);
    const [salaryForm, setSalaryForm] = useState({ user_id: '', salary: '' });

    const load = useCallback(() => {
        setLoading(true);
        hrService.getPayroll(month, year)
            .then(r => {
                setPayroll(r.data?.data?.payroll ?? []);
                setSummary(r.data?.data?.summary ?? null);
            })
            .catch(() => toast.error('Failed to load payroll'))
            .finally(() => setLoading(false));
    }, [month, year]);

    useEffect(() => { load(); }, [load]);

    const handleGenerate = async () => {
        try {
            const r = await hrService.generatePayroll(month, year);
            toast.success(r.data?.message || 'Payroll generated');
            load();
        } catch { toast.error('Failed to generate payroll'); }
    };

    const handlePay = async (id: number) => {
        try {
            await hrService.paySalary(id, 'bank_transfer');
            toast.success('Salary paid');
            load();
        } catch { toast.error('Failed to process payment'); }
    };

    const handleSetSalary = async () => {
        try {
            await hrService.setSalary(Number(salaryForm.user_id), Number(salaryForm.salary));
            toast.success('Salary updated');
            setShowSalary(false);
            setSalaryForm({ user_id: '', salary: '' });
        } catch { toast.error('Failed to set salary'); }
    };

    const openSetSalary = () => {
        hrService.staff().then(r => setStaff(r.data?.data?.staff ?? [])).catch(() => {});
        setShowSalary(true);
    };

    return (
        <div>
            <div className="card" style={{ marginBottom: 20 }}>
                <div className="card-header" style={{ padding: '18px 20px' }}>
                    <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <DollarSign size={20} style={{ color: 'var(--success)' }} />
                        Payroll — {MONTHS[month - 1]} {year}
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <select value={month} onChange={e => setMonth(Number(e.target.value))} style={{ width: 130 }}>
                            {MONTHS.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
                        </select>
                        <select value={year} onChange={e => setYear(Number(e.target.value))} style={{ width: 90 }}>
                            {[2024, 2025, 2026, 2027].map(y => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <button className="btn btn-secondary" onClick={openSetSalary}><DollarSign size={14} /> Set Salary</button>
                        <button className="btn btn-primary" onClick={handleGenerate}><ArrowUpDown size={14} /> Generate</button>
                    </div>
                </div>
            </div>

            {/* Summary Cards */}
            {summary && (
                <div className="stat-grid" style={{ gridTemplateColumns: 'repeat(4, 1fr)', marginBottom: 20 }}>
                    <div className="stat-card purple"><div className="stat-value" style={{ fontSize: '1.5rem' }}>₹{Number(summary.total_payable ?? 0).toLocaleString('en-IN')}</div><div className="stat-label">Total Payable</div></div>
                    <div className="stat-card green"><div className="stat-value" style={{ fontSize: '1.5rem' }}>₹{Number(summary.total_paid ?? 0).toLocaleString('en-IN')}</div><div className="stat-label">Total Paid</div></div>
                    <div className="stat-card yellow"><div className="stat-value" style={{ fontSize: '1.5rem' }}>₹{Number(summary.total_pending ?? 0).toLocaleString('en-IN')}</div><div className="stat-label">Pending</div></div>
                    <div className="stat-card blue"><div className="stat-value" style={{ fontSize: '1.5rem' }}>{summary.total_staff ?? 0}</div><div className="stat-label">Staff Count</div></div>
                </div>
            )}

            {/* Payroll Table */}
            <div className="card">
                <div className="card-body">
                    {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : payroll.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}>
                            <CreditCard size={40} style={{ margin: '0 auto 12px', display: 'block', opacity: 0.4 }} />
                            <div>No payroll data for this month.</div>
                            <div style={{ fontSize: '0.8rem', marginTop: 6 }}>Click "Generate" to create payroll entries.</div>
                        </div>
                    ) : (
                        <div className="table-wrapper">
                            <table>
                                <thead><tr><th>Staff Name</th><th>Employee ID</th><th>Basic Salary</th><th>Deductions</th><th>Net Salary</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    {payroll.map((p: any) => (
                                        <tr key={p.id}>
                                            <td style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{p.staff_name}</td>
                                            <td style={{ color: 'var(--text-muted)' }}>{p.employee_id || '-'}</td>
                                            <td style={{ color: 'var(--success)' }}>₹{Number(p.basic_salary).toLocaleString('en-IN')}</td>
                                            <td style={{ color: 'var(--danger)' }}>₹{Number(p.deductions).toLocaleString('en-IN')}</td>
                                            <td style={{ fontWeight: 700, color: 'var(--text-primary)' }}>₹{Number(p.net_salary).toLocaleString('en-IN')}</td>
                                            <td>
                                                <span className={`badge ${p.status === 'paid' ? 'badge-success' : 'badge-danger'}`}>{p.status}</span>
                                            </td>
                                            <td>
                                                {p.status === 'unpaid' && (
                                                    <button className="btn btn-success btn-sm" onClick={() => handlePay(p.id)}>
                                                        <CreditCard size={14} /> Pay
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Set Salary Modal */}
            {showSalary && (
                <Modal isOpen={showSalary} title="Set Staff Salary" onClose={() => setShowSalary(false)}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="form-group">
                            <label>Staff Member *</label>
                            <select value={salaryForm.user_id} onChange={e => setSalaryForm({ ...salaryForm, user_id: e.target.value })}>
                                <option value="">Select staff</option>
                                {staff.map(s => <option key={s.id} value={s.id}>{s.full_name} — Current: ₹{Number(s.salary ?? 0).toLocaleString('en-IN')}</option>)}
                            </select>
                        </div>
                        <div className="form-group">
                            <label>Monthly Salary (₹) *</label>
                            <input className="input" type="number" min="0" value={salaryForm.salary} onChange={e => setSalaryForm({ ...salaryForm, salary: e.target.value })} placeholder="e.g. 25000" />
                        </div>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowSalary(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleSetSalary} disabled={!salaryForm.user_id || !salaryForm.salary}>Save Salary</button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   DEPARTMENTS TAB
   ════════════════════════════════════════════════════════════════════════════ */
const DepartmentsTab: React.FC = () => {
    const [items, setItems] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', description: '' });

    const load = useCallback(() => {
        setLoading(true);
        hrService.getDepartments().then(r => setItems(r.data?.data?.departments ?? []))
            .catch(() => toast.error('Failed to load departments'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => { load(); }, [load]);

    const handleCreate = async () => {
        if (!form.name) return;
        try {
            await hrService.createDepartment(form);
            toast.success('Department created');
            setShowCreate(false);
            setForm({ name: '', description: '' });
            load();
        } catch { toast.error('Failed to create department'); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this department?')) return;
        try { await hrService.deleteDepartment(id); toast.success('Deleted'); load(); }
        catch { toast.error('Failed to delete'); }
    };

    return (
        <div className="card">
            <div className="card-header" style={{ padding: '18px 20px' }}>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Building2 size={20} style={{ color: 'var(--secondary)' }} /> Departments
                </div>
                <button className="btn btn-primary" onClick={() => setShowCreate(true)}><Plus size={16} /> Add Department</button>
            </div>
            <div className="card-body">
                {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : items.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}>No departments yet. Click "Add Department" to get started.</div>
                ) : (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 14 }}>
                        {items.map((d: any) => (
                            <div key={d.id} style={{ background: 'var(--bg-elevated)', borderRadius: 'var(--radius-md)', padding: 18, border: '1px solid var(--bg-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                <div>
                                    <div style={{ fontWeight: 700, color: 'var(--text-primary)', marginBottom: 4 }}>{d.name}</div>
                                    {d.description && <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>{d.description}</div>}
                                    {d.head_name && <div style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', marginTop: 6 }}>Head: {d.head_name}</div>}
                                </div>
                                <button onClick={() => handleDelete(d.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--danger)', padding: 4 }}>
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {showCreate && (
                <Modal isOpen={showCreate} title="Add Department" onClose={() => setShowCreate(false)}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="form-group"><label>Name *</label><input className="input" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="e.g. Science" /></div>
                        <div className="form-group"><label>Description</label><textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} placeholder="Optional description" /></div>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleCreate} disabled={!form.name}>Create</button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   DESIGNATIONS TAB
   ════════════════════════════════════════════════════════════════════════════ */
const DesignationsTab: React.FC = () => {
    const [items, setItems] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', description: '' });

    const load = useCallback(() => {
        setLoading(true);
        hrService.getDesignations().then(r => setItems(r.data?.data?.designations ?? []))
            .catch(() => toast.error('Failed to load'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => { load(); }, [load]);

    const handleCreate = async () => {
        if (!form.name) return;
        try { await hrService.createDesignation(form); toast.success('Created'); setShowCreate(false); setForm({ name: '', description: '' }); load(); }
        catch { toast.error('Failed'); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this designation?')) return;
        try { await hrService.deleteDesignation(id); toast.success('Deleted'); load(); }
        catch { toast.error('Failed'); }
    };

    return (
        <div className="card">
            <div className="card-header" style={{ padding: '18px 20px' }}>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Briefcase size={20} style={{ color: 'var(--info)' }} /> Designations
                </div>
                <button className="btn btn-primary" onClick={() => setShowCreate(true)}><Plus size={16} /> Add Designation</button>
            </div>
            <div className="card-body">
                {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : items.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}>No designations yet.</div>
                ) : (
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 14 }}>
                        {items.map((d: any) => (
                            <div key={d.id} style={{ background: 'var(--bg-elevated)', borderRadius: 'var(--radius-md)', padding: 18, border: '1px solid var(--bg-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                <div>
                                    <div style={{ fontWeight: 700, color: 'var(--text-primary)', marginBottom: 4 }}>{d.name}</div>
                                    {d.description && <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>{d.description}</div>}
                                </div>
                                <button onClick={() => handleDelete(d.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--danger)', padding: 4 }}>
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {showCreate && (
                <Modal isOpen={showCreate} title="Add Designation" onClose={() => setShowCreate(false)}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="form-group"><label>Name *</label><input className="input" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="e.g. Principal, Vice Principal, HOD" /></div>
                        <div className="form-group"><label>Description</label><textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} placeholder="Optional" /></div>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleCreate} disabled={!form.name}>Create</button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

/* ════════════════════════════════════════════════════════════════════════════
   LEAVE TYPES TAB
   ════════════════════════════════════════════════════════════════════════════ */
const LeaveTypesTab: React.FC = () => {
    const [items, setItems] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', days_allowed: '10', is_paid: '1' });

    const load = useCallback(() => {
        setLoading(true);
        hrService.getLeaveTypes().then(r => setItems(r.data?.data?.leave_types ?? []))
            .catch(() => toast.error('Failed to load'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => { load(); }, [load]);

    const handleCreate = async () => {
        if (!form.name) return;
        try { await hrService.createLeaveType({ name: form.name, days_allowed: Number(form.days_allowed), is_paid: Number(form.is_paid) }); toast.success('Created'); setShowCreate(false); setForm({ name: '', days_allowed: '10', is_paid: '1' }); load(); }
        catch { toast.error('Failed'); }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Delete this leave type?')) return;
        try { await hrService.deleteLeaveType(id); toast.success('Deleted'); load(); }
        catch { toast.error('Failed'); }
    };

    return (
        <div className="card">
            <div className="card-header" style={{ padding: '18px 20px' }}>
                <div className="card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <ClipboardList size={20} style={{ color: 'var(--warning)' }} /> Leave Types
                </div>
                <button className="btn btn-primary" onClick={() => setShowCreate(true)}><Plus size={16} /> Add Leave Type</button>
            </div>
            <div className="card-body">
                {loading ? <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" style={{ width: 28, height: 28, margin: '0 auto' }} /></div> : items.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 60, color: 'var(--text-muted)' }}>No leave types defined yet.</div>
                ) : (
                    <div className="table-wrapper">
                        <table>
                            <thead><tr><th>Name</th><th>Days Allowed</th><th>Paid/Unpaid</th><th>Action</th></tr></thead>
                            <tbody>
                                {items.map((lt: any) => (
                                    <tr key={lt.id}>
                                        <td style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{lt.name}</td>
                                        <td>{lt.days_allowed} days</td>
                                        <td><span className={`badge ${lt.is_paid == 1 ? 'badge-success' : 'badge-warning'}`}>{lt.is_paid == 1 ? 'Paid' : 'Unpaid'}</span></td>
                                        <td>
                                            <button onClick={() => handleDelete(lt.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--danger)' }}>
                                                <Trash2 size={16} />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {showCreate && (
                <Modal isOpen={showCreate} title="Add Leave Type" onClose={() => setShowCreate(false)}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        <div className="form-group"><label>Name *</label><input className="input" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="e.g. Casual Leave, Sick Leave" /></div>
                        <div className="grid-2">
                            <div className="form-group"><label>Days Allowed</label><input className="input" type="number" min="1" value={form.days_allowed} onChange={e => setForm({ ...form, days_allowed: e.target.value })} /></div>
                            <div className="form-group">
                                <label>Type</label>
                                <select value={form.is_paid} onChange={e => setForm({ ...form, is_paid: e.target.value })}>
                                    <option value="1">Paid</option>
                                    <option value="0">Unpaid</option>
                                </select>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowCreate(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleCreate} disabled={!form.name}>Create</button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default HumanResource;
