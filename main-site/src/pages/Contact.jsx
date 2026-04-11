import React, { useState } from 'react';
import { motion } from 'framer-motion';
import { Mail, Phone, MapPin, Send, CheckCircle, Smartphone, Globe, MessageSquare, Loader2 } from 'lucide-react';
import publicService from '../api/publicService';

export default function Contact() {
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    school_name: '',
    email: '',
    phone: '',
    plan: 'Professional',
    message: ''
  });

  const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      await publicService.submitContact(formData);
      setSubmitted(true);
    } catch (err) {
      console.error("Submission error:", err);
      // Optional: Add error toast here
    } finally {
      setLoading(false);
    }
  };

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
            <span className="section-label">Start Your Journey</span>
            <h1 className="text-5xl md:text-7xl font-black mb-8 tracking-tighter italic">
              Partner with <span className="text-gradient">EduCare</span>.
            </h1>
            <p className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
              Ready to transform your school? Our experts are here to help you choose the right plan and guide your digital transition.
            </p>
          </motion.div>
        </div>
      </section>

      {/* Main Content */}
      <section className="py-32 relative z-20">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid lg:grid-cols-5 gap-16">
            {/* Info Side */}
            <motion.div {...fadeInUp} className="lg:col-span-2 space-y-12">
              <div>
                <h2 className="section-title text-4xl mb-6">Contact Details</h2>
                <p className="text-slate-500 text-lg leading-relaxed font-medium">Join 500+ schools that have revolutionized their administration with EdduCare Cloud.</p>
              </div>

              <div className="space-y-6">
                {[
                  { icon: Mail, label: 'Email Outreach', value: 'partnership@edducare.in' },
                  { icon: Phone, label: 'Partnership Desk', value: '+91 8800223344' },
                  { icon: MapPin, label: 'Headquarters', value: 'Patna Bhairabpur, West Bengal , India' },
                  { icon: Globe, label: 'Global Sales', value: 'sales.global@edducare.in' },
                ].map((item, i) => (
                  <div key={i} className="flex items-start group p-6 rounded-[2rem] border border-slate-50 hover:border-primary/20 hover:bg-slate-50/50 transition-all">
                    <div className="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mr-6 group-hover:bg-primary group-hover:text-white transition-all">
                      <item.icon className="w-5 h-5" />
                    </div>
                    <div>
                      <p className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">{item.label}</p>
                      <p className="text-slate-900 font-bold text-lg">{item.value}</p>
                    </div>
                  </div>
                ))}
              </div>
            </motion.div>

            {/* Form Side */}
            <motion.div {...fadeInUp} transition={{ delay: 0.2 }} className="lg:col-span-3">
              <div className="bg-white p-10 md:p-16 rounded-[4rem] border border-slate-100 shadow-3xl relative overflow-hidden">
                <div className="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16"></div>

                {submitted ? (
                  <div className="text-center py-24">
                    <div className="w-24 h-24 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-10 animate-bounce">
                      <CheckCircle className="w-12 h-12" />
                    </div>
                    <h3 className="text-4xl font-black text-slate-900 mb-4 tracking-tight">Request Received!</h3>
                    <p className="text-slate-500 text-lg font-medium">One of our education consultants will contact you within 2-4 hours.</p>
                    <button onClick={() => setSubmitted(false)} className="mt-12 text-primary font-black uppercase text-xs tracking-[0.2em] hover:underline transition-all">Send Another Inquiry</button>
                  </div>
                ) : (
                  <form onSubmit={handleSubmit} className="space-y-8">
                    <div className="grid md:grid-cols-2 gap-8">
                      <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">School Name</label>
                        <input name="school_name" required onChange={handleChange} className="w-full bg-slate-50 border border-slate-100 py-4 px-6 rounded-2xl focus:outline-none focus:border-primary focus:bg-white transition-all font-bold text-slate-900" placeholder="St. Xavier's International" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Email Address</label>
                        <input type="email" name="email" required onChange={handleChange} className="w-full bg-slate-50 border border-slate-100 py-4 px-6 rounded-2xl focus:outline-none focus:border-primary focus:bg-white transition-all font-bold text-slate-900" placeholder="admin@school.edu" />
                      </div>
                    </div>
                    <div className="grid md:grid-cols-2 gap-8">
                      <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Contact Number</label>
                        <input type="tel" name="phone" required onChange={handleChange} className="w-full bg-slate-50 border border-slate-100 py-4 px-6 rounded-2xl focus:outline-none focus:border-primary focus:bg-white transition-all font-bold text-slate-900" placeholder="+91 8800XXXXXX" />
                      </div>
                      <div>
                        <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Interested Plan</label>
                        <select name="plan" onChange={handleChange} className="w-full bg-slate-50 border border-slate-100 py-4 px-6 rounded-2xl focus:outline-none focus:border-primary focus:bg-white transition-all font-bold text-slate-900 appearance-none">
                          <option>Basic Plan</option>
                          <option selected>Professional Plan</option>
                          <option>Enterprise Plan</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label className="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Message / Requirements</label>
                      <textarea name="message" rows="5" onChange={handleChange} className="w-full bg-slate-50 border border-slate-100 py-4 px-6 rounded-2xl focus:outline-none focus:border-primary focus:bg-white transition-all font-bold text-slate-900 resize-none" placeholder="How many students? Do you need a custom app?"></textarea>
                    </div>
                    <button
                      type="submit"
                      disabled={loading}
                      className="btn-primary w-full py-6 text-lg uppercase tracking-widest font-black disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                      {loading ? (
                        <>Processing... <Loader2 className="ml-3 w-5 h-5 animate-spin" /></>
                      ) : (
                        <>Submit Partner Request <Send className="ml-3 w-5 h-5" /></>
                      )}
                    </button>
                  </form>
                )}
              </div>
            </motion.div>
          </div>
        </div>
      </section>
    </div>
  );
}
