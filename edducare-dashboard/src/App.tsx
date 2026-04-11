import React, { useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from './contexts/AuthContext';

import Sidebar from './components/Sidebar';
import Header from './components/Header';

import Login from './pages/Login';
import ContactMessages from './pages/superadmin/ContactMessages';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import Dashboard from './pages/Dashboard';
import Students from './pages/Students';
import Teachers from './pages/Teachers';
import Classes from './pages/Classes';
import Attendance from './pages/Attendance';
import Fees from './pages/Fees';
import Library from './pages/Library';
import Assignments from './pages/Assignments';
import Exams from './pages/Exams';
import ExamResults from './pages/ExamResults';
import Timetable from './pages/Timetable';
import Announcements from './pages/Announcements';
import Analytics from './pages/Analytics';
import Reports from './pages/Reports';
import Settings from './pages/Settings';
import WebsiteSettings from './pages/WebsiteSettings';
import Admissions from './pages/Admissions';
import AcademicSessions from './pages/AcademicSessions';
import PromoteStudents from './pages/PromoteStudents';
import HumanResource from './pages/HumanResource';
import Subjects from './pages/Subjects';

import SuperAdminDashboard from './pages/superadmin/Dashboard';
import SuperAdminSchools from './pages/superadmin/Schools';
import SuperAdminPackages from './pages/superadmin/Packages';
import SuperAdminCMS from './pages/superadmin/CMSEditor';

import './index.css';

const PAGE_TITLES: Record<string, { title: string; subtitle: string }> = {
  '/': { title: 'Dashboard', subtitle: 'Welcome back! Here\'s what\'s happening today.' },
  '/students': { title: 'Students', subtitle: 'Manage student enrollment, records, and profiles.' },
  '/teachers': { title: 'Teachers', subtitle: 'Manage teaching staff and assignments.' },
  '/classes': { title: 'Classes', subtitle: 'Manage classes, sections, and academic structure.' },
  '/attendance': { title: 'Attendance', subtitle: 'Mark and track student attendance.' },
  '/fees': { title: 'Fees', subtitle: 'Manage fee collection and payment records.' },
  '/library': { title: 'Library', subtitle: 'Manage books, issues, and returns.' },
  '/assignments': { title: 'Assignments', subtitle: 'Create and manage student assignments.' },
  '/exams': { title: 'Examinations', subtitle: 'Schedule and manage school examinations.' },
  '/exam-results': { title: 'Upload Results', subtitle: 'Enter and manage student exam marks.' },
  '/timetable': { title: 'Timetable', subtitle: 'View and manage class timetables.' },
  '/announcements': { title: 'Announcements', subtitle: 'Publish and manage school announcements.' },
  '/analytics': { title: 'Analytics', subtitle: 'Deep insights into school performance.' },
  '/reports': { title: 'Reports', subtitle: 'Generate and download detailed reports.' },
  '/sessions': { title: 'Academic Sessions', subtitle: 'Manage academic years and active sessions.' },
  '/promote-students': { title: 'Promote Students', subtitle: 'Promote students to the next session, class, or section.' },
  '/hr': { title: 'Human Resource', subtitle: 'Manage staff, payroll, leaves, departments, and birthdays.' },
  '/subjects': { title: 'Subjects', subtitle: 'Manage school curriculum and subject allocations.' },
  '/settings': { title: 'Settings', subtitle: 'Configure school and system preferences.' },
  '/website': { title: 'Website Settings', subtitle: 'Customize your school website appearance and content.' },
  '/admissions': { title: 'Admissions', subtitle: 'Review and manage student admission requests.' },
  '/messages': { title: 'Contact Messages', subtitle: 'Respond to student and visitor inquiries.' },
  '/superadmin/schools': { title: 'Managed Schools', subtitle: 'Oversee and control all schools on the platform.' },
  '/superadmin/packages': { title: 'Pricing Models', subtitle: 'Manage subscription plans and pricing.' },
  '/superadmin/cms': { title: 'CMS Manager', subtitle: 'Manage Terms, Privacy Policy, and other legal pages.' },
  '/superadmin/contacts': { title: 'Site Inquiries', subtitle: 'Manage contact form submissions.' },
};

const AppShell: React.FC = () => {
  const { user, isAuthenticated, isLoading } = useAuth();
  const [collapsed, setCollapsed] = useState(false);
  const location = useLocation();
  const pathname = location.pathname;

  if (isLoading) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100vh', background: 'var(--bg-base)' }}>
        <div style={{ textAlign: 'center' }}>
          <div className="spinner" style={{ width: 40, height: 40, margin: '0 auto 16px' }} />
          <div style={{ color: 'var(--text-muted)', fontSize: '0.875rem' }}>Loading Edducare…</div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    );
  }

  const pageInfo = PAGE_TITLES[pathname] ?? { title: 'Edducare', subtitle: '' };

  return (
    <div className="app-shell">
      <Sidebar collapsed={collapsed} onToggle={() => setCollapsed(p => !p)} />

      <div className="main-content">
        <Header
          title={pageInfo.title}
          subtitle={pageInfo.subtitle}
          onMenuToggle={() => setCollapsed(p => !p)}
        />

        <main className="page-content">
          <Routes>
            <Route path="/" element={user?.role === 'super_admin' ? <SuperAdminDashboard /> : <Dashboard />} />
            <Route path="/students" element={<Students />} />
            <Route path="/teachers" element={<Teachers />} />
            <Route path="/classes" element={<Classes />} />
            <Route path="/attendance" element={<Attendance />} />
            <Route path="/fees" element={<Fees />} />
            <Route path="/library" element={<Library />} />
            <Route path="/assignments" element={<Assignments />} />
            <Route path="/exams" element={<Exams />} />
            <Route path="/exam-results" element={<ExamResults />} />
            <Route path="/timetable" element={<Timetable />} />
            <Route path="/announcements" element={<Announcements />} />
            <Route path="/analytics" element={<Analytics />} />
            <Route path="/reports" element={<Reports />} />
            <Route path="/sessions" element={<AcademicSessions />} />
            <Route path="/promote-students" element={<PromoteStudents />} />
            <Route path="/hr" element={<HumanResource />} />
            <Route path="/subjects" element={<Subjects />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/website" element={<WebsiteSettings />} />
            <Route path="/admissions" element={<Admissions />} />

            {/* Super Admin Routes */}
            <Route path="/superadmin/schools" element={<SuperAdminSchools />} />
            <Route path="/superadmin/packages" element={<SuperAdminPackages />} />
            <Route path="/superadmin/cms" element={<SuperAdminCMS />} />
            <Route path="/superadmin/contacts" element={<ContactMessages />} />

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </main>
      </div>
    </div>
  );
};

const App: React.FC = () => (
  <BrowserRouter>
    <AuthProvider>
      <AppShell />
      <Toaster
        position="top-right"
        toastOptions={{
          style: {
            background: 'var(--bg-elevated)',
            color: 'var(--text-primary)',
            border: '1px solid var(--bg-border)',
            borderRadius: 'var(--radius-sm)',
            fontSize: '0.875rem',
          },
          success: { iconTheme: { primary: '#10b981', secondary: 'white' } },
          error: { iconTheme: { primary: '#ef4444', secondary: 'white' } },
          duration: 3000,
        }}
      />
    </AuthProvider>
  </BrowserRouter>
);

export default App;
