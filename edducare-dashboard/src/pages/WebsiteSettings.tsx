import React, { useState, useEffect, useRef } from 'react';
import { websiteService } from '../api/services';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';
import {
    Globe, Palette, Image as ImageIcon, BarChart3, Layout, Upload, Save, Trash2, Plus,
    Eye, RefreshCw, Monitor, Mail, FileText
} from 'lucide-react';


interface ThemeData {
    primary_color: string;
    secondary_color: string;
    font_family: string;
    layout_style: string;
    hero_bg_image: string;
    principal_image: string;
    principal_message: string;
    about_text: string;
    about_image: string;
}

interface GalleryItem {
    id: number;
    image_path: string;
    caption: string;
    category: string;
    is_active: number;
}

interface StatItem {
    id?: number;
    label: string;
    value: string;
    icon: string;
    sort_order: number;
}

const TEMPLATES = [
    { id: 'modern', name: 'Modern', desc: 'Clean, minimal with glassmorphism', color: '#3b82f6' },
    { id: 'classic', name: 'Classic', desc: 'Traditional academic look', color: '#92400e' },
    { id: 'elegant', name: 'Elegant', desc: 'Sophisticated dark-accented', color: '#1e293b' },
    { id: 'bold', name: 'Bold', desc: 'Vibrant, dynamic, large typography', color: '#7c3aed' },
];

const FONTS = [
    "Inter, sans-serif",
    "Playfair Display, serif",
    "DM Sans, sans-serif",
    "Space Grotesk, sans-serif",
    "Poppins, sans-serif",
    "Roboto, sans-serif",
];

