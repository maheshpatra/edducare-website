import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.tsx'
import './index.css'

const theme = localStorage.getItem('theme') || 'dark';
const accentColor = localStorage.getItem('accentColor') || '#6366f1';
document.documentElement.setAttribute('data-theme', theme);
document.documentElement.style.setProperty('--primary', accentColor);

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
