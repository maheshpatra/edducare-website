import React, { useState, useEffect } from 'react';
import { useSchool } from '../context/SchoolContext';
import { motion } from 'framer-motion';
import { User, Phone, Mail, Upload, CheckCircle, Info, ChevronRight, FileCheck } from 'lucide-react';
import axios from 'axios';

export default function Admission() {
  const { activeSchool, API_BASE } = useSchool();
  const [step, setStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [trackingId, setTrackingId] = useState('');
  
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);

  const [formData, setFormData] = useState({
    student_name: '',
    guardian_name: '',
    email: '',
    phone: '',
    desired_class: '',
    dob: '',
    gender: '',
    address: '',
    previous_school: ''
  });

  useEffect(() => {
    if (activeSchool?.id) {
      axios.get(`${API_BASE}/admission_config?school_id=${activeSchool.id}`)
        .then(res => {
          if (res.data.success) {
            setConfig(res.data.data.fields);
          }
        })
        .catch(err => console.error("Error loading config", err))
        .finally(() => setLoading(false));
    } else {
        setLoading(false);
    }
  }, [activeSchool]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    
    try {
        const payload = { ...formData, school_id: activeSchool.id };
        const res = await axios.post(`${API_BASE}/submit_admission`, payload);
        if (res.data.success) {
            setTrackingId(res.data.tracking_id);
            setIsSuccess(true);
        } else {
            alert("Error: " + res.data.error);
        }
    } catch (err) {
        alert("Submission failed. Please try again.");
    }
    
    setIsSubmitting(false);
  };

  if (loading) {
    return <div className="min-h-screen flex items-center justify-center">Loading...</div>;
  }

  if (isSuccess) return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 py-24 text-center px-4">
      <div className="bg-green-100 text-green-600 w-24 h-24 rounded-full flex items-center justify-center mb-10 shadow-xl animate-scale-in">
        <CheckCircle className="w-12 h-12" />
      </div>
      <h1 className="text-4xl font-extrabold text-slate-900 mb-6">Application Submitted!</h1>
      <p className="text-xl text-slate-500 max-w-xl mx-auto mb-6 leading-relaxed">Your admission application for {activeSchool?.name || 'our school'} has been received. A notification has been sent via email.</p>
      <p className="text-sm font-bold text-slate-400 uppercase tracking-widest mb-12">Reference: {trackingId}</p>
      <button onClick={() => window.location.href='/'} className="btn-primary">Return Home</button>
    </div>
  );

  const isFieldEnabled = (field) => config?.[field]?.enabled !== false;
  const isFieldRequired = (field) => config?.[field]?.required === true;

  // Render text input helper
  const renderInput = (name, label, type="text", placeholder="") => {
    if (!isFieldEnabled(name)) return null;
    return (
      <div>
        <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">
            {label} {isFieldRequired(name) && <span className="text-red-500">*</span>}
        </label>
        {type === 'select' ? (
          <select name={name} value={formData[name]} onChange={handleChange} required={isFieldRequired(name)} className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none transition-all">
            <option value="">Choose...</option>
            {[1,2,3,4,5,6,7,8,9,10].map(g => <option key={g} value={`Grade ${g}`}>Grade {g}</option>)}
          </select>
        ) : (
          <input type={type} name={name} value={formData[name]} onChange={handleChange} required={isFieldRequired(name)} className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none transition-all" placeholder={placeholder} />
        )}
      </div>
    );
  };

  return (
    <div className="bg-slate-50 min-h-screen py-24">
      <div className="max-w-4xl mx-auto px-4">
        <div className="text-center mb-16">
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl font-extrabold text-slate-900 mb-6">Online Admission</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed">
            Secure your child's future. Join our vibrant learning community at {activeSchool?.name}.
          </motion.p>
        </div>

        {/* Progress Steps */}
        <div className="flex justify-between items-center mb-16 relative">
          <div className="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -translate-y-1/2 rounded-full overflow-hidden">
            <div className="h-full bg-primary transition-all duration-700" style={{ width: `${(step - 1) * 50}%` }}></div>
          </div>
          {[1, 2, 3].map(s => (
            <div key={s} className={`relative z-10 w-12 h-12 rounded-full flex items-center justify-center text-sm font-extrabold transition-all duration-500 shadow-lg ${step >= s ? 'bg-primary text-white scale-110' : 'bg-white text-slate-400 border-2 border-slate-200'}`}>
              {step > s ? <CheckCircle className="w-6 h-6" /> : s}
            </div>
          ))}
        </div>

        {/* Form */}
        <motion.div key={step} initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="card p-8 sm:p-14">
          <form onSubmit={step === 3 ? handleSubmit : (e) => { e.preventDefault(); setStep(step + 1); }}>
            {step === 1 && (
              <div className="space-y-10">
                <div className="flex items-center space-x-5 pb-6 border-b border-slate-100">
                  <div className="p-4 bg-primary/10 rounded-2xl text-primary"><User className="w-7 h-7" /></div>
                  <div>
                    <h3 className="text-2xl font-extrabold text-slate-900">Student Information</h3>
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">General details</p>
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  {renderInput('student_name', 'Student Name', 'text', 'e.g. John Doe')}
                  {renderInput('dob', 'Date of Birth', 'date')}
                  {renderInput('gender', 'Gender', 'text', 'e.g. Male/Female')}
                  {renderInput('desired_class', 'Grade Applying For', 'select')}
                </div>
              </div>
            )}
            {step === 2 && (
              <div className="space-y-10">
                <div className="flex items-center space-x-5 pb-6 border-b border-slate-100">
                  <div className="p-4 bg-primary/10 rounded-2xl text-primary"><Phone className="w-7 h-7" /></div>
                  <div>
                    <h3 className="text-2xl font-extrabold text-slate-900">Contact Details</h3>
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Guardian & Contact info</p>
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div className="md:col-span-2">
                      {renderInput('guardian_name', 'Guardian Name', 'text', 'Full name')}
                  </div>
                  {renderInput('phone', 'Contact Number', 'tel', '+91 XXXXXXXXXX')}
                  {renderInput('email', 'Email Address', 'email', 'email@example.com')}
                  <div className="md:col-span-2">
                       {renderInput('address', 'Current Address', 'text', 'Full Address')}
                  </div>
                  <div className="md:col-span-2">
                       {renderInput('previous_school', 'Previous School', 'text', 'School Name')}
                  </div>
                </div>
              </div>
            )}
            {step === 3 && (
              <div className="space-y-10">
                <div className="flex items-center space-x-5 pb-6 border-b border-slate-100">
                  <div className="p-4 bg-primary/10 rounded-2xl text-primary"><FileCheck className="w-7 h-7" /></div>
                  <div>
                    <h3 className="text-2xl font-extrabold text-slate-900">Submit Application</h3>
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Final review</p>
                  </div>
                </div>
                <div className="p-10 border-2 border-dashed border-slate-200 rounded-3xl bg-slate-50/50 hover:border-primary/30 hover:bg-primary/5 transition-all text-center cursor-pointer group">
                  <CheckCircle className="w-14 h-14 text-green-500/80 mx-auto mb-6 group-hover:scale-110 transition-all" />
                  <p className="text-lg font-bold text-slate-800 mb-2">Ready to Submit</p>
                  <p className="text-slate-400 text-sm">Review your details before confirming.</p>
                </div>
                <div className="flex items-start space-x-4 p-6 bg-blue-50 border border-blue-100 rounded-2xl text-blue-600">
                  <Info className="w-6 h-6 flex-shrink-0 mt-0.5" />
                  <p className="text-sm font-medium leading-relaxed">By submitting, you agree to the school's privacy policy and terms of admission. An email confirmation might be sent.</p>
                </div>
              </div>
            )}

            <div className="flex justify-between items-center mt-12 pt-8 border-t border-slate-100">
              {step > 1 && (
                <button onClick={() => setStep(step - 1)} type="button" className="text-slate-400 font-bold hover:text-slate-800 transition-colors py-3 px-5 flex items-center">
                  <ChevronRight className="w-5 h-5 mr-2 rotate-180" /> Back
                </button>
              )}
              <button type="submit" disabled={isSubmitting} className="ml-auto btn-primary py-4 px-10 text-lg flex items-center">
                {isSubmitting ? 'Processing...' : (step === 3 ? 'Submit Application' : 'Next Step')} 
                <ChevronRight className="ml-2 w-5 h-5" />
              </button>
            </div>
          </form>
        </motion.div>
      </div>
    </div>
  );
}
