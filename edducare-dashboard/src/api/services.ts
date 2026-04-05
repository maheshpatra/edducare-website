/**
 * services.ts – All API service methods, exactly matching the backend PHP file paths.
 *
 * Backend structure:
 *   /api/auth/login.php           → POST  auth/login
 *   /api/students/list.php        → GET   students/list
 *   /api/students/create.php      → POST  students/create
 *   /api/students/update.php      → PUT   students/update
 *   /api/teachers/manage.php      → GET/POST/PUT/DELETE  ?action=list|details …
 *   /api/classes/manage.php       → GET/POST/PUT/DELETE
 *   /api/attendance/list.php      → GET
 *   /api/attendance/mark.php      → POST
 *   /api/attendance/report.php    → GET
 *   /api/fees/manage.php          → GET/POST
 *   /api/library/books.php        → GET/POST
 *   /api/library/issue.php        → POST
 *   /api/library/return.php       → POST
 *   /api/library/transactions.php → GET
 *   /api/assignments/list.php     → GET
 *   /api/assignments/create.php   → POST
 *   /api/assignments/edit.php     → PUT
 *   /api/assignments/delete.php   → DELETE
 *   /api/exams/list.php           → GET
 *   /api/exams/create.php         → POST
 *   /api/announcements/list.php   → GET
 *   /api/announcements/create.php → POST
 *   /api/announcements/update.php → PUT
 *   /api/announcements/delete.php → DELETE
 *   /api/analytics/dashboard.php  → GET
 *   /api/reports/analytics.php    → GET
 *   /api/academic/years.php       → GET/POST
 *   /api/profile/picture.php      → GET
 *   /api/profile/upload-picture.php → POST
 */

import api, { BASE_URL } from './config';

// ─── Auth ─────────────────────────────────────────────────────────────────────
export const authService = {
    /** POST /auth/login – returns { success, token, user } */
    login: (username: string, password: string) =>
        api.post('/auth/login', { username, password }),

    /** POST /auth/logout */
    logout: () => api.post('/auth/logout'),
};

// ─── Analytics / Dashboard ────────────────────────────────────────────────────
export const analyticsService = {
    /** GET /analytics/dashboard – returns comprehensive dashboard stats */
    getDashboard: (params?: { start_date?: string; end_date?: string; school_id?: number }) =>
        api.get('/analytics/dashboard', { params }),

    /** GET /reports/analytics */
    getReports: (params?: any) => api.get('/reports/analytics', { params }),

    /** GET /reports/attendance */
    getAttendanceReport: (params?: any) => api.get('/reports/attendance', { params }),

    /** GET /reports/advanced */
    getAdvancedReport: (params?: any) => api.get('/reports/advanced', { params }),
};

// ─── Students ─────────────────────────────────────────────────────────────────
export const studentService = {
    /**
     * GET /students/list
     * Params: page, limit, search, class_id, section_id, caste, school_id
     * Returns: { success, data: Student[], pagination: { current_page, per_page, total, total_pages } }
     */
    list: (params?: {
        page?: number;
        limit?: number;
        search?: string;
        class_id?: number | string;
        section_id?: number | string;
        caste?: string;
    }) => api.get('/students/list', { params }),

    /** POST /students/create */
    create: (data: {
        first_name: string;
        last_name: string;
        email?: string;
        phone?: string;
        gender?: string;
        dob?: string;
        class_id?: number | string;
        section_id?: number | string;
        father_name?: string;
        mother_name?: string;
        address?: string;
        caste?: string;
    } | FormData) => {
        return api.post('/students/create', data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined);
    },

    /** PUT /students/update */
    update: (data: { id: number;[key: string]: any }) => api.put('/students/update', data),

    /** DELETE /students/delete?id=X */
    delete: (id: number) => api.delete(`/students/delete?id=${id}`),

    /** GET /students/routine */
    getRoutine: (student_id?: number) => api.get('/students/routine', { params: { student_id } }),
};

// ─── Teachers ─────────────────────────────────────────────────────────────────
export const teacherService = {
    /**
     * GET /teachers/manage?action=list
     * Returns: { success, data: { teachers, pagination } }
     */
    list: (params?: {
        page?: number;
        limit?: number;
        search?: string;
        is_active?: number;
        teacher_type?: string;
    }) => api.get('/teachers/manage', { params: { action: 'list', ...params } }),

    /** GET /teachers/manage?action=details&teacher_id=X */
    details: (teacherId: number) =>
        api.get('/teachers/manage', { params: { action: 'details', teacher_id: teacherId } }),

    /**
     * POST /teachers/manage – create teacher
     * Required: first_name, last_name, email, phone, role_id
     */
    create: (data: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        role_id: number;
        subject_specialization?: string;
        qualification?: string;
        experience_years?: number;
        address?: string;
        joining_date?: string;
    } | FormData) => {
        return api.post('/teachers/manage', data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined);
    },

    /** PUT /teachers/manage */
    update: (data: { id: number;[key: string]: any }) => api.put('/teachers/manage', data),

    /** DELETE /teachers/manage?id=X */
    delete: (id: number) => api.delete(`/teachers/manage?id=${id}`),
};

