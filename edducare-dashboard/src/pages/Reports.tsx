import React, { useState, useEffect } from 'react';
import { Download, FileText, Calendar, Users, DollarSign, Award, ChevronRight, Filter, Search, Clock, FileDown } from 'lucide-react';
import toast from 'react-hot-toast';
import api from '../api/config';
import { reportService, classService } from '../api/services';

const REPORT_TYPES = [
    { id: 'attendance', icon: Calendar, label: 'Attendance Report', desc: 'Daily/monthly attendance records per class or student', color: '#6366f1' },
    { id: 'financial', icon: DollarSign, label: 'Financial Report', desc: 'Fee collection, pending dues, and payment history', color: '#10b981' },
    { id: 'academic', icon: Award, label: 'Academic Performance', desc: 'Exam results, grade distribution, subject averages', color: '#f59e0b' },
    { id: 'enrollment', icon: Users, label: 'Enrollment Report', desc: 'Student enrollment trends by class, section, or period', color: '#0ea5e9' },
    { id: 'teacher', icon: Users, label: 'Teacher Report', desc: 'Teacher attendance, assignments assigned and graded', color: '#8b5cf6' },
    { id: 'library', icon: FileText, label: 'Library Report', desc: 'Books issued, returned, and overdue per student/class', color: '#ef4444' },
];

