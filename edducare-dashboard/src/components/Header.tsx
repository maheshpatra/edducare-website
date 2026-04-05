import React, { useState } from 'react';
import { Bell, Search, Menu, ChevronDown } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';

interface HeaderProps {
    title: string;
    subtitle?: string;
    onMenuToggle: () => void;
}

const Header: React.FC<HeaderProps> = ({ title, subtitle, onMenuToggle }) => {
    const { user, logout } = useAuth();
    const [showUserMenu, setShowUserMenu] = useState(false);

    const getInitials = () => {
        if (!user) return 'A';
        return `${user.first_name?.[0] ?? ''}${user.last_name?.[0] ?? ''}`.toUpperCase() || 'A';
    };

    const handleLogout = async () => {
        await logout();
        toast.success('Logged out successfully');
    };

    return (
        <header className="header">
            <div className="header-left">
                <button className="icon-btn" onClick={onMenuToggle} style={{ display: 'none' }} id="mobile-menu-btn">
                    <Menu size={18} />
                </button>
                <div>
                    <div className="header-title">{title}</div>
                    {subtitle && <div className="header-subtitle">{subtitle}</div>}
                </div>
            </div>

            <div className="header-right">
                {/* Notification bell */}
                <div className="icon-btn" style={{ position: 'relative' }}>
                    <Bell size={18} />
                    <span className="notif-dot" />
                </div>

                {/* User avatar */}
                <div style={{ position: 'relative' }}>
                    <div
                        className="avatar"
                        onClick={() => setShowUserMenu(!showUserMenu)}
                        style={{ display: 'flex', alignItems: 'center', gap: 8, width: 'auto', padding: '0 12px 0 6px', borderRadius: 'var(--radius-sm)', background: 'var(--bg-elevated)', border: '1px solid var(--bg-border)' }}
                    >
                        <div className="avatar" style={{ margin: 0 }}>{getInitials()}</div>
                        <div style={{ textAlign: 'left' }}>
                            <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)' }}>
                                {user?.first_name} {user?.last_name}
                            </div>
                            <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', textTransform: 'capitalize' }}>
                                {user?.role?.replace('_', ' ')}
                            </div>
                        </div>
                        <ChevronDown size={14} style={{ color: 'var(--text-muted)' }} />
                    </div>

                    {showUserMenu && (
                        <div
                            style={{
                                position: 'absolute', top: 'calc(100% + 8px)', right: 0,
                                background: 'var(--bg-card)', border: '1px solid var(--bg-border)',
                                borderRadius: 'var(--radius-md)', boxShadow: 'var(--shadow-lg)',
                                width: 200, zIndex: 100, overflow: 'hidden',
                            }}
                            onClick={() => setShowUserMenu(false)}
                        >
                            <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--bg-border)' }}>
                                <div style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>{user?.email}</div>
                                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2 }}>
                                    {user?.school_name}
                                </div>
                            </div>
                            <button
                                onClick={handleLogout}
                                style={{
                                    width: '100%', padding: '10px 16px', background: 'none', border: 'none',
                                    color: 'var(--danger-light)', fontSize: '0.875rem', fontWeight: 600,
                                    cursor: 'pointer', textAlign: 'left',
                                }}
                            >
                                Sign out
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
};

export default Header;
