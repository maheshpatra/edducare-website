import React, { createContext, useContext, useState, useEffect } from 'react';
import axios from 'axios';

const SchoolContext = createContext();

export const SchoolProvider = ({ children }) => {
  const [activeSchool, setActiveSchool] = useState(null);
  const [theme, setTheme] = useState(null);
  const [stats, setStats] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const API_BASE = "https://edducare.finafid.org/api/public";
  const FILE_BASE = "https://edducare.finafid.org/api/uploads";
  const FILE_BASE_NEW = "https://edducare.finafid.org/uploads";

  useEffect(() => {
    const initSchool = async () => {
      try {
        setLoading(true);
        // 1. Load config.json
        const configResp = await axios.get('/config.json');
        const { school_code } = configResp.data;

        // 2. Fetch School Details
        const detailsResp = await axios.get(`${API_BASE}/school_details?code=${school_code}`);
        if (!detailsResp.data.success) throw new Error("Failed to load school details");

        const school = detailsResp.data.data;
        setActiveSchool(school);

        // 3. Fetch Theme and stats
        const themeResp = await axios.get(`${API_BASE}/theme?school_id=${school.id}`);
        if (themeResp.data.success) {
          setTheme(themeResp.data.data.theme);
          setStats(themeResp.data.data.stats);
        }

        setLoading(false);
      } catch (err) {
        console.error("Initialization error:", err);
        setError(err.message);
        setLoading(false);
      }
    };

    initSchool();
  }, []);

  // Set CSS variables for colors when theme loads
  useEffect(() => {
    if (theme) {
      document.documentElement.style.setProperty('--primary-color', theme.primary_color || '#3b82f6');
      document.documentElement.style.setProperty('--secondary-color', theme.secondary_color || '#1e3a8a');

      if (theme.font_family) {
        document.body.style.fontFamily = theme.font_family;
      }
    }
  }, [theme]);

  if (loading) return (
    <div className="flex items-center justify-center min-h-screen bg-slate-50">
      <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
    </div>
  );

  if (error) return (
    <div className="flex flex-col items-center justify-center min-h-screen">
      <h1 className="text-2xl font-bold text-red-500 mb-4">Error loading website</h1>
      <p className="text-slate-600 mb-6">{error}</p>
      <button
        onClick={() => window.location.reload()}
        className="px-6 py-2 bg-blue-600 text-white rounded-lg"
      >
        Retry
      </button>
    </div>
  );

  return (
    <SchoolContext.Provider value={{ activeSchool, theme, stats, API_BASE, FILE_BASE, FILE_BASE_NEW }}>
      {children}
    </SchoolContext.Provider>
  );
};

export const useSchool = () => useContext(SchoolContext);