// ─── Classes & Sections ───────────────────────────────────────────────────────
export const classService = {
    /**
     * GET /classes/manage
     * Returns: { success, data: Class[] }
     * Each Class: { id, name, grade_level, description, school_id,
     *               section_count, student_count, class_teacher_id, … }
     */
    list: (params?: any) => api.get('/classes/manage', { params }),

    /**
     * POST /classes/manage
     * Required: name, grade_level, academic_year_id
     * Optional: sections (array of { name, capacity })
     */
    create: (data: {
        name: string;
        grade_level: number | string;
        academic_year_id?: number | string;
        description?: string;
        sections?: Array<{ name: string; capacity?: number }>;
    }) => api.post('/classes/manage', data),

    /** PUT /classes/manage */
    update: (data: { id: number;[key: string]: any }) => api.put('/classes/manage', data),

    /** DELETE /classes/manage?id=X */
    delete: (id: number) => api.delete(`/classes/manage?id=${id}`),

    /** GET /classes/manage?action=sections&class_id=X */
    sections: (classId: number | string) =>
        api.get('/classes/manage', { params: { action: 'sections', class_id: classId } }),

    /** POST /classes/manage?action=create_section */
    addSection: (data: { class_id: number; name: string; capacity?: number }) =>
        api.post('/classes/manage?action=create_section', data),

    /** DELETE /classes/manage?action=delete_section&id=X */
    deleteSection: (id: number) =>
        api.delete(`/classes/manage?action=delete_section&id=${id}`),
};

// ─── Academic Years ───────────────────────────────────────────────────────────
export const academicService = {
    /** GET /academic/years */
    list: () => api.get('/academic/years'),

    /** POST /academic/years */
    create: (data: { name: string; start_date: string; end_date: string }) =>
        api.post('/academic/years', data),

    /** PUT /academic/years */
    update: (data: { id: number;[key: string]: any }) => api.put('/academic/years', data),

    /** GET /academic/years (returns current year) */
    current: () => api.get('/academic/years', { params: { current: 1 } }),
};

// ─── Subjects ────────────────────────────────────────────────────────────────
export const subjectService = {
    /** GET /subjects/manage */
    list: (params?: { grade_level?: number | string; school_id?: number | string }) =>
        api.get('/subjects/manage', { params }),

    /** GET /subjects/manage?action=teachers – get available teachers for subjects */
    availableTeachers: (params?: { school_id?: number | string }) =>
        api.get('/subjects/manage', { params: { action: 'teachers', ...params } }),

    /** POST /subjects/manage */
    create: (data: any) => api.post('/subjects/manage', data),

    /** PUT /subjects/manage */
    update: (data: any) => api.put('/subjects/manage', data),

    /** DELETE /subjects/manage?subject_id=X */
    delete: (id: number) => api.delete(`/subjects/manage?subject_id=${id}`),
};

// ─── Attendance ───────────────────────────────────────────────────────────────
export const attendanceService = {
    /**
     * GET /attendance/list?class_id=X&section_id=Y
     * Returns: { success, students: AttendanceStudent[] }
     */
    listStudents: (params: { class_id: number | string; section_id: number | string; date?: string }) =>
        api.get('/attendance/list', { params }),

    /**
     * POST /attendance/mark
     * Body: { date, attendance_data: [{ student_id, status, remarks? }] }
     */
    mark: (data: {
        date: string;
        attendance_data: Array<{ student_id: number; status: 'present' | 'absent' | 'late' | 'half_day'; remarks?: string }>;
    }) => api.post('/attendance/mark', data),

    /** GET /attendance/report */
    report: (params?: { class_id?: string; section_id?: string; start_date?: string; end_date?: string }) =>
        api.get('/attendance/report', { params }),

    /** GET /attendance/student?student_id=X */
    studentAttendance: (params: { student_id: number }) =>
        api.get('/attendance/student', { params }),

    /** GET /attendance/advanced_report */
    advancedReport: (params?: any) => api.get('/attendance/advanced_report', { params }),
};

