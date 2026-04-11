import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useSchool } from '../context/SchoolContext';
import { DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import { Calendar, Bell, FileText } from 'lucide-react';

export default function Notices() {
  const { activeSchool, API_BASE } = useSchool();
  const [notices, setNotices] = useState([]);

  useEffect(() => {
    const fetchNotices = async () => {
      try {
        const resp = await axios.get(`${API_BASE}/announcements?school_id=${activeSchool.id}`);
        if (resp.data.success && resp.data.data?.length) setNotices(resp.data.data);
      } catch (err) { console.error(err); }
    };
    fetchNotices();
  }, [activeSchool?.id]);

  const displayNotices = notices.length > 0 ? notices : DUMMY_DATA.notices;

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true }
  };

  return (
    <div className="bg-white min-h-screen">
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 text-center relative z-10">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Official Updates</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">Notice Board</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            Stay updated with the latest announcements and important information.
          </motion.p>
        </div>
      </section>

      <section className="py-24">
        <div className="max-w-4xl mx-auto px-4 space-y-8">
          {displayNotices.map((notice, i) => (
            <motion.div
              key={notice.id || i}
              {...fadeInUp}
              transition={{ delay: i * 0.08 }}
              className="card p-8 sm:p-10 flex flex-col md:flex-row gap-8 group"
            >
              <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl school-gradient text-white flex flex-col items-center justify-center flex-shrink-0 shadow-lg">
                <span className="text-2xl font-bold font-mono">{String(i + 1).padStart(2, '0')}</span>
                <Bell className="w-4 h-4 opacity-70" />
              </div>
              <div className="flex-grow">
                <div className="flex flex-wrap items-center gap-3 mb-3">
                  <span className={`px-3 py-1 rounded-lg text-xs font-bold uppercase ${notice.priority === 'urgent' || notice.priority === 'high' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'
                    }`}>
                    {notice.priority || 'Normal'}
                  </span>
                  <span className="text-slate-400 text-sm font-medium flex items-center">
                    <Calendar className="w-4 h-4 mr-1.5" />
                    {new Date(notice.created_at).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })}
                  </span>
                </div>
                <h3 className="text-2xl font-extrabold text-slate-900 group-hover:text-primary transition-colors mb-3">{notice.title}</h3>
                <p className="text-slate-600 leading-relaxed">{notice.content}</p>
              </div>
            </motion.div>
          ))}
          {displayNotices.length === 0 && (
            <div className="text-center py-24 text-slate-400">
              <FileText className="w-16 h-16 mx-auto mb-4 opacity-30" />
              <p className="text-lg">No notices available at this time.</p>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
