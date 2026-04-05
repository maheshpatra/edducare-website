<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['school_admin', 'class_teacher']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
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
    $checkStmt->bindValue(':id', $input['id']);
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
    
    // Build update query
    $updateFields = [];
    $params = [':id' => $input['id'], ':school_id' => $user['school_id']];
    
    if (isset($input['title'])) {
        $updateFields[] = "title = :title";
        $params[':title'] = $input['title'];
    }
    
    if (isset($input['content'])) {
        $updateFields[] = "content = :content";
        $params[':content'] = $input['content'];
    }
    
    if (isset($input['priority'])) {
        $updateFields[] = "priority = :priority";
        $params[':priority'] = $input['priority'];
    }
    
    if (isset($input['target_audience'])) {
        $updateFields[] = "target_audience = :target_audience";
        $params[':target_audience'] = $input['target_audience'];
    }
    
    if (isset($input['is_active'])) {
        $updateFields[] = "is_active = :is_active";
        $params[':is_active'] = $input['is_active'];
    }
    
    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $query = "UPDATE announcements SET " . implode(', ', $updateFields) . " WHERE id = :id AND school_id = :school_id";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
