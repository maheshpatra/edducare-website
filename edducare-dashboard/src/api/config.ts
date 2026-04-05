import axios from 'axios';

/**
 * In development:  Vite proxies /api → https://edducare.com/dashboard/backend/api
 * In production:  Set BASE_URL to your actual API domain,
 *                 e.g. 'https://edducare.com/dashboard/backend/api'
 *
 * Using a relative path here keeps all requests same-origin in dev,
 * completely avoiding any CORS preflight issues.
 */
const BASE_URL = import.meta.env.VITE_API_URL ?? '/api';

const api = axios.create({
    baseURL: BASE_URL,
    headers: { 'Content-Type': 'application/json' },
    timeout: 20000,
});

// ─── Request interceptor – attach JWT token ────────────────────────────────
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) config.headers.Authorization = `Bearer ${token}`;
        return config;
    },
    (error) => Promise.reject(error)
);

// ─── Response interceptor – handle 401 ────────────────────────────────────
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;
export { BASE_URL };
