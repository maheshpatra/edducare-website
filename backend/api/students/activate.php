<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../middleware/auth.php';
require_once '../../config/database.php';

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'admin']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Check if student exists and belongs to the school
    $checkQuery = "SELECT id FROM students WHERE id = :id AND school_id = :school_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':id', $input['id']);
    $checkStmt->bindValue(':school_id', $user['school_id']);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    // Reactivate student
    $updateQuery = "UPDATE students SET status = 'active', is_active = 1, updated_at = NOW() WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bindValue(':id', $input['id'], PDO::PARAM_INT);
    $updateStmt->execute();

    // Re-activate enrollments if necessary
    $enrollQuery = "UPDATE student_enrollments SET status = 'active' WHERE student_id = :student_id AND status = 'inactive'";
    $enrollStmt = $db->prepare($enrollQuery);
    $enrollStmt->bindValue(':student_id', $input['id'], PDO::PARAM_INT);
    $enrollStmt->execute();

    // Log activity
    $logQuery = "INSERT INTO activity_logs (user_id, user_type, school_id, action, entity_type, entity_id, ip_address) 
                 VALUES (:user_id, 'user', :school_id, 'activate_student', 'student', :entity_id, :ip)";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id']);
    $logStmt->bindValue(':school_id', $user['school_id']);
    $logStmt->bindValue(':entity_id', $input['id']);
    $logStmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
    $logStmt->execute();

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student reactivated successfully'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
