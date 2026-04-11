import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useSchool } from '../context/SchoolContext';
import { DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import { BookOpen, Users, Clock, Layers, GraduationCap, Beaker, Palette, Code } from 'lucide-react';

export default function Academics() {
  const { activeSchool, API_BASE } = useSchool();
  const [classes, setClasses] = useState([]);

  useEffect(() => {
    const fetchClasses = async () => {
      try {
        const resp = await axios.get(`${API_BASE}/classes?school_id=${activeSchool.id}`);
        if (resp.data.success && resp.data.data?.length) setClasses(resp.data.data);
      } catch (err) { console.error(err); }
    };
    fetchClasses();
  }, [activeSchool?.id]);

  const displayClasses = classes.length > 0 ? classes : DUMMY_DATA.classes;

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7 },
    viewport: { once: true }
  };

  const programs = [
    { title: 'Primary', age: '5-11', icon: BookOpen, desc: 'Foundational literacy and social skills in a fun, nurturing environment.', color: 'from-blue-500 to-blue-600' },
    { title: 'Secondary', age: '11-16', icon: Layers, desc: 'Advanced concepts, critical thinking, and subject specialization.', color: 'from-emerald-500 to-emerald-600' },
    { title: 'Higher Secondary', age: '16-18', icon: GraduationCap, desc: 'Specialist subjects and career guidance for university entry.', color: 'from-purple-500 to-purple-600' },
    { title: 'Skill Based', age: 'All Ages', icon: Code, desc: 'Workshops in coding, public speaking, and leadership.', color: 'from-amber-500 to-amber-600' },
  ];

  return (
    <div className="bg-white">
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 text-center relative z-10">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Curriculum</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">Academic Programs</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto">
            A roadmap for lifelong learning and professional success.
          </motion.p>
        </div>
      </section>

      {/* Programs */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-24">
            {programs.map((program, idx) => (
              <motion.div key={idx} {...fadeInUp} transition={{ delay: idx * 0.1 }} className="card p-8 text-center group">
                <div className={`bg-gradient-to-br ${program.color} w-16 h-16 rounded-2xl flex items-center justify-center text-white mx-auto mb-6 shadow-lg group-hover:scale-110 transition-transform`}>
                  <program.icon className="w-8 h-8" />
                </div>
                <h4 className="text-xl font-bold text-slate-900 mb-2">{program.title}</h4>
                <p className="text-primary font-bold text-xs uppercase mb-3">Age: {program.age}</p>
                <p className="text-slate-500 text-sm leading-relaxed">{program.desc}</p>
              </motion.div>
            ))}
          </div>

          {/* Classes Table */}
          <motion.div {...fadeInUp}>
            <h2 className="section-title mb-8">Available Classes</h2>
            <div className="overflow-x-auto rounded-3xl border border-slate-200 shadow-sm">
              <table className="w-full text-left">
                <thead className="bg-slate-50 uppercase text-xs font-bold text-slate-500 tracking-wider">
                  <tr>
                    <th className="px-8 py-5">Class Name</th>
                    <th className="px-8 py-5">Level</th>
                    <th className="px-8 py-5">Room No.</th>
                    <th className="px-8 py-5 text-center">Capacity</th>
                    <th className="px-8 py-5 text-center">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {displayClasses.map((cls, i) => (
                    <tr key={cls.id || i} className="hover:bg-slate-50 transition-colors">
                      <td className="px-8 py-5 text-slate-900 font-bold">{cls.name}</td>
                      <td className="px-8 py-5 text-slate-600">{cls.grade_level}</td>
                      <td className="px-8 py-5 text-slate-600 font-mono">{cls.room_number || 'TBA'}</td>
                      <td className="px-8 py-5 text-slate-600 text-center">{cls.capacity}</td>
                      <td className="px-8 py-5 text-center">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase ${cls.current_students >= cls.capacity ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'
                          }`}>
                          {cls.current_students >= cls.capacity ? 'Full' : 'Open'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </motion.div>
        </div>
      </section>
    </div>
  );
}
