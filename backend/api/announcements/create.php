<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['super_admin', 'school_admin', 'admin', 'class_teacher']);

if (!$user) {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$required = ['title', 'content'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "INSERT INTO announcements (
        school_id, 
        title, 
        content, 
        priority, 
        target_audience, 
        is_active, 
        created_by
    ) VALUES (
        :school_id, 
        :title, 
        :content, 
        :priority, 
        :target_audience, 
        :is_active, 
        :created_by
    )";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':school_id', $user['school_id']);
    $stmt->bindValue(':title', $input['title']);
    $stmt->bindValue(':content', $input['content']);
    $stmt->bindValue(':priority', $input['priority'] ?? 'medium');
    $stmt->bindValue(':target_audience', $input['target_audience'] ?? 'all');
    $stmt->bindValue(':is_active', $input['is_active'] ?? 1);
    $stmt->bindValue(':created_by', $user['id']);
    
    $stmt->execute();
    $announcementId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement created successfully',
        'announcement_id' => $announcementId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
