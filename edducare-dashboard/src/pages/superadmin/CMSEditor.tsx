import React, { useState, useEffect } from 'react';
import api from '../../api/config';
import toast from 'react-hot-toast';
import { FileText, Save, Plus, Edit3, Eye, CheckCircle, Globe, RefreshCcw } from 'lucide-react';

interface CMSPage {
    id: number;
    slug: string;
    title: string;
    content: string;
    is_active: number;
    updated_at: string;
}

const CMSEditor: React.FC = () => {
    const [pages, setPages] = useState<CMSPage[]>([]);
    const [selectedPage, setSelectedPage] = useState<CMSPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isPreview, setIsPreview] = useState(false);
    const [isCreating, setIsCreating] = useState(false);

    const API_PATH = '/superadmin/cms';

    useEffect(() => {
        fetchPages();
    }, []);

    const fetchPages = async () => {
        setLoading(true);
        try {
            const response = await api.get(API_PATH);
            if (response.data.success) {
                setPages(response.data.data);
                if (response.data.data.length > 0 && !selectedPage) {
                    handleSelectPage(response.data.data[0].slug);
                }
            }
        } catch (err) {
            toast.error('Failed to fetch pages');
        } finally {
            setLoading(false);
        }
    };

    const handleSelectPage = async (slug: string) => {
        setLoading(true);
        setIsCreating(false);
        try {
            const response = await api.get(`${API_PATH}?slug=${slug}`);
            if (response.data.success) {
                setSelectedPage(response.data.data);
            }
        } catch (err) {
            toast.error('Failed to load page content');
        } finally {
            setLoading(false);
        }
    };

    const handleCreateNew = () => {
        setSelectedPage({
            id: 0,
            slug: 'new-page-' + Math.floor(Math.random() * 1000),
            title: 'New Page',
            content: '<h1>New Page</h1>\n<p>Start writing...</p>',
            is_active: 1,
            updated_at: new Date().toISOString()
        });
        setIsCreating(true);
        setIsPreview(false);
    };

    const handleSave = async () => {
        if (!selectedPage) return;
        if (!selectedPage.title || !selectedPage.slug) {
            toast.error('Title and Slug are required');
            return;
        }

        setSaving(true);
        try {
            const response = await api.post(API_PATH, selectedPage);
            if (response.data.success) {
                toast.success('Page saved successfully!');
                setIsCreating(false);
                fetchPages();
            }
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to save page');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fade-in">
            <div className="toolbar">
                <div style={{ display: 'flex', gap: '10px' }}>
                    <button className="btn btn-primary" onClick={handleCreateNew}>
                        <Plus size={18} /> Add New Page
                    </button>
                </div>
                <button className="btn btn-secondary" onClick={fetchPages} disabled={loading}>
                    <RefreshCcw size={18} className={loading ? "animate-spin" : ""} /> Refresh
                </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(250px, 1fr) 3fr', gap: '20px', marginTop: '20px', alignItems: 'start' }}>
                {/* Sidebar */}
                <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
                    <div style={{ padding: '16px', borderBottom: '1px solid var(--bg-border)', background: 'var(--bg-elevated)', fontSize: '0.875rem', fontWeight: 800, color: 'var(--text-secondary)' }}>
                        PAGES DIRECTORY
                    </div>
                    {loading && pages.length === 0 ? (
                        <div style={{ padding: '40px', textAlign: 'center' }}>
                            <div className="spinner" style={{ margin: 'auto' }} />
                        </div>
                    ) : pages.length === 0 && !isCreating ? (
                        <div style={{ padding: '30px', textAlign: 'center', color: 'var(--text-muted)', fontSize: '0.875rem' }}>
                            No pages found.
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                            {pages.map((page) => (
                                <div
                                    key={page.slug}
                                    onClick={() => handleSelectPage(page.slug)}
                                    style={{
                                        padding: '16px',
                                        cursor: 'pointer',
                                        borderBottom: '1px solid var(--bg-border)',
                                        background: selectedPage?.slug === page.slug && !isCreating ? 'var(--primary-glow)' : 'transparent',
                                        borderLeft: selectedPage?.slug === page.slug && !isCreating ? '4px solid var(--primary)' : '4px solid transparent',
                                        transition: 'all 0.2s ease',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between'
                                    }}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', overflow: 'hidden' }}>
                                        <FileText size={16} style={{ color: selectedPage?.slug === page.slug && !isCreating ? 'var(--primary)' : 'var(--text-muted)' }} />
                                        <span style={{ 
                                            fontWeight: selectedPage?.slug === page.slug ? 700 : 500, 
                                            color: selectedPage?.slug === page.slug && !isCreating ? 'var(--primary-dark)' : 'var(--text-primary)',
                                            whiteSpace: 'nowrap', textOverflow: 'ellipsis', overflow: 'hidden' 
                                        }}>
                                            {page.title}
                                        </span>
                                    </div>
                                    {page.is_active === 1 && (
                                        <CheckCircle size={14} style={{ color: 'var(--success)', flexShrink: 0 }} />
                                    )}
                                </div>
                            ))}
                            {isCreating && (
                                <div style={{
                                    padding: '16px', borderBottom: '1px solid var(--bg-border)', 
                                    background: 'var(--primary-glow)', borderLeft: '4px solid var(--primary)',
                                    display: 'flex', alignItems: 'center', gap: '10px'
                                }}>
                                    <Plus size={16} style={{ color: 'var(--primary)' }} />
                                    <span style={{ fontWeight: 700, color: 'var(--primary-dark)' }}>New Draft...</span>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Editor Content */}
                <div className="card" style={{ padding: '0px' }}>
                    {selectedPage ? (
                        <>
                            <div style={{ padding: '24px', borderBottom: '1px solid var(--bg-border)', background: 'var(--bg-elevated)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <div>
                                    <h2 style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--text-primary)', margin: 0, display: 'flex', alignItems: 'center', gap: '10px' }}>
                                        {isCreating ? 'Drafting New Page' : 'Edit Page'}
                                    </h2>
                                    {!isCreating && <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', margin: '4px 0 0 0' }}>Last updated: {new Date(selectedPage.updated_at).toLocaleString()}</p>}
                                </div>
                                <div style={{ display: 'flex', gap: '10px' }}>
                                    <button className="btn btn-secondary" onClick={() => setIsPreview(!isPreview)}>
                                        {isPreview ? <Edit3 size={16} /> : <Eye size={16} />}
                                        {isPreview ? 'Edit HTML' : 'Preview'}
                                    </button>
                                    <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                                        <Save size={16} />
                                        {saving ? 'Saving...' : 'Save Changes'}
                                    </button>
                                </div>
                            </div>

                            <div style={{ padding: '24px' }}>
                                <div className="grid-2" style={{ marginBottom: '20px' }}>
                                    <div className="form-group">
                                        <label>Page Title</label>
                                        <input 
                                            className="input" 
                                            value={selectedPage.title}
                                            onChange={(e) => setSelectedPage({...selectedPage, title: e.target.value})}
                                            placeholder="e.g. Terms and Conditions"
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label>Status</label>
                                        <select 
                                            className="input"
                                            value={selectedPage.is_active}
                                            onChange={(e) => setSelectedPage({...selectedPage, is_active: parseInt(e.target.value)})}
                                        >
                                            <option value={1}>Published</option>
                                            <option value={0}>Hidden / Draft</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="form-group" style={{ marginBottom: '20px' }}>
                                    <label style={{ display: 'flex', alignItems: 'center', gap: '6px' }}><Globe size={14} /> URL Slug</label>
                                    <div style={{ display: 'flex', alignItems: 'center', border: '1px solid var(--bg-border)', borderRadius: 'var(--radius-md)', background: 'var(--bg-elevated)', overflow: 'hidden' }}>
                                        <span style={{ padding: '0 12px', color: 'var(--text-muted)', fontSize: '0.875rem', borderRight: '1px solid var(--bg-border)', background: 'var(--bg-card)' }}>edducare.org/p/</span>
                                        <input 
                                            style={{ flex: 1, border: 'none', padding: '10px 12px', background: 'transparent', outline: 'none', fontSize: '0.875rem', color: 'var(--text-primary)' }}
                                            value={selectedPage.slug}
                                            onChange={(e) => setSelectedPage({...selectedPage, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-')})}
                                            placeholder="e.g. terms-of-service"
                                        />
                                    </div>
                                </div>

                                <div className="form-group">
                                    <label>Page Content (HTML)</label>
                                    {isPreview ? (
                                        <div style={{ 
                                            padding: '24px', 
                                            background: '#fff', 
                                            border: '1px solid var(--bg-border)', 
                                            borderRadius: 'var(--radius-md)', 
                                            minHeight: '400px',
                                            color: '#333'
                                        }}>
                                            <div dangerouslySetInnerHTML={{ __html: selectedPage.content }} />
                                        </div>
                                    ) : (
                                        <textarea 
                                            className="input" 
                                            style={{ minHeight: '400px', fontFamily: 'monospace', fontSize: '0.875rem', lineHeight: 1.5, padding: '16px' }}
                                            value={selectedPage.content}
                                            onChange={(e) => setSelectedPage({...selectedPage, content: e.target.value})}
                                            placeholder="<h1>Title here</h1><p>Content goes here...</p>"
                                        />
                                    )}
                                </div>
                            </div>
                        </>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', minHeight: '400px', color: 'var(--text-muted)' }}>
                            <FileText size={48} style={{ opacity: 0.2, marginBottom: '20px' }} />
                            <h3 style={{ margin: '0 0 8px 0', color: 'var(--text-primary)' }}>No Page Selected</h3>
                            <p style={{ fontSize: '0.875rem' }}>Select a page from the sidebar or create a new one.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default CMSEditor;
