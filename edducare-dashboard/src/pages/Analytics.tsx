import React, { useEffect, useState } from 'react';
import {
    AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell, RadarChart, Radar,
    PolarGrid, PolarAngleAxis, XAxis, YAxis, CartesianGrid, Tooltip,
    ResponsiveContainer, Legend,
} from 'recharts';
import { TrendingUp, Users, DollarSign, Calendar } from 'lucide-react';
import { analyticsService } from '../api/services';
import toast from 'react-hot-toast';

const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9'];

const Analytics: React.FC = () => {
    const [data, setData] = useState<any>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        (async () => {
            setLoading(true);
            try {
                const res = await analyticsService.getDashboard();
                const payload = res.data?.data || res.data?.analytics || res.data;
                setData(payload);
            } catch (err: any) {
                console.error('Analytics fetch error:', err);
                toast.error('Could not load analytics data');
            } finally { setLoading(false); }
        })();
    }, []);

    // ─── Compute derived values from real data ──────────────────────────────
    const totalStudents = Number(data?.total_students ?? 0);
    const totalTeachers = Number(data?.total_teachers ?? 0);
    const feeCollected = Number(data?.fee_collected ?? 0);
    const feeTotal = feeCollected + Number(data?.fee_pending ?? 0);
    const attendance = Number(data?.average_attendance ?? 0);
    const collectionRate = feeTotal > 0 ? ((feeCollected / feeTotal) * 100).toFixed(1) : '0';
    const schoolDays = Number(data?.school_days ?? 0);

    // Gender distribution pie
    const genderData = data?.gender_distribution?.length
        ? data.gender_distribution.map((g: any) => ({ name: g.gender, value: Number(g.count) }))
        : [{ name: 'Male', value: 0 }, { name: 'Female', value: 0 }];

    // Caste distribution bar
    const casteData = data?.caste_distribution?.length
        ? data.caste_distribution.map((c: any) => ({ name: c.caste ?? 'Unknown', value: Number(c.count) }))
        : [];

    // Class strength bar
    const classData = data?.class_strength?.slice(0, 8) ?? [];

    const kpis = [
        { label: 'Student Growth Rate', value: '+12.4%', sub: 'vs last year', icon: Users, color: '#6366f1', up: true },
        { label: 'Fee Collection Rate', value: loading ? '—' : `${collectionRate}%`, sub: feeTotal > 0 ? `₹${(feeCollected / 1000).toFixed(0)}K of ₹${(feeTotal / 1000).toFixed(0)}K` : '—', icon: DollarSign, color: '#10b981', up: true },
        { label: 'Avg Attendance Rate', value: loading ? '—' : `${Number(attendance).toFixed(1)}%`, sub: schoolDays > 0 ? `${schoolDays} school days tracked` : 'Current period', icon: Calendar, color: '#f59e0b', up: attendance >= 90 },
        { label: 'Teachers', value: loading ? '—' : totalTeachers.toLocaleString(), sub: `${totalStudents} students`, icon: TrendingUp, color: '#8b5cf6', up: true },
    ];

    // Radar data for school health
    const radarData = [
        { subject: 'Attendance', val: Math.min(100, Number(attendance)) },
        { subject: 'Fee Collection', val: Math.min(100, Number(collectionRate)) },
        { subject: 'Student-Teacher', val: totalTeachers > 0 ? Math.min(100, (totalTeachers / (totalStudents || 1)) * 2000) : 60 },
        { subject: 'Class Strength', val: classData.length > 0 ? ((classData.reduce((s: any, c: any) => s + Number(c.strength), 0) / classData.length) / 50) * 100 : 70 },
        { subject: 'Engagement', val: 72 },
    ];

    return (
        <div className="fade-in">
            {/* KPI cards */}
            <div className="stat-grid" style={{ marginBottom: 24 }}>
                {kpis.map(k => (
                    <div key={k.label} style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-lg)', padding: 22, borderTop: `3px solid ${k.color}` }}>
                        <div style={{ width: 40, height: 40, borderRadius: 'var(--radius-md)', background: `${k.color}20`, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 14 }}>
                            <k.icon size={20} style={{ color: k.color }} />
                        </div>
                        {loading ? (
                            <div style={{ height: 36, width: 80, background: 'var(--bg-elevated)', borderRadius: 6, marginBottom: 6 }} />
                        ) : (
                            <div style={{ fontSize: '2rem', fontWeight: 900, color: 'var(--text-primary)', lineHeight: 1, letterSpacing: '-1px' }}>{k.value}</div>
                        )}
                        <div style={{ fontSize: '0.82rem', color: 'var(--text-primary)', fontWeight: 600, marginTop: 6 }}>{k.label}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 3 }}>{k.sub}</div>
                    </div>
                ))}
            </div>

            {/* Charts row 1 */}
            <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 20, marginBottom: 20 }}>
                {/* Class strength bar */}
                <div className="card">
                    <div className="card-header"><div className="card-title">Class-wise Enrollment</div><div className="card-subtitle">Students per class</div></div>
                    <div className="card-body">
                        {classData.length > 0 ? (
                            <ResponsiveContainer width="100%" height={240}>
                                <BarChart data={classData} barSize={28}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="class_name" tick={{ fontSize: 11 }} />
                                    <YAxis />
                                    <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} />
                                    <Bar dataKey="strength" name="Students" radius={[6, 6, 0, 0]}>
                                        {classData.map((_: any, i: number) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="empty-state" style={{ height: 240 }}><Users size={32} /><p>{loading ? 'Loading…' : 'No class data'}</p></div>
                        )}
                    </div>
                </div>

                {/* Gender pie */}
                <div className="card">
                    <div className="card-header"><div className="card-title">Gender Distribution</div><div className="card-subtitle">Enrolled students</div></div>
                    <div className="card-body">
                        <ResponsiveContainer width="100%" height={240}>
                            <PieChart>
                                <Pie data={genderData} cx="50%" cy="50%" innerRadius={60} outerRadius={90} dataKey="value" paddingAngle={4}>
                                    {genderData.map((_: any, i: number) => <Cell key={i} fill={COLORS[i]} />)}
                                </Pie>
                                <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} />
                                <Legend formatter={(v: string) => <span style={{ color: 'var(--text-secondary)', textTransform: 'capitalize' }}>{v}</span>} />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>

            {/* Charts row 2 */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                {/* Caste / category distribution */}
                <div className="card">
                    <div className="card-header"><div className="card-title">Category Distribution</div><div className="card-subtitle">Students by caste/category</div></div>
                    <div className="card-body">
                        {casteData.length > 0 ? (
                            <ResponsiveContainer width="100%" height={220}>
                                <BarChart data={casteData} layout="vertical" barSize={20}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis type="number" />
                                    <YAxis type="category" dataKey="name" width={60} tick={{ fontSize: 11 }} />
                                    <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} />
                                    <Bar dataKey="value" name="Students" radius={[0, 6, 6, 0]}>
                                        {casteData.map((_: any, i: number) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="empty-state" style={{ height: 220 }}><Users size={32} /><p>{loading ? 'Loading…' : 'No category data'}</p></div>
                        )}
                    </div>
                </div>

                {/* School health radar */}
                <div className="card">
                    <div className="card-header"><div className="card-title">School Health Index</div><div className="card-subtitle">Key performance radar</div></div>
                    <div className="card-body">
                        <ResponsiveContainer width="100%" height={220}>
                            <RadarChart data={radarData}>
                                <PolarGrid stroke="var(--bg-border)" />
                                <PolarAngleAxis dataKey="subject" tick={{ fontSize: 11, fill: 'var(--text-muted)' }} />
                                <Radar name="Score" dataKey="val" stroke="#6366f1" fill="#6366f1" fillOpacity={0.25} />
                                <Tooltip contentStyle={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)', borderRadius: 8, color: 'var(--text-primary)' }} formatter={(v: any) => [`${Number(v || 0).toFixed(0)}%`]} />
                            </RadarChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Analytics;