const WebsiteSettings: React.FC = () => {
    const { user } = useAuth();
    const [activeTab, setActiveTab] = useState<'theme' | 'content' | 'gallery' | 'stats' | 'admissions' | 'email'>('theme');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    // Theme state
    const [theme, setTheme] = useState<ThemeData>({
        primary_color: '#3b82f6',
        secondary_color: '#1e3a8a',
        font_family: 'Inter, sans-serif',
        layout_style: 'modern',
        hero_bg_image: '',
        principal_image: '',
        principal_message: '',
        about_text: '',
        about_image: '',
    });

    // Gallery state
    const [gallery, setGallery] = useState<GalleryItem[]>([]);
    const [newCaption, setNewCaption] = useState('');
    const [newCategory, setNewCategory] = useState('General');
    const galleryFileRef = useRef<HTMLInputElement>(null);

    // Stats state
    const [stats, setStats] = useState<StatItem[]>([]);
    const [newStat, setNewStat] = useState<StatItem>({ label: '', value: '', icon: '', sort_order: 0 });

    // Admission Form state
    const [admissionFields, setAdmissionFields] = useState<Record<string, { enabled: boolean; required: boolean }>>({
        student_name: { enabled: true, required: true },
        guardian_name: { enabled: true, required: false },
        email: { enabled: true, required: true },
        phone: { enabled: true, required: true },
        desired_class: { enabled: true, required: true },
        dob: { enabled: false, required: false },
        gender: { enabled: false, required: false },
        address: { enabled: false, required: false },
        previous_school: { enabled: false, required: false }
    });

    // Email state
    const [emailConfig, setEmailConfig] = useState({
        use_custom: false,
        smtp_host: '',
        smtp_port: 587,
        smtp_user: '',
        smtp_pass: '',
        smtp_crypto: 'tls',
        from_email: '',
        from_name: ''
    });

    // Image file refs
    const heroFileRef = useRef<HTMLInputElement>(null);
    const principalFileRef = useRef<HTMLInputElement>(null);
    const aboutFileRef = useRef<HTMLInputElement>(null);

    // File state for uploads
    const [heroFile, setHeroFile] = useState<File | null>(null);
    const [principalFile, setPrincipalFile] = useState<File | null>(null);
    const [aboutFile, setAboutFile] = useState<File | null>(null);
    const [galleryFile, setGalleryFile] = useState<File | null>(null);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [themeRes, galleryRes, statsRes, admissionRes, emailRes] = await Promise.allSettled([
                websiteService.getTheme(),
                websiteService.getGallery(),
                websiteService.getStats(),
                websiteService.getAdmissionConfig(),
                websiteService.getEmailConfig()
            ]);

            if (themeRes.status === 'fulfilled' && themeRes.value.data?.success) {
                const t = themeRes.value.data.data;
                if (t) setTheme(prev => ({ ...prev, ...t }));
            }
            if (galleryRes.status === 'fulfilled' && galleryRes.value.data?.success) {
                setGallery(galleryRes.value.data.data || []);
            }
            if (statsRes.status === 'fulfilled' && statsRes.value.data?.success) {
                setStats(statsRes.value.data.data || []);
            }
            if (admissionRes?.status === 'fulfilled' && admissionRes.value.data?.success) {
                if (admissionRes.value.data.data?.fields_json) {
                    try {
                        const parsed = typeof admissionRes.value.data.data.fields_json === 'string'
                            ? JSON.parse(admissionRes.value.data.data.fields_json)
                            : admissionRes.value.data.data.fields_json;
                        setAdmissionFields(prev => ({ ...prev, ...parsed }));
                    } catch (e) {
                         console.error("error parse fields_json", e);
                    }
                }
            }
            if (emailRes?.status === 'fulfilled' && emailRes.value.data?.success) {
                if (emailRes.value.data.data) {
                    setEmailConfig(prev => ({
                        ...prev, ...emailRes.value.data.data,
                        use_custom: emailRes.value.data.data.use_custom === 1 || emailRes.value.data.data.use_custom === true
                    }));
                }
            }
        } catch (err) {
            console.error('Failed to load website settings:', err);
        }
        setLoading(false);
    };

    const handleSaveTheme = async () => {
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('primary_color', theme.primary_color);
            formData.append('secondary_color', theme.secondary_color);
            formData.append('font_family', theme.font_family);
            formData.append('layout_style', theme.layout_style);
            formData.append('principal_message', theme.principal_message);
            formData.append('about_text', theme.about_text);

            if (heroFile) formData.append('hero_bg_image', heroFile);
            if (principalFile) formData.append('principal_image', principalFile);
            if (aboutFile) formData.append('about_image', aboutFile);

            await websiteService.updateTheme(formData);
            toast.success('Website theme updated successfully!');
            setHeroFile(null);
            setPrincipalFile(null);
            setAboutFile(null);
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to save theme');
        }
        setSaving(false);
    };

    const handleAddGalleryImage = async () => {
        if (!galleryFile) return toast.error('Please select an image first');
        setSaving(true);
        try {
            const formData = new FormData();
            formData.append('image', galleryFile);
            formData.append('caption', newCaption);
            formData.append('category', newCategory);
            await websiteService.addGalleryImage(formData);
            toast.success('Image added to gallery!');
            setGalleryFile(null);
            setNewCaption('');
            setNewCategory('General');
            if (galleryFileRef.current) galleryFileRef.current.value = '';
            loadData();
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to upload image');
        }
        setSaving(false);
    };

    const handleDeleteGalleryImage = async (id: number) => {
        if (!confirm('Delete this gallery image?')) return;
        try {
            await websiteService.deleteGalleryImage(id);
            toast.success('Image deleted');
            setGallery(prev => prev.filter(g => g.id !== id));
        } catch {
            toast.error('Failed to delete image');
        }
    };

    const handleSaveStat = async (stat: StatItem) => {
        try {
            await websiteService.saveStat(stat);
            toast.success('Stat saved!');
            loadData();
        } catch {
            toast.error('Failed to save stat');
        }
    };

    const handleDeleteStat = async (id: number) => {
        if (!confirm('Delete this stat?')) return;
        try {
            await websiteService.deleteStat(id);
            toast.success('Stat deleted');
            setStats(prev => prev.filter(s => s.id !== id));
        } catch {
            toast.error('Failed to delete stat');
        }
    };

    const handleAddStat = async () => {
        if (!newStat.label || !newStat.value) return toast.error('Label and value are required');
        await handleSaveStat({ ...newStat, sort_order: stats.length });
        setNewStat({ label: '', value: '', icon: '', sort_order: 0 });
    };

    const handleSaveAdmissionConfig = async () => {
        setSaving(true);
        try {
            await websiteService.updateAdmissionConfig({ fields_json: JSON.stringify(admissionFields) });
            toast.success('Admission form settings saved!');
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to save admission settings');
        }
        setSaving(false);
    };

    const handleSaveEmailConfig = async () => {
        setSaving(true);
        try {
            await websiteService.updateEmailConfig(emailConfig);
            toast.success('Email settings saved!');
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to save email settings');
        }
        setSaving(false);
    };

    const tabs = [
        { id: 'theme' as const, label: 'Theme & Template', icon: Palette },
        { id: 'content' as const, label: 'Content', icon: Layout },
        { id: 'gallery' as const, label: 'Gallery', icon: ImageIcon },
        { id: 'stats' as const, label: 'Statistics', icon: BarChart3 },
        { id: 'admissions' as const, label: 'Admission Form', icon: FileText },
        { id: 'email' as const, label: 'Email Setup', icon: Mail },
    ];

    const FILE_BASE = 'https://edducare.finafid.org/uploads';

    const resolveImg = (path: string) => {
        if (!path) return '';
        return path.startsWith('http') ? path : `${FILE_BASE}/${path}`;
    };

    if (loading) {
        return (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: 400 }}>
                <div className="spinner" style={{ width: 32, height: 32 }} />
            </div>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
            {/* Header */}
            <div className="card" style={{ padding: '24px 32px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-sm)', background: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <Globe size={24} color="white" />
                    </div>
                    <div>
                        <h2 style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text-primary)', margin: 0 }}>Website Settings</h2>
                        <p style={{ fontSize: '0.82rem', color: 'var(--text-muted)', margin: 0 }}>Customize your school's public website appearance</p>
                    </div>
                </div>
                <a href={`https://edducare.finafid.org`} target="_blank" rel="noreferrer"
                    className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Eye size={16} /> Preview Website
                </a>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: 8, background: 'var(--bg-surface)', padding: 6, borderRadius: 'var(--radius-sm)', border: '1px solid var(--bg-border)' }}>
                {tabs.map(tab => (
                    <button key={tab.id} onClick={() => setActiveTab(tab.id)}
                        style={{
                            flex: 1, padding: '12px 16px', borderRadius: 'calc(var(--radius-sm) - 4px)', border: 'none', cursor: 'pointer',
                            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                            fontWeight: 600, fontSize: '0.85rem', transition: 'all 0.2s',
                            background: activeTab === tab.id ? 'var(--bg-elevated)' : 'transparent',
                            color: activeTab === tab.id ? 'var(--accent)' : 'var(--text-muted)',
                            boxShadow: activeTab === tab.id ? 'var(--shadow-sm)' : 'none',
                        }}>
                        <tab.icon size={16} /> {tab.label}
                    </button>
                ))}
            </div>

            {/* ═══ THEME TAB ═══ */}
            {activeTab === 'theme' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    {/* Template Selection */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>
                            <Layout size={18} style={{ display: 'inline', marginRight: 8, verticalAlign: 'middle' }} />
                            Choose Template
                        </h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 16 }}>
                            {TEMPLATES.map(tmpl => (
                                <div key={tmpl.id} onClick={() => setTheme(p => ({ ...p, layout_style: tmpl.id }))}
                                    style={{
                                        padding: 20, borderRadius: 'var(--radius-sm)', cursor: 'pointer', transition: 'all 0.2s',
                                        border: theme.layout_style === tmpl.id ? `2px solid var(--accent)` : '2px solid var(--bg-border)',
                                        background: theme.layout_style === tmpl.id ? 'var(--accent-light)' : 'var(--bg-surface)',
                                    }}>
                                    <div style={{ width: '100%', height: 80, borderRadius: 8, marginBottom: 14, background: `linear-gradient(135deg, ${tmpl.color}, ${tmpl.color}aa)`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                        <Monitor size={32} color="white" style={{ opacity: 0.5 }} />
                                    </div>
                                    <h4 style={{ fontWeight: 700, fontSize: '0.95rem', color: 'var(--text-primary)', marginBottom: 4 }}>{tmpl.name}</h4>
                                    <p style={{ fontSize: '0.78rem', color: 'var(--text-muted)', margin: 0 }}>{tmpl.desc}</p>
                                    {theme.layout_style === tmpl.id && (
                                        <span style={{ display: 'inline-block', marginTop: 10, padding: '4px 12px', borderRadius: 20, background: 'var(--accent)', color: 'white', fontSize: '0.7rem', fontWeight: 700 }}>ACTIVE</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Colors */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>
                            <Palette size={18} style={{ display: 'inline', marginRight: 8, verticalAlign: 'middle' }} />
                            Brand Colors
                        </h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 20 }}>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Primary Color</label>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                    <input type="color" value={theme.primary_color} onChange={e => setTheme(p => ({ ...p, primary_color: e.target.value }))}
                                        style={{ width: 50, height: 40, border: 'none', borderRadius: 8, cursor: 'pointer' }} />
                                    <input type="text" className="form-input" value={theme.primary_color} onChange={e => setTheme(p => ({ ...p, primary_color: e.target.value }))}
                                        style={{ flex: 1, fontFamily: 'monospace' }} />
                                </div>
                            </div>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Secondary Color</label>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                    <input type="color" value={theme.secondary_color} onChange={e => setTheme(p => ({ ...p, secondary_color: e.target.value }))}
                                        style={{ width: 50, height: 40, border: 'none', borderRadius: 8, cursor: 'pointer' }} />
                                    <input type="text" className="form-input" value={theme.secondary_color} onChange={e => setTheme(p => ({ ...p, secondary_color: e.target.value }))}
                                        style={{ flex: 1, fontFamily: 'monospace' }} />
                                </div>
                            </div>
                        </div>
                        {/* Color Preview */}
                        <div style={{ marginTop: 20, height: 48, borderRadius: 12, background: `linear-gradient(135deg, ${theme.primary_color}, ${theme.secondary_color})`, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'white', fontWeight: 700, fontSize: '0.85rem' }}>
                            Live Gradient Preview
                        </div>
                    </div>

                    {/* Font */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>Typography</h3>
                        <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Font Family</label>
                        <select className="form-input" value={theme.font_family} onChange={e => setTheme(p => ({ ...p, font_family: e.target.value }))}>
                            {FONTS.map(f => <option key={f} value={f} style={{ fontFamily: f }}>{f.split(',')[0]}</option>)}
                        </select>
                    </div>

                    <button onClick={handleSaveTheme} disabled={saving} className="btn btn-primary" style={{ alignSelf: 'flex-end', display: 'flex', alignItems: 'center', gap: 8, padding: '14px 32px' }}>
                        {saving ? <RefreshCw size={16} className="spin" /> : <Save size={16} />} Save Theme Settings
                    </button>
                </div>
            )}

            {/* ═══ CONTENT TAB ═══ */}
            {activeTab === 'content' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    {/* Hero Image */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>Hero Banner</h3>
                        <div style={{ display: 'flex', gap: 20, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                            {(theme.hero_bg_image || heroFile) && (
                                <img src={heroFile ? URL.createObjectURL(heroFile) : resolveImg(theme.hero_bg_image)} alt="" style={{ width: 200, height: 120, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--bg-border)' }} />
                            )}
                            <div style={{ flex: 1, minWidth: 200 }}>
                                <input type="file" ref={heroFileRef} accept="image/*" onChange={e => setHeroFile(e.target.files?.[0] || null)} style={{ display: 'none' }} />
                                <button onClick={() => heroFileRef.current?.click()} className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <Upload size={16} /> {heroFile ? heroFile.name : 'Upload Hero Image'}
                                </button>
                                <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 8 }}>Recommended: 1920×1080px, JPG/PNG</p>
                            </div>
                        </div>
                    </div>

                    {/* About Section */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>About Section</h3>
                        <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>About Text</label>
                        <textarea className="form-input" rows={5} value={theme.about_text} onChange={e => setTheme(p => ({ ...p, about_text: e.target.value }))} placeholder="Write about your school..." />
                        <div style={{ marginTop: 16, display: 'flex', gap: 20, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                            {(theme.about_image || aboutFile) && (
                                <img src={aboutFile ? URL.createObjectURL(aboutFile) : resolveImg(theme.about_image)} alt="" style={{ width: 160, height: 160, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--bg-border)' }} />
                            )}
                            <div>
                                <input type="file" ref={aboutFileRef} accept="image/*" onChange={e => setAboutFile(e.target.files?.[0] || null)} style={{ display: 'none' }} />
                                <button onClick={() => aboutFileRef.current?.click()} className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <Upload size={16} /> {aboutFile ? aboutFile.name : 'Upload About Image'}
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Principal */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>Principal's Message</h3>
                        <textarea className="form-input" rows={5} value={theme.principal_message} onChange={e => setTheme(p => ({ ...p, principal_message: e.target.value }))} placeholder="Principal's message..." />
                        <div style={{ marginTop: 16, display: 'flex', gap: 20, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                            {(theme.principal_image || principalFile) && (
                                <img src={principalFile ? URL.createObjectURL(principalFile) : resolveImg(theme.principal_image)} alt="" style={{ width: 120, height: 120, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--bg-border)' }} />
                            )}
                            <div>
                                <input type="file" ref={principalFileRef} accept="image/*" onChange={e => setPrincipalFile(e.target.files?.[0] || null)} style={{ display: 'none' }} />
                                <button onClick={() => principalFileRef.current?.click()} className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <Upload size={16} /> {principalFile ? principalFile.name : 'Upload Principal Photo'}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button onClick={handleSaveTheme} disabled={saving} className="btn btn-primary" style={{ alignSelf: 'flex-end', display: 'flex', alignItems: 'center', gap: 8, padding: '14px 32px' }}>
                        {saving ? <RefreshCw size={16} className="spin" /> : <Save size={16} />} Save Content
                    </button>
                </div>
            )}

            {/* ═══ GALLERY TAB ═══ */}
            {activeTab === 'gallery' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    {/* Upload New */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>Add New Image</h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16, alignItems: 'end' }}>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Image</label>
                                <input type="file" ref={galleryFileRef} accept="image/*" onChange={e => setGalleryFile(e.target.files?.[0] || null)} className="form-input" style={{ padding: 8 }} />
                            </div>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Caption</label>
                                <input className="form-input" value={newCaption} onChange={e => setNewCaption(e.target.value)} placeholder="Image caption" />
                            </div>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Category</label>
                                <select className="form-input" value={newCategory} onChange={e => setNewCategory(e.target.value)}>
                                    {['General', 'Academic', 'Sports', 'Events', 'Campus Life', 'Cultural'].map(c => <option key={c}>{c}</option>)}
                                </select>
                            </div>
                            <button onClick={handleAddGalleryImage} disabled={saving} className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'center' }}>
                                <Plus size={16} /> Upload
                            </button>
                        </div>
                    </div>

                    {/* Gallery Grid */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>
                            Gallery ({gallery.length} images)
                        </h3>
                        {gallery.length > 0 ? (
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 16 }}>
                                {gallery.map(item => (
                                    <div key={item.id} style={{ position: 'relative', borderRadius: 12, overflow: 'hidden', border: '1px solid var(--bg-border)', background: 'var(--bg-surface)' }}>
                                        <img src={resolveImg(item.image_path)} alt={item.caption} style={{ width: '100%', height: 160, objectFit: 'cover' }} />
                                        <div style={{ padding: '10px 12px' }}>
                                            <p style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-primary)', margin: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{item.caption || 'Untitled'}</p>
                                            <p style={{ fontSize: '0.7rem', color: 'var(--text-muted)', margin: 0 }}>{item.category}</p>
                                        </div>
                                        <button onClick={() => handleDeleteGalleryImage(item.id)}
                                            style={{ position: 'absolute', top: 8, right: 8, width: 28, height: 28, borderRadius: 8, background: 'rgba(239,68,68,0.9)', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <Trash2 size={14} color="white" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div style={{ textAlign: 'center', padding: 48, color: 'var(--text-muted)' }}>
                                <ImageIcon size={48} style={{ opacity: 0.2, marginBottom: 12 }} />
                                <p>No gallery images yet. Upload your first image above.</p>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ═══ STATS TAB ═══ */}
            {activeTab === 'stats' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    {/* Add New Stat */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>Add Statistic</h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16, alignItems: 'end' }}>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Label</label>
                                <input className="form-input" value={newStat.label} onChange={e => setNewStat(p => ({ ...p, label: e.target.value }))} placeholder="e.g. Students" />
                            </div>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Value</label>
                                <input className="form-input" value={newStat.value} onChange={e => setNewStat(p => ({ ...p, value: e.target.value }))} placeholder="e.g. 1500+" />
                            </div>
                            <div>
                                <label style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 8 }}>Icon Name</label>
                                <input className="form-input" value={newStat.icon} onChange={e => setNewStat(p => ({ ...p, icon: e.target.value }))} placeholder="e.g. Users" />
                            </div>
                            <button onClick={handleAddStat} className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'center' }}>
                                <Plus size={16} /> Add Stat
                            </button>
                        </div>
                    </div>

                    {/* Existing Stats */}
                    <div className="card" style={{ padding: 28 }}>
                        <h3 style={{ fontSize: '1rem', fontWeight: 700, marginBottom: 20, color: 'var(--text-primary)' }}>
                            Current Statistics ({stats.length})
                        </h3>
                        {stats.length > 0 ? (
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: 16 }}>
                                {stats.map((stat, i) => (
                                    <div key={stat.id || i} style={{ padding: 20, borderRadius: 12, border: '1px solid var(--bg-border)', background: 'var(--bg-surface)', position: 'relative' }}>
                                        <div style={{ fontSize: '2rem', fontWeight: 800, color: 'var(--accent)', marginBottom: 4 }}>{stat.value}</div>
                                        <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: 1 }}>{stat.label}</div>
                                        {stat.id && (
                                            <button onClick={() => handleDeleteStat(stat.id!)}
                                                style={{ position: 'absolute', top: 12, right: 12, background: 'none', border: 'none', cursor: 'pointer', color: 'var(--danger-light)', opacity: 0.6 }}>
                                                <Trash2 size={16} />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div style={{ textAlign: 'center', padding: 48, color: 'var(--text-muted)' }}>
                                <BarChart3 size={48} style={{ opacity: 0.2, marginBottom: 12 }} />
                                <p>No statistics configured. Add your first stat above.</p>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ═══ ADMISSIONS TAB ═══ */}
            {activeTab === 'admissions' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <div className="card" style={{ padding: 32 }}>
                        <div style={{ display: 'flex', gap: 20, marginBottom: 32 }}>
                            <div style={{ width: 56, height: 56, borderRadius: 16, background: 'rgba(59,130,246,0.1)', color: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                <FileText size={28} />
                            </div>
                            <div>
                                <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-primary)', marginBottom: 6 }}>Admission Form Configuration</h3>
                                <p style={{ fontSize: '0.875rem', color: 'var(--text-muted)', maxWidth: 600 }}>
                                    Control exactly which data points you collect from prospective students on your website. 
                                    Enable required flags to ensure you get critical information.
                                </p>
                            </div>
                        </div>
                        
                        <div style={{ background: 'var(--bg-elevated)', borderRadius: 20, border: '1px solid var(--bg-border)', overflow: 'hidden' }}>
                            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(200px, 1fr) 120px 120px', gap: 1, background: 'var(--bg-border)' }}>
                                <div style={{ background: 'var(--bg-surface)', padding: '16px 24px', fontWeight: 700, fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.05em' }}>Field Name</div>
                                <div style={{ background: 'var(--bg-surface)', padding: '16px 24px', fontWeight: 700, fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.05em', textAlign: 'center' }}>Visible</div>
                                <div style={{ background: 'var(--bg-surface)', padding: '16px 24px', fontWeight: 700, fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.05em', textAlign: 'center' }}>Required</div>

                                {Object.entries(admissionFields).map(([key, config]) => (
                                    <React.Fragment key={key}>
                                        <div style={{ background: 'var(--bg-surface)', padding: '20px 24px', borderTop: '1px solid var(--bg-border)' }}>
                                            <div style={{ fontWeight: 600, color: 'var(--text-primary)', textTransform: 'capitalize' }}>{key.replace(/_/g, ' ')}</div>
                                            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2 }}>
                                                {key === 'student_name' ? 'Full legal name of the student' : key === 'desired_class' ? 'Which grade they are applying for' : 'Additional contact/personal info'}
                                            </div>
                                        </div>
                                        <div style={{ background: 'var(--bg-surface)', padding: '20px 24px', borderTop: '1px solid var(--bg-border)', textAlign: 'center', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <label className="switch">
                                                <input type="checkbox" checked={config.enabled} 
                                                    onChange={e => {
                                                        const checked = e.target.checked;
                                                        setAdmissionFields(p => ({ ...p, [key]: { ...p[key], enabled: checked, required: checked ? p[key].required : false } }));
                                                    }} 
                                                />
                                                <span className="slider round"></span>
                                            </label>
                                        </div>
                                        <div style={{ background: 'var(--bg-surface)', padding: '20px 24px', borderTop: '1px solid var(--bg-border)', textAlign: 'center', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                            <input type="checkbox" checked={config.required} disabled={!config.enabled}
                                                onChange={e => setAdmissionFields(p => ({ ...p, [key]: { ...p[key], required: e.target.checked } }))} 
                                                style={{ width: 18, height: 18, cursor: config.enabled ? 'pointer' : 'not-allowed', opacity: config.enabled ? 1 : 0.3 }}
                                            />
                                        </div>
                                    </React.Fragment>
                                ))}
                            </div>
                        </div>
                    </div>
                    
                    <button onClick={handleSaveAdmissionConfig} disabled={saving} className="btn btn-primary" style={{ alignSelf: 'flex-end', display: 'flex', alignItems: 'center', gap: 10, padding: '16px 40px', borderRadius: 14 }}>
                        {saving ? <RefreshCw size={18} className="spin" /> : <Save size={18} />} Save Admission Rules
                    </button>
                </div>
            )}

            {/* ═══ EMAIL TAB ═══ */}
            {activeTab === 'email' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                    <div className="card" style={{ padding: 32 }}>
                        <div style={{ display: 'flex', gap: 20, marginBottom: 32 }}>
                            <div style={{ width: 56, height: 56, borderRadius: 16, background: 'rgba(16,185,129,0.1)', color: '#10b981', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                <Mail size={28} />
                            </div>
                            <div style={{ flex: 1 }}>
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 }}>
                                    <h3 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-primary)', margin: 0 }}>Mail Server Settings</h3>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 12, background: 'var(--bg-elevated)', padding: '8px 16px', borderRadius: 12, border: '1px solid var(--bg-border)' }}>
                                        <span style={{ fontSize: '0.8rem', fontWeight: 700, color: emailConfig.use_custom ? 'var(--accent)' : 'var(--text-muted)' }}>
                                            {emailConfig.use_custom ? 'CUSTOM SMTP ACTIVE' : 'USING DEFAULT SERVER'}
                                        </span>
                                        <label className="switch">
                                            <input type="checkbox" checked={emailConfig.use_custom} 
                                                onChange={e => setEmailConfig(p => ({ ...p, use_custom: e.target.checked }))} 
                                            />
                                            <span className="slider round"></span>
                                        </label>
                                    </div>
                                </div>
                                <p style={{ fontSize: '0.875rem', color: 'var(--text-muted)', margin: 0 }}>
                                    Configure how your school sends automated emails. We recommend using your official school SMTP for better deliverability.
                                </p>
                            </div>
                        </div>

                        {emailConfig.use_custom ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 24 }}>
                                    <div className="form-group-fancy">
                                        <label>Sender Identity</label>
                                        <div style={{ display: 'grid', gap: 16 }}>
                                            <input className="form-input-fancy" value={emailConfig.from_name} onChange={e => setEmailConfig(p => ({ ...p, from_name: e.target.value }))} placeholder="Principal - Edducare School" />
                                            <input className="form-input-fancy" value={emailConfig.from_email} onChange={e => setEmailConfig(p => ({ ...p, from_email: e.target.value }))} placeholder="admissions@school.com" />
                                        </div>
                                    </div>
                                    <div className="form-group-fancy">
                                        <label>Server Details</label>
                                        <div style={{ display: 'grid', gap: 16 }}>
                                            <input className="form-input-fancy" value={emailConfig.smtp_host} onChange={e => setEmailConfig(p => ({ ...p, smtp_host: e.target.value }))} placeholder="smtp.gmail.com" />
                                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                                                <input type="number" className="form-input-fancy" value={emailConfig.smtp_port} onChange={e => setEmailConfig(p => ({ ...p, smtp_port: parseInt(e.target.value) || 587 }))} placeholder="587" />
                                                <select className="form-input-fancy" value={emailConfig.smtp_crypto} onChange={e => setEmailConfig(p => ({ ...p, smtp_crypto: e.target.value }))}>
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                    <option value="none">None</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="form-group-fancy">
                                        <label>Authentication</label>
                                        <div style={{ display: 'grid', gap: 16 }}>
                                            <input className="form-input-fancy" value={emailConfig.smtp_user} onChange={e => setEmailConfig(p => ({ ...p, smtp_user: e.target.value }))} placeholder="SMTP Username" />
                                            <input type="password" className="form-input-fancy" value={emailConfig.smtp_pass} onChange={e => setEmailConfig(p => ({ ...p, smtp_pass: e.target.value }))} placeholder="••••••••••••" />
                                        </div>
                                    </div>
                                </div>
                                <div style={{ padding: '20px 24px', background: 'rgba(59,130,246,0.05)', borderRadius: 16, border: '1px solid rgba(59,130,246,0.1)', display: 'flex', gap: 16, alignItems: 'center' }}>
                                    <div style={{ color: 'var(--accent)' }}><Monitor size={20} /></div>
                                    <p style={{ fontSize: '0.82rem', color: 'var(--text-secondary)', margin: 0 }}>
                                        <strong>Tip:</strong> If using Gmail, make sure to use an "App Password" rather than your regular account password.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div style={{ textAlign: 'center', padding: '60px 40px', background: 'var(--bg-elevated)', borderRadius: 24, border: '2px dashed var(--bg-border)' }}>
                                <Globe size={48} style={{ color: 'var(--text-muted)', opacity: 0.3, marginBottom: 16 }} />
                                <h4 style={{ fontWeight: 700, color: 'var(--text-primary)', marginBottom: 8 }}>Platform Default Active</h4>
                                <p style={{ fontSize: '0.875rem', color: 'var(--text-muted)', margin: 0, maxWidth: 400, marginLeft: 'auto', marginRight: 'auto' }}>
                                    Emails are currently sent via Edducare's verified infrastructure. Switch on the custom SMTP toggle to use your own domain.
                                </p>
                            </div>
                        )}
                    </div>
                    
                    <button onClick={handleSaveEmailConfig} disabled={saving} className="btn btn-primary" style={{ alignSelf: 'flex-end', display: 'flex', alignItems: 'center', gap: 10, padding: '16px 40px', borderRadius: 14 }}>
                        {saving ? <RefreshCw size={18} className="spin" /> : <Save size={18} />} Save Mail Server
                    </button>
                </div>
            )}
        </div>
    );
};

export default WebsiteSettings;
