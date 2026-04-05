import React, { useEffect, useState } from 'react';
import {
    AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend,
} from 'recharts';
import { Users, BookOpen, DollarSign, Calendar, TrendingUp, TrendingDown, Activity, Clock } from 'lucide-react';
import { analyticsService } from '../api/services';
import { useAuth } from '../contexts/AuthContext';

// ─── Fallback data shown while API loads ─────────────────────────────────────
// ─── Fallback data ────────────────────────────────────────────────────────
const FEE_COLORS = ['#10b981', '#f59e0b', '#ef4444'];
const PIE_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9'];

interface DashboardStats {
    total_students: number;
    total_teachers: number;
    fee_collected: number;
    fee_pending?: number;
    average_attendance: number;
    school_days?: number;
    pending_fee_count: number;
    total_books: number;
    exams_pending: number;
    class_strength: { class_name: string; strength: number }[];
    gender_distribution: { gender: string; count: number }[];
    caste_distribution: { caste: string; count: number }[];
    recent_activities: { action: string; entity_type: string; user_name: string; created_at: string }[];
}

const FALLBACK_STATS: DashboardStats = {
    total_students: 0, total_teachers: 0, fee_collected: 0, average_attendance: 0,
    class_strength: [], gender_distribution: [], caste_distribution: [],
    pending_fee_count: 0, total_books: 0, exams_pending: 0, recent_activities: [],
};

