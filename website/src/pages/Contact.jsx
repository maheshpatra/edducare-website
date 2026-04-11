import React, { useState } from 'react';
import { useSchool } from '../context/SchoolContext';
import { motion } from 'framer-motion';
import { Phone, Mail, MapPin, Clock, Send, CheckCircle } from 'lucide-react';
import axios from 'axios';

export default function Contact() {
  const { activeSchool, API_BASE } = useSchool();
  const [submitted, setSubmitted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    full_name: '',
    email: '',
    phone: '',
    subject: 'General Inquiry',
    message: ''
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      const res = await axios.post(`${API_BASE}/submit_contact`, {
        ...formData,
        school_id: activeSchool.id
      });
      if (res.data.success) {
        setSubmitted(true);
      }
    } catch (err) {
      alert("Failed to send message. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleChange = (e) => {
    setFormData(p => ({ ...p, [e.target.name]: e.target.value }));
  };

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7 },
    viewport: { once: true }
  };

  const contactInfo = [
    { icon: Phone, label: 'Phone', value: activeSchool?.phone || '+91 XXXXXXXXXX', href: `tel:${activeSchool?.phone}` },
    { icon: Mail, label: 'Email', value: activeSchool?.email || 'info@school.com', href: `mailto:${activeSchool?.email}` },
    { icon: MapPin, label: 'Address', value: activeSchool?.address || 'School Campus, City, State', href: '#' },
    { icon: Clock, label: 'Office Hours', value: 'Mon - Sat: 8:00 AM - 4:00 PM', href: '#' },
  ];

  return (
    <div className="bg-white">
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 text-center relative z-10">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Get In Touch</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">Contact Us</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto">
            Reach out for any inquiries or to schedule a campus visit.
          </motion.p>
        </div>
      </section>

      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-5 gap-16">
          {/* Contact Info */}
          <motion.div {...fadeInUp} className="lg:col-span-2 space-y-6">
            <h2 className="section-title text-3xl">Let's Connect</h2>
            <p className="section-subtitle mb-8">We'd love to hear from you. Whether you have a question about admissions, curriculum, or anything else — our team is here to help.</p>

            {contactInfo.map((item, i) => (
              <a key={i} href={item.href} className="flex items-start group p-4 rounded-2xl hover:bg-slate-50 transition-all">
                <div className="w-12 h-12 rounded-xl bg-primary/10 group-hover:bg-primary flex items-center justify-center flex-shrink-0 mr-4 transition-all">
                  <item.icon className="w-5 h-5 text-primary group-hover:text-white transition-colors" />
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">{item.label}</p>
                  <p className="text-slate-800 font-semibold">{item.value}</p>
                </div>
              </a>
            ))}
          </motion.div>

          {/* Contact Form */}
          <motion.div {...fadeInUp} transition={{ delay: 0.2 }} className="lg:col-span-3">
            <div className="card p-8 sm:p-12">
              {submitted ? (
                <div className="text-center py-16">
                  <div className="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <CheckCircle className="w-10 h-10" />
                  </div>
                  <h3 className="text-2xl font-bold text-slate-900 mb-3">Message Sent!</h3>
                  <p className="text-slate-500">We'll get back to you within 24 hours.</p>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Full Name</label>
                      <input type="text" name="full_name" value={formData.full_name} onChange={handleChange} required className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/10 transition-all" placeholder="Your name" />
                    </div>
                    <div>
                      <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Email</label>
                      <input type="email" name="email" value={formData.email} onChange={handleChange} required className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/10 transition-all" placeholder="your@email.com" />
                    </div>
                  </div>
                  <div>
                    <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Phone Number</label>
                    <input type="tel" name="phone" value={formData.phone} onChange={handleChange} className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/10 transition-all" placeholder="+91 XXXXXXXXXX" />
                  </div>
                  <div>
                    <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Subject</label>
                    <select name="subject" value={formData.subject} onChange={handleChange} className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/10 transition-all text-slate-600">
                      <option>General Inquiry</option>
                      <option>Admission Query</option>
                      <option>Fee Structure</option>
                      <option>Campus Visit</option>
                      <option>Other</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">Message</label>
                    <textarea name="message" value={formData.message} onChange={handleChange} rows="5" required className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/10 transition-all resize-none" placeholder="Type your message..."></textarea>
                  </div>
                  <button type="submit" disabled={isSubmitting} className="btn-primary w-full text-lg">
                    {isSubmitting ? 'Sending...' : 'Send Message'} <Send className="ml-2 w-5 h-5" />
                  </button>
                </form>
              )}
            </div>
          </motion.div>
        </div>
      </section>

      {/* Map placeholder */}
      <section className="h-96 bg-slate-100 relative">
        <div className="absolute inset-0 flex items-center justify-center text-slate-400">
          <div className="text-center">
            <MapPin className="w-12 h-12 mx-auto mb-3 opacity-30" />
            <p className="font-bold">Google Maps Integration</p>
            <p className="text-sm">Map will be displayed here</p>
          </div>
        </div>
      </section>
    </div>
  );
}