// ─── Fees ─────────────────────────────────────────────────────────────────────
export const feeService = {
    /**
     * GET /fees/manage
     * Returns: { success, data: FeeCategory[] } (collection summary)
     * With ?student_id=X → returns student's fee details
     */
    getAll: (params?: { student_id?: number | string; action?: string }) => api.get('/fees/manage', { params }),

    /**
     * POST /fees/manage – record a payment
     * Required: student_id, fee_assignment_id, amount_paid, payment_method
     */
    record: (data: {
        student_id: number | string;
        fee_assignment_id: number | string;
        amount_paid: number | string;
        payment_method: 'cash' | 'upi' | 'bank_transfer' | 'cheque';
        payment_date?: string;
        transaction_id?: string;
        remarks?: string;
    }) => api.post('/fees/manage', data),

    /** GET /fees/manage?student_id=X */
    studentFees: (studentId: number) => api.get('/fees/manage', { params: { student_id: studentId } }),
};

// ─── Library ──────────────────────────────────────────────────────────────────
export const libraryService = {
    /** GET /library/books */
    books: (params?: { search?: string; category?: string; page?: number }) =>
        api.get('/library/books', { params }),

    /** POST /library/books */
    addBook: (data: {
        title: string;
        author?: string;
        isbn?: string;
        category?: string;
        total_copies?: number | string;
    }) => api.post('/library/books', data),

    /** POST /library/issue */
    issue: (data: {
        book_id: number | string;
        student_id: number | string;
        due_date: string;
    }) => api.post('/library/issue', data),

    /** POST /library/return */
    return: (data: { transaction_id: number | string }) => api.post('/library/return', data),

    /** GET /library/transactions */
    transactions: (params?: { student_id?: number; status?: string; page?: number }) =>
        api.get('/library/transactions', { params }),
};

// ─── Assignments ──────────────────────────────────────────────────────────────
export const assignmentService = {
    /**
     * GET /assignments/list
     * Returns: { success, data: Assignment[] }
     */
    list: (params?: { class_id?: string; subject_id?: string; page?: number }) =>
        api.get('/assignments/list', { params }),

    /** POST /assignments/create */
    create: (data: {
        title: string;
        subject_id?: number | string;
        class_id?: number | string;
        due_date?: string;
        max_marks?: number | string;
        description?: string;
    }) => api.post('/assignments/create', data),

    /** PUT /assignments/edit */
    edit: (data: { id: number;[key: string]: any }) => api.put('/assignments/edit', data),

    /** DELETE /assignments/delete?id=X */
    delete: (id: number) => api.delete(`/assignments/delete?id=${id}`),

    /** GET /assignments/view?id=X */
    view: (id: number) => api.get('/assignments/view', { params: { id } }),

    /** GET /assignments/submissions?assignment_id=X */
    submissions: (params: { assignment_id: number }) =>
        api.get('/assignments/submissions', { params }),
};

// ─── Exams ────────────────────────────────────────────────────────────────────
export const examService = {
    /**
     * GET /exams/list
     * Returns: { success, data: Exam[] }
     */
    list: (params?: { class_id?: string; academic_year_id?: string }) =>
        api.get('/exams/list', { params }),

    /** POST /exams/create */
    create: (data: {
        name: string;
        exam_type?: string;
        class_id?: number | string;
        start_date?: string;
        end_date?: string;
        academic_year_id?: number | string;
    }) => api.post('/exams/create', data),
};

// ─── Announcements ────────────────────────────────────────────────────────────
export const announcementService = {
    /**
     * GET /announcements/list
     * Returns: { success, data: Announcement[], pagination }
     */
    list: (params?: { priority?: string; search?: string; page?: number; limit?: number }) =>
        api.get('/announcements/list', { params }),

    /** POST /announcements/create */
    create: (data: {
        title: string;
        content: string;
        target_audience: 'all' | 'students' | 'teachers' | 'parents' | 'specific_class';
        priority: 'low' | 'medium' | 'high' | 'urgent';
        class_id?: number | string;
        section_id?: number | string;
        expires_at?: string;
        is_published?: boolean;
    }) => api.post('/announcements/create', data),

    /** PUT /announcements/update */
    update: (data: { id: number;[key: string]: any }) => api.put('/announcements/update', data),

    /** DELETE /announcements/delete?id=X */
    delete: (id: number) => api.delete(`/announcements/delete?id=${id}`),
};