const Dashboard: React.FC = () => {
    const { user } = useAuth();
    console.log('Dashboard for user:', user?.username);
    const [stats, setStats] = useState<DashboardStats>(FALLBACK_STATS as any);
    const [loading, setLoading] = useState(true);
    const [lastUpdated, setLastUpdated] = useState('');

    useEffect(() => {
        (async () => {
            setLoading(true);
            try {
                const res = await analyticsService.getDashboard();
                // Handle different response structures gracefully
                const payload = res.data?.data || res.data?.analytics || res.data;
                
                if (payload && typeof payload === 'object') {
                    // Normalize data structure
                    const counts = payload.counts || {};
                    const processed = {
                        total_students: counts.total_students ?? payload.total_students ?? 0,
                        total_teachers: counts.total_teachers ?? payload.total_teachers ?? 0,
                        fee_collected: counts.fee_collected ?? payload.fee_collected ?? 0,
                        fee_pending: counts.fee_pending ?? payload.fee_pending ?? 0,
                        average_attendance: counts.avg_attendance ?? payload.average_attendance ?? 0,
                        pending_fee_count: counts.pending_fee_count ?? payload.pending_fee_count ?? 0,
                        total_books: counts.total_books ?? payload.total_books ?? 0,
                        exams_pending: counts.exams_pending ?? payload.exams_pending ?? 0,
                        class_strength: payload.class_strength || [],
                        gender_distribution: payload.gender_distribution || [],
                        recent_activities: payload.recent_activities || []
                    };

                    setStats(processed as any);
                }
                setLastUpdated(new Date().toLocaleTimeString());
            } catch (err) {
                console.error('Dashboard fetch error:', err);
                setLastUpdated(new Date().toLocaleTimeString());
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    // Build pie chart data - Use real data or empty
    const genderPie = (stats.gender_distribution || []).map(g => ({ name: g.gender, value: Number(g.count) }));

    // Class-strength bar chart - Use real data only
    const classBar = (stats.class_strength || []).slice(0, 10);

    // Fee breakdown pie
    const feePie = [
        { name: 'Collected', value: Number(stats.fee_collected ?? 0) },
        { name: 'Pending', value: Number(stats.fee_pending ?? 0) },
    ].filter(x => x.value > 0);

    const attendanceRate = Number(stats.average_attendance ?? 0).toFixed(1);
    const feeK = (n: number) => n >= 1000 ? `₹${(n / 1000).toFixed(1)}K` : `₹${n}`;

    const kpis = [
        {
            label: 'Total Students',
            value: loading ? '—' : (Number(stats.total_students ?? 0)).toLocaleString('en-IN'),
            icon: Users, color: '#6366f1', trend: '+2.4%', up: true,
        },
        {
            label: 'Total Teachers',
            value: loading ? '—' : (Number(stats.total_teachers ?? 0)).toLocaleString('en-IN'),
            icon: BookOpen, color: '#0ea5e9', trend: '+1.1%', up: true,
        },
        {
            label: 'Fee Collected',
            value: loading ? '—' : feeK(Number(stats.fee_collected ?? 0)),
            icon: DollarSign, color: '#10b981', trend: '+8.2%', up: true,
        },
        {
            label: 'Attendance Rate',
            value: loading ? '—' : `${attendanceRate}%`,
            icon: Calendar, color: '#f59e0b', trend: '-0.6%', up: false,
        },
    ];

    const secondaryKpis = [
        { label: 'Total Classes', value: loading ? '—' : (classBar.length), color: '#6366f1', icon: '🏫' },
        { label: 'Pending Fees', value: loading ? '—' : Number(stats.pending_fee_count ?? 0), color: '#f59e0b', icon: '⏰' },
        { label: 'Exams Pending', value: loading ? '—' : Number(stats.exams_pending ?? 0), color: '#ef4444', icon: '📝' },
        { label: 'Library Books', value: loading ? '—' : (Number(stats.total_books ?? 0)).toLocaleString('en-IN'), color: '#0ea5e9', icon: '📚' },
    ];

    const activities = stats.recent_activities || [];

    return (
        <div className="fade-in">
            {/* Live indicator */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 24 }}>
                <span style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                    Academic Year 2025–26
                </span>
                <span style={{ fontSize: '0.75rem', color: loading ? 'var(--warning)' : 'var(--success)', display: 'flex', alignItems: 'center', gap: 5 }}>
                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: loading ? 'var(--warning)' : 'var(--success)', display: 'inline-block', animation: loading ? 'spin 1s linear infinite' : 'none' }} />
                    {loading ? 'Loading…' : `Live data · updated ${lastUpdated}`}
                </span>
            </div>

            {/* Primary KPI cards */}
            <div className="stat-grid" style={{ marginBottom: 20 }}>
                {kpis.map(k => (
                    <div key={k.label} style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-lg)', padding: 24, borderTop: `3px solid ${k.color}`, position: 'relative', overflow: 'hidden' }}>
                        <div style={{ position: 'absolute', top: 0, right: 0, width: 80, height: 80, borderRadius: '0 0 0 80px', background: `${k.color}08` }} />
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16 }}>
                            <div style={{ width: 44, height: 44, borderRadius: 'var(--radius-md)', background: `${k.color}20`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <k.icon size={20} style={{ color: k.color }} />
                            </div>
                            <span style={{ fontSize: '0.78rem', color: k.up ? 'var(--success)' : 'var(--danger)', display: 'flex', alignItems: 'center', gap: 3, background: k.up ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', padding: '3px 8px', borderRadius: 20 }}>
                                {k.up ? <TrendingUp size={12} /> : <TrendingDown size={12} />}{k.trend}
                            </span>
                        </div>
                        <div style={{ fontSize: '2.2rem', fontWeight: 900, color: 'var(--text-primary)', lineHeight: 1, letterSpacing: '-1px' }}>{k.value}</div>
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', marginTop: 6, fontWeight: 500 }}>{k.label}</div>
                    </div>
                ))}
            </div>

            {/* Secondary KPIs */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12, marginBottom: 24 }}>
                {secondaryKpis.map(s => (
                    <div key={s.label} style={{ background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-md)', padding: '14px 18px', display: 'flex', alignItems: 'center', gap: 12 }}>
                        <span style={{ fontSize: '1.4rem' }}>{s.icon}</span>
                        <div>
                            <div style={{ fontSize: '1.4rem', fontWeight: 800, color: 'var(--text-primary)', lineHeight: 1 }}>{s.value}</div>
                            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', marginTop: 2 }}>{s.label}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Charts row 1 */}
            <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 20, marginBottom: 20 }}>
                {/* Class-wise strength bar */}
                <div className="card">
                    <div className="card-header">
                        <div className="card-title">Class-wise Student Strength</div>
                        <div className="card-subtitle">Current enrollment per class</div>
                    </div>
                    <div className="card-body">
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={classBar} barSize={32}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="class_name" />
                                <YAxis />
                                <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} />
                                <Bar dataKey="strength" name="Students" radius={[6, 6, 0, 0]}>
                                    {classBar.map((_, i) => (
                                        <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Gender pie */}
                <div className="card">
                    <div className="card-header">
                        <div className="card-title">Gender Distribution</div>
                        <div className="card-subtitle">Student breakdown</div>
                    </div>
                    <div className="card-body" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie data={genderPie} cx="50%" cy="50%" innerRadius={55} outerRadius={85} dataKey="value" paddingAngle={3}>
                                    {genderPie.map((_, i) => <Cell key={i} fill={PIE_COLORS[i]} />)}
                                </Pie>
                                <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} />
                                <Legend formatter={(val) => <span style={{ color: 'var(--text-secondary)', fontSize: '0.8rem', textTransform: 'capitalize' }}>{val}</span>} />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>

            {/* Charts row 2 */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 20 }}>
                {/* Fee pie */}
                <div className="card">
                    <div className="card-header">
                        <div className="card-title">Fee Status</div>
                        <div className="card-subtitle">Current term overview</div>
                    </div>
                    <div className="card-body">
                        {feePie.length > 0 ? (
                            <ResponsiveContainer width="100%" height={200}>
                                <PieChart>
                                    <Pie data={feePie} cx="50%" cy="50%" innerRadius={55} outerRadius={85} dataKey="value" paddingAngle={3}>
                                        {feePie.map((_, i) => <Cell key={i} fill={FEE_COLORS[i]} />)}
                                    </Pie>
                                    <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} formatter={(v: number) => [`₹${v.toLocaleString('en-IN')}`]} />
                                    <Legend formatter={(val) => <span style={{ color: 'var(--text-secondary)', fontSize: '0.8rem' }}>{val}</span>} />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="empty-state" style={{ height: 200 }}>
                                <DollarSign size={32} style={{ opacity: 0.3 }} />
                                <p>Fee data from API</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Recent activity */}
                <div className="card">
                    <div className="card-header">
                        <div className="card-title">Recent Activity</div>
                        <div className="card-subtitle">Latest actions across the school</div>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        {activities.map((a, i) => (
                            <div key={i} style={{ display: 'flex', gap: 12, padding: '12px 20px', borderBottom: i < activities.length - 1 ? '1px solid var(--bg-border)' : 'none', alignItems: 'flex-start' }}>
                                <div style={{ width: 32, height: 32, borderRadius: '50%', background: 'var(--primary-glow)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, marginTop: 2 }}>
                                    <Activity size={14} style={{ color: 'var(--primary-light)' }} />
                                </div>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontSize: '0.85rem', color: 'var(--text-primary)', fontWeight: 600, textTransform: 'capitalize' }}>
                                        {(a.action ?? '').replace(/_/g, ' ')} —{' '}
                                        <span style={{ color: 'var(--text-secondary)', textTransform: 'capitalize' }}>{a.entity_type}</span>
                                    </div>
                                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2, display: 'flex', alignItems: 'center', gap: 5 }}>
                                        <Clock size={11} />
                                        {a.user_name} · {a.created_at ? new Date(a.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : ''}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
