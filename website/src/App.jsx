import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { SchoolProvider } from './context/SchoolContext';
import Layout from './components/Layout';

// Pages
import Home from './pages/Home';
import About from './pages/About';
import Academics from './pages/Academics';
import Teachers from './pages/Teachers';
import Notices from './pages/Notices';
import Gallery from './pages/Gallery';
import Contact from './pages/Contact';
import Admission from './pages/Admission';
import Result from './pages/Result';

export default function App() {
  return (
    <Router>
      <SchoolProvider>
        <Layout>
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/about" element={<About />} />
            <Route path="/academics" element={<Academics />} />
            <Route path="/teachers" element={<Teachers />} />
            <Route path="/notices" element={<Notices />} />
            <Route path="/gallery" element={<Gallery />} />
            <Route path="/contact" element={<Contact />} />
            <Route path="/admission" element={<Admission />} />
            <Route path="/results" element={<Result />} />
          </Routes>
        </Layout>
      </SchoolProvider>
    </Router>
  );
}
