import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useSchool } from '../context/SchoolContext';
import { getTemplate } from '../templates/templateConfig';
import { resolveImagePath, DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import {
  ArrowRight, Users, BookOpen, Award, Bell,
  Calendar, CheckCircle, Quote, Phone, Mail, MapPin,
  GraduationCap, Sparkles, Star, TrendingUp, Clock, Trophy
} from 'lucide-react';
import { Link } from 'react-router-dom';

export default function Home() {
  const { activeSchool, theme, stats, API_BASE, FILE_BASE, FILE_BASE_NEW } = useSchool();
  const template = getTemplate(theme?.layout_style);
  const [notices, setNotices] = useState([]);
  const [classes, setClasses] = useState([]);
  const [teachers, setTeachers] = useState([]);
  const [gallery, setGallery] = useState([]);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [noticesResp, classesResp, teachersResp, galleryResp] = await Promise.all([
          axios.get(`${API_BASE}/announcements?school_id=${activeSchool.id}`),
          axios.get(`${API_BASE}/classes?school_id=${activeSchool.id}`),
          axios.get(`${API_BASE}/teachers?school_id=${activeSchool.id}`),
          axios.get(`${API_BASE}/gallery?school_id=${activeSchool.id}`)
        ]);

        if (noticesResp.data.success && noticesResp.data.data?.length) setNotices(noticesResp.data.data);
        if (classesResp.data.success && classesResp.data.data?.length) setClasses(classesResp.data.data.slice(0, 3));
        if (teachersResp.data.success && teachersResp.data.data?.length) setTeachers(teachersResp.data.data.slice(0, 4));
        if (galleryResp.data.success && galleryResp.data.data?.length) setGallery(galleryResp.data.data.slice(0, 8));
      } catch (error) {
        console.error("Home data fetch error:", error);
      }
    };
    fetchData();
  }, [activeSchool?.id]);

  // Use dummy data when API returns empty
  const displayNotices = notices.length > 0 ? notices : DUMMY_DATA.notices;
  const displayClasses = classes.length > 0 ? classes : DUMMY_DATA.classes;
  const displayTeachers = teachers.length > 0 ? teachers : DUMMY_DATA.teachers;
  const displayGallery = gallery.length > 0 ? gallery : DUMMY_DATA.gallery;
  const displayStats = stats?.length > 0 ? stats : DUMMY_DATA.stats;
  const displayEvents = DUMMY_DATA.events;
  const displayTestimonials = DUMMY_DATA.testimonials;

  const heroImage = theme?.hero_bg_image
    ? resolveImagePath(theme.hero_bg_image, FILE_BASE_NEW)
    : DUMMY_DATA.heroImages[0];

  const aboutImage = theme?.about_image
    ? resolveImagePath(theme.about_image, FILE_BASE_NEW)
    : DUMMY_DATA.aboutImage;

  const principalImage = theme?.principal_image
    ? resolveImagePath(theme.principal_image, FILE_BASE)
    : DUMMY_DATA.principalImage;

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true, margin: "-50px" }
  };

  const statIcons = [GraduationCap, Users, Clock, Trophy];

  return (
    <div className="overflow-x-hidden">

      {/* ═══════════════ HERO SECTION ═══════════════ */}
      <section className="relative min-h-[90vh] flex items-center overflow-hidden">
        {/* Background Image */}
        <div className="absolute inset-0 z-0">
          <img src={heroImage} className="w-full h-full object-cover" alt="Campus" />
          <div className="absolute inset-0 bg-gradient-to-r from-slate-950/80 via-slate-950/60 to-slate-950/30"></div>
          <div className="absolute inset-0 bg-gradient-to-t from-slate-950/50 to-transparent"></div>
        </div>

        {/* Animated Blobs */}
        <div className="blob w-96 h-96 top-20 right-20 animate-float"></div>
        <div className="blob w-64 h-64 bottom-20 left-20 animate-float" style={{ animationDelay: '3s' }}></div>

        <div className="max-w-7xl mx-auto px-4 relative z-10 w-full py-32">
          <div className="max-w-3xl">
            <motion.div
              initial={{ opacity: 0, y: 30 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.2 }}
            >
              <span className="inline-flex items-center px-5 py-2 rounded-full bg-white/10 backdrop-blur-md text-white font-semibold text-sm mb-8 border border-white/20">
                <Sparkles className="w-4 h-4 mr-2 text-yellow-400" />
                Welcome to {activeSchool?.name || 'Our School'}
              </span>
            </motion.div>

            <motion.h1
              initial={{ opacity: 0, y: 40 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.3 }}
              className="text-5xl sm:text-6xl lg:text-7xl font-extrabold mb-8 leading-[1.05] text-white"
            >
              Where Young Minds{' '}
              <span className="text-gradient">Discover</span>{' '}
              Their True Potential
            </motion.h1>

            <motion.p
              initial={{ opacity: 0, y: 40 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.4 }}
              className="text-lg md:text-xl text-slate-300 mb-12 leading-relaxed max-w-2xl"
            >
              Experience world-class education that nurtures creativity, builds character, and prepares students for a dynamic global future.
            </motion.p>

            <motion.div
              initial={{ opacity: 0, y: 40 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.5 }}
              className="flex flex-col sm:flex-row gap-4"
            >
              <Link to="/admission" className="btn-primary text-lg shadow-2xl">
                Start Enrollment <ArrowRight className="ml-2 w-5 h-5" />
              </Link>
              <Link to="/about" className="btn-ghost text-lg">
                Explore Campus
              </Link>
            </motion.div>
          </div>
        </div>

        {/* Bottom wave decoration */}
        <div className="absolute bottom-0 left-0 right-0 z-10">
          <svg viewBox="0 0 1440 100" className="w-full h-16 fill-white">
            <path d="M0,64L48,58.7C96,53,192,43,288,42.7C384,43,480,53,576,58.7C672,64,768,64,864,56C960,48,1056,32,1152,32C1248,32,1344,48,1392,56L1440,64L1440,100L0,100Z"></path>
          </svg>
        </div>
      </section>

      {/* ═══════════════ STATS SECTION ═══════════════ */}
      <section className="relative z-20 -mt-8 mb-8">
        <div className="max-w-6xl mx-auto px-4">
          <motion.div {...fadeInUp} className="bg-white rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-10">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
              {displayStats.map((stat, idx) => {
                const Icon = statIcons[idx % statIcons.length];
                return (
                  <div key={idx} className="text-center group cursor-default">
                    <div className="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4 group-hover:scale-110 group-hover:bg-primary/20 transition-all duration-300">
                      <Icon className="w-6 h-6 text-primary" />
                    </div>
                    <div className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-1">{stat.value}{!String(stat.value).includes('%') && '+'}</div>
                    <div className="text-slate-500 font-medium text-xs sm:text-sm uppercase tracking-wider">{stat.label}</div>
                  </div>
                );
              })}
            </div>
          </motion.div>
        </div>
      </section>

      {/* ═══════════════ ABOUT SECTION ═══════════════ */}
      <section className="py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-24 items-center">
            <motion.div {...fadeInUp} className="relative">
              <div className="relative z-10 overflow-hidden rounded-3xl shadow-2xl">
                <img src={aboutImage} alt="Our School" className="w-full aspect-[4/5] object-cover hover:scale-105 transition-transform duration-700" />
              </div>
              {/* Decorative elements */}
              <div className="absolute -bottom-6 -right-6 w-48 h-48 school-gradient rounded-3xl opacity-10 -z-0"></div>
              <div className="absolute -top-6 -left-6 w-32 h-32 bg-primary/20 rounded-full filter blur-2xl -z-0"></div>
              {/* Experience badge */}
              <div className="absolute -bottom-4 -right-4 bg-white rounded-2xl shadow-xl p-6 z-20 border border-slate-100">
                <div className="text-3xl font-extrabold text-primary mb-1">{activeSchool?.established_year ? new Date().getFullYear() - parseInt(activeSchool.established_year) : '25'}+</div>
                <div className="text-xs font-bold text-slate-500 uppercase tracking-widest">Years of Excellence</div>
              </div>
            </motion.div>

            <motion.div {...fadeInUp} transition={{ delay: 0.2 }}>
              <span className="section-label">About Our Institution</span>
              <h2 className="section-title">
                Nurturing Minds,{' '}
                <span className="text-gradient">Empowering Leaders</span>
              </h2>
              <p className="section-subtitle mb-8">
                {theme?.about_text || "Founded with a vision to provide quality education, our school has been a cornerstone of academic excellence. We combine traditional values with modern pedagogical approaches to ensure holistic development of every student."}
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-10">
                {[
                  { icon: Award, title: 'National Accreditation', desc: 'Recognized excellence' },
                  { icon: CheckCircle, title: 'Quality Education', desc: 'CBSE/State curriculum' },
                  { icon: Users, title: 'Expert Faculty', desc: 'Qualified educators' },
                  { icon: TrendingUp, title: 'Proven Results', desc: '98%+ pass rate' },
                ].map((item, i) => (
                  <div key={i} className="flex items-start group">
                    <div className="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0 mr-4 group-hover:bg-primary group-hover:text-white transition-all">
                      <item.icon className="w-5 h-5 text-primary group-hover:text-white transition-colors" />
                    </div>
                    <div>
                      <h4 className="font-bold text-slate-800 text-sm">{item.title}</h4>
                      <p className="text-xs text-slate-500">{item.desc}</p>
                    </div>
                  </div>
                ))}
              </div>

              <Link to="/about" className="btn-primary">
                Learn More <ArrowRight className="ml-2 w-5 h-5" />
              </Link>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ═══════════════ PRINCIPAL'S MESSAGE ═══════════════ */}
      <section className="py-24 bg-slate-50 relative overflow-hidden">
        <div className="blob w-80 h-80 -top-20 -right-20"></div>
        <div className="max-w-7xl mx-auto px-4 relative z-10">
          <motion.div {...fadeInUp} className="bg-white rounded-[2.5rem] shadow-xl p-8 md:p-16 border border-slate-100 flex flex-col md:flex-row items-center gap-12">
            <div className="w-48 h-48 md:w-64 md:h-64 flex-shrink-0 relative">
              <img src={principalImage} className="w-full h-full object-cover rounded-3xl shadow-lg" alt="Principal" />
              <div className="absolute -bottom-3 -right-3 bg-primary text-white p-3 rounded-xl shadow-lg">
                <Quote className="w-6 h-6" />
              </div>
            </div>
            <div>
              <span className="section-label">From the Principal's Desk</span>
              <h3 className="text-3xl font-extrabold text-slate-900 mb-6 leading-tight">Developing Responsible Global Citizens</h3>
              <p className="text-lg text-slate-600 italic leading-relaxed mb-8">
                "{theme?.principal_message || "Education is not just about academic performance; it's about building character, fostering curiosity, and developing skills that last a lifetime. We invite you to be part of our journey in shaping the future."}"
              </p>
              <div>
                <p className="font-bold text-slate-900 text-xl">{activeSchool?.principal_name || "The Principal"}</p>
                <p className="text-slate-500 font-medium">Principal, {activeSchool?.name || 'Our School'}</p>
              </div>
            </div>
          </motion.div>
        </div>
      </section>

      {/* ═══════════════ NOTICES & EVENTS ═══════════════ */}
      <section className="py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-16">
          {/* Notices */}
          <motion.div {...fadeInUp}>
            <div className="flex justify-between items-end mb-8">
              <div>
                <span className="section-label">Stay Updated</span>
                <h3 className="text-3xl font-extrabold text-slate-900">Notice Board</h3>
              </div>
              <Link to="/notices" className="text-primary font-bold flex items-center text-sm hover:underline">
                View All <ArrowRight className="ml-1.5 w-4 h-4" />
              </Link>
            </div>
            <div className="bg-slate-50 rounded-3xl p-5 space-y-4 max-h-[480px] overflow-y-auto custom-scrollbar border border-slate-100">
              {displayNotices.map((notice) => (
                <div key={notice.id} className="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:border-primary/20 transition-all group">
                  <div className="flex items-center space-x-3 mb-2">
                    <span className={`px-2.5 py-0.5 rounded-lg text-[10px] font-bold uppercase ${notice.priority === 'high' || notice.priority === 'urgent' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'
                      }`}>
                      {notice.priority || 'Normal'}
                    </span>
                    <span className="text-slate-400 text-xs flex items-center">
                      <Calendar className="w-3 h-3 mr-1" /> {new Date(notice.created_at).toLocaleDateString()}
                    </span>
                  </div>
                  <h4 className="font-bold text-slate-800 text-base mb-1.5 group-hover:text-primary transition-colors">{notice.title}</h4>
                  <p className="text-slate-500 text-sm line-clamp-2">{notice.content}</p>
                </div>
              ))}
            </div>
          </motion.div>

          {/* Events */}
          <motion.div {...fadeInUp} transition={{ delay: 0.2 }}>
            <div className="mb-8">
              <span className="section-label">Mark Your Calendar</span>
              <h3 className="text-3xl font-extrabold text-slate-900">Upcoming Events</h3>
            </div>
            <div className="space-y-5">
              {displayEvents.map((event, i) => (
                <div key={i} className="flex gap-5 items-center p-5 rounded-2xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-100 group">
                  <div className="w-20 h-20 school-gradient text-white flex flex-col items-center justify-center rounded-2xl shadow-lg flex-shrink-0 group-hover:scale-105 transition-transform">
                    <span className="text-2xl font-bold">{event.date}</span>
                    <span className="text-[10px] font-bold uppercase opacity-80">{event.month}</span>
                  </div>
                  <div>
                    <span className="text-primary font-bold text-xs uppercase tracking-widest mb-0.5 block">{event.type}</span>
                    <h4 className="text-xl font-extrabold text-slate-800">{event.title}</h4>
                  </div>
                </div>
              ))}
            </div>
          </motion.div>
        </div>
      </section>

      {/* ═══════════════ FEATURED CLASSES ═══════════════ */}
      <section className="py-24 bg-slate-950 text-white relative overflow-hidden">
        <div className="blob w-96 h-96 -top-48 -right-48"></div>
        <div className="blob w-64 h-64 bottom-0 left-0" style={{ animationDelay: '2s' }}></div>
        <div className="max-w-7xl mx-auto px-4 relative z-10">
          <div className="text-center mb-16">
            <span className="section-label">Our Academics</span>
            <h3 className="text-4xl md:text-5xl font-extrabold mb-4 text-white">Featured Programs</h3>
            <p className="text-slate-400 max-w-2xl mx-auto text-lg">Providing a rigorous curriculum that caters to individual learning styles and interests.</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {displayClasses.map((cls, idx) => (
              <motion.div
                key={cls.id || idx}
                {...fadeInUp}
                transition={{ delay: idx * 0.1 }}
                className="card-dark p-8 group relative overflow-hidden"
              >
                <div className="absolute -top-12 -right-12 w-28 h-28 bg-primary opacity-10 rounded-full group-hover:scale-[4] transition-all duration-700"></div>
                <div className="relative z-10">
                  <div className="bg-primary/20 p-4 rounded-2xl w-fit mb-6 text-primary group-hover:bg-primary group-hover:text-white transition-all">
                    <BookOpen className="w-7 h-7" />
                  </div>
                  <h4 className="text-2xl font-extrabold mb-3 text-white">{cls.name}</h4>
                  <p className="text-slate-400 mb-6 text-sm">
                    Capacity: {cls.capacity} Students | Level: {cls.grade_level}
                  </p>
                  <Link to="/academics" className="flex items-center text-primary font-bold text-sm uppercase tracking-wider group-hover:text-white transition-colors">
                    View Curriculum <ArrowRight className="ml-2 w-4 h-4" />
                  </Link>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════ FACULTY ═══════════════ */}
      <section className="py-24 bg-white relative overflow-hidden">
        <div className="blob w-96 h-96 -bottom-48 -left-48 opacity-10"></div>
        <div className="max-w-7xl mx-auto px-4 relative z-10">
          <div className="text-center mb-20">
            <motion.div {...fadeInUp}>
              <span className="section-label">Expert Mentors</span>
              <h3 className="section-title">Our Distinguished Faculty</h3>
              <p className="section-subtitle mx-auto">Learn from passionate educators dedicated to excellence.</p>
            </motion.div>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">
            {displayTeachers.map((teacher, idx) => (
              <motion.div key={teacher.id || idx} {...fadeInUp} transition={{ delay: idx * 0.1 }} className="text-center group">
                <div className="mb-6 relative overflow-hidden rounded-[2rem] aspect-square bg-slate-100 shadow-lg group-hover:shadow-primary/20 transition-all duration-500">
                  <img
                    src={teacher.profile_image
                      ? resolveImagePath(teacher.profile_image, FILE_BASE)
                      : `https://i.pravatar.cc/500?u=${teacher.email}`}
                    alt={teacher.first_name}
                    className="w-full h-full object-cover transition-all duration-700 group-hover:scale-110"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-primary/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </div>
                <h4 className="text-xl font-bold text-slate-800 mb-1">{teacher.first_name} {teacher.last_name}</h4>
                <p className="text-primary font-semibold text-xs uppercase tracking-[0.2em] mb-2">{teacher.teacher_type || 'Faculty'}</p>
                <div className="h-0.5 w-10 bg-primary/20 mx-auto group-hover:w-20 transition-all duration-300"></div>
              </motion.div>
            ))}
          </div>
          <div className="text-center mt-20">
            <Link to="/teachers" className="btn-primary">
              View All Faculty <ArrowRight className="ml-2 w-5 h-5" />
            </Link>
          </div>
        </div>
      </section>

      {/* ═══════════════ GALLERY ═══════════════ */}
      <section className="py-24 bg-slate-50">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
            <motion.div {...fadeInUp}>
              <span className="section-label">Campus Moments</span>
              <h3 className="section-title mb-0">Life at {activeSchool?.name || 'Our School'}</h3>
            </motion.div>
            <Link to="/gallery" className="btn-primary py-3 px-6 text-sm">Visit Full Gallery</Link>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {displayGallery.slice(0, 8).map((item, i) => (
              <motion.div
                key={item.id || i}
                {...fadeInUp}
                transition={{ delay: i * 0.05 }}
                className={`overflow-hidden rounded-2xl group cursor-pointer relative ${i === 0 || i === 5 ? 'md:col-span-2 md:row-span-2' : ''}`}
              >
                <div className="aspect-square w-full h-full">
                  <img
                    src={resolveImagePath(item.image_path, FILE_BASE) || item.image_path}
                    alt={item.caption}
                    className="w-full h-full object-cover group-hover:scale-110 transition-all duration-700"
                  />
                </div>
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 flex items-end p-6">
                  <div className="text-white">
                    <p className="text-xs font-bold uppercase tracking-widest opacity-80 mb-1">{item.category}</p>
                    <p className="font-bold text-sm">{item.caption}</p>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════ TESTIMONIALS ═══════════════ */}
      <section className="py-24 bg-white">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <motion.div {...fadeInUp}>
            <span className="section-label">Voices of Trust</span>
            <h3 className="section-title mx-auto">What Parents Say</h3>
          </motion.div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mt-16">
            {displayTestimonials.map((t, i) => (
              <motion.div key={i} {...fadeInUp} transition={{ delay: i * 0.1 }} className="bg-slate-50 p-10 rounded-3xl relative text-left group hover:bg-white hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 border border-transparent hover:border-slate-100">
                <Quote className="w-10 h-10 text-primary/10 mb-6 group-hover:text-primary/20 transition-colors" />
                <p className="text-slate-600 mb-8 italic leading-relaxed">"{t.content}"</p>
                <div className="flex items-center">
                  <div className="w-12 h-12 school-gradient text-white flex items-center justify-center rounded-full font-bold mr-4 shadow-lg">{t.name[0]}</div>
                  <div>
                    <h4 className="font-bold text-slate-900">{t.name}</h4>
                    <p className="text-xs font-medium text-slate-400 uppercase tracking-wider">{t.role}</p>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
