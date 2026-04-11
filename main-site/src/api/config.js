import axios from 'axios';

export const API_BASE_URL = import.meta.env.VITE_PUBLIC_API_URL || 'https://edducare.finafid.org/api/main_site';

const api = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        'Content-Type': 'application/json',
    },
    timeout: 15000,
});

export default api;
