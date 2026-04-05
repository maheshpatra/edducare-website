import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: '/',
  server: {
    port: 3002,
    proxy: {
      // All /api/* requests are proxied to the live server → bypasses CORS in dev
      '/api': {
        target: 'https://edducare.finafid.org',
        changeOrigin: true,
        secure: true,
        // The backend PHP files are at /dashboard/backend/api/...
        // so /api/auth/login  →  https://edducare.com/dashboard/backend/api/auth/login
        rewrite: (path) => path.replace(/^\/api/, '/api'),
      },
    },
  },
})
