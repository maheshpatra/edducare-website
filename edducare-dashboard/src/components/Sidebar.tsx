import {
    LayoutDashboard, GraduationCap, ChevronLeft, LogOut,
    CalendarDays, Package, School, Clock, ClipboardList, Award, Calendar, DollarSign, Library, Bell, Settings, TrendingUp, Users, Globe, UserCheck, Mail, Upload, ArrowUpCircle, Briefcase, BookOpen, ChevronDown, FileBarChart, FileText, MessageSquare
} from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';
import { useLocation, NavLink } from 'react-router-dom';
import { useState } from 'react';

interface NavItem {
    to: string;
    icon: any;
    label: string;
    badge?: number;
}

interface NavGroup {
    label: string;
    icon?: any;
    collapsible?: boolean;
    items: NavItem[];
}

const NAV_GROUPS: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
        ],
    },
    {
        label: 'People Management',
        collapsible: true,
        items: [
            { to: '/students', icon: GraduationCap, label: 'Students' },
            { to: '/teachers', icon: Users, label: 'Teachers' },
            { to: '/admissions', icon: UserCheck, label: 'Admissions' },
            { to: '/messages', icon: Mail, label: 'Messages' },
        ],
    },
    {
        label: 'Academics',
        collapsible: true,
        items: [
            { to: '/classes', icon: School, label: 'Classes' },
            { to: '/subjects', icon: BookOpen, label: 'Subjects' },
            { to: '/sessions', icon: CalendarDays, label: 'Academic Sessions' },
            { to: '/timetable', icon: Clock, label: 'Timetable' },
            { to: '/assignments', icon: ClipboardList, label: 'Assignments' },
            { to: '/exams', icon: Award, label: 'Examinations' },
            { to: '/exam-results', icon: Upload, label: 'Upload Results' },
            { to: '/promote-students', icon: ArrowUpCircle, label: 'Promote Students' },
        ],
    },
    {
        label: 'Operations',
        collapsible: true,
        items: [
            { to: '/attendance', icon: Calendar, label: 'Attendance' },
            { to: '/fees', icon: DollarSign, label: 'Fees & Payments' },
            { to: '/library', icon: Library, label: 'Library' },
            { to: '/announcements', icon: Bell, label: 'Announcements' },
        ],
    },
    {
        label: 'Human Resource',
        collapsible: true,
        items: [
            { to: '/hr', icon: Briefcase, label: 'HR Management' },
        ],
    },
    {
        label: 'Reports & Analytics',
        collapsible: true,
        items: [
            { to: '/analytics', icon: TrendingUp, label: 'Analytics' },
            { to: '/reports', icon: FileBarChart, label: 'Reports' },
        ],
    },
    {
        label: 'Configuration',
        collapsible: true,
        items: [
            { to: '/settings', icon: Settings, label: 'System Settings' },
            { to: '/website', icon: Globe, label: 'Website Settings' },
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
            { to: '/superadmin/cms', icon: FileText, label: 'CMS Manager' },
            { to: '/superadmin/contacts', icon: MessageSquare, label: 'Site Inquiries' },
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
    const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set());

    const handleLogout = async () => {
        await logout();
        toast.success('Logged out successfully');
    };

    const getInitials = (u: typeof user) => {
        if (!u) return 'A';
        return `${u.first_name?.[0] ?? ''}${u.last_name?.[0] ?? ''}`.toUpperCase() || 'A';
    };

    const toggleGroup = (label: string) => {
        setCollapsedGroups(prev => {
            const next = new Set(prev);
            if (next.has(label)) next.delete(label);
            else next.add(label);
            return next;
        });
    };

    const isGroupActive = (group: NavGroup): boolean => {
        return group.items.some(item =>
            item.to === '/'
                ? location.pathname === '/'
                : location.pathname.startsWith(item.to)
        );
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
                {groups.map((group) => {
                    const isCollapsed = collapsedGroups.has(group.label);
                    const hasActiveItem = isGroupActive(group);

                    return (
                        <div key={group.label}>
                            <div
                                className={`nav-section-label ${group.collapsible ? 'nav-section-collapsible' : ''}`}
                                onClick={() => group.collapsible && !collapsed && toggleGroup(group.label)}
                                style={{
                                    cursor: group.collapsible && !collapsed ? 'pointer' : 'default',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    userSelect: 'none',
                                }}
                            >
                                <span style={{
                                    opacity: hasActiveItem ? 1 : undefined,
                                    color: hasActiveItem ? 'var(--primary-light)' : undefined,
                                }}>
                                    {collapsed ? group.label.charAt(0) : group.label}
                                </span>
                                {!collapsed && group.collapsible && (
                                    <ChevronDown
                                        size={13}
                                        style={{
                                            transition: 'transform 0.2s ease',
                                            transform: isCollapsed ? 'rotate(-90deg)' : 'rotate(0deg)',
                                            opacity: 0.5,
                                        }}
                                    />
                                )}
                            </div>
                            <div
                                style={{
                                    overflow: 'hidden',
                                    maxHeight: (isCollapsed && !collapsed) ? 0 : '500px',
                                    transition: 'max-height 0.25s ease',
                                }}
                            >
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
                        </div>
                    );
                })}
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
