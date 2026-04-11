import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Check, ArrowRight, Zap, Shield, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import publicService from '../api/publicService';

export default function Pricing() {
  const [packages, setPackages] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPlans = async () => {
      try {
        const resp = await publicService.getPackages();
        if (resp.data.success) {
          setPackages(resp.data.data);
        }
      } catch (err) {
        console.error("Pricing fetch error:", err);
        // Fallback dummy data if API fails
        setPackages([
          { id: 1, name: 'Basic', price: '2999', duration_months: 12, features: ['Up to 100 Students', 'Core ERP Features', 'Attendance Management', 'Basic Reports'], description: 'Perfect for small growing schools' },
          { id: 2, name: 'Professional', price: '5999', duration_months: 12, features: ['Up to 500 Students', 'All ERP Features', 'LMS Integration', 'Advanced Analytics', 'Mobile App Access'], description: 'Our most popular plan for mid-size schools' },
          { id: 3, name: 'Enterprise', price: '9999', duration_months: 12, features: ['Unlimited Students', 'Premium Support', 'Custom Integrations', 'Dedicated Account Manager', 'Custom Domain'], description: 'Fully customized solution for large institutions' }
        ]);
      } finally {
        setLoading(false);
      }
    };
    fetchPlans();
  }, []);

  const fadeInUp = {
    initial: { opacity: 0, y: 30 },
    whileInView: { opacity: 1, y: 0 },
    transition: { duration: 0.7, ease: [0.22, 1, 0.36, 1] },
    viewport: { once: true }
  };

  return (
    <div className="pt-20 bg-white">
      {/* Header */}
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="absolute inset-0 z-0 opacity-20 mesh-gradient"></div>
        <div className="max-w-7xl mx-auto px-4 relative z-10 text-center">
          <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }}>
            <span className="section-label">Investment in Excellence</span>
            <h1 className="text-5xl md:text-7xl font-black mb-8 tracking-tighter leading-tight italic">
              Transparent <br /><span className="text-gradient">Pricing</span> for Every School.
            </h1>
            <p className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
              Choose the perfect plan to digitize your institution. No hidden fees, just pure educational excellence.
            </p>
          </motion.div>
        </div>
      </section>

      {/* Pricing Cards */}
      <section className="py-32 -mt-20 relative z-20">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {packages.map((plan, i) => (
              <motion.div
                key={plan.id}
                {...fadeInUp}
                transition={{ delay: i * 0.1 }}
                className={`relative p-10 rounded-[3rem] border transition-all duration-500 flex flex-col ${i === 1
                  ? 'bg-slate-900 text-white border-primary shadow-3xl scale-105 z-10'
                  : 'bg-white text-slate-900 border-slate-100 hover:shadow-2xl'
                  }`}
              >
                {i === 1 && (
                  <div className="absolute -top-5 left-1/2 -translate-x-1/2 bg-primary text-white text-[10px] font-black uppercase tracking-[0.2em] px-4 py-2 rounded-full shadow-lg">
                    Best Value Selection
                  </div>
                )}

                <div className="mb-10">
                  <h3 className="text-2xl font-black mb-2 tracking-tight">{plan.name}</h3>
                  <p className={`text-sm ${i === 1 ? 'text-slate-400' : 'text-slate-500'} mb-8 font-medium`}>{plan.description}</p>
                  <div className="flex items-baseline">
                    <span className="text-4xl font-black">₹{plan.price}</span>
                    <span className={`text-sm ml-2 font-bold ${i === 1 ? 'text-slate-500' : 'text-slate-400 uppercase'}`}>/{plan.duration_months === 12 ? 'Year' : `${plan.duration_months} Months`}</span>
                  </div>
                </div>

                <ul className="space-y-5 mb-12 flex-grow">
                  {(Array.isArray(plan.features) ? plan.features : []).map((feature, idx) => (
                    <li key={idx} className="flex items-start">
                      <div className={`w-5 h-5 rounded-full flex items-center justify-center mr-3 mt-0.5 ${i === 1 ? 'bg-primary text-white' : 'bg-blue-50 text-primary'
                        }`}>
                        <Check className="w-3 h-3" />
                      </div>
                      <span className="text-sm font-bold tracking-tight">{feature}</span>
                    </li>
                  ))}
                  {/* Default features if none specified */}
                  {(!plan.features || plan.features.length === 0) && (
                    <>
                      <li className="flex items-start"><Check className="w-5 h-5 mr-3 text-primary" /> <span className="text-sm">Comprehensive ERP</span></li>
                      <li className="flex items-start"><Check className="w-5 h-5 mr-3 text-primary" /> <span className="text-sm">24/7 Priority Support</span></li>
                    </>
                  )}
                </ul>

                <Link
                  to="/contact"
                  className={`w-full py-5 rounded-2xl font-black text-sm uppercase tracking-widest transition-all text-center ${i === 1
                    ? 'bg-primary text-white hover:bg-blue-600 shadow-xl shadow-primary/30'
                    : 'bg-slate-50 text-slate-900 hover:bg-slate-100'
                    }`}
                >
                  Choose {plan.name} Plan
                </Link>
              </motion.div>
            ))}
          </div>

          {/* Trust indicators */}
          <div className="mt-32 text-center">
            <div className="inline-flex items-center space-x-12 opacity-50 grayscale hover:grayscale-0 transition-all duration-700">
              <div className="flex items-center gap-2"><Shield className="w-10 h-10" /> <span className="font-black">ISO SECURE</span></div>
              <div className="flex items-center gap-2"><Zap className="w-10 h-10" /> <span className="font-black">99.9% UPTIME</span></div>
              <div className="flex items-center gap-2"><Sparkles className="w-10 h-10" /> <span className="font-black">GDPR READY</span></div>
            </div>
          </div>
        </div>
      </section>

      {/* FAQ placeholder */}
      <section className="py-32 bg-slate-50">
        <div className="max-w-3xl mx-auto px-4 text-center">
          <h2 className="section-title">Common Questions</h2>
          <div className="space-y-6 text-left mt-16">
            {[
              "Can I switch plans later?",
              "Is our school data secure?",
              "Do you offer on-site training?",
              "What payment methods do you accept?"
            ].map((q, i) => (
              <div key={i} className="p-8 bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition-all flex justify-between items-center group cursor-pointer">
                <span className="font-bold text-slate-900">{q}</span>
                <ArrowRight className="w-5 h-5 text-slate-300 group-hover:text-primary transition-all" />
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
