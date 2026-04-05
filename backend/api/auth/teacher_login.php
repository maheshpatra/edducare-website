<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/jwt.php';
require_once '../../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password']) || !isset($input['school_code'])) {
    Response::error('Username, password, and school code are required', 400);
}

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

$username = trim($input['username']);
$password = $input['password'];
$school_code = trim($input['school_code']);

try {
    // Find teacher with school validation
    $query = "SELECT u.*, ur.name as role_name, ur.permissions, s.name as school_name, s.is_active as school_active, s.is_blocked as school_blocked
              FROM users u 
              JOIN user_roles ur ON u.role_id = ur.id 
              JOIN schools s ON u.school_id = s.id 
              WHERE (u.username = :username OR u.email = :username OR u.employee_id = :username) 
              AND s.code = :school_code 
              AND u.role_id IN (3, 4) 
              AND u.is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':school_code', $school_code);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        Response::error('Invalid credentials or school code', 401);
    }
    
    $teacher = $stmt->fetch();
    
    if (!password_verify($password, $teacher['password_hash'])) {
        Response::error('Invalid credentials', 401);
    }

    if (!$teacher['school_active'] || $teacher['school_blocked']) {
        Response::error('School account is inactive or blocked', 403);
    }

    // Create JWT payload
    $payload = [
        'user_id' => $teacher['id'],
        'user_type' => 'user',
        'username' => $teacher['username'],
        'role' => $teacher['role_name'],
        'employee_id' => $teacher['employee_id'],
        'school_id' => $teacher['school_id'],
        'school_code' => $school_code,
        'iat' => time(),
        'exp' => time() + (45 * 24 * 60 * 60) // 45 days
    ];
    
    $token = $jwt->encode($payload);
    
    // Store token in database
    $tokenQuery = "INSERT INTO auth_tokens (user_id, user_type, token, type, expires_at) 
                   VALUES (:user_id, 'user', :token, 'access', FROM_UNIXTIME(:expires_at))";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindValue(':user_id', $teacher['id']);
    $tokenStmt->bindValue(':token', $token);
    $tokenStmt->bindValue(':expires_at', $payload['exp']);
    $tokenStmt->execute();
    
    // Update last login
    $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':user_id', $teacher['id']);
    $updateStmt->execute();
    
    // Get teacher statistics
    $statsQuery = "SELECT 
                      (SELECT COUNT(DISTINCT cs.class_id) FROM class_subjects cs WHERE cs.teacher_id = :teacher_id) as total_classes,
                      (SELECT COUNT(DISTINCT cs.subject_id) FROM class_subjects cs WHERE cs.teacher_id = :teacher_id) as total_subjects,
                      (SELECT COUNT(*) FROM assignments WHERE teacher_id = :teacher_id) as total_assignments,
                      (SELECT COUNT(*) FROM timetables WHERE teacher_id = :teacher_id) as weekly_periods";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindValue(':teacher_id', $teacher['id']);
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
    Response::success([
        'token' => $token,
        'user' => [
            'id' => $teacher['id'],
            'username' => $teacher['username'],
            'email' => $teacher['email'],
            'first_name' => $teacher['first_name'],
            'last_name' => $teacher['last_name'],
            'employee_id' => $teacher['employee_id'],
            'role' => $teacher['role_name'],
            'school_id' => $teacher['school_id'],
            'school_name' => $teacher['school_name'],
            'school_code' => $school_code,
            'permissions' => json_decode($teacher['permissions'], true),
            'user_type' => 'user'
        ],
        'dashboard_stats' => $stats,
        'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
    ], 'Teacher login successful');
    
} catch (Exception $e) {
    error_log('Teacher login error: ' . $e->getMessage());
    Response::error('Login failed. Please try again.', 500);
}
?>
