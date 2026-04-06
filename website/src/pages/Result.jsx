import React, { useState } from 'react';
import { useSchool } from '../context/SchoolContext';
import { motion } from 'framer-motion';
import { Search, FileText, CheckCircle, XCircle, Printer, Download, Award, ChevronRight } from 'lucide-react';

export default function Result() {
  const { activeSchool } = useSchool();
  const [rollNumber, setRollNumber] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  const fetchResult = async (e) => {
    e.preventDefault();
    if (!rollNumber) {
      setError('Please enter a roll number.');
      return;
    }
    setLoading(true); setResult(null); setError(null);
    if (!activeSchool) {
      setError('School information not loaded. Please refresh the page.');
      setLoading(false);
      return;
    }
    try {
      const response = await fetch(`https://edducare.finafid.org/api/public/result?roll_number=${rollNumber}&school_id=${activeSchool.id}`);
      const data = await response.json();
      if (data.success && data.current_result) {
        setResult({
          student_name: data.student_info.student_name,
          class: data.student_info.class,
          academic_year: data.student_info.academic_year,
          total_marks: data.current_result.total_marks,
          max_marks: data.current_result.max_marks,
          percentage: data.current_result.percentage,
          grade: data.current_result.grade,
          status: data.current_result.status,
          subjects: data.current_result.subjects.map(s => ({
            name: s.name,
            marks: s.marks,
            max: s.max
          }))
        });
      } else if (data.success) {
        setError(data.message || 'No result records found for this student.');
      } else {
        setError(data.error || 'Failed to fetch result. Please try again.');
      }
    } catch (err) {
      console.error(err);
      setError('An error occurred while fetching the result. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-slate-50 min-h-screen py-24">
      <div className="max-w-4xl mx-auto px-4">
        <div className="text-center mb-16">
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl font-extrabold text-slate-900 mb-6">Examination Results</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed">
            Access transcript records using your institutional roll number.
          </motion.p>
        </div>

        {/* Search */}
        <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }} className="card p-6 flex flex-col md:flex-row gap-4 mb-12">
          <div className="flex-grow relative">
            <Search className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5" />
            <input 
              type="text" 
              className="w-full pl-14 pr-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 focus:border-primary focus:bg-white focus:outline-none transition-all font-bold"
              placeholder="Enter Roll Number"
              value={rollNumber}
              onChange={(e) => setRollNumber(e.target.value)}
            />
          </div>
          <button onClick={fetchResult} disabled={loading} className="btn-primary py-4 px-8 min-w-[180px]">
            {loading ? 'Searching...' : 'View Result'}
          </button>
        </motion.div>

        {error && (
          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="bg-red-50 border border-red-100 p-8 rounded-3xl flex items-center mb-8">
            <div className="bg-white text-red-600 p-3 rounded-xl shadow-sm mr-6"><XCircle className="w-8 h-8" /></div>
            <p className="text-red-800 font-bold text-lg">{error}</p>
          </motion.div>
        )}

        {result && (
          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="space-y-8">
            {/* Student Info */}
            <div className="card p-10">
              <div className="flex flex-col md:flex-row justify-between items-center gap-8 mb-10">
                <div className="flex items-center space-x-6">
                  <div className="w-20 h-20 bg-primary/10 text-primary rounded-2xl flex items-center justify-center shadow-lg">
                    <Award className="w-10 h-10" />
                  </div>
                  <div>
                    <p className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Student Name</p>
                    <h2 className="text-3xl font-extrabold text-slate-900">{result.student_name}</h2>
                  </div>
                </div>
                <div className="flex space-x-3">
                  <button className="p-3 bg-slate-50 text-slate-400 hover:text-primary hover:bg-primary/5 rounded-xl transition-all border border-slate-100"><Printer className="w-5 h-5" /></button>
                  <button className="p-3 bg-slate-50 text-slate-400 hover:text-primary hover:bg-primary/5 rounded-xl transition-all border border-slate-100"><Download className="w-5 h-5" /></button>
                </div>
              </div>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {[
                  { label: 'Class', value: result.class },
                  { label: 'Academic Year', value: result.academic_year },
                  { label: 'Percentage', value: result.percentage, highlight: true },
                  { label: 'Grade / Status', value: `${result.grade} / ${result.status}`, success: true },
                ].map((item, i) => (
                  <div key={i} className="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">{item.label}</p>
                    <p className={`font-extrabold text-lg ${item.highlight ? 'text-primary' : item.success ? 'text-green-600' : 'text-slate-900'}`}>{item.value}</p>
                  </div>
                ))}
              </div>
            </div>

            {/* Marks Table */}
            <div className="card overflow-hidden">
              <div className="bg-slate-900 p-6 text-white flex justify-between items-center">
                <div className="flex items-center space-x-3">
                  <FileText className="text-primary w-5 h-5" />
                  <h4 className="text-lg font-extrabold">Mark Transcript</h4>
                </div>
                <span className="text-xs font-bold uppercase opacity-60 tracking-widest">Roll: {rollNumber}</span>
              </div>
              <div className="p-6">
                <table className="w-full text-left uppercase text-xs font-bold tracking-widest">
                  <thead className="text-slate-400 border-b border-slate-100">
                    <tr>
                      <th className="px-5 py-4">Subject</th>
                      <th className="px-5 py-4 text-center">Max</th>
                      <th className="px-5 py-4 text-center">Obtained</th>
                      <th className="px-5 py-4 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {result.subjects.map((subj, idx) => (
                      <tr key={idx} className="hover:bg-slate-50 transition-colors">
                        <td className="px-5 py-5 text-slate-900 font-extrabold">{subj.name}</td>
                        <td className="px-5 py-5 text-center text-slate-400">{subj.max}</td>
                        <td className="px-5 py-5 text-center text-primary font-extrabold text-lg">{subj.marks}</td>
                        <td className="px-5 py-5 text-center"><span className="bg-green-100 text-green-600 px-3 py-1 rounded-full text-[10px]">PASS</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="bg-slate-50 p-6 border-t border-slate-100 flex justify-between items-center">
                <p className="text-slate-400 font-bold text-sm uppercase tracking-widest">Total</p>
                <p className="text-2xl font-extrabold text-slate-900">{result.total_marks} / {result.max_marks}</p>
              </div>
            </div>
          </motion.div>
        )}
      </div>
    </div>
  );
}