const Reports: React.FC = () => {
    const [selected, setSelected] = useState('attendance');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [classFilter, setClassFilter] = useState('');
    const [sectionFilter, setSectionFilter] = useState('');
    const [format, setFormat] = useState<'pdf' | 'excel' | 'csv' | 'json'>('csv');
    const [generating, setGenerating] = useState(false);
    const [classes, setClasses] = useState<any[]>([]);
    const [sections, setSections] = useState<any[]>([]);
    const [recentReports, setRecentReports] = useState<any[]>([
        { name: 'Attendance_Feb_2026.csv', type: 'Attendance', size: '12 KB', date: '2026-02-25', format: 'csv' },
        { name: 'Fee_Report_Jan_2026.csv', type: 'Financial', size: '24 KB', date: '2026-02-20', format: 'csv' },
        { name: 'Exam_Results_Term1.csv', type: 'Academic', size: '18 KB', date: '2026-02-15', format: 'csv' },
    ]);

    useEffect(() => {
        fetchClasses();
    }, []);

    useEffect(() => {
        if (classFilter) {
            fetchSections(classFilter);
        } else {
            setSections([]);
            setSectionFilter('');
        }
    }, [classFilter]);

    const fetchClasses = async () => {
        try {
            const res = await classService.list();
            if (res.data.success) setClasses(res.data.data);
        } catch (err) {
            console.error('Failed to load classes');
        }
    };

    const fetchSections = async (classId: number | string) => {
        try {
            const res = await classService.sections(classId);
            if (res.data.success) setSections(res.data.data);
        } catch (err) {
            console.error('Failed to load sections');
        }
    };

    const handleGenerate = async () => {
        if (!selected) {
            toast.error('Please select a report type');
            return;
        }

        setGenerating(true);

        try {
            // Use the service to generate the path (we might need to remove the token logic from service)
            const reportUrl = reportService.generate({
                type: selected as any,
                format: format,
                dateFrom,
                dateTo,
                classId: classFilter,
                sectionId: sectionFilter
            });

            // For JSON/CSV/Excel, we download via Axios to ensure headers are sent
            if (format !== 'pdf') {
                const response = await api.get(reportUrl, { responseType: 'blob' });
                const blob = new Blob([response.data], { type: response.headers['content-type'] });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                
                // Extract filename
                const disposition = response.headers['content-disposition'];
                let fileName = `${selected}_report_${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'csv' : format}`;
                if (disposition && disposition.indexOf('filename=') !== -1) {
                    const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    const matches = filenameRegex.exec(disposition);
                    if (matches != null && matches[1]) fileName = matches[1].replace(/['"]/g, '');
                }
                
                link.setAttribute('download', fileName);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
                
                toast.success('Report downloaded successfully!');
            } else {
                // For PDF (HTML view), we can open in new tab
                // We'll still use window.open but now the backend supports token in URL if headers fail
                window.open(reportUrl, '_blank');
                toast.success('Report opened in new tab for printing');
            }

            // Update recent reports list
            const newReport = {
                name: `${selected}_report_${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'csv' : format}`,
                type: REPORT_TYPES.find(r => r.id === selected)?.label,
                size: 'Generated',
                date: new Date().toISOString().split('T')[0],
                format: format
            };
            setRecentReports(prev => [newReport, ...prev.slice(0, 4)]);

        } catch (err: any) {
            console.error('Report error:', err);
            const errorMsg = err.response?.data?.error || 'Failed to generate report. Please check your filters and try again.';
            toast.error(errorMsg);
        } finally {
            setGenerating(false);
        }
    };

    return (
        <div className="fade-in" style={{ padding: '0 8px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 2fr) minmax(0, 1fr)', gap: 32 }}>
                {/* Main Action Area */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <div className="card" style={{ overflow: 'hidden' }}>
                        <div style={{ padding: '24px', borderBottom: '1px solid var(--bg-border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <h3 style={{ margin: 0, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 10 }}>
                                <Filter size={18} style={{ color: 'var(--primary)' }} />
                                Report Configuration
                            </h3>
                            <div style={{ background: 'var(--primary-glow)', color: 'var(--text-accent)', fontSize: '0.7rem', fontWeight: 700, padding: '4px 12px', borderRadius: 99, textTransform: 'uppercase' }}>
                                {format} Selected
                            </div>
                        </div>

                        <div style={{ padding: 32 }}>
                            <h4 style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-secondary)', marginBottom: 24, textTransform: 'uppercase', letterSpacing: '0.1em', display: 'flex', alignItems: 'center', gap: 10 }}>
                                <div style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--primary)' }}></div>
                                1. Select Report Type
                            </h4>
                            
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 16, marginBottom: 40 }}>
                                {REPORT_TYPES.map(r => (
                                    <div
                                        key={r.id}
                                        onClick={() => setSelected(r.id)}
                                        style={{
                                            padding: 20, borderRadius: 'var(--radius-lg)', cursor: 'pointer', transition: 'all 0.3s ease', border: '2px solid transparent',
                                            background: selected === r.id ? 'var(--primary-glow)' : 'var(--bg-elevated)',
                                            borderColor: selected === r.id ? 'var(--primary)' : 'transparent',
                                            transform: selected === r.id ? 'translateY(-2px)' : 'none',
                                            boxShadow: selected === r.id ? 'var(--shadow-glow)' : 'none'
                                        }}
                                    >
                                        <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
                                            <div style={{ 
                                                padding: 12, borderRadius: 12, 
                                                background: selected === r.id ? 'var(--primary)' : 'var(--bg-surface)', 
                                                color: selected === r.id ? 'white' : 'var(--text-muted)',
                                                boxShadow: selected === r.id ? 'var(--shadow-sm)' : 'none'
                                            }}>
                                                <r.icon size={20} />
                                            </div>
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontWeight: 700, fontSize: '0.9rem', color: selected === r.id ? 'var(--text-primary)' : 'var(--text-secondary)' }}>{r.label}</div>
                                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 4, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{r.desc}</div>
                                            </div>
                                            {selected === r.id && <ChevronRight size={16} style={{ color: 'var(--primary)' }} />}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <h4 style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-secondary)', marginBottom: 24, textTransform: 'uppercase', letterSpacing: '0.1em', display: 'flex', alignItems: 'center', gap: 10 }}>
                                <div style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--primary)' }}></div>
                                2. Define Parameters
                            </h4>

                            <div style={{ display: 'flex', flexDirection: 'column', gap: 24, marginBottom: 32 }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                                    <div className="form-group">
                                        <label>Date Range (From)</label>
                                        <input className="input" type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
                                    </div>
                                    <div className="form-group">
                                        <label>Date Range (To)</label>
                                        <input className="input" type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} />
                                    </div>
                                </div>
                                
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                                    <div className="form-group">
                                        <label>Class Selection</label>
                                        <select className="input" value={classFilter} onChange={e => setClassFilter(e.target.value)}>
                                            <option value="">All Classes</option>
                                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="form-group">
                                        <label>Section Selection</label>
                                        <select className="input" value={sectionFilter} onChange={e => setSectionFilter(e.target.value)} disabled={!classFilter}>
                                            <option value="">All Sections</option>
                                            {sections.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div style={{ 
                                padding: 24, background: 'var(--bg-surface)', borderRadius: 'var(--radius-lg)', 
                                display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 24,
                                border: '1px solid var(--bg-border)'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                                    <div style={{ fontSize: '0.7rem', fontWeight: 800, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Format:</div>
                                    <div style={{ display: 'flex', gap: 4, background: 'var(--bg-elevated)', padding: 4, borderRadius: 'var(--radius-md)' }}>
                                        {['csv', 'excel', 'pdf'].map(f => (
                                            <button
                                                key={f}
                                                onClick={() => setFormat(f as any)}
                                                style={{
                                                    padding: '6px 16px', borderRadius: 8, fontSize: '0.75rem', fontWeight: 700, border: 'none', transition: 'all 0.2s', cursor: 'pointer',
                                                    background: format === f ? 'var(--primary)' : 'transparent',
                                                    color: format === f ? 'white' : 'var(--text-secondary)',
                                                    boxShadow: format === f ? 'var(--shadow-sm)' : 'none'
                                                }}
                                            >
                                                {f.toUpperCase()}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <button className="btn btn-primary" onClick={handleGenerate} disabled={generating} style={{ height: 48, padding: '0 32px', borderRadius: 12 }}>
                                    {generating ? <div className="spinner" style={{ width: 16, height: 16 }} /> : <FileDown size={18} />}
                                    <span style={{ fontWeight: 800, letterSpacing: '0.02em' }}>{generating ? 'Processing…' : 'Generate & Download'}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Sidebar */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <div className="card">
                        <div style={{ padding: '20px', borderBottom: '1px solid var(--bg-border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <h3 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: 10 }}>
                                <Clock size={16} style={{ color: 'var(--primary)' }} />
                                Recent Activity
                            </h3>
                            <button style={{ border: 'none', background: 'transparent', color: 'var(--primary)', fontSize: '0.7rem', fontWeight: 700, cursor: 'pointer' }}>Clear</button>
                        </div>
                        <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {recentReports.map((r, i) => (
                                <div key={i} style={{ padding: 16, background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-md)', transition: 'all 0.2s' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontSize: '0.8rem', fontWeight: 700, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{r.name}</div>
                                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 6 }}>
                                                <span style={{ fontSize: '0.65rem', fontWeight: 800, background: 'var(--bg-surface)', padding: '2px 6px', borderRadius: 4, color: 'var(--text-secondary)' }}>{r.format.toUpperCase()}</span>
                                                <span style={{ fontSize: '0.65rem', color: 'var(--text-muted)' }}>{r.date}</span>
                                            </div>
                                        </div>
                                        <button 
                                            className="btn-icon" 
                                            onClick={() => {
                                                const url = reportService.generate({
                                                    type: r.type.toLowerCase().split(' ')[0] as any,
                                                    format: r.format as any,
                                                });
                                                window.open(url, '_blank');
                                            }}
                                            style={{ background: 'var(--bg-surface)', borderRadius: 8 }}
                                        >
                                            <Download size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="card" style={{ background: 'var(--grad-primary)', border: 'none', position: 'relative', overflow: 'hidden' }}>
                        <div style={{ position: 'absolute', top: -20, right: -20, width: 100, height: 100, background: 'rgba(255,255,255,0.1)', borderRadius: '50%', filter: 'blur(20px)' }}></div>
                        <div style={{ padding: 24, position: 'relative', zIndex: 1, color: 'white' }}>
                            <h3 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 800, display: 'flex', alignItems: 'center', gap: 10, color: 'white' }}>
                                <Search size={16} />
                                Analytics Usage
                            </h3>
                            <div style={{ marginTop: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                                {[
                                    { label: 'Monthly Allowance', value: '45/100', percent: 45 },
                                    { label: 'Storage Used', value: '1.2 GB', percent: 12 },
                                ].map(s => (
                                    <div key={s.label} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.7rem', fontWeight: 700, opacity: 0.8 }}>
                                            <span>{s.label}</span>
                                            <span>{s.value}</span>
                                        </div>
                                        <div style={{ height: 6, background: 'rgba(255,255,255,0.2)', borderRadius: 3 }}>
                                            <div style={{ height: '100%', background: 'white', width: `${s.percent}%`, borderRadius: 3 }}></div>
                                        </div>
                                    </div>
                                ))}
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 4 }}>
                                    {[{ label: 'Generated', val: '24' }, { label: 'Active', val: '12K' }].map(s => (
                                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.1)', padding: 12, borderRadius: 12, border: '1px solid rgba(255,255,255,0.1)' }}>
                                            <div style={{ fontSize: '0.65rem', fontWeight: 700, opacity: 0.7 }}>{s.label}</div>
                                            <div style={{ fontSize: '1rem', fontWeight: 900, marginTop: 4 }}>{s.val}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Reports;
