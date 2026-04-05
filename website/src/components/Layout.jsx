import React, { useState, useEffect } from 'react';
import { NavLink, Link, useLocation } from 'react-router-dom';
import { useSchool } from '../context/SchoolContext';
import { getTemplate } from '../templates/templateConfig';
import { resolveImagePath } from '../utils/helpers';
import { Menu, X, Phone, Mail, MapPin, ChevronUp, Facebook, Instagram, Youtube, ArrowRight } from 'lucide-react';

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
    const base = 'text-sm font-semibold transition-all duration-300 py-2 px-3';
    if (template.navStyle === 'pill') {
      return `${base} rounded-xl ${isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:text-primary'}`;
    }
    if (template.navStyle === 'underline') {
      return `${base} border-b-2 ${isActive ? 'border-primary text-primary' : 'border-transparent text-slate-600 hover:text-primary hover:border-primary/30'}`;
    }
    if (template.navStyle === 'glow') {
      return `${base} ${isActive ? 'text-white font-bold' : 'text-slate-300 hover:text-white'}`;
    }
    if (template.navStyle === 'block') {
      return `${base} ${isActive ? 'bg-white/20 text-white rounded-xl font-bold' : 'text-white/80 hover:text-white hover:bg-white/10 rounded-xl'}`;
    }
    return `${base} ${isActive ? 'text-primary' : 'text-slate-600 hover:text-primary'}`;
  };

  const headerBg = () => {
    if (template.headerStyle === 'dark') {
      return scrolled ? 'bg-slate-900/95 backdrop-blur-xl shadow-2xl' : 'bg-slate-900';
    }
    if (template.headerStyle === 'gradient') {
      return scrolled ? 'school-gradient shadow-2xl' : 'school-gradient';
    }
    if (template.headerStyle === 'transparent') {
      return scrolled ? 'bg-white/95 backdrop-blur-xl shadow-lg border-b border-slate-100' : 'bg-white';
    }
    // solid (classic)
    return scrolled ? 'bg-white shadow-xl' : 'bg-white shadow-md border-b-4 border-primary';
  };

  const isLightHeader = template.headerStyle === 'dark' || template.headerStyle === 'gradient';

  return (
    <div className={`flex flex-col min-h-screen template-${template.id}`}>
      {/* Top Info Bar */}
      <div className="school-gradient text-white py-2 relative z-[60]">
        <div className="max-w-7xl mx-auto px-4 flex justify-between items-center text-xs font-medium">
          <div className="flex items-center space-x-6">
            <a href={`tel:${activeSchool?.phone}`} className="flex items-center hover:opacity-80 transition-opacity">
              <Phone className="w-3 h-3 mr-1.5" /> {activeSchool?.phone || 'N/A'}
            </a>
            <a href={`mailto:${activeSchool?.email}`} className="hidden sm:flex items-center hover:opacity-80 transition-opacity">
              <Mail className="w-3 h-3 mr-1.5" /> {activeSchool?.email || 'N/A'}
            </a>
          </div>
          <div className="flex items-center space-x-4">
            <Link to="/admission" className="hover:opacity-80 transition-opacity">Admissions</Link>
            <span className="opacity-30">|</span>
            <Link to="/results" className="hover:opacity-80 transition-opacity">Results</Link>
          </div>
        </div>
      </div>

      {/* Main Header */}
      <header className={`sticky top-0 z-50 transition-all duration-500 ${headerBg()}`}>
        <nav className="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
          {/* Logo */}
          <Link to="/" className="flex items-center space-x-3 group">
            {logoSrc ? (
              <div className="w-12 h-12 rounded-xl overflow-hidden shadow-md group-hover:shadow-lg transition-shadow flex-shrink-0">
                <img src={logoSrc} alt={activeSchool?.name} className="h-full w-full object-contain bg-white p-0.5" />
              </div>
            ) : (
              <div className="w-12 h-12 school-gradient rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-md group-hover:shadow-lg transition-shadow flex-shrink-0">
                {activeSchool?.name?.[0] || 'S'}
              </div>
            )}
            <div className="hidden sm:block">
              <span className={`text-lg font-bold block leading-tight ${isLightHeader ? 'text-white' : 'text-slate-800'}`}>
                {activeSchool?.name || 'School Name'}
              </span>
              <span className={`text-[10px] font-medium uppercase tracking-widest ${isLightHeader ? 'text-white/60' : 'text-slate-400'}`}>
                Since {activeSchool?.established_year || '2000'}
              </span>
            </div>
          </Link>

          {/* Desktop Nav */}
          <div className="hidden lg:flex items-center space-x-1">
            {navigation.map((item) => (
              <NavLink
                key={item.name}
                to={item.href}
                className={({ isActive }) => getNavActiveClass(isActive)}
              >
                {item.name}
              </NavLink>
            ))}
            <Link to="/admission" className="btn-primary py-2.5 px-6 text-sm ml-4 shadow-lg">
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
        <div className={`lg:hidden absolute w-full bg-white shadow-2xl border-t transition-all duration-500 overflow-hidden ${isMenuOpen ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'}`}>
          <div className="p-6 space-y-2">
            {navigation.map((item) => (
              <NavLink
                key={item.name}
                to={item.href}
                onClick={() => setIsMenuOpen(false)}
                className={({ isActive }) => 
                  `block text-base font-semibold py-3 px-5 rounded-2xl transition-all ${isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50'}`
                }
              >
                {item.name}
              </NavLink>
            ))}
            <Link 
              to="/admission" 
              onClick={() => setIsMenuOpen(false)}
              className="btn-primary w-full text-center mt-4 block"
            >
              Apply Now
            </Link>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-grow">
        {children}
      </main>

      {/* Footer */}
      <footer className="bg-slate-950 text-slate-400 relative overflow-hidden">
        {/* Decorative gradient */}
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-96 rounded-full school-gradient opacity-[0.03] filter blur-3xl"></div>
        
        <div className="max-w-7xl mx-auto px-4 pt-20 pb-8 relative z-10">
          {/* Footer Top CTA */}
          <div className="relative -mt-32 mb-20">
            <div className="school-gradient rounded-[2.5rem] p-8 md:p-14 flex flex-col lg:flex-row items-center justify-between gap-10 shadow-2xl relative overflow-hidden">
              {/* Decorative background elements */}
              <div className="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl"></div>
              <div className="absolute bottom-0 left-0 w-48 h-48 bg-black/10 rounded-full -ml-10 -mb-10 blur-2xl"></div>
              
              <div className="relative z-10 text-center lg:text-left max-w-2xl">
                <h3 className="text-3xl md:text-4xl font-black text-white mb-4 leading-tight">Ready to begin the journey?</h3>
                <p className="text-white/80 text-lg font-medium">Join our thriving community of learners and leaders today. Secure your child's future with us.</p>
              </div>
              <div className="relative z-10 flex-shrink-0">
                <Link to="/admission" className="bg-white text-primary hover:bg-slate-50 px-10 py-5 rounded-2xl font-black text-lg transition-all shadow-xl hover:shadow-2xl hover:scale-105 active:scale-95 flex items-center group">
                  Start Application <ArrowRight className="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" />
                </Link>
              </div>
            </div>
          </div>

          {/* Footer Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
            <div>
              <div className="flex items-center space-x-3 mb-6">
                {logoSrc ? (
                  <img src={logoSrc} alt="" className="w-10 h-10 rounded-lg object-contain bg-white p-0.5" />
                ) : (
                  <div className="w-10 h-10 school-gradient rounded-lg flex items-center justify-center text-white font-bold">
                    {activeSchool?.name?.[0] || 'S'}
                  </div>
                )}
                <span className="text-white font-bold text-lg">{activeSchool?.name || 'School'}</span>
              </div>
              <p className="text-sm leading-relaxed mb-6 text-slate-500">
                Empowering students with knowledge, skills, and values to become responsible global citizens and leaders of tomorrow.
              </p>
              <div className="flex space-x-3">
                <a href="#" className="w-10 h-10 rounded-xl bg-slate-800 hover:bg-primary flex items-center justify-center transition-all text-slate-400 hover:text-white">
                  <Facebook className="w-4 h-4" />
                </a>
                <a href="#" className="w-10 h-10 rounded-xl bg-slate-800 hover:bg-primary flex items-center justify-center transition-all text-slate-400 hover:text-white">
                  <Instagram className="w-4 h-4" />
                </a>
                <a href="#" className="w-10 h-10 rounded-xl bg-slate-800 hover:bg-primary flex items-center justify-center transition-all text-slate-400 hover:text-white">
                  <Youtube className="w-4 h-4" />
                </a>
              </div>
            </div>
            
            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-widest">Quick Links</h4>
              <ul className="space-y-3 text-sm">
                {[{to:'/about', label:'About Us'}, {to:'/academics', label:'Academics'}, {to:'/teachers', label:'Faculty'}, {to:'/gallery', label:'Gallery'}, {to:'/notices', label:'Notice Board'}].map(link => (
                  <li key={link.to}>
                    <Link to={link.to} className="hover:text-white transition-colors hover:translate-x-1 inline-block">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-widest">Academics</h4>
              <ul className="space-y-3 text-sm">
                {[{to:'/admission', label:'Admission Process'}, {to:'/results', label:'Student Results'}, {to:'/academics', label:'Curriculum'}].map(link => (
                  <li key={link.to}>
                    <Link to={link.to} className="hover:text-white transition-colors hover:translate-x-1 inline-block">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h4 className="text-white font-bold mb-6 text-sm uppercase tracking-widest">Contact Info</h4>
              <ul className="space-y-4 text-sm">
                <li className="flex items-start group">
                  <div className="w-9 h-9 rounded-lg bg-slate-800 group-hover:bg-primary flex items-center justify-center flex-shrink-0 mr-3 transition-colors">
                    <Phone className="w-4 h-4 text-slate-400 group-hover:text-white" />
                  </div>
                  <span className="pt-2">{activeSchool?.phone || 'N/A'}</span>
                </li>
                <li className="flex items-start group">
                  <div className="w-9 h-9 rounded-lg bg-slate-800 group-hover:bg-primary flex items-center justify-center flex-shrink-0 mr-3 transition-colors">
                    <Mail className="w-4 h-4 text-slate-400 group-hover:text-white" />
                  </div>
                  <span className="pt-2">{activeSchool?.email || 'N/A'}</span>
                </li>
                <li className="flex items-start group">
                  <div className="w-9 h-9 rounded-lg bg-slate-800 group-hover:bg-primary flex items-center justify-center flex-shrink-0 mr-3 transition-colors">
                    <MapPin className="w-4 h-4 text-slate-400 group-hover:text-white" />
                  </div>
                  <span className="pt-2">{activeSchool?.address || 'School Address'}</span>
                </li>
              </ul>
            </div>
          </div>

          {/* Bottom Bar */}
          <div className="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-slate-600">
            <p>© {new Date().getFullYear()} {activeSchool?.name || 'School'}. All rights reserved.</p>
            <p className="mt-2 md:mt-0">Powered by <span className="text-primary font-bold">Edducare</span> SaaS Platform</p>
          </div>
        </div>
      </footer>

      {/* Scroll to Top */}
      <button
        onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
        className={`fixed bottom-8 right-8 z-50 w-12 h-12 school-gradient text-white rounded-2xl shadow-2xl flex items-center justify-center transition-all duration-500 hover:scale-110 ${showScrollTop ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10 pointer-events-none'}`}
      >
        <ChevronUp className="w-5 h-5" />
      </button>
    </div>
  );
}
