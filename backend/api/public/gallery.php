<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID is required']);
    exit;
}

try {
    $query = "SELECT id, image_path, caption, category 
              FROM school_gallery 
              WHERE school_id = :school_id AND is_active = 1 
              ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([':school_id' => $school_id]);
    $gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $gallery]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
