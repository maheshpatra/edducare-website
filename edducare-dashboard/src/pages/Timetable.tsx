import React, { useState, useEffect } from 'react';
import { Plus, Clock, Loader2, Calendar, User as UserIcon, MoreVertical, Trash2, MapPin, Search } from 'lucide-react';
import { timetableService, classService, academicService, subjectService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';
import '../timetable.css';

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const SUBJECT_COLORS: Record<string, string> = {
    Mathematics: '#6366f1', English: '#0ea5e9', Science: '#10b981', Hindi: '#f59e0b',
    Physics: '#8b5cf6', Chemistry: '#ef4444', Biology: '#06b6d4', History: '#fb923c',
    Geography: '#22c55e', 'Computer Science': '#ec4899', 'Physical Education': '#84cc16', Sanskrit: '#f97316',
};

const Timetable: React.FC = () => {
    // Selection state
    const [academicYears, setAcademicYears] = useState<any[]>([]);
    const [classes, setClasses] = useState<any[]>([]);
    const [sections, setSections] = useState<any[]>([]);
    const [subjects, setSubjects] = useState<any[]>([]);
    const [teachers, setTeachers] = useState<any[]>([]);

    const [filters, setFilters] = useState({
        academic_year_id: '',
        class_id: '',
        section_id: '',
    });

    const [timetable, setTimetable] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [saving, setSaving] = useState(false);

    const [form, setForm] = useState({
        day_of_week: 'monday',
        start_time: '08:00:00',
        end_time: '08:45:00',
        subject_id: '',
        teacher_id: '',
        room: '',
        period_number: 1
    });

    const todayName = new Date().toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();

    useEffect(() => {
        const fetchInit = async () => {
            try {
                const [ayRes, classRes, subRes, teacherRes] = await Promise.all([
                    academicService.list(),
                    classService.list(),
                    subjectService.list(),
                    subjectService.availableTeachers()
                ]);

                setAcademicYears(ayRes.data.data || ayRes.data.academic_years || []);
                setClasses(classRes.data.data || []);
                setSubjects(subRes.data.data?.subjects || subRes.data.subjects || []);
                setTeachers(teacherRes.data.data?.teachers || teacherRes.data.teachers || []);

                const currentAY = (ayRes.data.data || ayRes.data.academic_years || []).find((y: any) => y.is_current);
                if (currentAY) setFilters((prev: any) => ({ ...prev, academic_year_id: currentAY.id }));
            } catch (err) {
                console.error('Failed to load initial data', err);
            }
        };
        fetchInit();
    }, []);

    useEffect(() => {
        if (!filters.class_id) {
            setSections([]);
            return;
        }
        const fetchSections = async () => {
            try {
                const res = await classService.sections(filters.class_id);
                setSections(res.data.data || []);
                if (res.data.data?.length > 0) {
                    setFilters((prev: any) => ({ ...prev, section_id: res.data.data[0].id }));
                }
            } catch (err) {
                console.error('Failed to fetch sections', err);
            }
        };
        fetchSections();
    }, [filters.class_id]);

    useEffect(() => {
        if (!filters.class_id) return;

        const fetchTimetable = async () => {
            setLoading(true);
            try {
                const res = await timetableService.view({
                    class_id: filters.class_id,
                    section_id: filters.section_id,
                    academic_year_id: filters.academic_year_id
                });
                setTimetable(res.data.data?.class_timetable || res.data.class_timetable);
            } catch (err) {
                console.error('Failed to fetch timetable', err);
                toast.error('Could not load timetable');
            } finally {
                setLoading(false);
            }
        };
        fetchTimetable();
    }, [filters.class_id, filters.section_id, filters.academic_year_id]);

    const handleSave = async () => {
        if (!form.subject_id || !form.teacher_id) {
            toast.error('Subject and Teacher are required');
            return;
        }

        setSaving(true);
        try {
            await timetableService.create({
                ...form,
                class_id: filters.class_id,
                section_id: filters.section_id,
                academic_year_id: filters.academic_year_id
            });
            toast.success('Period added successfully');
            setShowModal(false);
            const res = await timetableService.view({
                class_id: filters.class_id,
                section_id: filters.section_id,
                academic_year_id: filters.academic_year_id
            });
            setTimetable(res.data.data?.class_timetable || res.data.class_timetable);
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to save');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure you want to remove this period?')) return;
        try {
            await timetableService.delete(id);
            toast.success('Period removed');
            const res = await timetableService.view({
                class_id: filters.class_id,
                section_id: filters.section_id,
                academic_year_id: filters.academic_year_id
            });
            setTimetable(res.data.data?.class_timetable || res.data.class_timetable);
        } catch (err) {
            toast.error('Failed to remove');
        }
    };

    const formatTime = (timeStr: string) => {
        if (!timeStr) return '';
        const [h, m] = timeStr.split(':');
        const hour = parseInt(h);
        const ampm = hour >= 12 ? 'pm' : 'am';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${m}${ampm}`;
    };

    const f = (k: string, v: any) => setForm((p: any) => ({ ...p, [k]: v }));

    return (
        <div className="fade-in">
            {/* Header / Search Controls */}
            <div className="card mb-24" style={{ padding: '24px', borderBottom: '4px solid var(--primary)' }}>
                <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap', alignItems: 'flex-end' }}>
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 8, display: 'block' }}>School Year</span>
                        <select
                            className="input"
                            style={{ height: 48, fontSize: '0.95rem', fontWeight: 600 }}
                            value={filters.academic_year_id}
                            onChange={e => setFilters(prev => ({ ...prev, academic_year_id: e.target.value }))}
                        >
                            <option value="">Select Year</option>
                            {academicYears.map((ay: any) => <option key={ay.id} value={ay.id}>{ay.name} {ay.is_current ? '(Active)' : ''}</option>)}
                        </select>
                    </div>
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 8, display: 'block' }}>Target Class</span>
                        <select
                            className="input"
                            style={{ height: 48, fontSize: '0.95rem', fontWeight: 600 }}
                            value={filters.class_id}
                            onChange={e => setFilters(prev => ({ ...prev, class_id: e.target.value }))}
                        >
                            <option value="">Select Class</option>
                            {classes.map((c: any) => <option key={c.id} value={c.id}>{c.name} (Grade {c.grade_level})</option>)}
                        </select>
                    </div>
                    <div style={{ flex: 1, minWidth: 150 }}>
                        <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 8, display: 'block' }}>Section</span>
                        <select
                            className="input"
                            style={{ height: 48, fontSize: '0.95rem', fontWeight: 600 }}
                            value={filters.section_id}
                            onChange={e => setFilters(prev => ({ ...prev, section_id: e.target.value }))}
                        >
                            <option value="">All Sections</option>
                            {sections.map((s: any) => <option key={s.id} value={s.id}>{s.name || 'Section ' + (sections.indexOf(s) + 1)}</option>)}
                        </select>
                    </div>
                    <button
                        className="btn btn-primary"
                        onClick={() => setShowModal(true)}
                        disabled={!filters.class_id}
                        style={{ height: 48, padding: '0 24px', borderRadius: 'var(--radius-md)' }}
                    >
                        <Plus size={20} /> Add Class session
                    </button>
                </div>
            </div>

            {loading ? (
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '120px 0', gap: 16 }}>
                    <div className="spinner" style={{ width: 48, height: 48 }}></div>
                    <p style={{ color: 'var(--text-muted)', fontSize: '0.9rem', fontWeight: 500 }}>Fetching latest schedule...</p>
                </div>
            ) : !timetable ? (
                <div className="card" style={{ padding: 80, textAlign: 'center', background: 'rgba(255,255,255,0.02)', border: '1px dashed var(--bg-border)' }}>
                    <div style={{ width: 80, height: 80, borderRadius: 20, background: 'var(--bg-elevated)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 24px' }}>
                        <Search size={40} style={{ color: 'var(--text-muted)', opacity: 0.5 }} />
                    </div>
                    <h2 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: 8 }}>Ready to manage?</h2>
                    <p style={{ color: 'var(--text-muted)', maxWidth: 300, margin: '0 auto' }}>Select an academic year and class above to start building the weekly schedule.</p>
                </div>
            ) : (
                <div style={{ overflowX: 'auto', paddingBottom: 24 }}>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 16, minWidth: 1200 }}>
                        {DAYS.map(day => (
                            <div key={day} style={{ display: 'flex', flexDirection: 'column' }}>
                                <div className={`timetable-day-header ${day.toLowerCase() === todayName ? 'active' : ''}`}>
                                    {day}
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                    {(timetable[day.toLowerCase()] || []).length > 0 ? (
                                        timetable[day.toLowerCase()].map((period: any, idx: number) => {
                                            const color = SUBJECT_COLORS[period.subject_name] || '#6366f1';
                                            return (
                                                <div key={period.id} className="glass-card timetable-slot" style={{
                                                    padding: '16px',
                                                    borderRadius: 'var(--radius-lg)',
                                                    borderLeft: `5px solid ${color}`,
                                                }}>
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, opacity: 0.8, fontSize: '0.72rem', fontWeight: 700, color: 'var(--text-secondary)' }}>
                                                            <Clock size={12} /> {formatTime(period.start_time)} - {formatTime(period.end_time)}
                                                        </div>
                                                        <div className="timetable-actions">
                                                            <button
                                                                onClick={() => handleDelete(period.id)}
                                                                className="delete-btn"
                                                            >
                                                                <Trash2 size={13} />
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <h4 style={{ fontSize: '1rem', fontWeight: 800, color: 'var(--text-primary)', marginBottom: 12, lineHeight: 1.2 }}>
                                                        {period.subject_name}
                                                    </h4>

                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                                                            <UserIcon size={14} style={{ color: 'var(--primary-light)' }} />
                                                            <span style={{ fontWeight: 600 }}>{period.teacher_name}</span>
                                                        </div>
                                                        {period.room && (
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '0.75rem', color: 'var(--primary-light)' }}>
                                                                <MapPin size={14} />
                                                                <span style={{ fontWeight: 700 }}>{period.room}</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div style={{
                                            padding: '40px 20px',
                                            textAlign: 'center',
                                            background: 'rgba(255,255,255,0.01)',
                                            borderRadius: 'var(--radius-lg)',
                                            border: '1px dashed var(--bg-border)',
                                            color: 'var(--text-muted)',
                                            fontSize: '0.75rem',
                                            fontWeight: 500
                                        }}>
                                            No sessions scheduled
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Modal for adding session */}
            <Modal isOpen={showModal} onClose={() => setShowModal(false)} title="New Schedule Entry"
                footer={<><button className="btn btn-secondary" onClick={() => setShowModal(false)}>Discard</button><button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? <Loader2 className="spinner" size={16} /> : 'Save Class'}</button></>}>

                <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: 20 }}>
                    <div className="form-group">
                        <label>Weekly Day</label>
                        <select className="input" value={form.day_of_week} onChange={e => f('day_of_week', e.target.value)}>
                            {DAYS.map(d => <option key={d} value={d.toLowerCase()}>{d}</option>)}
                        </select>
                    </div>
                    <div className="form-group">
                        <label>Period #</label>
                        <input type="number" className="input" value={form.period_number} onChange={e => f('period_number', parseInt(e.target.value))} />
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginTop: 20 }}>
                    <div className="form-group">
                        <label>Session Start</label>
                        <input type="time" className="input" value={form.start_time.substring(0, 5)} onChange={e => f('start_time', e.target.value + ':00')} />
                    </div>
                    <div className="form-group">
                        <label>Session End</label>
                        <input type="time" className="input" value={form.end_time.substring(0, 5)} onChange={e => f('end_time', e.target.value + ':00')} />
                    </div>
                </div>

                <div className="form-group" style={{ marginTop: 20 }}>
                    <label>Course / Subject</label>
                    <div style={{ position: 'relative' }}>
                        <select className="input" style={{ paddingLeft: 40 }} value={form.subject_id} onChange={e => f('subject_id', e.target.value)}>
                            <option value="">Select a course</option>
                            {subjects.map((s: any) => <option key={s.id} value={s.id}>{s.name} ({s.code})</option>)}
                        </select>
                        <div style={{ position: 'absolute', left: 14, top: 12, color: 'var(--primary-light)' }}>
                            <Calendar size={18} />
                        </div>
                    </div>
                </div>

                <div className="form-group" style={{ marginTop: 20 }}>
                    <label>Instructor / Teacher</label>
                    <div style={{ position: 'relative' }}>
                        <select className="input" style={{ paddingLeft: 40 }} value={form.teacher_id} onChange={e => f('teacher_id', e.target.value)}>
                            <option value="">Assign an instructor</option>
                            {teachers.map((t: any) => <option key={t.id} value={t.id}>{t.first_name} {t.last_name} ({t.employee_id})</option>)}
                        </select>
                        <div style={{ position: 'absolute', left: 14, top: 12, color: 'var(--primary-light)' }}>
                            <UserIcon size={18} />
                        </div>
                    </div>
                </div>

                <div className="form-group" style={{ marginTop: 20 }}>
                    <label>Assigned Room</label>
                    <div style={{ position: 'relative' }}>
                        <input className="input" style={{ paddingLeft: 40 }} value={form.room} onChange={e => f('room', e.target.value)} placeholder="e.g. Science Lab, Room 402" />
                        <div style={{ position: 'absolute', left: 14, top: 12, color: 'var(--primary-light)' }}>
                            <MapPin size={18} />
                        </div>
                    </div>
                </div>
            </Modal>
        </div>
    );
};

export default Timetable;
