<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin']);

if (!$user) {
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        exit;
    }
    
    $studentId = $input['id'];
    
    // Check if student exists and belongs to the user's school (if not super admin)
    $checkQuery = "SELECT u.*, se.id as enrollment_id FROM users u 
                   LEFT JOIN student_enrollments se ON u.id = se.student_id AND se.status = 'active'
                   JOIN user_roles ur ON u.role_id = ur.id 
                   WHERE u.id = :student_id AND ur.name = 'student'";
    
    if ($user['role'] !== 'super_admin') {
        $checkQuery .= " AND u.school_id = :school_id";
    }
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':student_id', $studentId);
    if ($user['role'] !== 'super_admin') {
        $checkStmt->bindValue(':school_id', $user['school_id']);
    }
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Update user information
    $updateFields = [];
    $params = [':id' => $studentId];
    
    if (isset($input['first_name'])) {
        $updateFields[] = "first_name = :first_name";
        $params[':first_name'] = $input['first_name'];
    }
    
    if (isset($input['last_name'])) {
        $updateFields[] = "last_name = :last_name";
        $params[':last_name'] = $input['last_name'];
    }
    
    if (isset($input['email'])) {
        $updateFields[] = "email = :email";
        $params[':email'] = $input['email'];
    }
    
    if (isset($input['phone'])) {
        $updateFields[] = "phone = :phone";
        $params[':phone'] = $input['phone'];
    }
    
    if (isset($input['address'])) {
        $updateFields[] = "address = :address";
        $params[':address'] = $input['address'];
    }
    
    if (isset($input['date_of_birth'])) {
        $updateFields[] = "date_of_birth = :date_of_birth";
        $params[':date_of_birth'] = $input['date_of_birth'];
    }
    
    if (isset($input['gender'])) {
        $updateFields[] = "gender = :gender";
        $params[':gender'] = $input['gender'];
    }
    
    if (!empty($updateFields)) {
        $updateFields[] = "updated_at = NOW()";
        $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute($params);
    }
    
    // Update enrollment information if provided
    if (isset($input['class_id']) || isset($input['section_id']) || isset($input['roll_number'])) {
        $student = $checkStmt->fetch();
        
        if ($student['enrollment_id']) {
            $enrollmentFields = [];
            $enrollmentParams = [':enrollment_id' => $student['enrollment_id']];
            
            if (isset($input['class_id'])) {
                $enrollmentFields[] = "class_id = :class_id";
                $enrollmentParams[':class_id'] = $input['class_id'];
            }
            
            if (isset($input['section_id'])) {
                $enrollmentFields[] = "section_id = :section_id";
                $enrollmentParams[':section_id'] = $input['section_id'];
            }
            
            if (isset($input['roll_number'])) {
                $enrollmentFields[] = "roll_number = :roll_number";
                $enrollmentParams[':roll_number'] = $input['roll_number'];
            }
            
            if (!empty($enrollmentFields)) {
                $enrollmentQuery = "UPDATE student_enrollments SET " . implode(', ', $enrollmentFields) . " WHERE id = :enrollment_id";
                $enrollmentStmt = $db->prepare($enrollmentQuery);
                $enrollmentStmt->execute($enrollmentParams);
            }
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Student updated successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>
