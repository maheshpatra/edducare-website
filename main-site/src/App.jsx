import React from 'react'
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Pricing from './pages/Pricing'
import Contact from './pages/Contact'
import About from './pages/About'
import CMSPage from './pages/CMSPage'

function App() {
  return (
    <Router>
      <div className="flex flex-col min-h-screen">
        <Navbar />
        <main className="flex-grow">
          <Routes>
            <Route path="/" element={<Home />} />
            <Route path="/pricing" element={<Pricing />} />
            <Route path="/contact" element={<Contact />} />
            <Route path="/about" element={<About />} />
            <Route path="/terms" element={<CMSPage slug="terms-and-conditions" />} />
            <Route path="/privacy" element={<CMSPage slug="privacy-policy" />} />
          </Routes>
        </main>
        <Footer />
      </div>
    </Router>
  )
}

export default App
