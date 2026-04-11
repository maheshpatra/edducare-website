import api from './config';

export const publicService = {
    getCMSPage: (slug) => api.get(`/cms?slug=${slug}`),
    getPackages: () => api.get('/packages'),
    submitContact: (data) => api.post('/submit_contact', data),
    submitAdmission: (data) => api.post('/submit_admission', data),
};

export default publicService;
