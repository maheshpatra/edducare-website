import React, { useState, useEffect } from 'react';
import { NavLink, Link, useLocation } from 'react-router-dom';
import { useSchool } from '../context/SchoolContext';
import { getTemplate } from '../templates/templateConfig';
import { resolveImagePath } from '../utils/helpers';
import { Menu, X, Phone, Mail, MapPin, ChevronUp, Facebook, Instagram, Youtube, ArrowRight, ExternalLink } from 'lucide-react';

export default function Layout({ children }) {
  const { activeSchool, theme, FILE_BASE } = useSchool();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [showScrollTop, setShowScrollTop] = useState(false);
  const location = useLocation();
  const template = getTemplate(theme?.layout_style);

  useEffect(() => {
    setIsMenuOpen(false);
    window.scrollTo(0, 0);
  }, [location.pathname]);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
      setShowScrollTop(window.scrollY > 600);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const navigation = [
    { name: 'Home', href: '/' },
    { name: 'About', href: '/about' },
    { name: 'Academics', href: '/academics' },
    { name: 'Teachers', href: '/teachers' },
    { name: 'Notices', href: '/notices' },
    { name: 'Gallery', href: '/gallery' },
    { name: 'Contact', href: '/contact' },
  ];

  const logoSrc = activeSchool?.logo ? resolveImagePath(activeSchool.logo, FILE_BASE) : null;

  const getNavActiveClass = (isActive) => {
    const base = 'text-[13px] font-semibold transition-all duration-300 py-2 px-3.5 rounded-xl';
    if (template.navStyle === 'pill') {
      return `${base} ${isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:text-primary hover:bg-slate-50'}`;
    }
    if (template.navStyle === 'underline') {
      return `${base} border-b-2 ${isActive ? 'border-primary text-primary' : 'border-transparent text-slate-600 hover:text-primary hover:border-primary/30'} rounded-none`;
    }
    if (template.navStyle === 'glow') {
      return `${base} ${isActive ? 'text-white font-bold bg-white/10' : 'text-slate-300 hover:text-white hover:bg-white/5'}`;
    }
    if (template.navStyle === 'block') {
      return `${base} ${isActive ? 'bg-white/20 text-white font-bold' : 'text-white/80 hover:text-white hover:bg-white/10'}`;
    }
    return `${base} ${isActive ? 'text-primary bg-primary/8' : 'text-slate-600 hover:text-primary hover:bg-slate-50'}`;
  };

  const headerBg = () => {
    if (template.headerStyle === 'dark') {
      return scrolled ? 'bg-slate-900/95 backdrop-blur-xl shadow-2xl shadow-slate-900/10' : 'bg-slate-900';
    }
    if (template.headerStyle === 'gradient') {
      return scrolled ? 'school-gradient shadow-2xl shadow-primary/10' : 'school-gradient';
    }
    if (template.headerStyle === 'transparent') {
      return scrolled ? 'bg-white/90 backdrop-blur-xl shadow-lg shadow-slate-900/5 border-b border-slate-100/50' : 'bg-white';
    }
    // solid (classic)
    return scrolled ? 'bg-white shadow-xl shadow-slate-900/5' : 'bg-white shadow-sm border-b-4 border-primary';
  };

  const isLightHeader = template.headerStyle === 'dark' || template.headerStyle === 'gradient';

  return (
    <div className={`flex flex-col min-h-screen template-${template.id}`}>
      {/* ── Top Info Bar ── */}
      <div className="school-gradient text-white py-2 relative z-[60]">
        <div className="max-w-7xl mx-auto px-4 flex justify-between items-center text-xs font-medium">
          <div className="flex items-center space-x-6">
            <a href={`tel:${activeSchool?.phone}`} className="flex items-center hover:opacity-80 transition-opacity gap-1.5">
              <Phone className="w-3 h-3" /> {activeSchool?.phone || 'N/A'}
            </a>
            <a href={`mailto:${activeSchool?.email}`} className="hidden sm:flex items-center hover:opacity-80 transition-opacity gap-1.5">
              <Mail className="w-3 h-3" /> {activeSchool?.email || 'N/A'}
            </a>
          </div>
          <div className="flex items-center space-x-4">
            <Link to="/admission" className="hover:opacity-80 transition-opacity flex items-center gap-1">
              Admissions <ExternalLink className="w-3 h-3" />
            </Link>
            <span className="opacity-30">|</span>
            <Link to="/results" className="hover:opacity-80 transition-opacity">Results</Link>
          </div>
        </div>
      </div>

      {/* ── Main Header ── */}
      <header className={`sticky top-0 z-50 transition-all duration-500 ${headerBg()}`}>
        <nav className="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
          {/* Logo */}
          <Link to="/" className="flex items-center space-x-3 group">
            {logoSrc ? (
              <div className="w-11 h-11 rounded-xl overflow-hidden shadow-md group-hover:shadow-lg transition-all duration-300 flex-shrink-0 ring-2 ring-white/30">
                <img src={logoSrc} alt={activeSchool?.name} className="h-full w-full object-contain bg-white p-0.5" />
              </div>
            ) : (
              <div className="w-11 h-11 school-gradient rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-md group-hover:shadow-lg transition-all duration-300 flex-shrink-0">
                {activeSchool?.name?.[0] || 'S'}
              </div>
            )}
            <div className="hidden sm:block">
              <span className={`text-[15px] font-bold block leading-tight ${isLightHeader ? 'text-white' : 'text-slate-800'}`}>
                {activeSchool?.name || 'School Name'}
              </span>
              <span className={`text-[10px] font-semibold uppercase tracking-[0.15em] ${isLightHeader ? 'text-white/50' : 'text-slate-400'}`}>
                Since {activeSchool?.established_year || '2000'}
              </span>
            </div>
          </Link>

          {/* Desktop Nav */}
          <div className="hidden lg:flex items-center space-x-0.5">
            {navigation.map((item) => (
              <NavLink
                key={item.name}
                to={item.href}
                className={({ isActive }) => getNavActiveClass(isActive)}
              >
                {item.name}
              </NavLink>
            ))}
            <Link to="/admission" className="ml-3 btn-primary py-2.5 px-5 text-sm shadow-lg shadow-primary/25 hover:shadow-xl hover:shadow-primary/30 transition-all">
              Apply Now <ArrowRight className="ml-1.5 w-4 h-4" />
            </Link>
          </div>

          {/* Mobile Toggle */}
          <button
            className={`lg:hidden p-2.5 rounded-xl transition-colors ${isLightHeader ? 'text-white hover:bg-white/10' : 'text-slate-600 hover:bg-slate-100'}`}
            onClick={() => setIsMenuOpen(!isMenuOpen)}
          >
            {isMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
          </button>
        </nav>

        {/* Mobile Menu */}
        <div className={`lg:hidden absolute w-full bg-white/95 backdrop-blur-xl shadow-2xl border-t border-slate-100/50 transition-all duration-500 overflow-hidden ${isMenuOpen ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'}`}>
          <div className="p-5 space-y-1">
            {navigation.map((item) => (
              <NavLink
                key={item.name}
                to={item.href}
                onClick={() => setIsMenuOpen(false)}
                className={({ isActive }) =>
                  `block text-[15px] font-semibold py-3 px-5 rounded-2xl transition-all ${isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50'}`
                }
              >
                {item.name}
              </NavLink>
            ))}
            <Link
              to="/admission"
              onClick={() => setIsMenuOpen(false)}
              className="btn-primary w-full text-center mt-3 block"
            >
              Apply Now
            </Link>
          </div>
        </div>
      </header>

      {/* ── Main Content ── */}
      <main className="flex-grow">
        {children}
      </main>

      {/* ── Footer ── */}
      <footer className="relative overflow-hidden bg-slate-950 text-slate-400">
        {/* Mesh gradient overlay */}
        <div className="absolute inset-0 mesh-gradient-dark pointer-events-none"></div>

        {/* Grid pattern */}
        <div className="absolute inset-0 grid-pattern opacity-50 pointer-events-none"></div>

        {/* Decorative blobs */}
        <div className="absolute top-0 left-1/3 w-[500px] h-[500px] rounded-full school-gradient opacity-[0.03] filter blur-[100px]"></div>
        <div className="absolute bottom-0 right-1/4 w-[400px] h-[400px] rounded-full bg-purple-500 opacity-[0.02] filter blur-[80px]"></div>

        <div className="max-w-7xl mx-auto px-4 pt-20 pb-8 relative z-10">
          {/* CTA Strip */}
          <div className="school-gradient rounded-3xl p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6 mb-16 shadow-2xl shadow-primary/20 relative overflow-hidden">
            <div className="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20viewBox%3D%220%200%20200%20200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cfilter%20id%3D%22noise%22%3E%3CfeTurbulence%20type%3D%22fractalNoise%22%20baseFrequency%3D%220.65%22%20numOctaves%3D%223%22%20stitchTiles%3D%22stitch%22%2F%3E%3C%2Ffilter%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20filter%3D%22url(%23noise)%22%20opacity%3D%220.04%22%2F%3E%3C%2Fsvg%3E')] opacity-40"></div>
            <div className="relative z-10">
              <h3 className="text-2xl md:text-3xl font-extrabold text-white mb-2">Ready to Join Our Community?</h3>
              <p className="text-white/70 text-sm md:text-base">Begin your child's journey of excellence today.</p>
            </div>
            <Link to="/admission" className="bg-white text-slate-900 px-8 py-4 rounded-2xl font-bold hover:bg-slate-50 transition-all shadow-lg hover:shadow-xl flex-shrink-0 flex items-center gap-2 relative z-10">
              Start Application <ArrowRight className="w-5 h-5" />
            </Link>
          </div>

          {/* Footer Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
            <div>
              <div className="flex items-center space-x-3 mb-6">
                {logoSrc ? (
                  <img src={logoSrc} alt="" className="w-10 h-10 rounded-xl object-contain bg-white p-0.5 shadow-md" />
                ) : (
                  <div className="w-10 h-10 school-gradient rounded-xl flex items-center justify-center text-white font-bold shadow-md">
                    {activeSchool?.name?.[0] || 'S'}
                  </div>
                )}
                <span className="text-white font-bold text-lg">{activeSchool?.name || 'School'}</span>
              </div>
              <p className="text-sm leading-relaxed mb-6 text-slate-500">
                Empowering students with knowledge, skills, and values to become responsible global citizens and leaders of tomorrow.
              </p>
              <div className="flex space-x-2">
                {[
                  { icon: Facebook, href: '#' },
                  { icon: Instagram, href: '#' },
                  { icon: Youtube, href: '#' },
                ].map((social, i) => (
                  <a key={i} href={social.href} className="w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-primary flex items-center justify-center transition-all text-slate-500 hover:text-white hover:scale-105 hover:shadow-lg hover:shadow-primary/20">
                    <social.icon className="w-4 h-4" />
                  </a>
                ))}
              </div>
            </div>

            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-[0.15em]">Quick Links</h4>
              <ul className="space-y-3 text-sm">
                {[{ to: '/about', label: 'About Us' }, { to: '/academics', label: 'Academics' }, { to: '/teachers', label: 'Faculty' }, { to: '/gallery', label: 'Gallery' }, { to: '/notices', label: 'Notice Board' }].map(link => (
                  <li key={link.to}>
                    <Link to={link.to} className="hover:text-white transition-colors hover:translate-x-1 inline-flex items-center gap-1.5 group">
                      <span className="w-1.5 h-1.5 rounded-full bg-slate-700 group-hover:bg-primary transition-colors flex-shrink-0"></span>
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-[0.15em]">Academics</h4>
              <ul className="space-y-3 text-sm">
                {[{ to: '/admission', label: 'Admission Process' }, { to: '/results', label: 'Student Results' }, { to: '/academics', label: 'Curriculum' }].map(link => (
                  <li key={link.to}>
                    <Link to={link.to} className="hover:text-white transition-colors hover:translate-x-1 inline-flex items-center gap-1.5 group">
                      <span className="w-1.5 h-1.5 rounded-full bg-slate-700 group-hover:bg-primary transition-colors flex-shrink-0"></span>
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-[0.15em]">Contact Info</h4>
              <ul className="space-y-4 text-sm">
                {[
                  { icon: Phone, value: activeSchool?.phone || 'N/A' },
                  { icon: Mail, value: activeSchool?.email || 'N/A' },
                  { icon: MapPin, value: activeSchool?.address || 'School Address' },
                ].map((item, i) => (
                  <li key={i} className="flex items-start group">
                    <div className="w-9 h-9 rounded-xl bg-slate-800/80 group-hover:bg-primary flex items-center justify-center flex-shrink-0 mr-3 transition-all">
                      <item.icon className="w-4 h-4 text-slate-500 group-hover:text-white transition-colors" />
                    </div>
                    <span className="pt-2 group-hover:text-white transition-colors">{item.value}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          {/* Bottom Bar */}
          <div className="border-t border-slate-800/80 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-slate-600">
            <p>© {new Date().getFullYear()} {activeSchool?.name || 'School'}. All rights reserved.</p>
            <p className="mt-2 md:mt-0">Powered by <span className="text-primary font-bold">Edducare</span> SaaS Platform</p>
          </div>
        </div>
      </footer>

      {/* ── Scroll to Top ── */}
      <button
        onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
        className={`fixed bottom-8 right-8 z-50 w-12 h-12 school-gradient text-white rounded-2xl shadow-2xl shadow-primary/30 flex items-center justify-center transition-all duration-500 hover:scale-110 hover:shadow-primary/40 ${showScrollTop ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10 pointer-events-none'}`}
      >
        <ChevronUp className="w-5 h-5" />
      </button>
    </div>
  );
}
