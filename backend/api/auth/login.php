<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$jwt = new JWT();

$username = trim($input['username']);
$password = $input['password'];

try {
    // Check in users table
    $userQuery = "SELECT u.*, ur.name as role_name, ur.permissions, s.name as school_name, s.is_active as school_active, s.is_blocked as school_blocked 
                  FROM users u 
                  JOIN user_roles ur ON u.role_id = ur.id 
                  LEFT JOIN schools s ON u.school_id = s.id 
                  WHERE u.username = :username AND u.is_active = 1";
    
    $stmt = $db->prepare($userQuery);
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    
    $user = $stmt->fetch();
    
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Check school status for non-super admin users
    if ($user['role_name'] !== 'super_admin') {
        if (!$user['school_active'] || $user['school_blocked']) {
            http_response_code(403);
            echo json_encode(['error' => 'School account is inactive or blocked']);
            exit;
        }
    }

    // Create JWT payload for user
    $payload = [
        'user_id' => $user['id'],
        'user_type' => 'user',
        'username' => $user['username'],
        'role' => $user['role_name'],
        'school_id' => $user['school_id'],
        'iat' => time(),
        'exp' => time() + (45 * 24 * 60 * 60) // 45 days
    ];
    
    $token = $jwt->encode($payload);
    
    // Store token in database
    $tokenQuery = "INSERT INTO auth_tokens (user_id, user_type, token, type, expires_at) 
                   VALUES (:user_id, 'user', :token, 'access', FROM_UNIXTIME(:expires_at))";
    $tokenStmt = $db->prepare($tokenQuery);
    $tokenStmt->bindValue(':user_id', $user['id']);
    $tokenStmt->bindValue(':token', $token);
    $tokenStmt->bindValue(':expires_at', $payload['exp']);
    $tokenStmt->execute();
    
    // Update last login
    $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':user_id', $user['id']);
    $updateStmt->execute();
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role_name'],
            'school_id' => $user['school_id'],
            'school_name' => $user['school_name'],
            'permissions' => json_decode($user['permissions'], true),
            'user_type' => 'user'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed']);
}
?>
