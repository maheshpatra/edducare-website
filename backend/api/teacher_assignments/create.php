<?php
require_once '../../middleware/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['admin', 'principal']);

if (!$user) exit;

$data = json_decode(file_get_contents("php://input"), true);
$required = ['teacher_id', 'class_id', 'role'];

foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$section_id = $data['section_id'] ?? null;

$db = (new Database())->getConnection();

try {
    $query = "INSERT INTO teacher_assignments 
                (teacher_id, class_id, section_id, role, is_active) 
              VALUES 
                (:teacher_id, :class_id, :section_id, :role, 1)";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':teacher_id', $data['teacher_id']);
    $stmt->bindValue(':class_id', $data['class_id']);
    $stmt->bindValue(':section_id', $section_id);
    $stmt->bindValue(':role', $data['role']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Assignment created successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
