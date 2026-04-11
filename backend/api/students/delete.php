<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $studentId = $_GET['id'] ?? null;
    
    if (!$studentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        exit;
    }
    
    // Check if student exists and belongs to the user's school (if not super admin)
    $checkQuery = "SELECT * FROM students WHERE id = :student_id";
    
    if ($user['role'] !== 'super_admin') {
        $checkQuery .= " AND school_id = :school_id";
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
    
    // Soft delete - mark as inactive instead of hard delete
    $deleteQuery = "UPDATE students SET status = 'inactive', updated_at = NOW() WHERE id = :student_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':student_id', $studentId);
    $deleteStmt->execute();
    
    // Also update enrollment status
    $enrollmentQuery = "UPDATE student_enrollments SET status = 'inactive' WHERE student_id = :student_id";
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindValue(':student_id', $studentId);
    $enrollmentStmt->execute();
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Student deleted successfully'
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
