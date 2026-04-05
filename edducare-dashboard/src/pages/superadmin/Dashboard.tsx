import React, { useEffect, useState } from 'react';
import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { School, DollarSign, Activity, ShieldCheck, Package } from 'lucide-react';
import { superadminService } from '../../api/superadmin';

interface DashboardStats {
    schools: {
        total: number;
        active: number;
        blocked: number;
        purchased: number;
    };
    revenue: number;
    students: number;
    recent_schools: {
        name: string;
        code: string;
        created_at: string;
        package_name: string;
    }[];
}

const SuperAdminDashboard: React.FC = () => {
    const [stats, setStats] = useState<DashboardStats | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const res = await superadminService.getDashboardStats();
                setStats(res.data.data);
            } catch (error) {
                console.error('Failed to fetch stats', error);
            } finally {
                setLoading(false);
            }
        };
        fetchStats();
    }, []);

    const kpis = [
        { label: 'Total Schools', value: stats?.schools.total ?? 0, icon: School, color: '#6366f1' },
        { label: 'Active Schools', value: stats?.schools.active ?? 0, icon: ShieldCheck, color: '#10b981' },
        { label: 'Purchased Plans', value: stats?.schools.purchased ?? 0, icon: Package, color: '#f59e0b' },
        { label: 'Total Revenue', value: `₹${(stats?.revenue ?? 0).toLocaleString('en-IN')}`, icon: DollarSign, color: '#8b5cf6' },
    ];

    const schoolStatusData = [
        { name: 'Active', value: stats?.schools.active ?? 0, color: '#10b981' },
        { name: 'Blocked', value: stats?.schools.blocked ?? 0, color: '#ef4444' },
    ];

    if (loading) return <div className="loading-container">Loading...</div>;

    return (
        <div className="fade-in">
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 24 }}>
                <Activity size={20} className="text-primary" />
                <h2 style={{ fontSize: '1.25rem', fontWeight: 600 }}>System Overview</h2>
            </div>

            <div className="stat-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 20, marginBottom: 24 }}>
                {kpis.map((k) => (
                    <div key={k.label} className="card" style={{ padding: 24, borderTop: `4px solid ${k.color}` }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                            <div style={{ padding: 10, background: `${k.color}15`, borderRadius: 12 }}>
                                <k.icon size={22} style={{ color: k.color }} />
                            </div>
                        </div>
                        <div style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-primary)' }}>{k.value}</div>
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: 4 }}>{k.label}</div>
                    </div>
                ))}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr', gap: 24 }}>
                <div className="card">
                    <div className="card-header">
                        <div className="card-title">School Status</div>
                        <div className="card-subtitle">Active vs Blocked Schools</div>
                    </div>
                    <div className="card-body" style={{ height: 300 }}>
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={schoolStatusData}
                                    innerRadius={60}
                                    outerRadius={80}
                                    paddingAngle={5}
                                    dataKey="value"
                                >
                                    {schoolStatusData.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip />
                                <Legend />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="card">
                    <div className="card-header">
                        <div className="card-title">Recent Schools</div>
                        <div className="card-subtitle">Latest schools onboarded to the platform</div>
                    </div>
                    <div className="card-body" style={{ padding: 0 }}>
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>School Name</th>
                                    <th>Code</th>
                                    <th>Package</th>
                                    <th>Joined Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {stats?.recent_schools.map((s, i) => (
                                    <tr key={i}>
                                        <td style={{ fontWeight: 600 }}>{s.name}</td>
                                        <td><code>{s.code}</code></td>
                                        <td>
                                            <span className="badge" style={{ background: 'var(--primary-glow)', color: 'var(--primary-light)' }}>
                                                {s.package_name || 'No Plan'}
                                            </span>
                                        </td>
                                        <td style={{ color: 'var(--text-muted)', fontSize: '0.875rem' }}>
                                            {new Date(s.created_at).toLocaleDateString()}
                                        </td>
                                    </tr>
                                ))}
                                {(!stats?.recent_schools || stats.recent_schools.length === 0) && (
                                    <tr>
                                        <td colSpan={4} style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>
                                            No recent schools found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SuperAdminDashboard;
