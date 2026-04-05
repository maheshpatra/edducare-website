import React, { useState, useEffect, useRef } from 'react';
import { School, Lock, Bell, Palette, Save, Upload, Camera } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { profileService, schoolService } from '../api/services';
import toast from 'react-hot-toast';

const Settings: React.FC = () => {
    const { user } = useAuth();
    const [tab, setTab] = useState<'school' | 'account' | 'notifications' | 'appearance'>('school');
    const [saving, setSaving] = useState(false);
    const [loading, setLoading] = useState(false);

    const [schoolForm, setSchoolForm] = useState({
        name: '',
        address: '',
        phone: '',
        email: '',
        website: '',
        principal_name: '',
        established_year: '',
        school_type: 'General',
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [currentLogo, setCurrentLogo] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [accountForm, setAccountForm] = useState({
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        email: user?.email ?? '',
        phone: '',
        current_password: '',
        new_password: '',
        confirm_password: '',
    });

    const [notifForm, setNotifForm] = useState({
        email_attendance: true, email_fees: true, email_exams: true, email_announcements: false,
        sms_fees: true, sms_attendance: false,
    });

    const [theme, setTheme] = useState(localStorage.getItem('theme') || 'dark');
    const [accentColor, setAccentColor] = useState(localStorage.getItem('accentColor') || '#6366f1');

    useEffect(() => {
        if (tab === 'school') {
            fetchSchoolData();
        }
    }, [tab]);

    const fetchSchoolData = async () => {
        setLoading(true);
        try {
            const res = await schoolService.getProfile();
            if (res.data.success) {
                const s = res.data.data;
                setSchoolForm({
                    name: s.name || '',
                    address: s.address || '',
                    phone: s.phone || '',
                    email: s.email || '',
                    website: s.website || '',
                    principal_name: s.principal_name || '',
                    established_year: s.established_year || '',
                    school_type: s.school_type || 'General',
                });
                setCurrentLogo(s.logo);
                if (s.logo) setLogoPreview(`/api/uploads/${s.logo}`);
            }
        } catch (err) {
            toast.error('Failed to load school profile');
        } finally {
            setLoading(false);
        }
    };

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setLogoFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setLogoPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const saveSchool = async () => {
        setSaving(true);
        try {
            if (logoFile) {
                const formData = new FormData();
                formData.append('logo', logoFile);
                formData.append('name', schoolForm.name);
                formData.append('address', schoolForm.address);
                formData.append('phone', schoolForm.phone);
                formData.append('email', schoolForm.email);
                formData.append('website', schoolForm.website);
                formData.append('principal_name', schoolForm.principal_name);
                formData.append('established_year', schoolForm.established_year);
                formData.append('school_type', schoolForm.school_type);

                // Using axios directly for FormData if service doesn't handle it
                await schoolService.updateProfile(formData);
            } else {
                await schoolService.updateProfile(schoolForm);
            }
            toast.success('School settings saved successfully');
            setLogoFile(null);
            fetchSchoolData(); // Refresh to get the new logo path
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to save school settings');
        } finally {
            setSaving(false);
        }
    };

    const saveAccount = async () => {
        if (accountForm.new_password && accountForm.new_password !== accountForm.confirm_password) {
            toast.error('Passwords do not match'); return;
        }
        setSaving(true);
        try {
            await profileService.update({
                first_name: accountForm.first_name,
                last_name: accountForm.last_name,
                email: accountForm.email,
                phone: accountForm.phone,
                current_password: accountForm.current_password,
                new_password: accountForm.new_password
            });
            toast.success('Account details updated successfully');
            setAccountForm(p => ({ ...p, current_password: '', new_password: '', confirm_password: '' }));
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to update account');
        } finally { setSaving(false); }
    };

    const sf = (k: string, v: string) => setSchoolForm(p => ({ ...p, [k]: v }));
    const af = (k: string, v: string) => setAccountForm(p => ({ ...p, [k]: v }));

    return (
        <div className="fade-in">
            <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', gap: 24 }}>
                {/* Sidebar nav */}
                <div className="card" style={{ padding: 12, height: 'fit-content' }}>
                    {[
                        { id: 'school', icon: School, label: 'School Profile' },
                        { id: 'account', icon: Lock, label: 'Account & Security' },
                        { id: 'notifications', icon: Bell, label: 'Notifications' },
                        { id: 'appearance', icon: Palette, label: 'Appearance' },
                    ].map(item => (
                        <div
                            key={item.id}
                            className={`nav-item ${tab === item.id ? 'active' : ''}`}
                            style={{ margin: '2px 0' }}
                            onClick={() => setTab(item.id as any)}
                        >
                            <div className="nav-item-icon"><item.icon size={16} /></div>
                            <span className="nav-item-label">{item.label}</span>
                        </div>
                    ))}
                </div>

                {/* Content */}
                <div>
                    {loading && tab === 'school' ? (
                        <div className="card" style={{ padding: 50, textAlign: 'center' }}>
                            <div className="spinner" style={{ margin: '0 auto' }} />
                            <p style={{ marginTop: 10, color: 'var(--text-muted)' }}>Loading Profile...</p>
                        </div>
                    ) : (
                        <>
                            {/* School Profile */}
                            {tab === 'school' && (
                                <div className="card" style={{ padding: '0 28px 28px' }}>
                                    <div style={{
                                        height: 120, margin: '0 -28px 60px',
                                        background: 'linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%)',
                                        position: 'relative', borderTopLeftRadius: 'var(--radius-lg)', borderTopRightRadius: 'var(--radius-lg)'
                                    }}>
                                        <div style={{
                                            position: 'absolute', bottom: -50, left: 28, width: 100, height: 100,
                                            borderRadius: 20, background: 'var(--bg-card)', padding: 4,
                                            border: '4px solid var(--bg-card)', boxShadow: '0 4px 6px -1px var(--shadow-color)'
                                        }}>
                                            <div style={{
                                                width: '100%', height: '100%', borderRadius: 16, overflow: 'hidden',
                                                background: 'var(--bg-elevated)', display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                position: 'relative'
                                            }}>
                                                {logoPreview ? (
                                                    <img src={logoPreview} alt="School Logo" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                                ) : (
                                                    <School size={40} style={{ opacity: 0.2 }} />
                                                )}
                                                <div
                                                    style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.4)', opacity: 0, transition: 'opacity 0.2s', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}
                                                    onMouseEnter={e => e.currentTarget.style.opacity = '1'}
                                                    onMouseLeave={e => e.currentTarget.style.opacity = '0'}
                                                    onClick={() => fileInputRef.current?.click()}
                                                >
                                                    <Camera color="white" size={24} />
                                                </div>
                                            </div>
                                            <input ref={fileInputRef} type="file" hidden accept="image/*" onChange={handleLogoChange} />
                                        </div>
                                    </div>

                                    <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1.2rem', marginBottom: 24, paddingLeft: 110 }}>School Settings</div>

                                    <div className="grid-2" style={{ marginBottom: 16 }}>
                                        <div className="form-group"><label>School Name</label><input className="input" value={schoolForm.name} onChange={e => sf('name', e.target.value)} /></div>
                                        <div className="form-group"><label>School Type</label>
                                            <select className="input" value={schoolForm.school_type} onChange={e => sf('school_type', e.target.value)}>
                                                <option>General</option><option>Primary</option><option>Middle</option><option>Secondary</option><option>Higher Secondary</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div className="form-group" style={{ marginBottom: 16 }}><label>Address</label><textarea className="input" value={schoolForm.address} onChange={e => sf('address', e.target.value)} rows={2} /></div>
                                    <div className="grid-2" style={{ marginBottom: 16 }}>
                                        <div className="form-group"><label>Phone</label><input className="input" value={schoolForm.phone} onChange={e => sf('phone', e.target.value)} /></div>
                                        <div className="form-group"><label>Official Email</label><input className="input" type="email" value={schoolForm.email} onChange={e => sf('email', e.target.value)} /></div>
                                    </div>
                                    <div className="grid-2" style={{ marginBottom: 16 }}>
                                        <div className="form-group"><label>Website</label><input className="input" value={schoolForm.website} onChange={e => sf('website', e.target.value)} /></div>
                                        <div className="form-group"><label>Principal Name</label><input className="input" value={schoolForm.principal_name} onChange={e => sf('principal_name', e.target.value)} /></div>
                                    </div>
                                    <div className="form-group" style={{ marginBottom: 24 }}><label>Year Established</label><input className="input" type="number" value={schoolForm.established_year} onChange={e => sf('established_year', e.target.value)} style={{ maxWidth: 140 }} /></div>
                                    <button className="btn btn-primary" onClick={saveSchool} disabled={saving} style={{ height: 44, padding: '0 30px' }}>{saving ? <div className="spinner" /> : <><Save size={18} />Save All Changes</>}</button>
                                </div>
                            )}

                            {/* Account & Security */}
                            {tab === 'account' && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                                    <div className="card" style={{ padding: 28 }}>
                                        <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1.05rem', marginBottom: 20 }}>Profile Information</div>
                                        <div className="grid-2" style={{ marginBottom: 16 }}>
                                            <div className="form-group"><label>First Name</label><input className="input" value={accountForm.first_name} onChange={e => af('first_name', e.target.value)} /></div>
                                            <div className="form-group"><label>Last Name</label><input className="input" value={accountForm.last_name} onChange={e => af('last_name', e.target.value)} /></div>
                                        </div>
                                        <div className="grid-2" style={{ marginBottom: 20 }}>
                                            <div className="form-group"><label>Email</label><input className="input" type="email" value={accountForm.email} onChange={e => af('email', e.target.value)} /></div>
                                            <div className="form-group"><label>Phone</label><input className="input" value={accountForm.phone} onChange={e => af('phone', e.target.value)} /></div>
                                        </div>
                                        <button className="btn btn-primary btn-sm" onClick={saveAccount} disabled={saving}>{saving ? <div className="spinner" /> : <><Save size={14} />Save Profile</>}</button>
                                    </div>
                                    <div className="card" style={{ padding: 28 }}>
                                        <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1.05rem', marginBottom: 20 }}>Change Password</div>
                                        <div className="form-group" style={{ marginBottom: 16 }}><label>Current Password</label><input className="input" type="password" value={accountForm.current_password} onChange={e => af('current_password', e.target.value)} placeholder="Current password" /></div>
                                        <div className="grid-2" style={{ marginBottom: 20 }}>
                                            <div className="form-group"><label>New Password</label><input className="input" type="password" value={accountForm.new_password} onChange={e => af('new_password', e.target.value)} placeholder="Min 8 chars" /></div>
                                            <div className="form-group"><label>Confirm Password</label><input className="input" type="password" value={accountForm.confirm_password} onChange={e => af('confirm_password', e.target.value)} placeholder="Repeat new password" /></div>
                                        </div>
                                        <button className="btn btn-primary btn-sm" onClick={saveAccount} disabled={saving}><Lock size={14} />Update Password</button>
                                    </div>
                                </div>
                            )}

                            {/* Notifications */}
                            {tab === 'notifications' && (
                                <div className="card" style={{ padding: 28 }}>
                                    <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1.05rem', marginBottom: 20 }}>Notification Preferences</div>
                                    {[
                                        { key: 'email_attendance', label: 'Email – Attendance Alerts', desc: 'Receive email when students are absent repeatedly' },
                                        { key: 'email_fees', label: 'Email – Fee Reminders', desc: 'Send automated fee reminders before due dates' },
                                        { key: 'email_exams', label: 'Email – Exam Results Published', desc: 'Notify when exam results are published' },
                                        { key: 'email_announcements', label: 'Email – New Announcements', desc: 'Send email for every new announcement' },
                                        { key: 'sms_fees', label: 'SMS – Fee Due Reminders', desc: 'SMS alerts to parents for fee dues' },
                                        { key: 'sms_attendance', label: 'SMS – Absence Notification', desc: 'SMS to parent when child is absent' },
                                    ].map(item => (
                                        <div key={item.key} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '14px 0', borderBottom: '1px solid var(--bg-border)' }}>
                                            <div>
                                                <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: '0.875rem' }}>{item.label}</div>
                                                <div style={{ fontSize: '0.78rem', color: 'var(--text-muted)', marginTop: 2 }}>{item.desc}</div>
                                            </div>
                                            <label style={{ position: 'relative', display: 'inline-block', width: 44, height: 24, flexShrink: 0 }}>
                                                <input type="checkbox" checked={notifForm[item.key as keyof typeof notifForm] as boolean} onChange={e => setNotifForm(p => ({ ...p, [item.key]: e.target.checked }))} style={{ opacity: 0, width: 0, height: 0 }} />
                                                <span style={{
                                                    position: 'absolute', inset: 0, borderRadius: 24,
                                                    background: notifForm[item.key as keyof typeof notifForm] ? 'var(--primary)' : 'var(--bg-elevated)',
                                                    border: '1px solid var(--bg-border)', cursor: 'pointer', transition: 'background 0.2s',
                                                }}>
                                                    <span style={{
                                                        position: 'absolute', top: 3, left: notifForm[item.key as keyof typeof notifForm] ? 22 : 3,
                                                        width: 16, height: 16, borderRadius: '50%', background: 'white', transition: 'left 0.2s',
                                                    }} />
                                                </span>
                                            </label>
                                        </div>
                                    ))}
                                    <button className="btn btn-primary btn-sm" style={{ marginTop: 20 }} onClick={() => toast.success('Preferences saved')}><Save size={14} />Save Preferences</button>
                                </div>
                            )}

                            {/* Appearance */}
                            {tab === 'appearance' && (
                                <div className="card" style={{ padding: 28 }}>
                                    <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: '1.05rem', marginBottom: 20 }}>Appearance</div>
                                    <div className="form-group" style={{ marginBottom: 20 }}>
                                        <label>Accent Color</label>
                                        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
                                            {['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'].map(c => (
                                                <div key={c} onClick={() => setAccentColor(c)} style={{ width: 36, height: 36, borderRadius: 8, background: c, cursor: 'pointer', border: accentColor === c ? '3px solid white' : '2px solid transparent', boxShadow: accentColor === c ? '0 0 0 2px ' + c : 'none', transition: 'all 0.15s' }} />
                                            ))}
                                        </div>
                                    </div>
                                    <div style={{ marginBottom: 20 }}>
                                        <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: 10 }}>Interface Theme</div>
                                        <div style={{ display: 'flex', gap: 10 }}>
                                            {['dark', 'light', 'system'].map(t => (
                                                <div key={t} onClick={() => setTheme(t)} style={{ padding: '10px 20px', borderRadius: 'var(--radius-sm)', border: `1px solid ${theme === t ? 'var(--primary)' : 'var(--bg-border)'}`, background: theme === t ? 'var(--primary-glow)' : 'var(--bg-elevated)', color: theme === t ? 'var(--primary-light)' : 'var(--text-secondary)', fontWeight: 600, cursor: 'pointer', fontSize: '0.875rem', textTransform: 'capitalize', transition: 'all 0.15s' }}>{t}</div>
                                            ))}
                                        </div>
                                    </div>
                                    <button className="btn btn-primary btn-sm" onClick={() => {
                                        localStorage.setItem('theme', theme);
                                        localStorage.setItem('accentColor', accentColor);
                                        document.documentElement.setAttribute('data-theme', theme);
                                        document.documentElement.style.setProperty('--primary', accentColor);
                                        toast.success('Appearance saved');
                                    }}><Save size={14} />Apply</button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Settings;
