import axios from 'axios';

const API_BASE_URL = '/api';

const getHeaders = () => ({
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
    'Content-Type': 'application/json'
});

export const superadminService = {
    getDashboardStats: () => axios.get(`${API_BASE_URL}/superadmin/dashboard`, { headers: getHeaders() }),

    // Schools
    getSchools: (page = 1, limit = 10) => axios.get(`${API_BASE_URL}/schools/manage?page=${page}&limit=${limit}`, { headers: getHeaders() }),
    updateSchool: (data: any) => axios.put(`${API_BASE_URL}/schools/manage`, data, { headers: getHeaders() }),
    createSchool: (data: any) => {
        const headers: any = getHeaders();
        if (data instanceof FormData) {
            delete headers['Content-Type']; // Let axios set it for multipart
        }
        return axios.post(`${API_BASE_URL}/schools/manage`, data, { headers });
    },

    // Packages
    getPackages: () => axios.get(`${API_BASE_URL}/superadmin/packages`, { headers: getHeaders() }),
    createPackage: (data: any) => axios.post(`${API_BASE_URL}/superadmin/packages`, data, { headers: getHeaders() }),
    updatePackage: (data: any) => axios.put(`${API_BASE_URL}/superadmin/packages`, data, { headers: getHeaders() }),
    deletePackage: (id: number) => axios.delete(`${API_BASE_URL}/superadmin/packages?id=${id}`, { headers: getHeaders() }),
};
