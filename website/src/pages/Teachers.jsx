import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useSchool } from '../context/SchoolContext';
import { resolveImagePath, DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import { Mail, Briefcase, Linkedin } from 'lucide-react';

export default function Teachers() {
  const { activeSchool, API_BASE, FILE_BASE } = useSchool();
  const [teachers, setTeachers] = useState([]);

  useEffect(() => {
    const fetchTeachers = async () => {
      try {
        const resp = await axios.get(`${API_BASE}/teachers?school_id=${activeSchool.id}`);
        if (resp.data.success && resp.data.data?.length) setTeachers(resp.data.data);
      } catch (err) { console.error(err); }
    };
    fetchTeachers();
  }, [activeSchool?.id]);

  const displayTeachers = teachers.length > 0 ? teachers : DUMMY_DATA.teachers;

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true }
  };

  return (
    <div className="bg-white min-h-screen">
      {/* Hero */}
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 text-center relative z-10">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Our Mentors</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">Expert Faculty</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            Highly qualified professionals dedicated to student success and academic excellence.
          </motion.p>
        </div>
      </section>

      {/* Teachers Grid */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            {displayTeachers.map((teacher, idx) => (
              <motion.div
                key={teacher.id || idx}
                {...fadeInUp}
                transition={{ delay: idx * 0.08 }}
                className="card group"
              >
                <div className="aspect-[4/5] overflow-hidden relative">
                  <img
                    src={teacher.profile_image
                      ? resolveImagePath(teacher.profile_image, FILE_BASE)
                      : `https://i.pravatar.cc/500?u=${teacher.email}`}
                    alt={teacher.first_name}
                    className="w-full h-full object-cover grayscale group-hover:grayscale-0 group-hover:scale-105 transition-all duration-700"
                  />
                  <div className="absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/80 to-transparent text-white">
                    <p className="text-xs font-bold uppercase tracking-widest opacity-80 mb-1">{teacher.teacher_type || 'Faculty'}</p>
                    <h4 className="text-xl font-bold">{teacher.first_name} {teacher.last_name}</h4>
                  </div>
                </div>
                <div className="p-6">
                  <div className="flex items-center text-primary font-bold text-sm mb-3">
                    <Briefcase className="w-4 h-4 mr-2" /> {teacher.qualification}
                  </div>
                  <p className="text-slate-500 text-sm leading-relaxed mb-4">
                    {teacher.experience_years ? `${teacher.experience_years} years` : 'Several years'} of dedicated teaching and mentoring.
                  </p>
                  <div className="flex space-x-3 pt-4 border-t border-slate-100">
                    <a href={`mailto:${teacher.email}`} className="w-9 h-9 rounded-xl bg-slate-50 hover:bg-primary hover:text-white flex items-center justify-center text-slate-400 transition-all">
                      <Mail className="w-4 h-4" />
                    </a>
                    <a href="#" className="w-9 h-9 rounded-xl bg-slate-50 hover:bg-primary hover:text-white flex items-center justify-center text-slate-400 transition-all">
                      <Linkedin className="w-4 h-4" />
                    </a>
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
