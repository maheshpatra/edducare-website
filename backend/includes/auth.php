<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function login($username, $password, $school_code = null) {
        try {
            // Build query based on user type
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
            $tokens = $this->generateTokens($user['id']);
            
            // Update last login
            $this->updateLastLogin($user['id']);

            // Log activity
            $this->logActivity($user['id'], $user['school_id'], 'login', 'user', $user['id']);

            return [
                'success' => true,
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

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    public function generateTokens($userId) {
        $accessPayload = [
            'user_id' => $userId,
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + JWT_ACCESS_TOKEN_EXPIRE
        ];

        $refreshPayload = [
            'user_id' => $userId,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + JWT_REFRESH_TOKEN_EXPIRE
        ];

        $accessToken = JWT::encode($accessPayload, JWT_SECRET, JWT_ALGORITHM);
        $refreshToken = JWT::encode($refreshPayload, JWT_SECRET, JWT_ALGORITHM);

        // Store tokens in database
        $this->storeToken($userId, $accessToken, 'access', $accessPayload['exp']);
        $this->storeToken($userId, $refreshToken, 'refresh', $refreshPayload['exp']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => JWT_ACCESS_TOKEN_EXPIRE
        ];
    }

    private function storeToken($userId, $token, $type, $expiresAt) {
        $query = "INSERT INTO auth_tokens (user_id, token, type, expires_at) VALUES (:user_id, :token, :type, FROM_UNIXTIME(:expires_at))";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':expires_at', $expiresAt);
        $stmt->execute();
    }

    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));
            
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

        $query = "SELECT u.*, ur.name as role_name, ur.permissions, s.name as school_name 
                 FROM users u 
                 JOIN user_roles ur ON u.role_id = ur.id 
                 LEFT JOIN schools s ON u.school_id = s.id 
                 WHERE u.id = :user_id AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $decoded->user_id);
        $stmt->execute();

        $user = $stmt->fetch();
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
        }

        return $user;
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

    private function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    }

    private function logActivity($userId, $schoolId, $action, $entityType, $entityId) {
        $query = "INSERT INTO activity_logs (user_id, school_id, action, entity_type, entity_id, ip_address, user_agent) 
                 VALUES (:user_id, :school_id, :action, :entity_type, :entity_id, :ip_address, :user_agent)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':school_id', $schoolId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entityType);
        $stmt->bindParam(':entity_id', $entityId);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
        $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
        $stmt->execute();
    }
}
?>
