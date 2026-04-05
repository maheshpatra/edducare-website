<?php
require_once '../../middleware/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['admin', 'principal']);

if (!$user) exit;

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $query = "UPDATE teacher_assignments SET 
                teacher_id = :teacher_id,
                class_id = :class_id,
                section_id = :section_id,
                role = :role,
                is_active = :is_active
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':teacher_id', $data['teacher_id']);
    $stmt->bindValue(':class_id', $data['class_id']);
    $stmt->bindValue(':section_id', $data['section_id']);
    $stmt->bindValue(':role', $data['role']);
    $stmt->bindValue(':is_active', $data['is_active']);
    $stmt->bindValue(':id', $data['id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Assignment updated successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
