<?php
require_once '../../middleware/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = new AuthMiddleware();
$user = $auth->requireRole(['admin', 'principal']);

if (!$user) exit;

parse_str(file_get_contents("php://input"), $data);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Assignment ID is required']);
    exit;
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare("DELETE FROM teacher_assignments WHERE id = :id");
    $stmt->bindValue(':id', $data['id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Assignment deleted']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
