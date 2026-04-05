<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../includes/response.php';

class AuthMiddleware {
    private $db;
    private $jwt;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->jwt = new JWT();
    }
    
    public function authenticate() {
        $headers = $this->getAllHeaders();
        $token = null;
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication token required']);
            return false;
        }
        
        try {
            $payload = $this->jwt->decode($token);
            
            // Verify token exists in database and is not revoked
            $tokenQuery = "SELECT at.*, at.user_type 
                          FROM auth_tokens at 
                          WHERE at.token = :token 
                            AND at.expires_at > NOW() 
                            AND at.is_revoked = 0";
            $tokenStmt = $this->db->prepare($tokenQuery);
            $tokenStmt->bindParam(':token', $token);
            $tokenStmt->execute();
            
            $tokenData = $tokenStmt->fetch();
            if (!$tokenData) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired token']);
                return false;
            }
            
            // Get user details based on user type from token
            $userType = $tokenData['user_type'];
            
            if ($userType === 'student') {
                // Query students table
                $userQuery = "SELECT s.*, 
                                     sc.name as school_name, 
                                     sc.code as school_code,
                                     sc.id as school_id,
                                     'student' as role_name,
                                     'student' as user_type,
                                     -- Get current enrollment info
                                     se.class_id as current_class_id,
                                     se.section_id as current_section_id,
                                     se.roll_number,
                                     c.name as class_name,
                                     c.grade_level,
                                     sec.name as section_name,
                                     ay.name as academic_year_name,
                                     ay.id as academic_year_id
                             FROM students s 
                             JOIN schools sc ON s.school_id = sc.id 
                             LEFT JOIN student_enrollments se ON s.id = se.student_id AND se.status = 'active'
                             LEFT JOIN classes c ON se.class_id = c.id
                             LEFT JOIN sections sec ON se.section_id = sec.id
                             LEFT JOIN academic_years ay ON se.academic_year_id = ay.id AND ay.is_current = 1
                             WHERE s.id = :user_id AND s.status = 'active' AND s.is_active = 1";
                
                $userStmt = $this->db->prepare($userQuery);
                $userStmt->bindParam(':user_id', $payload['user_id']);
                $userStmt->execute();
                
            } else {
                // Query users table for staff/admin
                $userQuery = "SELECT u.*, 
                                     ur.name as role_name, 
                                     ur.permissions, 
                                     s.name as school_name,
                                     s.code as school_code,
                                     'user' as user_type
                             FROM users u 
                             JOIN user_roles ur ON u.role_id = ur.id 
                             LEFT JOIN schools s ON u.school_id = s.id 
                             WHERE u.id = :user_id AND u.is_active = 1";
                
                $userStmt = $this->db->prepare($userQuery);
                $userStmt->bindParam(':user_id', $payload['user_id']);
                $userStmt->execute();
            }
            
            if ($userStmt->rowCount() === 0) {
                http_response_code(401);
                echo json_encode(['error' => 'User not found or inactive']);
                return false;
            }
            
            $user = $userStmt->fetch();
            
            // Ensure consistent role field
            $user['role'] = $userType === 'student' ? 'student' : $user['role_name'];
            
            // Update last login
            $this->updateLastLogin($user['id'], $userType);
            
            return $user;
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get all headers in a cross-platform way
     * Fixes the deprecation warning for automatic conversion of false to array
     */
    private function getAllHeaders() {
        $headers = [];
        
        // Try getallheaders() first (Apache)
        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if ($allHeaders !== false && is_array($allHeaders)) {
                $headers = $allHeaders;
            }
        }
        
        // Fallback: Parse from $_SERVER
        if (empty($headers)) {
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) === 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    public function requireAuth() {
        return $this->authenticate();
    }
    
    public function requireRole($allowedRoles) {
        $user = $this->authenticate();
        
        if (!$user) {
            return false;
        }
        
        // Normalize role checking for admin vs school_admin
        if (in_array('admin', $allowedRoles) && !in_array('school_admin', $allowedRoles)) {
            $allowedRoles[] = 'school_admin';
        }
        if (in_array('school_admin', $allowedRoles) && !in_array('admin', $allowedRoles)) {
            $allowedRoles[] = 'admin';
        }

        $userRole = $user['role'];
        
        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            echo json_encode([
                'error' => 'Insufficient permissions',
                'required_roles' => $allowedRoles,
                'user_role' => $userRole
            ]);
            return false;
        }
        
        return $user;
    }
    
    public function requireSchoolAccess($schoolId = null) {
        $user = $this->authenticate();
        
        if (!$user) {
            return false;
        }
        
        // Super admin can access any school
        if ($user['role'] === 'super_admin') {
            return $user;
        }
        
        // Check if user belongs to the requested school
        if ($schoolId && $user['school_id'] != $schoolId) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this school']);
            return false;
        }
        
        return $user;
    }
    
    public function requireStudentAccess($studentId = null) {
        $user = $this->authenticate();
        
        if (!$user) {
            return false;
        }
        
        // If user is a student, they can only access their own data
        if ($user['role'] === 'student') {
            if ($studentId && $user['id'] != $studentId) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to other student data']);
                return false;
            }
            return $user;
        }
        
        // Staff can access students in their school
        if (in_array($user['role'], ['admin', 'school_admin', 'teacher_academic', 'teacher_administrative'])) {
            if ($studentId) {
                // Verify student belongs to same school
                $studentQuery = "SELECT school_id FROM students WHERE id = :student_id";
                $studentStmt = $this->db->prepare($studentQuery);
                $studentStmt->bindParam(':student_id', $studentId);
                $studentStmt->execute();
                
                $student = $studentStmt->fetch();
                if (!$student || $student['school_id'] != $user['school_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied to this student']);
                    return false;
                }
            }
            return $user;
        }
        
        // Super admin can access any student
        if ($user['role'] === 'super_admin') {
            return $user;
        }
        
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions for student access']);
        return false;
    }
    
    public function requireClassAccess($classId = null) {
        $user = $this->authenticate();
        
        if (!$user) {
            return false;
        }
        
        // Super admin can access any class
        if ($user['role'] === 'super_admin') {
            return $user;
        }
        
        // Students can only access their own class
        if ($user['role'] === 'student') {
            if ($classId && $user['current_class_id'] != $classId) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to other class data']);
                return false;
            }
            return $user;
        }
        
        // Staff can access classes in their school
        if ($classId) {
            $classQuery = "SELECT school_id FROM classes WHERE id = :class_id";
            $classStmt = $this->db->prepare($classQuery);
            $classStmt->bindParam(':class_id', $classId);
            $classStmt->execute();
            
            $class = $classStmt->fetch();
            if (!$class || $class['school_id'] != $user['school_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to this class']);
                return false;
            }
        }
        
        return $user;
    }
    
    private function updateLastLogin($userId, $userType) {
        try {
            if ($userType === 'student') {
                $updateQuery = "UPDATE students SET last_login = NOW() WHERE id = :user_id";
            } else {
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
            }
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
        } catch (Exception $e) {
            // Log error but don't fail authentication
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
    
    public function logActivity($user, $action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        try {
            $logQuery = "INSERT INTO activity_logs (
                            user_id, student_id, user_type, school_id, action, 
                            entity_type, entity_id, old_values, new_values, 
                            ip_address, user_agent
                         ) VALUES (
                            :user_id, :student_id, :user_type, :school_id, :action,
                            :entity_type, :entity_id, :old_values, :new_values,
                            :ip_address, :user_agent
                         )";
            
            $logStmt = $this->db->prepare($logQuery);
            
            $nullValue = null;
            if ($user['user_type'] === 'student') {
                $logStmt->bindParam(':user_id', $nullValue);
                $logStmt->bindParam(':student_id', $user['id']);
            } else {
                $logStmt->bindParam(':user_id', $user['id']);
                $logStmt->bindParam(':student_id', $nullValue);
            }
            
            $logStmt->bindParam(':user_type', $user['user_type']);
            $logStmt->bindParam(':school_id', $user['school_id']);
            $logStmt->bindParam(':action', $action);
            $logStmt->bindParam(':entity_type', $entityType);
            $logStmt->bindParam(':entity_id', $entityId);
            $logStmt->bindParam(':old_values', $oldValues ? json_encode($oldValues) : null);
            $logStmt->bindParam(':new_values', $newValues ? json_encode($newValues) : null);
            $logStmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR'] );
            $logStmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] );
            
            $logStmt->execute();
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
?>