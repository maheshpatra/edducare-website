import React, { useState, useRef, useEffect } from 'react';
import { Bell, Menu, ChevronDown, User, Settings, LogOut, CheckCircle, Info, AlertTriangle } from 'lucide-react';
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
    const [showNotifs, setShowNotifs] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const notifRef = useRef<HTMLDivElement>(null);

    // Mock notifications
    const [notifications] = useState([
        { id: 1, type: 'success', title: 'Payment Received', msg: 'Fee for Aditya Verma (Class 5) confirmed.', time: '2 min ago' },
        { id: 2, type: 'info', title: 'New Admission', msg: 'Ananya Kapoor just registered for nursery.', time: '1 hour ago' },
        { id: 3, type: 'warning', title: 'System Update', msg: 'Scheduled maintenance tonight at 12 AM.', time: '5 hours ago' },
    ]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) setShowUserMenu(false);
            if (notifRef.current && !notifRef.current.contains(event.target as Node)) setShowNotifs(false);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

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
                {/* Notification section */}
                <div ref={notifRef} style={{ position: 'relative' }}>
                    <div className={`icon-btn ${showNotifs ? 'active' : ''}`} onClick={() => setShowNotifs(!showNotifs)}>
                        <Bell size={20} />
                        {notifications.length > 0 && <span className="notif-dot" />}
                    </div>

                    {showNotifs && (
                        <div className="dropdown-menu" style={{ width: 340 }}>
                            <div className="dropdown-header">
                                <span style={{ fontWeight: 700, fontSize: '0.9rem' }}>Notifications</span>
                                <span className="badge badge-primary">{notifications.length} New</span>
                            </div>
                            <div className="dropdown-body">
                                {notifications.map(n => (
                                    <div key={n.id} className="notif-item">
                                        <div className={`notif-icon-circle ${n.type === 'success' ? 'badge-success' : n.type === 'warning' ? 'badge-warning' : 'badge-info'}`}>
                                            {n.type === 'success' && <CheckCircle size={16} />}
                                            {n.type === 'warning' && <AlertTriangle size={16} />}
                                            {n.type === 'info' && <Info size={16} />}
                                        </div>
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)' }}>{n.title}</div>
                                            <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: 2, lineHeight: 1.4 }}>{n.msg}</div>
                                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)', marginTop: 4 }}>{n.time}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <div style={{ padding: '12px', textAlign: 'center', borderTop: '1px solid var(--bg-border)' }}>
                                <button style={{ background: 'none', border: 'none', color: 'var(--primary-light)', fontSize: '0.75rem', fontWeight: 700, cursor: 'pointer' }}>
                                    View All Notifications
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* User section */}
                <div ref={menuRef} style={{ position: 'relative' }}>
                    <div 
                        className={`user-profile-btn ${showUserMenu ? 'active' : ''}`}
                        onClick={() => setShowUserMenu(!showUserMenu)}
                    >
                        <div className="avatar" style={{ margin: 0, width: 32, height: 32, fontSize: '0.75rem' }}>
                            {getInitials()}
                        </div>
                        <div style={{ textAlign: 'left', display: 'none', display: 'block' } as any}>
                            <div style={{ fontSize: '0.82rem', fontWeight: 700, color: 'var(--text-primary)', lineHeight: 1 }}>
                                {user?.first_name} {user?.last_name}
                            </div>
                            <div style={{ fontSize: '0.68rem', color: 'var(--text-muted)', textTransform: 'capitalize', marginTop: 2 }}>
                                {user?.role?.replace('_', ' ')}
                            </div>
                        </div>
                        <ChevronDown size={14} style={{ color: 'var(--text-muted)', marginLeft: 4 }} />
                    </div>

                    {showUserMenu && (
                        <div className="dropdown-menu">
                            <div className="dropdown-header" style={{ padding: '16px 20px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                    <div className="avatar" style={{ width: 44, height: 44, fontSize: '1rem' }}>{getInitials()}</div>
                                    <div>
                                        <div style={{ fontWeight: 700, color: 'var(--text-primary)' }}>{user?.first_name} {user?.last_name}</div>
                                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: 2 }}>{user?.email}</div>
                                    </div>
                                </div>
                            </div>
                            <div className="dropdown-body" style={{ padding: '8px' }}>
                                {[
                                    { icon: <User size={16} />, label: 'My Profile', path: '/settings' },
                                    { icon: <Settings size={16} />, label: 'Preferences', path: '/settings' },
                                ].map((item, i) => (
                                    <div key={i} className="nav-item" style={{ margin: '2px 0' }} onClick={() => setShowUserMenu(false)}>
                                        <div className="nav-item-icon" style={{ color: 'var(--text-muted)' }}>{item.icon}</div>
                                        <span className="nav-item-label">{item.label}</span>
                                    </div>
                                ))}
                            </div>
                            <div style={{ padding: '8px', borderTop: '1px solid var(--bg-border)' }}>
                                <button
                                    onClick={handleLogout}
                                    style={{
                                        display: 'flex', alignItems: 'center', gap: 10,
                                        width: '100%', padding: '10px 12px', background: 'none', border: 'none',
                                        color: '#f87171', fontSize: '0.875rem', fontWeight: 600,
                                        cursor: 'pointer', textAlign: 'left', borderRadius: '8px',
                                        transition: 'background 0.2s'
                                    }}
                                    onMouseOver={(e) => e.currentTarget.style.background = 'rgba(248, 113, 113, 0.1)'}
                                    onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                >
                                    <LogOut size={16} /> Sign out
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
};

export default Header;

