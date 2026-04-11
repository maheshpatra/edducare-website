import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import publicService from '../api/publicService';

export default function CMSPage({ slug }) {
  const [content, setContent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchContent = async () => {
      try {
        const response = await publicService.getCMSPage(slug);
        setContent(response.data);
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to load content');
      } finally {
        setLoading(false);
      }
    };

    fetchContent();
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen pt-32 pb-20 px-4 text-center">
        <h1 className="text-4xl font-black text-slate-900 mb-4">Oops!</h1>
        <p className="text-slate-500">{error}</p>
      </div>
    );
  }

  return (
    <div className="pt-32 pb-20 px-4">
      <div className="max-w-4xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8 }}
        >
          <h1 className="text-4xl md:text-5xl font-black text-slate-900 mb-12 text-center tracking-tighter">
            {content.title}
          </h1>
          <div
            className="prose prose-slate prose-lg max-w-none 
              prose-headings:text-slate-900 prose-headings:font-black
              prose-p:text-slate-600 prose-p:leading-relaxed
              prose-strong:text-slate-900 prose-a:text-primary"
            dangerouslySetInnerHTML={{ __html: content.content }}
          />
        </motion.div>
      </div>
    </div>
  );
}
