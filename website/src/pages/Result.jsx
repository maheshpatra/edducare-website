import React, { useState } from 'react';
import { useSchool } from '../context/SchoolContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Search, FileText, CheckCircle, XCircle, Printer, Award, ChevronDown, GraduationCap, BookOpen, TrendingUp, Hash } from 'lucide-react';

export default function Result() {
  const { activeSchool } = useSchool();
  const [rollNumber, setRollNumber] = useState('');
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);
  const [selectedExamIndex, setSelectedExamIndex] = useState(0);
  const [error, setError] = useState(null);

  const fetchResult = async (e) => {
    e.preventDefault();
    if (!rollNumber.trim()) {
      setError('Please enter a roll number.');
      return;
    }
    if (!activeSchool) {
      setError('School information not loaded. Please refresh the page.');
      return;
    }
    setLoading(true); setData(null); setError(null); setSelectedExamIndex(0);
    try {
      const response = await fetch(`https://edducare.finafid.org/api/public/result?roll_number=${encodeURIComponent(rollNumber.trim())}&school_id=${activeSchool.id}`);
      const json = await response.json();
      if (json.success && json.all_exams && json.all_exams.length > 0) {
        setData(json);
      } else if (json.success) {
        setError(json.message || 'No exam results have been published for this student yet.');
      } else {
        setError(json.error || 'Failed to fetch result. Please try again.');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching the result. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  const handlePrint = () => window.print();

  const result = data?.all_exams?.[selectedExamIndex];

  return (
    <div className="bg-slate-50 min-h-screen py-24">
      <div className="max-w-5xl mx-auto px-4">

        {/* Header */}
        <div className="text-center mb-12">
          <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="inline-flex items-center gap-2 bg-primary/10 text-primary px-4 py-2 rounded-full text-sm font-bold mb-6">
            <GraduationCap className="w-4 h-4" />
            Student Result Portal
          </motion.div>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.05 }} className="text-4xl md:text-5xl font-extrabold text-slate-900 mb-4">
            Examination Results
          </motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-lg text-slate-500 max-w-xl mx-auto">
            Enter your roll number to view your complete academic transcript.
          </motion.p>
        </div>

        {/* Search */}
        <motion.form onSubmit={fetchResult} initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }}
          className="card p-5 sm:p-6 flex flex-col sm:flex-row gap-4 mb-10 print:hidden">
          <div className="flex-grow relative">
            <Search className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
            <input
              type="text"
              className="w-full pl-14 pr-6 py-4 rounded-2xl bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all font-bold text-lg"
              placeholder="Enter Roll Number"
              value={rollNumber}
              onChange={(e) => setRollNumber(e.target.value)}
            />
          </div>
          <button type="submit" disabled={loading}
            className="btn-primary py-4 px-8 min-w-[180px] flex items-center justify-center gap-2 text-base">
            {loading ? (
              <><span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Searching...</>
            ) : (
              <><Search className="w-4 h-4" /> View Result</>
            )}
          </button>
        </motion.form>

        {/* Error */}
        <AnimatePresence>
          {error && (
            <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.95 }}
              className="bg-red-50 border border-red-200 p-6 rounded-2xl flex items-start gap-4 mb-8 print:hidden">
              <div className="bg-white text-red-500 p-2.5 rounded-xl shadow-sm flex-shrink-0"><XCircle className="w-6 h-6" /></div>
              <div>
                <p className="text-red-800 font-bold text-base">{error}</p>
                <p className="text-red-500 text-sm mt-1">Please verify your roll number and try again.</p>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Results */}
        <AnimatePresence>
          {data && result && (
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="space-y-6">

              {/* Student Info + Exam Selector */}
              <div className="card p-6 sm:p-8">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
                  <div className="flex items-center gap-5">
                    <div className="w-16 h-16 bg-gradient-to-br from-primary/20 to-primary/5 text-primary rounded-2xl flex items-center justify-center shadow-sm">
                      <Award className="w-8 h-8" />
                    </div>
                    <div>
                      <p className="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Student Name</p>
                      <h2 className="text-2xl md:text-3xl font-extrabold text-slate-900">{data.student_info.student_name}</h2>
                    </div>
                  </div>
                  <div className="flex gap-2 print:hidden">
                    <button onClick={handlePrint} title="Print Result"
                      className="p-3 bg-slate-50 text-slate-500 hover:text-primary hover:bg-primary/5 rounded-xl transition-all border border-slate-200 hover:border-primary/30">
                      <Printer className="w-5 h-5" />
                    </button>
                  </div>
                </div>

                {/* Student details grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                  {[
                    { label: 'Roll No.', value: data.student_info.roll_number, icon: Hash },
                    { label: 'Class', value: data.student_info.class, icon: BookOpen },
                    { label: 'Adm. No.', value: data.student_info.admission_number, icon: FileText },
                    { label: 'Session', value: data.student_info.academic_year, icon: GraduationCap },
                  ].map((item, i) => (
                    <div key={i} className="p-4 bg-slate-50 rounded-xl border border-slate-100">
                      <div className="flex items-center gap-1.5 mb-1">
                        <item.icon className="w-3 h-3 text-slate-400" />
                        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{item.label}</p>
                      </div>
                      <p className="font-extrabold text-sm text-slate-900 truncate">{item.value || '—'}</p>
                    </div>
                  ))}
                </div>

                {/* Exam Selector */}
                {data.all_exams.length > 1 && (
                  <div className="print:hidden">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Select Examination</p>
                    <div className="relative">
                      <select
                        value={selectedExamIndex}
                        onChange={(e) => setSelectedExamIndex(Number(e.target.value))}
                        className="w-full py-3 px-4 pr-10 rounded-xl bg-white border border-slate-200 font-bold text-slate-800 appearance-none focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer"
                      >
                        {data.all_exams.map((ex, idx) => (
                          <option key={idx} value={idx}>
                            {ex.exam_name} — {ex.exam_type?.replace('_', ' ')} ({ex.exam_date})
                          </option>
                        ))}
                      </select>
                      <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 pointer-events-none" />
                    </div>
                  </div>
                )}
              </div>

              {/* Score Overview Cards */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                {[
                  { label: 'Total Marks', value: `${result.total_marks} / ${result.max_marks}`, color: 'text-slate-800' },
                  { label: 'Percentage', value: result.percentage, color: 'text-primary' },
                  { label: 'Grade', value: result.grade, color: 'text-amber-600' },
                  { label: 'Status', value: result.status,
                    color: result.status === 'Pass' ? 'text-emerald-600' : 'text-red-600',
                    bg: result.status === 'Pass' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200' },
                ].map((item, i) => (
                  <div key={i} className={`p-5 rounded-2xl border ${item.bg || 'bg-white border-slate-200'}`}>
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">{item.label}</p>
                    <p className={`font-extrabold text-xl ${item.color}`}>{item.value}</p>
                  </div>
                ))}
              </div>

              {/* Marks Table */}
              <div className="card overflow-hidden">
                <div className="bg-slate-900 p-5 sm:p-6 text-white flex justify-between items-center">
                  <div className="flex items-center gap-3">
                    <div className="w-9 h-9 bg-primary/20 rounded-lg flex items-center justify-center">
                      <FileText className="text-primary w-4 h-4" />
                    </div>
                    <div>
                      <h4 className="text-base font-extrabold">{result.exam_name}</h4>
                      <p className="text-xs text-slate-400 capitalize">{result.exam_type?.replace('_', ' ')} • {result.exam_date}</p>
                    </div>
                  </div>
                  <span className="text-xs font-bold uppercase opacity-60 tracking-widest hidden sm:block">Roll: {data.student_info.roll_number}</span>
                </div>

                <div className="overflow-x-auto">
                  <table className="w-full text-left">
                    <thead>
                      <tr className="border-b border-slate-100">
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">#</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Subject</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Max</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Pass</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Obtained</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Grade</th>
                        <th className="px-5 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {result.subjects.map((subj, idx) => (
                        <tr key={idx} className="hover:bg-slate-50/50 transition-colors">
                          <td className="px-5 py-4 text-slate-400 text-sm font-bold">{idx + 1}</td>
                          <td className="px-5 py-4 text-slate-900 font-extrabold text-sm">{subj.name}</td>
                          <td className="px-5 py-4 text-center text-slate-400 font-bold text-sm">{subj.max}</td>
                          <td className="px-5 py-4 text-center text-slate-400 font-bold text-sm">{subj.pass_marks}</td>
                          <td className="px-5 py-4 text-center font-extrabold text-base text-primary">{subj.marks}</td>
                          <td className="px-5 py-4 text-center">
                            <span className="text-xs font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">{subj.grade}</span>
                          </td>
                          <td className="px-5 py-4 text-center">
                            {subj.status === 'Pass' ? (
                              <span className="inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[11px] font-bold">
                                <CheckCircle className="w-3 h-3" /> PASS
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 bg-red-50 text-red-600 px-3 py-1 rounded-full text-[11px] font-bold">
                                <XCircle className="w-3 h-3" /> FAIL
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Footer Total */}
                <div className="bg-slate-50 p-5 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-3">
                  <div className="flex items-center gap-3">
                    <TrendingUp className="w-5 h-5 text-primary" />
                    <p className="text-slate-600 font-bold text-sm uppercase tracking-wider">Overall Total</p>
                  </div>
                  <div className="flex items-baseline gap-2">
                    <p className="text-3xl font-extrabold text-slate-900">{result.total_marks}</p>
                    <p className="text-lg text-slate-400 font-bold">/ {result.max_marks}</p>
                    <span className={`ml-3 text-sm font-extrabold px-3 py-1 rounded-full ${
                      result.status === 'Pass' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                    }`}>
                      {result.percentage} • {result.grade}
                    </span>
                  </div>
                </div>
              </div>

              {/* Print Footer */}
              <div className="hidden print:block text-center text-xs text-slate-400 mt-8 pt-4 border-t border-slate-200">
                <p>This is a computer-generated result. Printed on {new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' })}.</p>
                {activeSchool && <p className="mt-1">{activeSchool.name} • {activeSchool.address}</p>}
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
}
