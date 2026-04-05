<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'message' => 'Edducare API',
    'version' => '2.0.0',
    'documentation' => 'https://your-domain.com/api-docs',
    'endpoints' => [
        'authentication' => [
            'POST /api/auth/login' => 'User login with JWT token',
            'POST /api/auth/logout' => 'User logout and token revocation',
            'POST /api/auth/send-otp' => 'Send OTP for email verification',
            'POST /api/auth/verify-otp' => 'Verify OTP code',
            'POST /api/auth/forgot-password' => 'Request password reset',
            'POST /api/auth/reset-password' => 'Reset password with token'
        ],
        'profile_management' => [
            'POST /api/profile/upload-picture' => 'Upload profile picture',
            'GET /api/profile/picture' => 'Get profile picture',
            'PUT /api/profile/update' => 'Update user profile'
        ],
        'student_management' => [
            'GET /api/students/list' => 'List students with filters',
            'POST /api/students/create' => 'Create new student',
            'PUT /api/students/update' => 'Update student information',
            'DELETE /api/students/delete' => 'Delete student record',
            'GET /api/students/routine' => 'Get student class routine/timetable',
            'POST /api/students/promote' => 'Promote students to next academic year',
            'GET /api/students/promotion-history' => 'View promotion history',
            'GET /api/students/class-history' => 'View complete class history'
        ],
        'class_management' => [
            'GET /api/classes/manage' => 'List classes with sections and statistics',
            'POST /api/classes/manage' => 'Create new class with sections',
            'PUT /api/classes/manage' => 'Update class information',
            'DELETE /api/classes/manage' => 'Delete class (with safety checks)',
            'GET /api/sections/manage' => 'List sections for a class',
            'POST /api/sections/manage' => 'Create new section',
            'PUT /api/sections/manage' => 'Update section details',
            'DELETE /api/sections/manage' => 'Delete section'
        ],
        'academic_management' => [
            'GET /api/academic/years' => 'List academic years',
            'POST /api/academic/years' => 'Create new academic year',
            'PUT /api/academic/years' => 'Update academic year (set current)'
        ],
        'attendance_management' => [
            'POST /api/attendance/mark' => 'Mark student attendance',
            'GET /api/attendance/report' => 'Generate attendance reports',
            'GET /api/attendance/student' => 'Get individual student attendance'
        ],
        'assignment_management' => [
            'GET /api/assignments/list' => 'List assignments with filters',
            'POST /api/assignments/create' => 'Create assignment with file upload',
            'PUT /api/assignments/edit' => 'Edit assignment details',
            'DELETE /api/assignments/delete' => 'Delete assignment',
            'GET /api/assignments/view' => 'View assignment details',
            'GET /api/assignments/submissions' => 'View assignment submissions',
            'GET /api/assignments/download' => 'Download assignment files'
        ],
        'fee_management' => [
            'GET /api/fees/manage' => 'Fee collection and reports',
            'POST /api/fees/manage' => 'Record fee payment',
            'GET /api/fees/student' => 'Get student fee details',
            'GET /api/fees/reports' => 'Generate fee collection reports'
        ],
        'library_management' => [
            'GET /api/library/books' => 'List library books',
            'POST /api/library/books' => 'Add new book',
            'POST /api/library/issue' => 'Issue book to student',
            'POST /api/library/return' => 'Return book',
            'GET /api/library/transactions' => 'View library transactions'
        ],
        'exam_management' => [
            'GET /api/exams/list' => 'List exams',
            'POST /api/exams/create' => 'Create new exam',
            'POST /api/exams/results' => 'Enter exam results',
            'GET /api/exams/reports' => 'Generate exam reports'
        ],
        'inventory_management' => [
            'GET /api/inventory/items' => 'List inventory items',
            'POST /api/inventory/items' => 'Add inventory item',
            'PUT /api/inventory/items' => 'Update inventory item',
            'GET /api/inventory/categories' => 'List inventory categories'
        ],
        'timetable_management' => [
            'GET /api/timetable/view' => 'View class timetable',
            'POST /api/timetable/create' => 'Create timetable entry',
            'PUT /api/timetable/update' => 'Update timetable',
            'DELETE /api/timetable/delete' => 'Delete timetable entry'
        ],
        'reports_analytics' => [
            'GET /api/reports/analytics' => 'School analytics dashboard',
            'GET /api/reports/attendance' => 'Attendance reports',
            'GET /api/reports/academic' => 'Academic performance reports',
            'GET /api/reports/financial' => 'Financial reports'
        ],
        'school_administration' => [
            'GET /api/schools/manage' => 'List schools (Super Admin)',
            'POST /api/schools/manage' => 'Create school (Super Admin)',
            'PUT /api/schools/manage' => 'Update school settings (Super Admin)',
            'GET /api/schools/packages' => 'List subscription packages'
        ],
        'notifications' => [
            'GET /api/notifications/list' => 'List user notifications',
            'POST /api/notifications/send' => 'Send notification',
            'PUT /api/notifications/read' => 'Mark notification as read'
        ],
        'announcements' => [
            'GET /api/announcements/list' => 'List announcements',
            'POST /api/announcements/create' => 'Create announcement',
            'PUT /api/announcements/update' => 'Update announcement',
            'DELETE /api/announcements/delete' => 'Delete announcement'
        ]
    ],
    'authentication' => [
        'type' => 'Bearer Token (JWT)',
        'header' => 'Authorization: Bearer <token>',
        'token_expiry' => '24 hours',
        'refresh_mechanism' => 'Re-login required'
    ],
    'user_roles' => [
        'super_admin' => [
            'description' => 'Full system access across all schools',
            'permissions' => ['manage_schools', 'view_all_data', 'system_settings']
        ],
        'school_admin' => [
            'description' => 'Complete school management access',
            'permissions' => ['manage_students', 'manage_teachers', 'view_reports', 'manage_classes']
        ],
        'class_teacher' => [
            'description' => 'Class and student management',
            'permissions' => ['manage_assignments', 'mark_attendance', 'view_student_data']
        ],
        'payment_teacher' => [
            'description' => 'Fee collection and financial management',
            'permissions' => ['collect_fees', 'generate_receipts', 'view_financial_reports']
        ]
    ],
    'features' => [
        'email_system' => [
            'welcome_emails' => 'Professional welcome emails for new users',
            'otp_verification' => 'Email-based OTP verification system',
            'password_reset' => 'Secure password reset via email',
            'notifications' => 'Email notifications for important events'
        ],
        'file_management' => [
            'profile_pictures' => 'User profile picture upload and management',
            'assignment_files' => 'File attachments for assignments',
            'secure_downloads' => 'Role-based file access control',
            'file_validation' => 'MIME type and size validation'
        ],
        'academic_features' => [
            'student_promotion' => 'Individual and bulk student promotion',
            'class_management' => 'Complete class and section management',
            'academic_years' => 'Multi-year academic management',
            'routine_management' => 'Student timetable and routine system'
        ],
        'security' => [
            'jwt_authentication' => 'Secure JWT-based authentication',
            'role_based_access' => 'Granular permission system',
            'activity_logging' => 'Complete audit trail',
            'rate_limiting' => 'API rate limiting for security'
        ]
    ],
    'response_format' => [
        'success' => [
            'success' => true,
            'data' => 'Response data',
            'message' => 'Success message (optional)'
        ],
        'error' => [
            'error' => 'Error type',
            'message' => 'Error description'
        ],
        'pagination' => [
            'current_page' => 'Current page number',
            'per_page' => 'Items per page',
            'total' => 'Total items',
            'total_pages' => 'Total pages'
        ]
    ],
    'status_codes' => [
        '200' => 'Success',
        '201' => 'Created',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '429' => 'Too Many Requests',
        '500' => 'Internal Server Error'
    ],
    'contact' => [
        'support_email' => 'support@schoolerp.com',
        'documentation' => 'https://docs.schoolerp.com',
        'github' => 'https://github.com/schoolerp/api'
    ]
]);
?>
