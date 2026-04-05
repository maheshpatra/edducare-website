<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

$announcementId = $_GET['id'] ?? null;

if (!$announcementId) {
    http_response_code(400);
    echo json_encode(['error' => 'Announcement ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Check if announcement exists and user has permission
    $checkQuery = "SELECT id FROM announcements WHERE id = :id AND school_id = :school_id";
    if ($user['role'] !== 'school_admin') {
        $checkQuery .= " AND created_by = :created_by";
    }
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindValue(':id', $announcementId);
    $checkStmt->bindValue(':school_id', $user['school_id']);
    if ($user['role'] !== 'school_admin') {
        $checkStmt->bindValue(':created_by', $user['id']);
    }
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Announcement not found or access denied']);
        exit;
    }
    
    // Delete announcement
    $deleteQuery = "DELETE FROM announcements WHERE id = :id AND school_id = :school_id";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindValue(':id', $announcementId);
    $deleteStmt->bindValue(':school_id', $user['school_id']);
    $deleteStmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
