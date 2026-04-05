import React from 'react';
import { useSchool } from '../context/SchoolContext';
import { resolveImagePath, DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import { Award, Zap, Heart, CheckCircle, Target, Lightbulb, Globe, Users } from 'lucide-react';

export default function About() {
  const { activeSchool, theme, FILE_BASE } = useSchool();

  const aboutImage = theme?.about_image 
    ? resolveImagePath(theme.about_image, FILE_BASE) 
    : DUMMY_DATA.aboutImage;

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true }
  };

  const values = [
    { title: 'Excellence', desc: 'We strive for perfection in every academic and extracurricular endeavor.', icon: Award, color: 'from-blue-500 to-blue-600' },
    { title: 'Integrity', desc: 'Honesty, ethics, and transparency are the pillars of our institution.', icon: CheckCircle, color: 'from-emerald-500 to-emerald-600' },
    { title: 'Innovation', desc: 'Embracing cutting-edge methods for future-ready education.', icon: Lightbulb, color: 'from-amber-500 to-amber-600' },
    { title: 'Community', desc: 'Building a supportive ecosystem of students, teachers, and parents.', icon: Users, color: 'from-purple-500 to-purple-600' },
    { title: 'Global Vision', desc: 'Preparing students with a worldview and international perspective.', icon: Globe, color: 'from-cyan-500 to-cyan-600' },
    { title: 'Empowerment', desc: 'Nurturing self-confidence and leadership in every student.', icon: Target, color: 'from-rose-500 to-rose-600' },
  ];

  return (
    <div className="bg-white">
      {/* Hero */}
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="absolute inset-0 z-0">
          <img src="https://images.unsplash.com/photo-1546410531-bb4caa1b424d?q=80&w=2071&auto=format&fit=crop" className="w-full h-full object-cover opacity-15" alt="" />
        </div>
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 relative z-10 text-center">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Our Story</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">About Our Institution</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            Dedicated to academic excellence and holistic development since {activeSchool?.established_year || 'many years'}.
          </motion.p>
        </div>
      </section>

      {/* History */}
      <section className="py-28">
        <div className="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
          <motion.div {...fadeInUp}>
            <span className="section-label">Our Legacy</span>
            <h2 className="section-title">Shaping Futures Through Quality Education</h2>
            <p className="text-lg text-slate-600 mb-6 leading-relaxed">
              {theme?.about_text || "Our school was founded on the principles of excellence and accessibility. We believe that every child has the potential to become a leader if provided with the right environment and guidance."}
            </p>
            <p className="text-lg text-slate-600 mb-10 leading-relaxed">
              With a focus on STEM, arts, and humanities, we provide a balanced curriculum that prepares students for the challenges of the 21st century.
            </p>
            <div className="grid grid-cols-2 gap-6">
              {[
                { icon: Award, label: 'National Accreditation' },
                { icon: Zap, label: 'Modern Campus' },
                { icon: Heart, label: 'Holistic Growth' },
                { icon: Globe, label: 'Global Network' },
              ].map((item, i) => (
                <div key={i} className="flex items-center space-x-3">
                  <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                    <item.icon className="w-5 h-5" />
                  </div>
                  <span className="text-slate-800 font-bold text-sm">{item.label}</span>
                </div>
              ))}
            </div>
          </motion.div>
          
          <motion.div {...fadeInUp} transition={{ delay: 0.2 }} className="relative">
            <img src={aboutImage} className="rounded-3xl shadow-2xl relative z-10 w-full" alt="School" />
            <div className="absolute -bottom-8 -left-8 w-56 h-56 school-gradient rounded-3xl opacity-10 z-0"></div>
          </motion.div>
        </div>
      </section>

      {/* Values */}
      <section className="py-28 bg-slate-50">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <span className="section-label">Values that Define Us</span>
          <h3 className="section-title mx-auto">Our Core Values</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-16">
            {values.map((value, i) => (
              <motion.div key={i} {...fadeInUp} transition={{ delay: i * 0.08 }} className="bg-white p-10 rounded-3xl shadow-sm border border-slate-100 hover:-translate-y-2 hover:shadow-xl transition-all text-left group">
                <div className={`bg-gradient-to-br ${value.color} w-16 h-16 rounded-2xl flex items-center justify-center text-white mb-6 shadow-lg group-hover:scale-110 transition-transform`}>
                  <value.icon className="w-8 h-8" />
                </div>
                <h4 className="text-xl font-extrabold text-slate-900 mb-3">{value.title}</h4>
                <p className="text-slate-500 leading-relaxed">{value.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