// ─── Profile ──────────────────────────────────────────────────────────────────
export const profileService = {
    /** PUT /profile/update */
    update: (data: { first_name?: string; last_name?: string; email?: string; phone?: string; current_password?: string; new_password?: string }) =>
        api.put('/profile/update', data),

    /** GET /profile/picture */
    getPicture: () => api.get('/profile/picture'),

    /** POST /profile/upload-picture (multipart/form-data) */
    uploadPicture: (formData: FormData) =>
        api.post('/profile/upload-picture', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
};

// ─── Schools ──────────────────────────────────────────────────────────────────
export const schoolService = {
    /** GET /schools/manage */
    list: () => api.get('/schools/manage'),

    /** POST /schools/create */
    create: (data: any) => api.post('/schools/create', data),

    /** GET /schools/profile – get specific school details */
    getProfile: (id?: number) => api.get('/schools/profile', { params: { id } }),

    /** POST /schools/profile – update specific school details */
    updateProfile: (data: any) => api.post('/schools/profile', data),
};

// ─── Website Settings ─────────────────────────────────────────────────────────
export const websiteService = {
    /** GET /website/theme – get school website theme */
    getTheme: () => api.get('/website/theme'),

    /** POST /website/theme – update school website theme (supports multipart for images) */
    updateTheme: (data: FormData | Record<string, any>) => {
        if (data instanceof FormData) {
            return api.post('/website/theme', data, { headers: { 'Content-Type': 'multipart/form-data' } });
        }
        return api.post('/website/theme', data);
    },

    /** GET /website/gallery – get school gallery images */
    getGallery: () => api.get('/website/gallery'),

    /** POST /website/gallery – upload gallery image */
    addGalleryImage: (data: FormData) =>
        api.post('/website/gallery', data, { headers: { 'Content-Type': 'multipart/form-data' } }),

    /** DELETE /website/gallery?id=X */
    deleteGalleryImage: (id: number) => api.delete(`/website/gallery?id=${id}`),

    /** GET /website/stats – get school stats */
    getStats: () => api.get('/website/stats'),

    /** POST /website/stats – create/update stat */
    saveStat: (data: { id?: number; label: string; value: string; icon?: string; sort_order?: number }) =>
        api.post('/website/stats', data),

    /** DELETE /website/stats?id=X */
    deleteStat: (id: number) => api.delete(`/website/stats?id=${id}`),

    /** GET /website/admission_config */
    getAdmissionConfig: () => api.get('/website/admission_config'),

    /** POST /website/admission_config */
    updateAdmissionConfig: (data: any) => api.post('/website/admission_config', data),

    /** GET /website/email_config */
    getEmailConfig: () => api.get('/website/email_config'),

    /** POST /website/email_config */
    updateEmailConfig: (data: any) => api.post('/website/email_config', data),

    /** GET /website/contact_list */
    listContactMessages: (params?: { page?: number; limit?: number; search?: string; status?: string }) => 
        api.get('/website/contact_list', { params }),
};

// ─── Admissions ───────────────────────────────────────────────────────────────
export const admissionsService = {
    /** GET /admissions/list – list all admission requests */
    list: (params?: { page?: number; limit?: number; search?: string; status?: string }) =>
        api.get('/admissions/list', { params }),

    /** GET /admissions/get_by_tracking – find one by tracking ID */
    getByTracking: (tracking_id: string) =>
        api.get('/admissions/get_by_tracking', { params: { tracking_id } }),
};

// ─── Reports ───────────────────────────────────────────────────────────────────
export const reportService = {
    /** GET /reports/generate – triggers download or returns report content */
    generate: (params: { 
        type: 'attendance' | 'financial' | 'academic' | 'enrollment' | 'teacher' | 'library';
        format: 'pdf' | 'excel' | 'csv' | 'json';
        dateFrom?: string;
        dateTo?: string;
        classId?: number | string;
        sectionId?: number | string;
    }) => {
        const token = localStorage.getItem('auth_token');
        const queryParams = { ...params, token };
        const queryString = new URLSearchParams(queryParams as any).toString();
        
        // Use the absolute URL if BASE_URL is set, otherwise relative
        let baseUrl = BASE_URL;
        if (baseUrl === '/api') {
            // Fallback to absolute if needed, or stick to /api relative
            baseUrl = '/api';
        }

        return `${baseUrl}/reports/generate?${queryString}`;
    }
};

// ─── Timetable (if endpoint exists) ───────────────────────────────────────────
export const timetableService = {
    /** GET timetable for a class */
    view: (params?: { class_id?: string; section_id?: string; academic_year_id?: string }) =>
        api.get('/timetable/manage', { params: { action: 'class_timetable', ...params } }),
    create: (data: any) => api.post('/timetable/manage', data),
    update: (data: any) => api.put('/timetable/manage', data),
    delete: (id: number) => api.delete(`/timetable/manage?timetable_id=${id}`),
};
