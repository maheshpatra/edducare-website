import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useSchool } from '../context/SchoolContext';
import { resolveImagePath, DUMMY_DATA } from '../utils/helpers';
import { motion } from 'framer-motion';
import { ZoomIn, X } from 'lucide-react';

export default function Gallery() {
  const { activeSchool, API_BASE, FILE_BASE } = useSchool();
  const [gallery, setGallery] = useState([]);
  const [activeCategory, setActiveCategory] = useState('All');
  const [lightbox, setLightbox] = useState(null);

  useEffect(() => {
    const fetchGallery = async () => {
      try {
        const resp = await axios.get(`${API_BASE}/gallery?school_id=${activeSchool.id}`);
        if (resp.data.success && resp.data.data?.length) setGallery(resp.data.data);
      } catch (err) { console.error(err); }
    };
    fetchGallery();
  }, [activeSchool?.id]);

  const displayGallery = gallery.length > 0 ? gallery : DUMMY_DATA.gallery;
  const categories = ['All', ...new Set(displayGallery.map(item => item.category))];
  const filteredGallery = activeCategory === 'All' ? displayGallery : displayGallery.filter(item => item.category === activeCategory);

  const fadeInUp = {
    initial: { opacity: 0, scale: 0.95 },
    whileInView: { opacity: 1, scale: 1 },
    transition: { duration: 0.5 },
    viewport: { once: true }
  };

  const getImageSrc = (item) => {
    if (!item.image_path) return '';
    return item.image_path.startsWith('http') ? item.image_path : resolveImagePath(item.image_path, FILE_BASE);
  };

  return (
    <div className="bg-white min-h-screen">
      {/* Hero */}
      <section className="bg-slate-950 py-32 text-white relative overflow-hidden">
        <div className="blob w-80 h-80 top-10 right-10"></div>
        <div className="max-w-7xl mx-auto px-4 text-center relative z-10">
          <motion.span initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="section-label text-primary">Life at {activeSchool?.name || 'Campus'}</motion.span>
          <motion.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="text-5xl md:text-6xl font-extrabold mb-6">Photo Gallery</motion.h1>
          <motion.p initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            A visual celebration of our journey through events, activities, and achievements.
          </motion.p>
        </div>
      </section>

      {/* Filters & Grid */}
      <section className="py-24">
        <div className="max-w-7xl mx-auto px-4">
          {/* Category Filters */}
          <div className="flex flex-wrap justify-center gap-3 mb-16">
            {categories.map(cat => (
              <button 
                key={cat} 
                onClick={() => setActiveCategory(cat)}
                className={`px-6 py-2.5 rounded-full text-sm font-bold transition-all border ${
                  activeCategory === cat 
                    ? 'bg-primary text-white border-primary shadow-lg scale-105' 
                    : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>

          {/* Grid */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {filteredGallery.map((item, i) => (
              <motion.div 
                key={item.id || i} 
                {...fadeInUp} 
                transition={{ delay: i * 0.04 }}
                className="group relative overflow-hidden rounded-3xl bg-slate-100 aspect-square cursor-pointer shadow-sm hover:shadow-2xl transition-all"
                onClick={() => setLightbox(getImageSrc(item))}
              >
                <img 
                  src={getImageSrc(item)}
                  alt={item.caption} 
                  className="w-full h-full object-cover transition-all duration-700 group-hover:scale-110 grayscale-[30%] group-hover:grayscale-0"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 flex flex-col justify-end p-6">
                  <p className="text-xs font-bold uppercase tracking-widest text-white/70 mb-1">{item.category}</p>
                  <h4 className="text-white font-bold text-lg">{item.caption}</h4>
                </div>
                <div className="absolute top-4 right-4 bg-white/20 backdrop-blur-md p-2.5 rounded-xl opacity-0 group-hover:opacity-100 transition-all scale-75 group-hover:scale-100">
                  <ZoomIn className="w-5 h-5 text-white" />
                </div>
              </motion.div>
            ))}
          </div>

          {filteredGallery.length === 0 && (
            <div className="text-center py-24 text-slate-400 text-lg">No images available for this category.</div>
          )}
        </div>
      </section>

      {/* Lightbox */}
      {lightbox && (
        <div className="fixed inset-0 z-[100] bg-black/90 backdrop-blur-xl flex items-center justify-center p-4 animate-fade-in" onClick={() => setLightbox(null)}>
          <button className="absolute top-6 right-6 text-white/60 hover:text-white transition-colors" onClick={() => setLightbox(null)}>
            <X className="w-8 h-8" />
          </button>
          <img src={lightbox} alt="" className="max-w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl animate-scale-in" />
        </div>
      )}
    </div>
  );
}
