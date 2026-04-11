import React, { useState, useEffect } from 'react';
import { useSchool } from '../context/SchoolContext';
import { motion } from 'framer-motion';
import { User, Phone, Mail, Upload, CheckCircle, Info, ChevronRight, FileCheck, Shield, CreditCard } from 'lucide-react';
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
    previous_school: '',
    utr_number: '',
    payment_method: ''
  });

  const [selectedMethod, setSelectedMethod] = useState('');

  useEffect(() => {
    if (activeSchool?.id) {
      axios.get(`${API_BASE}/admission_config?school_id=${activeSchool.id}`)
        .then(res => {
          if (res.data.success) {
            setConfig(res.data.data);
          }
        })
        .catch(err => console.error("Error loading config", err))
        .finally(() => setLoading(false));
    } else {
        setLoading(false);
    }

    // Check for PayU return status
    const params = new URLSearchParams(window.location.search);
    if (params.get('status') === 'success') {
       setTrackingId(params.get('txnid'));
       setIsSuccess(true);
       // Clear params to prevent double success on refresh
       window.history.replaceState({}, document.title, window.location.pathname);
    } else if (params.get('status') === 'failure') {
       alert("Payment failed. Please try again.");
       window.history.replaceState({}, document.title, window.location.pathname);
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

  const loadRazorpay = () => {
    return new Promise((resolve) => {
      if (window.Razorpay) return resolve(true);
      const script = document.createElement('script');
      script.src = 'https://checkout.razorpay.com/v1/checkout.js';
      script.onload = () => resolve(true);
      script.onerror = () => resolve(false);
      document.body.appendChild(script);
    });
  };

  const handleRazorpayShow = async () => {
    const res = await loadRazorpay();
    if (!res) {
      alert("Razorpay SDK failed to load. Please check your internet connection.");
      return;
    }

    try {
        const orderRes = await axios.post(`${API_BASE}/create_razorpay_order`, {
            school_id: activeSchool.id,
            amount: config.admission_fee_amount
        });

        if (!orderRes.data.success) {
            alert("Error starting payment: " + orderRes.data.error);
            return;
        }

        const order = orderRes.data.order;
        const razorpayMethod = config.payment_methods.find(m => m.name === 'razorpay');

        const options = {
            key: razorpayMethod.mode === 'sandbox' ? (razorpayMethod.config.sandbox_key_id || razorpayMethod.config.key_id) : (razorpayMethod.config.live_key_id || razorpayMethod.config.key_id),
            amount: order.amount,
            currency: 'INR',
            name: activeSchool.name,
            description: "Online Admission Fee",
            order_id: order.id,
            handler: function(response) {
                // On success, store the payment ID and auto-submit
                const updatedData = { 
                    ...formData, 
                    utr_number: response.razorpay_payment_id,
                    payment_method: 'razorpay'
                };
                setFormData(updatedData);
                
                // Submit application immediately
                setIsSubmitting(true);
                axios.post(`${API_BASE}/submit_admission`, { ...updatedData, school_id: activeSchool.id })
                    .then(r => {
                        if (r.data.success) {
                            setTrackingId(r.data.tracking_id);
                            setIsSuccess(true);
                        } else {
                            alert("Payment successful but application failed: " + r.data.error);
                        }
                    })
                    .finally(() => setIsSubmitting(false));
            },
            prefill: {
                name: formData.student_name,
                email: formData.email,
                contact: formData.phone
            },
            theme: { color: "#2563eb" }
        };

        const rzp = new window.Razorpay(options);
        rzp.open();
    } catch (err) {
        console.error(err);
        alert("Payment initialization failed");
    }
  };

  const handlePayUShow = async () => {
    setIsSubmitting(true);
    try {
        // 1. Submit application first as pending
        const payload = { 
            ...formData, 
            school_id: activeSchool.id,
            payment_status: 'pending' // explicit
        };
        const submitRes = await axios.post(`${API_BASE}/submit_admission`, payload);
        
        if (!submitRes.data.success) {
            alert("Failed to save application: " + submitRes.data.error);
            setIsSubmitting(false);
            return;
        }

        const tid = submitRes.data.tracking_id;

        // 2. Get PayU Hash and params
        const hashRes = await axios.post(`${API_BASE}/create_payu_hash`, {
            school_id: activeSchool.id,
            amount: config.admission_fee_amount,
            firstname: formData.student_name,
            email: formData.email,
            phone: formData.phone,
            productinfo: "Admission_" + tid,
            udf1: tid // Pass tid so it can be included in the hash
        });

        if (!hashRes.data.success) {
            alert("Payment init error: " + hashRes.data.error);
            setIsSubmitting(false);
            return;
        }

        const params = hashRes.data.payment_params;
        
        // 3. Create a temporary form and submit it to PayU
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = params.action;

        // Add all params as hidden inputs (hashRes already includes udf1, surl, furl)
        const finalParams = {
            ...params,
            surl: `https://edducare.finafid.org/api/public/payu_callback.php`,
            furl: `https://edducare.finafid.org/api/public/payu_callback.php`,
        };

        Object.keys(finalParams).forEach(key => {
            if (key !== 'action') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = finalParams[key];
                form.appendChild(input);
            }
        });

        document.body.appendChild(form);
        form.submit();

    } catch (err) {
        console.error(err);
        alert("PayU connection failed");
        setIsSubmitting(false);
    }
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

  const admissionFields = typeof config?.fields_json === 'string' ? JSON.parse(config.fields_json) : config?.fields_json;
  const isFieldEnabled = (field) => admissionFields?.[field]?.enabled !== false;
  const isFieldRequired = (field) => admissionFields?.[field]?.required === true;

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
                {config?.is_admission_fee_enabled === 1 && (
                  <div className="p-8 bg-white border-2 border-primary/20 rounded-3xl shadow-sm space-y-6">
                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                       <h4 className="text-lg font-extrabold text-slate-800">Admission Fee: ₹{config?.admission_fee_amount}</h4>
                       <span className="px-3 py-1 bg-primary text-white text-[10px] font-black uppercase rounded-full tracking-wider">Online Payment Required</span>
                    </div>

                    {/* Payment Method Selector */}
                    <div className="space-y-4">
                      <p className="text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Choose Payment Method</p>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {config?.payment_methods?.map(method => (
                          <div 
                            key={method.name}
                            onClick={() => {
                              setSelectedMethod(method.name);
                              setFormData(p => ({ ...p, payment_method: method.name }));
                            }}
                            className={`p-5 rounded-2xl border-2 cursor-pointer transition-all flex items-center space-x-4 ${selectedMethod === method.name ? 'border-primary bg-primary/5' : 'border-slate-100 hover:border-slate-200'}`}
                          >
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${selectedMethod === method.name ? 'bg-primary text-white' : 'bg-slate-100 text-slate-400'}`}>
                              {method.name === 'upi_qr' ? <Upload size={20} /> : <FileCheck size={20} />}
                            </div>
                            <div>
                               <div className="font-extrabold text-slate-900 leading-tight capitalize">{method.name.replace('_', ' ')}</div>
                               {method.name !== 'upi_qr' && (
                                 <div className="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">{method.mode} Mode</div>
                               )}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* QR Code Integration */}
                    {selectedMethod === 'upi_qr' && (
                      <div className="flex flex-col items-center p-6 bg-slate-50 rounded-3xl border border-slate-100 animate-fade-in">
                        <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Scan QR to pay via UPI</p>
                        <div className="p-4 bg-white rounded-2xl shadow-sm">
                          <img 
                            src={`https://edducare.finafid.org/uploads/${config.payment_methods?.find(m => m.name === 'upi_qr')?.config?.qr_path || config.qr_code}`} 
                            alt="Payment QR" 
                            className="w-48 h-48 object-contain"
                          />
                        </div>
                      </div>
                    )}

                    {/* Razorpay Integration Skeleton */}
                    {selectedMethod === 'razorpay' && (
                      <div className="p-6 bg-blue-50 rounded-3xl border border-blue-100 text-center animate-fade-in">
                         <div className="text-blue-600 font-extrabold mb-4 flex items-center justify-center space-x-2">
                           <Shield size={20} /> <span>Secure Razorpay Gateway</span>
                         </div>
                         <p className="text-sm text-blue-600/70 mb-6 font-medium">Card, Netbanking, Wallets & Postpaid</p>
                         <button 
                            type="button" 
                            onClick={handleRazorpayShow}
                            disabled={isSubmitting}
                            className="w-full py-4 bg-blue-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg shadow-blue-600/20 hover:bg-blue-700 transition-colors"
                          >
                            {isSubmitting ? 'Syncing...' : `Pay ₹${config?.admission_fee_amount} Now`}
                          </button>
                      </div>
                    )}

                    {/* PayU Integration Skeleton */}
                    {selectedMethod === 'payu' && (
                      <div className="p-6 bg-emerald-50 rounded-3xl border border-emerald-100 text-center animate-fade-in">
                         <div className="text-emerald-600 font-extrabold mb-4 flex items-center justify-center space-x-2">
                           <CreditCard size={20} /> <span>PayU Checkout</span>
                         </div>
                         <p className="text-sm text-emerald-600/70 mb-6 font-medium">Fast & Secure multi-option payments</p>
                          <button 
                            type="button" 
                            onClick={handlePayUShow}
                            disabled={isSubmitting}
                            className="w-full py-4 bg-emerald-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition-colors"
                          >
                            {isSubmitting ? 'Initializing...' : `Pay ₹${config?.admission_fee_amount} Now`}
                          </button>
                      </div>
                    )}

                    {(selectedMethod === 'upi_qr' || (config?.is_admission_fee_enabled === 1 && !selectedMethod)) && (
                      <div className={!selectedMethod ? 'opacity-30 blur-[2px] pointer-events-none' : ''}>
                        <label className="block text-slate-500 text-xs font-bold uppercase tracking-widest mb-3">
                            {selectedMethod === 'upi_qr' ? 'UTR / Transaction ID' : 'Transaction Reference'} <span className="text-red-500">*</span>
                        </label>
                        <input 
                          type="text" 
                          name="utr_number" 
                          value={formData.utr_number} 
                          onChange={handleChange} 
                          required={selectedMethod === 'upi_qr'}
                          className="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none transition-all font-mono" 
                          placeholder="Enter the 12-digit transaction number" 
                        />
                        <p className="text-[10px] text-slate-400 mt-2 font-medium italic">* Payment will be verified by the school administration.</p>
                      </div>
                    )}
                  </div>
                )}
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
              <button 
                type="submit" 
                disabled={isSubmitting || (step === 3 && config?.is_admission_fee_enabled === 1 && (!selectedMethod || ((selectedMethod === 'razorpay' || selectedMethod === 'payu') && !formData.utr_number)))} 
                className="ml-auto btn-primary py-4 px-10 text-lg flex items-center"
              >
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
