import {
    LayoutDashboard, GraduationCap, ChevronLeft, LogOut,
    CalendarDays, Package, School, Clock, ClipboardList, Award, Calendar, DollarSign, Library, Bell, Settings, FileText, TrendingUp, Users, Globe
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';
import { useLocation, NavLink } from 'react-router-dom';

interface NavItem {
    to: string;
    icon: any;
    label: string;
    badge?: number;
}

interface NavGroup {
    label: string;
    items: NavItem[];
}

const NAV_GROUPS: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
            { to: '/analytics', icon: TrendingUp, label: 'Analytics' },
        ],
    },
    {
        label: 'People',
        items: [
            { to: '/students', icon: GraduationCap, label: 'Students' },
            { to: '/teachers', icon: Users, label: 'Teachers' },
        ],
    },
    {
        label: 'Academics',
        items: [
            { to: '/classes', icon: School, label: 'Classes' },
            { to: '/timetable', icon: Clock, label: 'Timetable' },
            { to: '/assignments', icon: ClipboardList, label: 'Assignments' },
            { to: '/exams', icon: Award, label: 'Exams' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { to: '/attendance', icon: Calendar, label: 'Attendance' },
            { to: '/fees', icon: DollarSign, label: 'Fees' },
            { to: '/library', icon: Library, label: 'Library' },
            { to: '/announcements', icon: Bell, label: 'Announcements', badge: 3 },
        ],
    },
    {
        label: 'Settings',
        items: [
            { to: '/website', icon: Globe, label: 'Website Settings' },
            { to: '/reports', icon: FileText, label: 'Reports' },
            { to: '/sessions', icon: CalendarDays, label: 'Academic Sessions' },
            { to: '/settings', icon: Settings, label: 'System Settings' },
        ],
    },
];

const SUPERADMIN_NAV_GROUPS: NavGroup[] = [
    {
        label: 'Administration',
        items: [
            { to: '/', icon: LayoutDashboard, label: 'Overview' },
            { to: '/superadmin/schools', icon: School, label: 'Manage Schools' },
            { to: '/superadmin/packages', icon: Package, label: 'Pricing Models' },
        ],
    },
    {
        label: 'System',
        items: [
            { to: '/settings', icon: Settings, label: 'Settings' },
        ],
    },
];

interface SidebarProps { collapsed: boolean; onToggle: () => void; }

const Sidebar: React.FC<SidebarProps> = ({ collapsed, onToggle }) => {
    const { user, logout } = useAuth();
    const location = useLocation();

    const handleLogout = async () => {
        await logout();
        toast.success('Logged out successfully');
    };

    const getInitials = (u: typeof user) => {
        if (!u) return 'A';
        return `${u.first_name?.[0] ?? ''}${u.last_name?.[0] ?? ''}`.toUpperCase() || 'A';
    };

    const groups = user?.role === 'super_admin' ? SUPERADMIN_NAV_GROUPS : NAV_GROUPS;

    return (
        <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`}>
            {/* Logo */}
            <div className="sidebar-logo" onClick={onToggle}>
                <div className="sidebar-logo-icon">
                    <GraduationCap size={20} color="white" />
                </div>
                {!collapsed && (
                    <div className="sidebar-logo-text">
                        <h1>Edducare</h1>
                        <span>{user?.role === 'super_admin' ? 'Center Control' : 'School ERP'}</span>
                    </div>
                )}
                {!collapsed && (
                    <button
                        onClick={(e) => { e.stopPropagation(); onToggle(); }}
                        style={{ marginLeft: 'auto', background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
                    >
                        <ChevronLeft size={16} />
                    </button>
                )}
            </div>

            {/* Nav */}
            <nav className="sidebar-nav">
                {groups.map((group) => (
                    <div key={group.label}>
                        <div className="nav-section-label">{group.label}</div>
                        {group.items.map((item) => {
                            const isActive = item.to === '/'
                                ? location.pathname === '/'
                                : location.pathname.startsWith(item.to);
                            return (
                                <NavLink
                                    key={item.to}
                                    to={item.to}
                                    className={`nav-item ${isActive ? 'active' : ''}`}
                                    data-tooltip={collapsed ? item.label : undefined}
                                >
                                    <div className="nav-item-icon">
                                        <item.icon size={18} />
                                    </div>
                                    {!collapsed && <span className="nav-item-label">{item.label}</span>}
                                    {!collapsed && item.badge && (
                                        <span className="nav-badge">{item.badge}</span>
                                    )}
                                </NavLink>
                            );
                        })}
                    </div>
                ))}
            </nav>

            {/* Footer */}
            <div className="sidebar-footer">
                <div
                    className="nav-item"
                    onClick={handleLogout}
                    style={{ color: 'var(--danger-light)' }}
                >
                    <div className="nav-item-icon"><LogOut size={18} /></div>
                    {!collapsed && <span className="nav-item-label">Logout</span>}
                </div>
                {!collapsed && user && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 10px 2px' }}>
                        <div className="avatar" style={{ width: 32, height: 32, fontSize: '0.75rem' }}>
                            {getInitials(user)}
                        </div>
                        <div style={{ overflow: 'hidden' }}>
                            <div style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {user.first_name} {user.last_name}
                            </div>
                            <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)', textTransform: 'capitalize' }}>{user.role?.replace('_', ' ')}</div>
                        </div>
                    </div>
                )}
            </div>
        </aside>
    );
};

export default Sidebar;
