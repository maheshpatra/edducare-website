import React from 'react';
import { motion } from 'framer-motion';
import { Shield, Zap, Heart, Globe, Users, Trophy } from 'lucide-react';

export default function About() {
   const fadeInUp = {
      initial: { opacity: 0, y: 30 },
      whileInView: { opacity: 1, y: 0 },
      transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
      viewport: { once: true }
   };

   return (
      <div className="pt-20 bg-white">
         {/* Hero */}
         <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
            <div className="absolute inset-0 z-0 opacity-20 mesh-gradient"></div>
            <div className="max-w-7xl mx-auto px-4 relative z-10 text-center">
               <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }}>
                  <span className="section-label">Our Philosophy</span>
                  <h1 className="text-5xl md:text-7xl font-black mb-8 tracking-tighter italic">
                     Built for <span className="text-gradient">Educators</span>, <br />by Visionaries.
                  </h1>
                  <p className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed font-medium">
                     We believe that education is the world's most important engine. Our mission is to keep it running smoothly with world-class technology.
                  </p>
               </motion.div>
            </div>
         </section>

         {/* Story Section */}
         <section className="py-32">
            <div className="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
               <motion.div {...fadeInUp}>
                  <span className="section-label">The EdduCare Story</span>
                  <h2 className="section-title">Revolutionizing Institutional Management</h2>
                  <p className="text-lg text-slate-600 mb-8 leading-relaxed font-medium">
                     EdduCare Cloud started with a simple observation: most school management tools were outdated, clunky, and difficult to use. We set out to build a platform that feels like modern consumer software but handles the complex requirements of an educational institution.
                  </p>
                  <div className="grid grid-cols-2 gap-8">
                     {[
                        { label: 'Founded', value: '2018' },
                        { label: 'Platform Uptime', value: '99.99%' },
                        { label: 'User Satisfaction', value: '4.9/5' },
                        { label: 'Data Security', value: 'Bank-Grade' },
                     ].map((stat, i) => (
                        <div key={i}>
                           <p className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">{stat.label}</p>
                           <p className="text-3xl font-black text-slate-900">{stat.value}</p>
                        </div>
                     ))}
                  </div>
               </motion.div>
               <motion.div
                  {...fadeInUp}
                  transition={{ delay: 0.2 }}
                  className="relative"
               >
                  <div className="aspect-square rounded-[4rem] overflow-hidden shadow-3xl bg-slate-100">
                     <img
                        src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop"
                        className="w-full h-full object-cover grayscale hover:grayscale-0 transition-all duration-700"
                        alt="Our Team"
                     />
                  </div>
                  <div className="absolute -bottom-10 -left-10 bg-primary p-12 rounded-[3rem] text-white shadow-3xl hidden md:block">
                     <Heart className="w-12 h-12 mb-4" />
                     <p className="text-xl font-black leading-tight">Driven by <br />Passion.</p>
                  </div>
               </motion.div>
            </div>
         </section>

         {/* Values */}
         <section className="py-32 bg-slate-50">
            <div className="max-w-7xl mx-auto px-4 text-center">
               <h2 className="section-title mx-auto">Values that Drive Us</h2>
               <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mt-20">
                  {[
                     { icon: Shield, title: 'Absolute Security', desc: 'We treat student and school data with the highest level of encryption and privacy standards.' },
                     { icon: Zap, title: 'Constant Innovation', desc: 'We release weekly updates to ensure our partners always have the latest tools at their fingertips.' },
                     { icon: Globe, label: 'Global Accessibility', desc: 'Our cloud architecture ensures seamless access from any corner of the world, on any device.' },
                  ].map((val, i) => (
                     <div key={i} className="bg-white p-12 rounded-[3rem] border border-slate-100 shadow-sm hover:shadow-2xl transition-all text-left">
                        <div className="w-16 h-16 bg-blue-50 text-primary rounded-2xl flex items-center justify-center mb-8">
                           <val.icon className="w-8 h-8" />
                        </div>
                        <h4 className="text-2xl font-black text-slate-900 mb-4">{val.title}</h4>
                        <p className="text-slate-500 font-medium leading-relaxed">{val.desc}</p>
                     </div>
                  ))}
               </div>
            </div>
         </section>
      </div>
   );
}
