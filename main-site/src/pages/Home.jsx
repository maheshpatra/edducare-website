import React from 'react';
import { motion } from 'framer-motion';
import {
  ArrowRight, Shield, Zap, Link as LinkIcon,
  Users, BookOpen, Clock, Heart, Sparkles,
  Smartphone, BarChart3, Cloud
} from 'lucide-react';
import { Link } from 'react-router-dom';

export default function Home() {
  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.8, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true }
  };

  const features = [
    {
      title: "Smart Attendance",
      desc: "Automated attendance tracking for students and staff with real-time notifications.",
      icon: Clock,
      color: "bg-blue-50 text-blue-500"
    },
    {
      title: "Fee Management",
      desc: "Simplify fee collection with online payments, automated invoicing and receipts.",
      icon: BarChart3,
      color: "bg-green-50 text-green-500"
    },
    {
      title: "LMS & Academics",
      desc: "Manage classes, subjects, exams and results seamlessly in one central place.",
      icon: BookOpen,
      color: "bg-purple-50 text-purple-500"
    },
    {
      title: "Admin Dashboard",
      desc: "Comprehensive analytics and reporting for school administrators and principals.",
      icon: Shield,
      color: "bg-orange-50 text-orange-500"
    },
    {
      title: "Parent Portal",
      desc: "Give parents direct access to their child's progress, attendance and announcements.",
      icon: Users,
      color: "bg-rose-50 text-rose-500"
    },
    {
      title: "Cloud Infrastructure",
      desc: "Secure, reliable and accessible from anywhere on any device with 99.9% uptime.",
      icon: Cloud,
      color: "bg-cyan-50 text-cyan-500"
    }
  ];

  return (
    <div className="pt-20">
      {/* Hero Section */}
      <section className="relative min-h-[90vh] flex items-center overflow-hidden px-4">
        <div className="absolute inset-0 z-0 opacity-40 mesh-gradient"></div>
        <div className="absolute inset-0 z-0 grid-pattern opacity-30"></div>

        {/* Blobs */}
        <div className="blob w-96 h-96 top-20 right-20 animate-float opacity-30"></div>
        <div className="blob w-64 h-64 bottom-20 left-20 animate-float delay-1000 opacity-20"></div>

        <div className="max-w-7xl mx-auto w-full relative z-10 py-20">
          <div className="grid lg:grid-cols-2 gap-16 items-center">
            <motion.div
              initial={{ opacity: 0, x: -50 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.8 }}
            >
              <div className="inline-flex items-center px-4 py-2 rounded-full bg-blue-50 text-primary font-bold text-xs uppercase tracking-widest mb-8 border border-blue-100">
                <Sparkles className="w-4 h-4 mr-2" />
                The Future of School Management
              </div>
              <h1 className="text-6xl md:text-8xl font-black text-slate-900 leading-[0.9] mb-8 tracking-tighter">
                Manage Your <br />
                <span className="text-gradient">School</span> in <br />
                The Cloud.
              </h1>
              <p className="text-xl text-slate-500 mb-12 max-w-xl leading-relaxed font-medium">
                EdduCare is a secure, all-in-one platform designed to streamline administration, engage parents, and empower teachers.
              </p>
              <div className="flex flex-col sm:flex-row gap-5">
                <Link to="/contact" className="btn-primary">
                  Free Trial Registration<ArrowRight className="ml-2 w-5 h-5" />
                </Link>
                <Link to="/pricing" className="btn-outline">
                  View Plans
                </Link>
              </div>

              <div className="mt-16 flex items-center space-x-8 opacity-60">
                <div className="text-center">
                  <div className="text-2xl font-black text-slate-900 uppercase">500+</div>
                  <div className="text-xs font-bold text-slate-500 uppercase tracking-widest">Schools</div>
                </div>
                <div className="w-px h-10 bg-slate-200"></div>
                <div className="text-center">
                  <div className="text-2xl font-black text-slate-900 uppercase">100k+</div>
                  <div className="text-xs font-bold text-slate-500 uppercase tracking-widest">Students</div>
                </div>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 1 }}
              className="relative hidden lg:block"
            >
              {/* Decorative elements */}
              <div className="absolute -top-10 -right-10 w-40 h-40 bg-accent/20 rounded-full blur-3xl animate-pulse"></div>

              <div className="relative z-10 bg-slate-900 rounded-[2.5rem] p-4 shadow-3xl transform rotate-2 hover:rotate-0 transition-all duration-700">
                <div className="bg-slate-800 rounded-[2rem] overflow-hidden aspect-video relative">
                  <img
                    src="https://images.unsplash.com/photo-1531403009284-440f080d1e12?q=80&w=2070&auto=format&fit=crop"
                    className="w-full h-full object-cover opacity-80"
                    alt="Platform Dashboard"
                  />
                  <div className="absolute inset-0 flex items-center justify-center">
                    <div className="w-20 h-20 bg-white/10 backdrop-blur-xl rounded-full flex items-center justify-center border border-white/20 hover:scale-110 transition-all cursor-pointer">
                      <Zap className="w-10 h-10 text-white" />
                    </div>
                  </div>
                </div>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* Social Proof Strip */}
      <section className="py-20 border-y border-slate-100 bg-slate-50 relative overflow-hidden">
        <div className="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-12 opacity-40">
          <Smartphone className="w-12 h-12" />
          <BarChart3 className="w-12 h-12" />
          <Cloud className="w-12 h-12" />
          <Shield className="w-12 h-12" />
          <Zap className="w-12 h-12" />
          <Users className="w-12 h-12" />
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-32 bg-white">
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-24">
            <motion.div {...fadeInUp}>
              <span className="section-label">Everything you need</span>
              <h2 className="section-title mx-auto text-center">Comprehensive Tools for <br />Modern Schools</h2>
              <p className="text-xl text-slate-500 max-w-2xl mx-auto font-medium">
                Ditch the paperwork and spreadsheets. EdduCare brings all your school operations into one intuitive platform.
              </p>
            </motion.div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {features.map((f, i) => (
              <motion.div
                key={i}
                {...fadeInUp}
                transition={{ delay: i * 0.1 }}
                className="p-10 rounded-[2.5rem] bg-white border border-slate-100 hover:border-primary/20 hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group"
              >
                <div className={`w-16 h-16 rounded-2xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform ${f.color}`}>
                  <f.icon className="w-8 h-8" />
                </div>
                <h3 className="text-2xl font-black text-slate-900 mb-4">{f.title}</h3>
                <p className="text-slate-500 leading-relaxed font-medium">{f.desc}</p>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4">
          <motion.div
            {...fadeInUp}
            className="bg-slate-900 rounded-[3rem] p-12 md:p-24 overflow-hidden relative"
          >
            <div className="absolute top-0 right-0 w-1/3 h-full bg-primary/20 -skew-x-12"></div>
            <div className="relative z-10 max-w-2xl">
              <h2 className="text-4xl md:text-6xl font-black text-white mb-8 tracking-tighter">Ready to digitize your institution?</h2>
              <p className="text-xl text-slate-400 mb-12 font-medium">Join 500+ schools that trust EdduCare to manage their daily operations and student growth.</p>
              <div className="flex flex-col sm:flex-row gap-5">
                <Link to="/contact" className="btn-primary bg-white text-slate-900 hover:bg-slate-100">Contact Us Today</Link>
                <Link to="/pricing" className="text-white font-bold flex items-center px-8">View Pricing Plans <ArrowRight className="ml-2 w-5 h-5" /></Link>
              </div>
            </div>
          </motion.div>
        </div>
      </section>
    </div>
  );
}
