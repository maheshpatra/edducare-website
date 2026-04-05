<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../includes/enhanced_auth.php';
require_once '../../includes/response.php';
require_once '../../includes/validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('Invalid JSON format');
    }

    // Validate required fields
    $validator = new Validator();
    $validator->required($input, ['username', 'password', 'login_type']);
    
    if (!$validator->isValid()) {
        Response::error('Validation failed', 400, ['errors' => $validator->getErrors()]);
    }

    $username = trim($input['username']);
    $password = $input['password'];
    $login_type = $input['login_type']; // 'admin', 'teacher', 'student'
    $school_code = isset($input['school_code']) ? trim($input['school_code']) : null;

    // Validate login type
    if (!in_array($login_type, ['admin', 'teacher', 'student', 'super_admin'])) {
        Response::error('Invalid login type');
    }

    // School code is required for non-super admin logins
    if ($login_type !== 'super_admin' && empty($school_code)) {
        Response::error('School code is required');
    }

    $auth = new EnhancedAuth();
    
    // Determine user type for authentication
    $user_type = ($login_type === 'student') ? 'student' : 'user';
    
    $result = $auth->login($username, $password, $school_code, $user_type);
    
    if (!$result['success']) {
        Response::error($result['message'], 401);
    }

    // Additional role validation for non-student users
    if ($user_type === 'user' && $login_type !== 'super_admin') {
        $user_role = $result['user']['role'];
        
        // Map login types to allowed roles
        $roleMapping = [
            'admin' => ['admin', 'super_admin'],
            'teacher' => ['teacher', 'admin', 'super_admin'],
            'super_admin' => ['super_admin']
        ];
        
        if (!in_array($user_role, $roleMapping[$login_type])) {
            Response::error('Access denied for this user type', 403);
        }
    }

    // Get additional context based on login type
    $additionalData = [];
    
    if ($login_type === 'student') {
        $additionalData = getStudentContext($result['user']['id']);
    } elseif ($login_type === 'teacher') {
        $additionalData = getTeacherContext($result['user']['id']);
    } elseif ($login_type === 'admin') {
        $additionalData = getAdminContext($result['user']['school_id']);
    } elseif ($login_type === 'super_admin') {
        $additionalData = getSuperAdminContext();
    }

    $response = [
        'user' => array_merge($result['user'], $additionalData),
        'user_type' => $result['user_type'],
        'login_type' => $login_type,
        'tokens' => $result['tokens'],
        'login_time' => date('Y-m-d H:i:s'),
        'session_expires_at' => date('Y-m-d H:i:s', time() + $result['tokens']['expires_in'])
    ];

    Response::success($response, 'Login successful');

} catch (Exception $e) {
    error_log('Enhanced login error: ' . $e->getMessage());
    Response::error('Login failed. Please try again.', 500);
}

function getStudentContext($studentId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Get current class and section
        $query = "SELECT c.name as class_name, c.section, c.academic_year,
                         s.name as subject_name, s.code as subject_code
                  FROM student_classes sc
                  JOIN classes c ON sc.class_id = c.id
                  LEFT JOIN class_subjects cs ON c.id = cs.class_id
                  LEFT JOIN subjects s ON cs.subject_id = s.id
                  WHERE sc.student_id = :student_id AND sc.is_active = 1
                  ORDER BY sc.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':student_id', $studentId);
        $stmt->execute();
        $classData = $stmt->fetchAll();
        
        $context = [
            'current_class' => null,
            'subjects' => [],
            'dashboard_stats' => []
        ];
        
        if (!empty($classData)) {
            $context['current_class'] = [
                'name' => $classData[0]['class_name'],
                'section' => $classData[0]['section'],
                'academic_year' => $classData[0]['academic_year']
            ];
            
            foreach ($classData as $row) {
                if ($row['subject_name']) {
                    $context['subjects'][] = [
                        'name' => $row['subject_name'],
                        'code' => $row['subject_code']
                    ];
                }
            }
        }
        
        // Get dashboard statistics
        $statsQuery = "SELECT 
                        (SELECT COUNT(*) FROM assignment_submissions WHERE student_id = :student_id) as submitted_assignments,
                        (SELECT COUNT(*) FROM attendance WHERE student_id = :student_id AND status = 'present') as present_days,
                        (SELECT COUNT(*) FROM attendance WHERE student_id = :student_id AND status = 'absent') as absent_days";
        
        $stmt = $conn->prepare($statsQuery);
        $stmt->bindValue(':student_id', $studentId);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        $context['dashboard_stats'] = $stats;
        
        return $context;
        
    } catch (Exception $e) {
        return ['error' => 'Failed to load student context'];
    }
}

function getTeacherContext($userId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Get assigned classes and subjects
        $query = "SELECT DISTINCT c.name as class_name, c.section, s.name as subject_name
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.id
                  JOIN subjects s ON cs.subject_id = s.id
                  WHERE cs.teacher_id = :user_id AND c.is_active = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        $assignments = $stmt->fetchAll();
        
        // Get teacher statistics
        $statsQuery = "SELECT 
                        (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.teacher_id = :user_id) as total_classes,
                        (SELECT COUNT(DISTINCT cs.subject_id) FROM class_subjects cs WHERE cs.teacher_id = :user_id) as total_subjects,
                        (SELECT COUNT(*) FROM assignments a JOIN class_subjects cs ON a.class_id = cs.class_id WHERE cs.teacher_id = :user_id) as total_assignments";
        
        $stmt = $conn->prepare($statsQuery);
        $stmt->bindValue(':user_id', $userId);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return [
            'assigned_classes' => $assignments,
            'dashboard_stats' => $stats
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Failed to load teacher context'];
    }
}

function getAdminContext($schoolId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $query = "SELECT 
                    (SELECT COUNT(*) FROM students WHERE school_id = :school_id AND is_active = 1) as total_students,
                    (SELECT COUNT(*) FROM users WHERE school_id = :school_id AND is_active = 1) as total_teachers,
                    (SELECT COUNT(*) FROM classes WHERE school_id = :school_id AND is_active = 1) as total_classes,
                    (SELECT COUNT(*) FROM subjects WHERE school_id = :school_id AND is_active = 1) as total_subjects";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':school_id', $schoolId);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return [
            'school_stats' => $stats,
            'dashboard_stats' => $stats
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Failed to load admin context'];
    }
}

function getSuperAdminContext() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $query = "SELECT 
                    (SELECT COUNT(*) FROM schools WHERE is_active = 1) as total_schools,
                    (SELECT COUNT(*) FROM students WHERE is_active = 1) as total_students,
                    (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
                    (SELECT COUNT(*) FROM schools WHERE is_blocked = 1) as blocked_schools";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        return [
            'system_stats' => $stats,
            'dashboard_stats' => $stats
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Failed to load super admin context'];
    }
}
?>
