<?php
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class EnhancedAuth {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function login($username, $password, $school_code = null, $user_type = 'user') {
        try {
            if ($user_type === 'student') {
                return $this->loginStudent($username, $password, $school_code);
            } else {
                return $this->loginUser($username, $password, $school_code);
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    private function loginUser($username, $password, $school_code) {
        $query = "SELECT u.*, ur.name as role_name, ur.permissions, s.name as school_name, s.is_active as school_active, s.is_blocked as school_blocked, s.package_expires_at
                 FROM users u 
                 JOIN user_roles ur ON u.role_id = ur.id 
                 LEFT JOIN schools s ON u.school_id = s.id 
                 WHERE (u.username = :username OR u.email = :username) AND u.is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Check if super admin
        if ($user['role_name'] !== 'super_admin') {
            // Validate school code for non-super admin users
            if (!$school_code) {
                return ['success' => false, 'message' => 'School code is required'];
            }

            // Check school status
            if (!$user['school_active'] || $user['school_blocked']) {
                return ['success' => false, 'message' => 'School account is inactive or blocked'];
            }

            // Check package expiry
            if ($user['package_expires_at'] && strtotime($user['package_expires_at']) < time()) {
                return ['success' => false, 'message' => 'School package has expired'];
            }
        }

        // Generate tokens
        $tokens = $this->generateTokens($user['id'], 'user');
        
        // Update last login
        $this->updateLastLogin($user['id'], 'user');

        // Log activity
        $this->logActivity($user['id'], null, 'user', $user['school_id'], 'login', 'user', $user['id']);

        return [
            'success' => true,
            'user_type' => 'user',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role_name'],
                'school_id' => $user['school_id'],
                'school_name' => $user['school_name'],
                'permissions' => json_decode($user['permissions'], true)
            ],
            'tokens' => $tokens
        ];
    }

    private function loginStudent($username, $password, $school_code) {
        if (!$school_code) {
            return ['success' => false, 'message' => 'School code is required for student login'];
        }

        $query = "SELECT s.*, sc.name as school_name, sc.is_active as school_active, sc.is_blocked as school_blocked, sc.package_expires_at
                 FROM students s 
                 JOIN schools sc ON s.school_id = sc.id 
                 WHERE (s.username = :username OR s.email = :username) AND s.is_active = 1 AND sc.code = :school_code";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':school_code', $school_code);
        $stmt->execute();

        $student = $stmt->fetch();

        if (!$student || !password_verify($password, $student['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Check school status
        if (!$student['school_active'] || $student['school_blocked']) {
            return ['success' => false, 'message' => 'School account is inactive or blocked'];
        }

        // Check package expiry
        if ($student['package_expires_at'] && strtotime($student['package_expires_at']) < time()) {
            return ['success' => false, 'message' => 'School package has expired'];
        }

        // Generate tokens
        $tokens = $this->generateTokens($student['id'], 'student');
        
        // Update last login
        $this->updateLastLogin($student['id'], 'student');

        // Log activity
        $this->logActivity(null, $student['id'], 'student', $student['school_id'], 'login', 'student', $student['id']);

        return [
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
                'permissions' => ['view_assignments', 'submit_assignments', 'view_grades', 'view_announcements']
            ],
            'tokens' => $tokens
        ];
    }

    public function generateTokens($userId, $userType) {
        $accessPayload = [
            'user_id' => $userId,
            'user_type' => $userType,
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + JWT_ACCESS_TOKEN_EXPIRE
        ];

        $refreshPayload = [
            'user_id' => $userId,
            'user_type' => $userType,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + JWT_REFRESH_TOKEN_EXPIRE
        ];

        $accessToken = JWT::encode($accessPayload, JWT_SECRET);
        $refreshToken = JWT::encode($refreshPayload, JWT_SECRET);

        // Store tokens in database
        $this->storeToken($userId, $userType, $accessToken, 'access', $accessPayload['exp']);
        $this->storeToken($userId, $userType, $refreshToken, 'refresh', $refreshPayload['exp']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWT_ACCESS_TOKEN_EXPIRE
        ];
    }

    private function storeToken($userId, $userType, $token, $type, $expiresAt) {
        $userIdField = $userType === 'student' ? 'student_id' : 'user_id';
        
        $query = "INSERT INTO auth_tokens ({$userIdField}, user_type, token, type, expires_at) 
                 VALUES (:user_id, :user_type, :token, :type, FROM_UNIXTIME(:expires_at))";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':user_type', $userType);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->execute();
    }

    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, JWT_SECRET);
            
            // Check if token exists and is not revoked
            $query = "SELECT * FROM auth_tokens WHERE token = :token AND is_revoked = 0 AND expires_at > NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            if (!$stmt->fetch()) {
                return false;
            }

            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getCurrentUser($token) {
        $decoded = $this->validateToken($token);
        if (!$decoded) {
            return false;
        }

        if ($decoded->user_type === 'student') {
            return $this->getCurrentStudent($decoded->user_id);
        } else {
            return $this->getCurrentRegularUser($decoded->user_id);
        }
    }

    private function getCurrentRegularUser($userId) {
        $query = "SELECT u.*, ur.name as role_name, ur.permissions, s.name as school_name 
                 FROM users u 
                 JOIN user_roles ur ON u.role_id = ur.id 
                 LEFT JOIN schools s ON u.school_id = s.id 
                 WHERE u.id = :user_id AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $user = $stmt->fetch();
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
            $user['user_type'] = 'user';
        }

        return $user;
    }

    private function getCurrentStudent($studentId) {
        $query = "SELECT s.*, sc.name as school_name 
                 FROM students s 
                 JOIN schools sc ON s.school_id = sc.id 
                 WHERE s.id = :student_id AND s.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->execute();

        $student = $stmt->fetch();
        if ($student) {
            $student['role_name'] = 'student';
            $student['permissions'] = ['view_assignments', 'submit_assignments', 'view_grades', 'view_announcements'];
            $student['user_type'] = 'student';
        }

        return $student;
    }

    public function logout($token) {
        $query = "UPDATE auth_tokens SET is_revoked = 1 WHERE token = :token";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }

    public function hasPermission($user, $permission) {
        if (in_array('all', $user['permissions'])) {
            return true;
        }
        return in_array($permission, $user['permissions']);
    }

    private function updateLastLogin($userId, $userType) {
        $table = $userType === 'student' ? 'students' : 'users';
        $query = "UPDATE {$table} SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    }

    private function logActivity($userId, $studentId, $userType, $schoolId, $action, $entityType, $entityId) {
        $query = "INSERT INTO activity_logs (user_id, student_id, user_type, school_id, action, entity_type, entity_id, ip_address, user_agent) 
                 VALUES (:user_id, :student_id, :user_type, :school_id, :action, :entity_type, :entity_id, :ip_address, :user_agent)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->bindParam(':user_type', $userType);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entityType);
        $stmt->bindParam(':entity_id', $entityId);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] );
        $stmt->execute();
    }
}
?>
