<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../includes/enhanced_auth.php';
require_once '../../includes/response.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['username', 'password', 'school_code'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        Response::error("Field '$field' is required", 400);
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Find student with school code validation
    $query = "SELECT s.*, sc.name as school_name, sc.is_active as school_active, 
                     sc.is_blocked as school_blocked, sc.package_expires_at
              FROM students s 
              JOIN schools sc ON s.school_id = sc.id 
              WHERE (s.username = :username OR s.email = :username) 
              AND s.is_active = 1 AND sc.code = :school_code";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $input['username']);
    $stmt->bindValue(':school_code', $input['school_code']);
    $stmt->execute();
    
    $student = $stmt->fetch();
    
    if (!$student || !password_verify($input['password'], $student['password_hash'])) {
        Response::error('Invalid credentials or school code', 401);
    }
    
    // Check school status
    if (!$student['school_active'] || $student['school_blocked']) {
        Response::error('School account is inactive or blocked', 403);
    }
    
    // Check package expiry
    if ($student['package_expires_at'] && strtotime($student['package_expires_at']) < time()) {
        Response::error('School package has expired', 403);
    }
    
    // Get current enrollment details
    $enrollmentQuery = "SELECT se.class_id, se.section_id, se.roll_number, se.academic_year_id,
                               c.name as class_name, c.grade_level,
                               sec.name as section_name,
                               ay.name as academic_year_name, ay.is_current
                        FROM student_enrollments se
                        JOIN classes c ON se.class_id = c.id
                        JOIN sections sec ON se.section_id = sec.id
                        JOIN academic_years ay ON se.academic_year_id = ay.id
                        WHERE se.student_id = :student_id AND se.status = 'active'
                        ORDER BY ay.is_current DESC, ay.start_date DESC
                        LIMIT 1";
    
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':student_id', $student['id']);
    $enrollmentStmt->execute();
    
    $enrollment = $enrollmentStmt->fetch();
    
    // Generate JWT token
    $auth = new EnhancedAuth();
    $tokens = $auth->generateTokens($student['id'], 'student');
    
    // Update last login
    $updateQuery = "UPDATE students SET last_login = NOW() WHERE id = :student_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':student_id', $student['id']);
    $updateStmt->execute();
    
    // Log activity
    $logQuery = "INSERT INTO activity_logs (student_id, user_type, school_id, action, entity_type, entity_id, ip_address, user_agent) 
                 VALUES (:student_id, 'student', :school_id, 'login', 'student', :entity_id, :ip_address, :user_agent)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':student_id', $student['id']);
    $logStmt->bindValue(':school_id', $student['school_id']);
    $logStmt->bindValue(':entity_id', $student['id']);
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
    $logStmt->execute();
    
    $response = [
        'success' => true,
        'user_type' => 'student',
        'user' => [
            'id' => $student['id'],
            'username' => $student['username'],
            'email' => $student['email'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'student_id' => $student['student_id'],
            'admission_number' => $student['admission_number'],
            'caste' => $student['caste'],
            'school_id' => $student['school_id'],
            'school_name' => $student['school_name'],
            'role' => 'student',
            'permissions' => ['view_assignments', 'submit_assignments', 'view_grades', 'view_announcements', 'view_attendance']
        ],
        'tokens' => $tokens
    ];
    
    if ($enrollment) {
        $response['user']['enrollment'] = $enrollment;
    }
    
    Response::success($response, 'Student login successful');
    
} catch (Exception $e) {
    Response::error('Login failed: ' . $e->getMessage(), 500);
}
?>
